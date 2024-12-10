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
 * Render the Nugget widget which allows to display a Nugget in Moodle.
 * @author Bruno Ilponse
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_nugget
 */
namespace mod_nugget;

/**
 * Render the Nugget widget which allows to display a Nugget in Moodle.
 * @author Bruno Ilponse
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_nugget
 */
class nugget_widget {
    /**
     * Render this Nugget widget as HTML
     * @param int $nuggetid
     * @param int $cmid
     * @param string $component
     * @return string
     */
    public static function nugget_widget_html($nuggetid, $cmid, $component): string {
        global $CFG;
        $widgetconfig = json_encode([
            "mount_point" => "#nugget_widget",
            "proxy_url" => "$CFG->wwwroot/mod/nugget/proxy.php",
            "component" => $component,
            "nugget_id" => $nuggetid,
            "cm_id" => $cmid, // Course module ID.
            "labels" => [
                "nugget_search_here" => get_string('nugget_search_here', 'nugget'),
                "nugget_search_no_result" => get_string('nugget_search_no_result', 'nugget'),
                "search" => get_string('nugget_search', 'nugget'),
                "click_to_replace" => get_string('click_to_replace', 'nugget'),
                "clear_filters" => get_string('clear_filters', 'nugget'),
                "show_more_authors" => get_string('show_more_authors', 'nugget'),
                "hide_authors" => get_string('hide_authors', 'nugget'),
                "no_nugget" => get_string('no_nugget', 'nugget'),
                "about" => get_string('about', 'nugget'),
                "back_to_course" => get_string('back_to_course', 'nugget'),
                "next_unit" => get_string('next_unit', 'nugget'),
                "show_more_nugget_button" => get_string('show_more_nugget_button', 'nugget'),
                "select_button" => get_string('select_button', 'nugget'),
                "preview_button" => get_string('preview_button', 'nugget'),
                "loading" => get_string('loading', 'nugget'),
                "metadata" => [
                    "preview" => get_string('preview', 'nugget'),
                    "description" => get_string('description', 'nugget'),
                    "in_brief" => get_string('in_brief', 'nugget'),
                    "about_author" => get_string('about_author', 'nugget'),
                    "learning_outcomes" => get_string('learning_outcomes', 'nugget'),
                    "prerequisites" => get_string('prerequisites', 'nugget'),
                    "references" => get_string('references', 'nugget'),
                    "field_of_study" => get_string('field_of_study', 'nugget'),
                    "language" => get_string('language', 'nugget'),
                    "duration" => get_string('duration', 'nugget'),
                    "level" => get_string('level', 'nugget'),
                    "structure_id" => get_string('structure_id', 'nugget'),
                    "advanced" => get_string('advanced', 'nugget'),
                    "intermediate" => get_string('intermediate', 'nugget'),
                    "beginner" => get_string('beginner', 'nugget'),
                    "tags" => get_string('tags', 'nugget'),
                    "producers" => get_string('producers', 'nugget'),
                    "authors" => get_string('authors', 'nugget'),
                    "related_domains" => get_string('field_of_study', 'nugget'),
                    "type" => get_string('type', 'nugget'),
                    "lesson" => get_string('lesson', 'nugget'),
                    "demo" => get_string('demo', 'nugget'),
                    "tutorial" => get_string('tutorial', 'nugget'),
                    "en" => get_string('en', 'nugget'),
                    "fr" => get_string('fr', 'nugget'),
                    "de" => get_string('de', 'nugget'),
                    "es" => get_string('es', 'nugget'),
                    "it" => get_string('it', 'nugget'),
                    "pl" => get_string('pl', 'nugget'),
                    "sv" => get_string('sv', 'nugget'),
                    "publication_date" => get_string('publication_date', 'nugget'),
                ],
                "rating" => [
                    "title" => get_string('rating_title', 'nugget'),
                    "description" => get_string('rating_description', 'nugget'),
                    "send" => get_string('rating_send', 'nugget'),
                    "sent" => get_string('rating_sent', 'nugget'),
                ],
                "learning_outcomes_desc" => get_string('learning_outcomes_desc', 'nugget'),
                "complete_nugget" => get_string('complete_nugget', 'nugget'),
            ],
        ]);
        $html = "<div id='nugget_widget'></div>";
        $html .= "<script>NAAS=$widgetconfig</script>";
        $widgetjsurl = new \moodle_url('/mod/nugget/assets/vue/nugget_widget.js');
        $html .= "<script src='$widgetjsurl' ></script>";

        return $html;
    }

    /**
     * Returns the grading options.
     * @return array int => lang string the options for calculating the NaaS grade
     *      from the individual attempt grades.
     */
    public static function nugget_get_grading_options() {
        return [
            NAAS_GRADEHIGHEST => get_string('gradehighest', 'nugget'),
            NAAS_ATTEMPTFIRST => get_string('attemptfirst', 'nugget'),
            NAAS_ATTEMPTLAST  => get_string('attemptlast', 'nugget'),
        ];
    }
}
