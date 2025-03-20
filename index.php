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
 * Moodle Nugget Plugin
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT); // Course id.
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course, true);

$PAGE->set_pagelayout('incourse');
$strnaas          = get_string('modulename', 'naas');
$strnaases        = get_string('modulenameplural', 'naas');
$strname         = get_string('name');
$strintro        = get_string('moduleintro');
$strlastmodified = get_string('lastmodified');

$PAGE->set_url('/mod/naas/index.php', ['id' => $course->id]);
$PAGE->set_title($course->shortname.': '.$strnaases);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strnaases);
echo $OUTPUT->header();
echo $OUTPUT->heading($strnaases);

if (!$naases = get_all_instances_in_course('naas', $course)) {
    notice(get_string('thereareno', 'moodle', $strnaases), "$CFG->wwwroot/course/view.php?id=$course->id");
    exit;
}

$usesections = course_format_uses_sections($course->format);
$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_'.$course->format);
    $table->head  = [$strsectionname, $strname, $strintro];
    $table->align = ['center', 'left', 'left'];
} else {
    $table->head  = [$strlastmodified, $strname, $strintro];
    $table->align = ['left', 'left', 'left'];
}

$modinfo = get_fast_modinfo($course);
$currentsection = '';
foreach ($naases as $naas) {
    $cm = $modinfo->cms[$naas->coursemodule];
    if ($usesections) {
        $printsection = '';
        if ($naas->section !== $currentsection) {
            if ($naas->section) {
                $printsection = get_section_name($course, $naas->section);
            }
            if ($currentsection !== '') {
                $table->data[] = 'hr';
            }
            $currentsection = $naas->section;
        }
    } else {
        $printsection = '<span class="smallinfo">'.userdate($naas->timemodified)."</span>";
    }

    $extra = empty($cm->extra) ? '' : $cm->extra;
    $icon = '';
    if (!empty($cm->icon)) {
        // Each naas has an icon in 2.0.
        $icon = $OUTPUT->pix_icon($cm->icon, get_string('modulename', $cm->modname)) . ' ';
    }

    $class = $naas->visible ? '' : 'class="dimmed"'; // Hidden modules are dimmed.
    $table->data[] = [
        $printsection,
        "<a $class $extra href=\"view.php?id=$cm->id\">".$icon.format_string($naas->name)."</a>",
        format_module_intro('naas', $naas, $cm->id)];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
