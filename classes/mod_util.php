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
 * Provides utility functions on Moodle modules.
 * @author Bruno Ilponse
 * @author John Tranier
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
namespace mod_naas;

/**
 * Provides utility functions on Moodle modules.
 * @author Bruno Ilponse
 * @author John Tranier
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
class mod_util {

    /**
     * Return a link to the next activity
     * @return object|null { link: string, name: string }
     */
    public static function get_next_activity_url() {
        /* Gets the link to the next activity of the course */
        /* Adapted from https://gist.github.com/frumbert/b4fbb8e6f9a23c7233128a1f51df02b7 */

        global $PAGE, $CFG, $COURSE, $DB;

        require_once($CFG->libdir . '/modinfolib.php');

        $cmid = $PAGE->cm->id;
        $modinfo = get_fast_modinfo($COURSE);
        $context = \context_course::instance($COURSE->id);
        $sections = $DB->get_records('course_sections', ['course' => $COURSE->id], 'section', 'section,visible,summary');

        $next = null;
        $prev = null;
        $firstcourse = null;
        $firstsection = null;
        $lastcourse = null;
        $lastsection = null;

        $sectionnum = -1;
        $thissection = null;
        $firstthissection = null;
        $flag = false;
        $sectionflag = false;
        $previousmod = null;

        foreach ($modinfo->cms as $mod) {
            if ($mod->modname == 'label') {
                continue;
            }
            $format = course_get_format($COURSE);
            if (method_exists($format, 'get_last_section_number')) {
                $numsections = $format->get_last_section_number();
            } else {
                $opts = course_get_format($COURSE)->get_format_options();
                $numsections = isset($opts['numsections']) ? $opts['numsections'] : 0;
            }
            if ($numsections && $mod->sectionnum > $numsections) {
                break;
            }
            if (!$mod->uservisible) {
                continue;
            }
            if ($mod->sectionnum > 0 && $sectionnum != $mod->sectionnum) {
                $thissection = $sections[$mod->sectionnum];
                if ($thissection->visible ||
                    has_capability('moodle/course:viewhiddensections', $context)
                ) {
                    $sectionnum = $mod->sectionnum;
                    $firstthissection = false;
                    if ($sectionflag) {
                        if ($flag) { // Flag means selected mod was the last in the section.
                            $lastsection = 'none';
                        } else {
                            $lastsection = $previousmod;
                        }
                        $sectionflag = false;
                    }
                } else {
                    continue;
                }
            }
            $thismod = (object)[
                'link' => new \moodle_url('/mod/'.$mod->modname.'/view.php', ['id' => $mod->id]),
                'name' => strip_tags(format_string($mod->name, true)),
            ];
            if ($flag) { // Current mod is the 'next' mod.
                $next = $thismod;
                $flag = false;
            }
            if ($cmid == $mod->id) {
                $flag = true;
                $sectionflag = true;
                $prev = $previousmod;
                $firstsection = $firstthissection;
            }
            if (!$firstthissection) {
                $firstthissection = $thismod;
            }
            if (!$firstcourse) {
                $firstcourse = $thismod;
            }
            $previousmod = $thismod;
        }

        return $next;
    }

}
