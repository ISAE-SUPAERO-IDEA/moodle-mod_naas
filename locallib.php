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
 * Private url module utility functions
 *
 * @package    mod_naas
 * @copyright  2019 Bruno Ilponse  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function naas_widget_html($nugget_id, $component) {
    global $CFG;
    $widget_config = json_encode([
        "mount_point"=> "#naas_search_widget",
        "proxy_url"=> "$CFG->wwwroot/mod/naas/proxy.php",
        "component" => $component,
        "nugget_id" => $nugget_id,
        "labels" => [
            "search_here" => get_string('nugget_search_here','naas'),
            "search" => get_string('nugget_search','naas'),
            "click_to_modify" => get_string('click_to_modify','naas'),
            "clear_filters" => get_string('clear_filters','naas'),
            "no_nugget" => get_string('no_nugget','naas'),
            "see_nugget_details" => get_string('see_nugget_details','naas'),
            "back_to_course" => get_string('back_to_course','naas'),
            "preview_button" => get_string('preview_button','naas'),
            "details_button" => get_string('details_button','naas'),
            "metadata" => [
                "preview" => get_string('preview','naas'),
                "details" => get_string('details','naas'),
                "resume" => get_string('resume','naas'),
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
                "en" => get_string('en','naas'),
                "english" => get_string('english','naas'),
                "fr" => get_string('fr','naas'),
                "french" => get_string('french','naas'),
            ]
        ]
    ]);
    $html = "";
    $html .= "<div id='naas_search_widget'></div>"; 
    $html .= "<script>NAAS=$widget_config</script>"; 
    // TODO: use $PAGE->require->js
    $search_widget_url = new moodle_url('/mod/naas/assets/vue/search_widget.js');
    $html .= "<script src='$search_widget_url' ></script>";

    return $html;
}
