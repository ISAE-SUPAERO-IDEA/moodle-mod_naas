<?php
// This file is part of Moodle - http://moodle.org
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Entrypoint for receiving grade and completion information from the NaaS plateform.
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
require_once('../../config.php');
require_once("$CFG->libdir/completionlib.php");
require_once('classes/completion/custom_completion.php');

$entitybody = file_get_contents('php://input');

$xmloutput = simplexml_load_string($entitybody);
$score = (string) $xmloutput->imsx_POXBody->replaceResultRequest->resultRecord->result->resultScore->score;

$sourcedid = (string) $xmloutput->imsx_POXBody->replaceResultRequest->resultRecord->sourcedGUID->sourcedId;

// Check in the database if user_id and activity_id exist with the sourcedId.
$conditions = ['sourced_id' => $sourcedid];
$records = $DB->get_records('naas_activity_outcome', $conditions);
if ($records) {
    foreach ($records as $record) {
        $userid = $record->user_id;
        $activityid = $record->activity_id;
    }
}


// Course data.
$cm = get_coursemodule_from_id('naas', $activityid, 0, false, MUST_EXIST);
$naasinstance = $DB->get_record('naas', ['id' => $cm->instance], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_once($CFG->libdir . '/grade/grade_item.php');
require_once($CFG->libdir . '/gradelib.php');

$itemnumber = 0; // Change this only if your activity needs to store more than one grade per user.

// Get existing grades.
$existinggrades = grade_get_grades($course->id, 'mod', 'naas', $cm->instance, $userid);
$existinggradesdata = $existinggrades->items[$itemnumber]->grades;
$grademax = $existinggrades->items[$itemnumber]->grademax;

$grade = new stdClass();
$grade->userid = $userid;
$grade->rawgrade = $score * $grademax;    // Score is between 0 and 1.

$activityname = $cm->name;

// Grading method.
$grademethod = $naasinstance->grade_method;

if ($grademethod == NAAS_GRADEHIGHEST) {
    $currenthighestgrade = -1;

    foreach ($existinggradesdata as $data) {
        if ($data->grade > $currenthighestgrade) {
            $currenthighestgrade = $data->grade;
        }
    }

    if ($grade->rawgrade > $currenthighestgrade) {
        grade_update('mod/naas', $course->id, 'mod', 'naas', $cm->instance, $itemnumber, $grade);
    }
} else if ($grademethod == NAAS_ATTEMPTFIRST) {

    // Check the 1st grade is undefined.
    if (count($existinggradesdata) && array_values($existinggradesdata)[0]->grade === null) {
        grade_update('mod/naas', $course->id, 'mod', 'naas', $cm->instance, $itemnumber, $grade);
    }
} else if ($grademethod == NAAS_ATTEMPTLAST) {
    grade_update('mod/naas', $course->id, 'mod', 'naas', $cm->instance, $itemnumber, $grade);
} else { // Grade Method unknown.
    throw new moodle_exception(
        'error:unsupported_grade_method',
        'naas',
        '',
        $grademethod
    );
}

// Set up completion object and check it is enabled.
$completion = new completion_info($course);
if (!$completion->is_enabled()) {
    throw new moodle_exception('completionnotenabled', 'completion');
}

$targetstate = COMPLETION_COMPLETE;
$completion->update_state($cm, $targetstate);
debugging("completion_complete", DEBUG_DEVELOPER);
