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
 * @package mod_naas
 */

require_once($_SERVER['DOCUMENT_ROOT']."/config.php");
require_once($CFG->dirroot.'/mod/lti/locallib.php');
require_once('classes/NaasClient.php');
require_once('locallib.php');

require_once("$CFG->libdir/completionlib.php");
require_once('classes/completion/custom_completion.php');


$entitybody = file_get_contents('php://input');
debugging(print_r($entitybody, 1));

$xmloutput = simplexml_load_string($entitybody);
$score = (string) $xmloutput->imsx_POXBody->replaceResultRequest->resultRecord->result->resultScore->score;

debugging("Score: " . $score);  // Range from 0 to 1.

$sourcedid = (string) $xmloutput->imsx_POXBody->replaceResultRequest->resultRecord->sourcedGUID->sourcedId;

// Check in the database if user_id and activity_id exist with the sourcedId.
$conditions = ['sourced_id' => $sourcedid];
$records = $DB->get_records('naas_activity_outcome', $conditions);
if ($records) {
    foreach ($records as $record) {
        $userid = $record->user_id;
        $activityid = $record->activity_id;
    }
} else {
    debugging("No records found for this user.");
}

debugging("user_id: ".$userid);
debugging("activity_id: ".$activityid);


// Course data.
$cm = get_coursemodule_from_id('naas', $activityid, 0, false, MUST_EXIST);
$naasinstance = $DB->get_record('naas', ['id' => $cm->instance], '*', MUST_EXIST);
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
$grademethod = $naasinstance->grade_method;


// Highest Grade.
if ($grademethod == "1") {
    debugging("highest_grade");

    $existinggrades = grade_get_grades($course->id, 'mod', 'naas', $cm->instance, $userid);
    $existinggradesdata = $existinggrades->items[0]->grades;

    $currenthighestgrade = 0;

    foreach ($existinggradesdata as $data) {
        if ($data->grade > $currenthighestgrade) {
            $currenthighestgrade = $data->grade;
        }
    }

    if ($score * 100 > $currenthighestgrade) {
        grade_update('mod/naas', $course->id, 'mod', 'naas', $cm->instance, $itemnumber, $grade, $gradeinfo);
    }
} else if ($grademethod == "2") { // Grade Average.
    debugging("grade_average");
} else if ($grademethod == "3") { // Grade First Attempt.
    debugging("first_attempt");

    $existinggrades = grade_get_grades($course->id, 'mod', 'naas', $cm->instance, $userid);
    $existinggradesdata = $existinggrades->items[0]->grades;

    if (count($existinggradesdata) == 0) {
        grade_update('mod/naas', $course->id, 'mod', 'naas', $cm->instance, $itemnumber, $grade, $gradeinfo);
    }
} else if ($grademethod == "4") { // Grade Last Attempt.
    debugging("last_attemp");
    grade_update('mod/naas', $course->id, 'mod', 'naas', $cm->instance, $itemnumber, $grade, $gradeinfo);
} else { // Grade Method unknown.
    debugging("Grading method error");
}

// Set up completion object and check it is enabled.
$completion = new completion_info($course);
if (!$completion->is_enabled()) {
    throw new moodle_exception('completionnotenabled', 'completion');
}

$targetstate = COMPLETION_COMPLETE;
$completion->update_state($cm, $targetstate);
debugging("completion_complete");


