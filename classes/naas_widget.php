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
 * Render the NaaS widget which allows to display a Nugget in Moodle.
 * @author Bruno Ilponse
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
namespace mod_naas;

/**
 * Render the NaaS widget which allows to display a Nugget in Moodle.
 * @author Bruno Ilponse
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
class naas_widget {
    /**
     * Render this NaaS widget as HTML
     * @param int $nuggetid
     * @param int $courseid
     * @param int $cmid
     * @param string $component
     * @return string
     */
    public static function naas_widget_html($nuggetid, $courseid, $cmid, $component): string {
        global $CFG, $PAGE;

        $PAGE->requires->js_call_amd('core/first', null, true);

        $widgetconfig = json_encode([
            "moodle_url" => $CFG->wwwroot,
            "mount_point" => "#naas_widget",
            "component" => $component,
            "nugget_id" => $nuggetid,
            "courseId" => $courseid,
            "cm_id" => $cmid, // Course module ID.
            "labels" => [
                "error_generic_user_message" => get_string("error:generic_user_message", "naas"),
                "nugget_search_here" => get_string('nugget_search_here', 'naas'),
                "nugget_search_no_result" => get_string('nugget_search_no_result', 'naas'),
                "search" => get_string('nugget_search', 'naas'),
                "click_to_replace" => get_string('click_to_replace', 'naas'),
                "clear_filters" => get_string('clear_filters', 'naas'),
                "show_more_authors" => get_string('show_more_authors', 'naas'),
                "hide_authors" => get_string('hide_authors', 'naas'),
                "no_nugget" => get_string('no_nugget', 'naas'),
                "about" => get_string('about', 'naas'),
                "back_to_course" => get_string('back_to_course', 'naas'),
                "next_unit" => get_string('next_unit', 'naas'),
                "show_more_nugget_button" => get_string('show_more_nugget_button', 'naas'),
                "select_button" => get_string('select_button', 'naas'),
                "preview_button" => get_string('preview_button', 'naas'),
                "loading" => get_string('loading', 'naas'),
                "metadata" => [
                    "preview" => get_string('preview', 'naas'),
                    "description" => get_string('description', 'naas'),
                    "in_brief" => get_string('in_brief', 'naas'),
                    "about_author" => get_string('about_author', 'naas'),
                    "learning_outcomes" => get_string('learning_outcomes', 'naas'),
                    "prerequisites" => get_string('prerequisites', 'naas'),
                    "references" => get_string('references', 'naas'),
                    "field_of_study" => get_string('field_of_study', 'naas'),
                    "language" => get_string('language', 'naas'),
                    "duration" => get_string('duration', 'naas'),
                    "level" => get_string('level', 'naas'),
                    "structure_id" => get_string('structure_id', 'naas'),
                    "advanced" => get_string('advanced', 'naas'),
                    "intermediate" => get_string('intermediate', 'naas'),
                    "beginner" => get_string('beginner', 'naas'),
                    "tags" => get_string('tags', 'naas'),
                    "producers" => get_string('producers', 'naas'),
                    "authors" => get_string('authors', 'naas'),
                    "related_domains" => get_string('field_of_study', 'naas'),
                    "type" => get_string('type', 'naas'),
                    "lesson" => get_string('lesson', 'naas'),
                    "demo" => get_string('demo', 'naas'),
                    "tutorial" => get_string('tutorial', 'naas'),
                    "en" => get_string('en', 'naas'),
                    "fr" => get_string('fr', 'naas'),
                    "de" => get_string('de', 'naas'),
                    "es" => get_string('es', 'naas'),
                    "it" => get_string('it', 'naas'),
                    "pl" => get_string('pl', 'naas'),
                    "sv" => get_string('sv', 'naas'),
                    "publication_date" => get_string('publication_date', 'naas'),
                ],
                "rating" => [
                    "title" => get_string('rating_title', 'naas'),
                    "description" => get_string('rating_description', 'naas'),
                    "send" => get_string('rating_send', 'naas'),
                    "sent" => get_string('rating_sent', 'naas'),
                ],
                "learning_outcomes_desc" => get_string('learning_outcomes_desc', 'naas'),
                "complete_nugget" => get_string('complete_nugget', 'naas'),
            ],
        ]);
        $html = "<div id='naas_widget'></div>";
        $html .= "<script>NAAS=$widgetconfig</script>";
        $widgetjsurl = new \moodle_url('/mod/naas/assets/vue/naas_widget-2025061000.js');
        $html .= "<script src='$widgetjsurl' ></script>";

        return $html;
    }

    /**
     * Returns the grading options.
     * @return array int => lang string the options for calculating the NaaS grade
     *      from the individual attempt grades.
     */
    public static function naas_get_grading_options() {
        return [
            NAAS_GRADEHIGHEST => get_string('gradehighest', 'naas'),
            NAAS_ATTEMPTFIRST => get_string('attemptfirst', 'naas'),
            NAAS_ATTEMPTLAST  => get_string('attemptlast', 'naas'),
        ];
    }
}
