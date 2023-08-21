<?php

/**
 * Moodle Nugget Plugin : view
 *
 * @package    mod_naas
 * @copyright  2019 Bruno Ilponse
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($_SERVER['DOCUMENT_ROOT']."/config.php");
require_once($CFG->dirroot.'/mod/lti/locallib.php');
require_once('classes/NaasClient.php');
require_once('locallib.php');

$id        = optional_param('id', 0, PARAM_INT);        // Course module ID
$u         = optional_param('u', 0, PARAM_INT);         // NaaS instance id
$redirect  = optional_param('redirect', 0, PARAM_BOOL);
$forceview = optional_param('forceview', 0, PARAM_BOOL);

if ($u) {  // Two ways to specify the module
    $naas_instance = $DB->get_record('naas', array('id'=>$u), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('naas', $naas->id, $naas->course, false, MUST_EXIST);

} else {
    $cm = get_coursemodule_from_id('naas', $id, 0, false, MUST_EXIST);
    $naas_instance = $DB->get_record('naas', array('id'=>$cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// Check credentials
require_course_login($course, true, $cm);
require_capability('mod/naas:view', $context);

// Completion and trigger events.
naas_view($course, $cm, $context);

// Set page stuff
$PAGE->set_cm($cm, $course); // Set's up global $COURSE.
$PAGE->set_context($context);
$url = new moodle_url('/mod/naas/view.php', array('id' => $cm->id));
$PAGE->set_url($url);
$pagetitle = strip_tags($course->shortname.': '.format_string($naas_instance->name));
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

// Print the page header.
echo $OUTPUT->header();

// Back to course button
$back_course_button = "<a class='btn btn-outline-secondary btn-sm course-button' 
    href=".$CFG->wwwroot."/course/view.php?id=".$COURSE->id.">".get_string('back_to_course', 'naas')."</a>";

echo $back_course_button;

echo naas_widget_html($naas_instance->nugget_id, "NuggetView");

// Toggles the Nugget 'Details' Modal
echo "<script>
let details_button = document.querySelector('.secondary-navigation nav ul li[data-key=details]');
let widget = document.querySelector('#nugget-info-button div a');
details_button.onclick = function() { widget.click(); };
</script>";

// Request the launch content with an iframe tag.
$iframeresizer_url = new moodle_url('/mod/naas/assets/iframeResizer.min.js');
echo "<script src='$iframeresizer_url' ></script>";
echo "<script>window.setTimeout(() => { iFrameResize({ log: false, checkOrigin:false, heightCalculationMethod: 'lowestElement' }, '#naascontentframe') }, 100);</script>";
echo "<iframe id='naascontentframe' height='600px' width='100%' style='border:none' src='launch.php?id=".$cm->id.
        "&triggerview=0\' webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>";

// Completion activity
echo naas_widget_html($naas_instance->nugget_id, "NuggetCompletionModal");

echo $back_course_button;

// Finish the page.
echo $OUTPUT->footer();
