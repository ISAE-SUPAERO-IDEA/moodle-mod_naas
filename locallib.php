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
 * Moodle Nugget Plugin : Private url module utility functions
 *
 * @package    mod_naas
 * @copyright  2019 Bruno Ilponse  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function naas_widget_html($nugget_id, $cm_id, $component) {
    global $CFG;
    $widget_config = json_encode([
        "mount_point"=> "#naas_widget",
        "proxy_url"=> "$CFG->wwwroot/mod/naas/proxy.php",
        "component" => $component,
        "nugget_id" => $nugget_id,
        "cm_id" => $cm_id, // Course module ID
        "labels" => [
            "nugget_search_here" => get_string('nugget_search_here','naas'),
            "nugget_search_no_result" => get_string('nugget_search_no_result','naas'),
            "search" => get_string('nugget_search','naas'),
            "click_to_replace" => get_string('click_to_replace','naas'),
            "clear_filters" => get_string('clear_filters','naas'),
            "show_more_authors" => get_string('show_more_authors','naas'),
            "hide_authors" => get_string('hide_authors','naas'),
            "no_nugget" => get_string('no_nugget','naas'),
            "about" => get_string('about','naas'),
            "back_to_course" => get_string('back_to_course','naas'),
            "next_unit" => get_string('next_unit','naas'),
            "show_more_nugget_button" => get_string('show_more_nugget_button','naas'),
            "select_button" => get_string('select_button','naas'),
            "preview_button" => get_string('preview_button','naas'),
            "loading" => get_string('loading','naas'),
            "metadata" => [
                "preview" => get_string('preview','naas'),
                "description" => get_string('description','naas'),
                "in_brief" => get_string('in_brief','naas'),
                "about_author" => get_string('about_author','naas'),
                "learning_outcomes" => get_string('learning_outcomes','naas'),
                "prerequisites" => get_string('prerequisites','naas'),
                "references" => get_string('references','naas'),
                "field_of_study" => get_string('field_of_study','naas'),
                "language" => get_string('language','naas'),
                "duration" => get_string('duration','naas'),
                "level" => get_string('level','naas'),
                "structure_id" => get_string('structure_id','naas'),
                "advanced" => get_string('advanced','naas'),
                "intermediate" => get_string('intermediate','naas'),
                "beginner" => get_string('beginner','naas'),
                "tags" => get_string('tags','naas'),
                "producers" => get_string('producers','naas'),
                "authors" => get_string('authors','naas'),
                "related_domains" => get_string('field_of_study','naas'),
                "type" => get_string('type','naas'),
                "lesson" => get_string('lesson','naas'),
                "demo" => get_string('demo','naas'),
                "tutorial" => get_string('tutorial','naas'),
                "en" => get_string('en','naas'),
                "fr" => get_string('fr','naas'),
                "de" => get_string('de','naas'),
                "es" => get_string('es','naas'),
                "it" => get_string('it','naas'),
                "pl" => get_string('pl','naas'),
                "sv" => get_string('sv','naas'),
                "publication_date" => get_string('publication_date','naas'),
            ],
            "rating" => [
                "title" => get_string('rating_title','naas'),
                "description" => get_string('rating_description','naas'),
                "send" => get_string('rating_send','naas'),
                "sent" => get_string('rating_sent','naas'),
            ],
            "learning_outcomes_desc" => get_string('learning_outcomes_desc','naas'),
            "complete_nugget" => get_string('complete_nugget','naas'),
        ]
    ]);
    $html = "<div id='naas_widget'></div>"; 
    $html .= "<script>NAAS=$widget_config</script>"; 
    // TODO: use $PAGE->require->js
    $widget_js_url = new moodle_url('/mod/naas/assets/vue/naas_widget.js');
    $html .= "<script src='$widget_js_url' ></script>";

    return $html;
}

function get_next_activity_url() {
    /* Gets the link to the next activity of the course */
    /* Adapted from https://gist.github.com/frumbert/b4fbb8e6f9a23c7233128a1f51df02b7 */

    global $PAGE, $CFG, $COURSE, $DB;

    require_once($CFG->libdir . '/modinfolib.php');

    $cmid = $PAGE->cm->id;
    $modinfo = get_fast_modinfo($COURSE);
    $context = context_course::instance($COURSE->id);
    $sections = $DB->get_records('course_sections', array('course' => $COURSE->id), 'section', 'section,visible,summary');

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
                // !$COURSE->hiddensections ||
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
            'link' => new moodle_url('/mod/'.$mod->modname.'/view.php', array('id' => $mod->id)),
            'name' => strip_tags(format_string($mod->name, true))
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

/**
 * @return array int => lang string the options for calculating the NaaS grade
 *      from the individual attempt grades.
 */
function naas_get_grading_options() {
    return array(
        NAAS_GRADEHIGHEST => get_string('gradehighest', 'naas'),
        NAAS_ATTEMPTFIRST => get_string('attemptfirst', 'naas'),
        NAAS_ATTEMPTLAST  => get_string('attemptlast', 'naas')
    );
}

/**
 * @param int $option one of the values NAAS_GRADEHIGHEST,
 *      NAAS_ATTEMPTFIRST or NAAS_ATTEMPTLAST.
 * @return the lang string for that option.
 */
function naas_get_grading_option_name($option) {
    $strings = naas_get_grading_options();
    return $strings[$option];
}
