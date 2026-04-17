<?php
// This file is part of Moodle - http://moodle.org/
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
 * Display information about all Nugget modules in the requested course.
 *
 * @package     mod_naas
 * @copyright   2019 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author      Thomas Delalbre
 */

require_once('../../config.php');
require_once('lib.php');

// Course ID is a required parameter.
$id = optional_param('id', 0, PARAM_INT);  // Using optional_param to handle missing ID gracefully.

// Redirect with error message if ID is missing or invalid.
if (empty($id)) {
    redirect(
        new moodle_url('/'),
        get_string('invalidcoursemodule', 'error'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Get the course - with graceful error handling.
try {
    $course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
} catch (dml_exception $e) {
    redirect(
        new moodle_url('/'),
        get_string('invalidcourseid', 'error'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Check user is logged in and has access to this course.
require_login($course, true);  // Second parameter true forces login for the course.

// Check if user has capability to view NAAS content.
$context = context_course::instance($course->id);
if (!has_capability('mod/naas:view', $context)) {
    redirect(
        new moodle_url('/course/view.php', ['id' => $course->id]),
        get_string('nopermissions', 'error', get_string('view')),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Set up page.
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/mod/naas/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();

$courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);

// Get all the appropriate data.
if (!$naasmodules = get_all_instances_in_course('naas', $course)) {
    echo $OUTPUT->heading(get_string('modulenameplural', 'naas'), 2);
    echo $OUTPUT->notification(get_string('nonewmodules', 'naas'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->continue_button($courseurl);
    echo $OUTPUT->footer();
    die();
}

$usesections = course_format_uses_sections($course->format);
$heading = $OUTPUT->heading(get_string('modulenameplural', 'naas'), 2);
$backtocourse = get_string('back_to_course', 'naas');

$rows = [];
foreach ($naasmodules as $naasmodule) {
    $dimmed = !$naasmodule->visible;
    $url = new moodle_url('/mod/naas/view.php', ['id' => $naasmodule->coursemodule]);
    
    $row = [
        'dimmed' => $dimmed,
        'url' => $url->out(false),
        'name' => format_string($naasmodule->name),
        'intro' => format_module_intro('naas', $naasmodule, $naasmodule->coursemodule),
        'timemodified' => userdate($naasmodule->timemodified),
    ];
    
    if ($usesections) {
        $row['sectionname'] = get_section_name($course, $naasmodule->section);
    }
    
    $rows[] = $row;
}

$renderer = $PAGE->get_renderer('mod_naas');
$indexpage = new \mod_naas\output\index_page($courseurl, $backtocourse, $heading, $usesections, $rows);

echo $renderer->render($indexpage);

echo $OUTPUT->footer();
