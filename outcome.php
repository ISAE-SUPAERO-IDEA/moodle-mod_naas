<?php
/**
 * Moodle Nugget Plugin : NaaS Outcome file
 *
 * @package   mod_naas
 * @copyright 2024 SUPAERO-IDEA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($_SERVER['DOCUMENT_ROOT']."/config.php");
require_once($CFG->dirroot.'/mod/lti/locallib.php');
require_once('classes/NaasClient.php');
require_once('locallib.php');

require_once("$CFG->libdir/completionlib.php");
require_once('classes/completion/custom_completion.php');


$entityBody = file_get_contents('php://input');
error_log(print_r($entityBody, 1));

$xmlOutput = simplexml_load_string($entityBody);
$score = (string) $xmlOutput->imsx_POXBody->replaceResultRequest->resultRecord->result->resultScore->score;

error_log("Score: " . $score);  // compris en 0 et 1

$sourcedId = (string) $xmlOutput->imsx_POXBody->replaceResultRequest->resultRecord->sourcedGUID->sourcedId;
// error_log("sourcedId: " . $sourcedId);



// Check in the database if user_id and activity_id exist with the sourcedId
$conditions = array('sourced_id' => $sourcedId);
$records = $DB->get_records('naas_activity_outcome', $conditions);
if ($records) {
    foreach ($records as $record) {
        $user_id = $record->user_id;
        $activity_id = $record->activity_id;
    }
}
else {
    error_log("No records found for this user.");
}

error_log("user_id: ".$user_id);
error_log("activity_id: ".$activity_id);


// info sur le cours
$cm = get_coursemodule_from_id('naas', $activity_id, 0, false, MUST_EXIST);
$naas_instance = $DB->get_record('naas', array('id'=>$cm->instance), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);




require_once($CFG->libdir . '/grade/grade_item.php');
// require_once($CFG->libdir . '/grade/grade_consts.php');
require_once($CFG->libdir . '/gradelib.php');



$itemnumber = 0;    // is the item number (only change this if your activity needs to store more than one grade per user)

$grade = new stdClass();
$grade->userid = $user_id;
$grade->rawgrade = $score * 100;    // % de reussite

$activity_name = $cm->name;

$gradeinfo = array(
    'itemname' => $activity_name,
    // 'idnumber' => $activity_id,
    // 'gradetype' => GRADE_TYPE_VALUE,
    // 'grademax' => 100,
    // 'grademin' => 0,
    // 'gradepass' => 75
);


error_log(serialize($cm));
// error_log(serialize($naas_instance));
// error_log(serialize($course));
// error_log(serialize($context));


grade_update('mod/naas', $course->id, 'mod', 'naas', $cm->instance, $itemnumber, $grade, $gradeinfo);


// Set up completion object and check it is enabled.
$completion = new completion_info($course);
if (!$completion->is_enabled()) {
    throw new moodle_exception('completionnotenabled', 'completion');
}

/*
// Check completion state is manual.
if ($cm->completion != COMPLETION_TRACKING_MANUAL) {
    throw new moodle_exception('cannotmanualctrack', 'error');
}
*/

$targetstate = COMPLETION_COMPLETE;
$completion->update_state($cm, $targetstate);
error_log("completion_complete");




// Highest grade

// First attempt

// Last attempt





