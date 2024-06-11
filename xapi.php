<?php
/**
 * Moodle Nugget Plugin : send xAPI statement
 *
 * @package    mod_naas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($_SERVER['DOCUMENT_ROOT']."/config.php");
require_once('classes/NaasClient.php');

$verb = required_param('verb', PARAM_TEXT);
$version_id = required_param('version_id', PARAM_TEXT);
$body = optional_param('body', null, PARAM_TEXT);

// Get data from DB
$id = required_param('id', PARAM_INT); // Course Module ID.
$cm = get_coursemodule_from_id('naas', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

// Check credentials
require_login($course, true, $cm);
require_capability('mod/naas:view', $context);

// Get NaaS Client
$config = (object) array_merge((array) get_config('naas'), (array) $CFG);
$naas = new NaasClient($config);

// Get user info from Moodle
$user = new stdClass();
$user->name = $USER->firstname.' '.$USER->lastname;
$user->email = $USER->email;

// Request post data
$data = new stdClass();
$data->user = $user;
if (!$body) {
    $data->body = new stdClass();
} else {
    $data->body = (array)json_decode($body);
}

$data->resource_link_id = $_SESSION["resource_link_id"];

// error_log(json_encode($data));

$response = $naas->post_xapi_statement($verb, $version_id, $data);
// echo json_encode($response);
