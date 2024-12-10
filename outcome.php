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
 * Moodle Nugget Plugin : NaaS Outcome file
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_nugget
 */

require_once($_SERVER['DOCUMENT_ROOT']."/config.php");
require_once("$CFG->libdir/completionlib.php");
require_login(null, false);

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
$cm = get_coursemodule_from_id('nugget', $activityid, 0, false, MUST_EXIST);
$nuggetinstance = $DB->get_record('nugget', ['id' => $cm->instance], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_once($CFG->libdir . '/grade/grade_item.php');
require_once($CFG->libdir . '/gradelib.php');

$itemnumber = 0;    // Change this only if your activity needs to store more than one grade per user.

$grade = new stdClass();
$grade->userid = $userid;
$grade->rawgrade = $score * 100;    // Success percentage.

$activityname = $cm->name;

$gradeinfo = [
    'itemname' => $activityname,
];

// Grading method.
$grademethod = $nuggetinstance->grade_method;


// Highest Grade.
if ($grademethod == "1") {
    debugging("highest_grade", DEBUG_DEVELOPER);

    $existinggrades = grade_get_grades($course->id, 'mod', 'nugget', $cm->instance, $userid);
    $existinggradesdata = $existinggrades->items[0]->grades;

    $currenthighestgrade = 0;

    foreach ($existinggradesdata as $data) {
        if ($data->grade > $currenthighestgrade) {
            $currenthighestgrade = $data->grade;
        }
    }

    if ($score * 100 > $currenthighestgrade) {
        grade_update('mod/nugget', $course->id, 'mod', 'nugget', $cm->instance, $itemnumber, $grade, $gradeinfo);
    }
} else if ($grademethod == "2") { // Grade Average.
    debugging("grade_average", DEBUG_DEVELOPER);
} else if ($grademethod == "3") { // Grade First Attempt.
    debugging("first_attempt", DEBUG_DEVELOPER);

    $existinggrades = grade_get_grades($course->id, 'mod', 'nugget', $cm->instance, $userid);
    $existinggradesdata = $existinggrades->items[0]->grades;

    if (count($existinggradesdata) == 0) {
        grade_update('mod/nugget', $course->id, 'mod', 'nugget', $cm->instance, $itemnumber, $grade, $gradeinfo);
    }
} else if ($grademethod == "4") { // Grade Last Attempt.
    debugging("last_attemp", DEBUG_DEVELOPER);
    grade_update('mod/nugget', $course->id, 'mod', 'nugget', $cm->instance, $itemnumber, $grade, $gradeinfo);
} else { // Grade Method unknown.
    debugging("Grading method error", DEBUG_DEVELOPER);
}

// Set up completion object and check it is enabled.
$completion = new completion_info($course);
if (!$completion->is_enabled()) {
    throw new moodle_exception('completionnotenabled', 'completion');
}

$targetstate = COMPLETION_COMPLETE;
$completion->update_state($cm, $targetstate);
debugging("completion_complete", DEBUG_DEVELOPER);


