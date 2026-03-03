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
 * Moodle Nugget Plugin : view
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */

require_once('../../config.php');

$id        = optional_param('id', 0, PARAM_INT);        // Course module ID.
$u         = optional_param('u', 0, PARAM_INT);         // NaaS instance id.
$redirect  = optional_param('redirect', 0, PARAM_BOOL);
$forceview = optional_param('forceview', 0, PARAM_BOOL);

if ($u) {  // Two ways to specify the module.
    $naasinstance = $DB->get_record('naas', ['id' => $u], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('naas', $naas->id, $naas->course, false, MUST_EXIST);

} else {
    $cm = get_coursemodule_from_id('naas', $id, 0, false, MUST_EXIST);
    $naasinstance = $DB->get_record('naas', ['id' => $cm->instance], '*', MUST_EXIST);
}

$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// Check credentials.
require_course_login($course, true, $cm);
require_capability('mod/naas:view', $context);

// Completion and trigger events.
naas_view($course, $cm, $context);

// Set page stuff.
$PAGE->set_cm($cm, $course); // Set's up global $COURSE.
$PAGE->set_context($context);
$url = new moodle_url('/mod/naas/view.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$pagetitle = strip_tags($course->shortname.': '.format_string($naasinstance->name));
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

// Print the page header.
echo $OUTPUT->header();

$courseurl = new moodle_url('/course/view.php', ['id' => $COURSE->id]);
// Back to course button.
$backcoursebutton = "<div class='course-button'><a class='btn btn-outline-secondary btn-sm'
    href=".$courseurl.">".get_string('back_to_course', 'naas')."</a></div>";

echo $backcoursebutton;

$nextactivityurl = \mod_naas\mod_util::get_next_activity_url();
if ($nextactivityurl) {
    echo "<div class='next-activity hidden'><a class='btn btn-outline-secondary btn-sm'
    href=".$nextactivityurl->link."&forceview=1>".$nextactivityurl->name."</a></div>";
}

// Displays Nugget.
echo \mod_naas\naas_widget::naas_widget_html($naasinstance->nugget_id, $cm->course, $cm->id, "NuggetView");

// Toggles the Nugget 'About' Modal.
echo "<script>
let about_button = document.querySelector('.secondary-navigation nav ul li[data-key=about]');
if (about_button){
    let widget = document.querySelector('#nugget-info-button div a');
    about_button.onclick = function() { widget.click(); };
}
</script>";

echo $backcoursebutton;

// Finish the page.
echo $OUTPUT->footer();
