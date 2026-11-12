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
 * Output for the NaaS widget.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\output;

use renderable;
use stdClass;
use templatable;

/**
 * Output for the NaaS widget.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class widget implements renderable, templatable {
    /**
     * Version suffix of the Vue bundle shipped in assets/vue/naas_widget-<version>.js.
     * Kept in sync with BUNDLE_VERSION in vue/vite.config.ts by vue/scripts/bump-version.js.
     */
    public const BUNDLE_VERSION = '2026111206';

    /** @var int The nugget ID. */
    protected $nuggetid;

    /** @var int The course ID. */
    protected $courseid;

    /** @var int The course module ID. */
    protected $cmid;

    /** @var string The component name. */
    protected $component;

    /**
     * Constructor.
     *
     * @param int $nuggetid
     * @param int $courseid
     * @param int $cmid
     * @param string $component
     */
    public function __construct($nuggetid, $courseid, $cmid, $component) {
        $this->nuggetid = $nuggetid;
        $this->courseid = $courseid;
        $this->cmid = $cmid;
        $this->component = $component;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return stdClass
     */
    public function export_for_template($output): stdClass {
        global $CFG;

        // Only the activity form browses the catalogue. The learner view must not
        // receive the cached catalogue (producers, author labels, card texts).
        $snapshot = $this->component === 'NuggetSearchWidget'
            ? \mod_naas\catalogue_export::export_for_widget()
            : null;

        $widgetconfig = [
            "moodle_url" => $CFG->wwwroot,
            "mount_point" => "#naas_widget",
            "component" => $this->component,
            "nugget_id" => $this->nuggetid,
            "courseId" => $this->courseid,
            "cm_id" => $this->cmid,
            "catalogue_snapshot" => $snapshot,
            "license_filter" => \mod_naas\catalogue_filters::widget_license_config(),
            // The About sheet shows this institute's licence. Not secret: the LTI launch sends it too.
            "structure_id" => (string) get_config('naas', 'naas_structure_id'),
            // Same rule as the LTI "feedback" option sent to the player (naas_lti::lti_custom_options).
            "rating_enabled" => (string) get_config('naas', 'naas_feedback') === '1',
            "labels" => [
                "error_generic_user_message" => get_string("error:generic_user_message", "naas"),
                "error_nugget_not_found" => get_string("error_nugget_not_found", "naas"),
                "nugget_search_here" => get_string('nugget_search_here', 'naas'),
                "nugget_search_no_result" => get_string('nugget_search_no_result', 'naas'),
                "nugget_search_collecting" => get_string('nugget_search_collecting', 'naas'),
                "search" => get_string('nugget_search', 'naas'),
                "click_to_replace" => get_string('click_to_replace', 'naas'),
                "clear_filters" => get_string('clear_filters', 'naas'),
                "show_more_authors" => get_string('show_more_authors', 'naas'),
                "hide_authors" => get_string('hide_authors', 'naas'),
                "no_nugget" => get_string('no_nugget', 'naas'),
                "about" => get_string('about', 'naas'),
                "about_title" => get_string('about_title', 'naas'),
                "labelsep" => get_string('labelsep', 'langconfig'),
                "preview_title" => get_string('preview_title', 'naas'),
                "close" => get_string('closebuttontitle'),
                "back_to_course" => get_string('back_to_course', 'naas'),
                "next_unit" => get_string('next_unit', 'naas'),

                "show_more_nugget_button" => get_string('show_more_nugget_button', 'naas'),
                "select_button" => get_string('select_button', 'naas'),
                "preview_button" => get_string('preview_button', 'naas'),
                "loading" => get_string('loading', 'naas'),
                "open_access" => get_string('open_access', 'naas'),
                "all_nuggets" => get_string('all_nuggets', 'naas'),
                "by_producers" => get_string('by_producers', 'naas'),
                "view_as_cards" => get_string('view_as_cards', 'naas'),
                "view_as_list" => get_string('view_as_list', 'naas'),
                "browse_help" => get_string('browse_help', 'naas'),
                "back_to_catalogue" => get_string('back_to_catalogue', 'naas'),
                "checking_updates" => get_string('checking_updates', 'naas'),
                "browse_nugget_count" => get_string('browse_nugget_count', 'naas'),
                "browse_no_producers" => get_string('browse_no_producers', 'naas'),
                "retry" => get_string('retry', 'naas'),
                "active_filters" => get_string('active_filters', 'naas'),
                "clear_search" => get_string('clear_search', 'naas'),
                "remove_filter" => get_string('remove_filter', 'naas'),
                "duration_minutes" => get_string('duration_minutes', 'naas'),
                "unknown_author" => get_string('unknown_author', 'naas'),
                "unknown_producer" => get_string('unknown_producer', 'naas'),
                "metadata" => [
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
                    "domains" => get_string('field_of_study', 'naas'),
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
                    "license" => get_string('license', 'naas'),
                    "license_1" => get_string('license_1', 'naas'),
                    "license_2" => get_string('license_2', 'naas'),
                    "license_3" => get_string('license_3', 'naas'),
                    "license_4" => get_string('license_4', 'naas'),
                    "license_right_by" => get_string('license_right_by', 'naas'),
                    "license_right_co" => get_string('license_right_co', 'naas'),
                    "license_right_nc" => get_string('license_right_nc', 'naas'),
                    "license_right_nd" => get_string('license_right_nd', 'naas'),
                    "license_right_ru" => get_string('license_right_ru', 'naas'),
                    "license_right_sa" => get_string('license_right_sa', 'naas'),
                    "license_right_uu" => get_string('license_right_uu', 'naas'),
                    "producer" => get_string('producer', 'naas'),
                    "partner" => get_string('partner', 'naas'),
                    "is_public" => get_string('is_public', 'naas'),
                    "public" => get_string('public', 'naas'),
                    "private" => get_string('private', 'naas'),
                    "partner_with" => get_string('partner_with', 'naas'),
                    "filters" => get_string('filters', 'naas'),
                ],
                "rating" => [
                    "title" => get_string('rating_title', 'naas'),
                    "description" => get_string('rating_description', 'naas'),
                    "send" => get_string('rating_send', 'naas'),
                    "sent" => get_string('rating_sent', 'naas'),
                    "value" => get_string('rating_value', 'naas'),
                ],
                "learning_outcomes_desc" => get_string('learning_outcomes_desc', 'naas'),
                "complete_nugget" => get_string('complete_nugget', 'naas'),
            ],
        ];

        $data = new stdClass();
        $data->config = json_encode(
            $widgetconfig,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
        );
        $widgetjsurl = new \moodle_url('/mod/naas/assets/vue/naas_widget-' . self::BUNDLE_VERSION . '.js');
        $data->widgetjsurl = $widgetjsurl->out(false);

        return $data;
    }
}
