<?php
/**
 * Moodle Nugget Plugin : launch LTI
 *
 * @package    mod_naas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($_SERVER['DOCUMENT_ROOT']."/config.php");
require_once($CFG->dirroot.'/mod/lti/lib.php');
require_once($CFG->dirroot.'/mod/lti/locallib.php');
require_once('classes/NaasClient.php');
require_once('classes/NaasMoodle.php');


// Get data from DB
$id = required_param('id', PARAM_INT); // Course Module ID.
$cm = get_coursemodule_from_id('naas', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
$language = optional_param('language', null, PARAM_TEXT); // multilanguage change
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

// Check credentials
require_login($course, true, $cm);
require_capability('mod/naas:view', $context);

# Launch LTI
$naas_moodle = new \mod_naas\NaasMoodle();
$naas_moodle->lti_launch($id, $language);
