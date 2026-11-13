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
 * Web service mod_naas_view_nugget.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
namespace mod_naas\external;

/**
 * Get the Nugget of an activity for a learner enrolled in its course.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
class view_nugget extends naas_external_base {
    /**
     * View nugget parameters description.
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters(
            [
                'cmId' => new \core_external\external_value(PARAM_INT, 'Course Module ID'),
            ]
        );
    }

    /**
     * View nugget returns description.
     * @return \core_external\external_value
     */
    public static function execute_returns() {
        return new \core_external\external_value(PARAM_RAW, 'API response');
    }

    /**
     * View nugget method.
     * @param int $cmid
     * @return string the JSON encoded response.
     */
    public static function execute(int $cmid) {
        global $DB;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmId' => $cmid]
        );

        $context = \context_module::instance($params['cmId']);
        self::validate_context($context);
        require_capability('mod/naas:view', $context);

        // Get course module and instance.
        $cm = get_coursemodule_from_id('naas', $params['cmId'], 0, false, MUST_EXIST);

        // Verify the calling user is actually enrolled in the module's course.
        self::require_active_course_enrolment((int) $cm->course);

        $naasinstance = $DB->get_record('naas', ['id' => $cm->instance], '*', MUST_EXIST);
        // The stored id comes from the activity form (PARAM_TEXT), so check it before it reaches the URL path.
        self::validate_id_param((string) $naasinstance->nugget_id, 'nugget_id');

        $naas = static::make_naas_client(self::naas_config());

        $url = "/nuggets/{$naasinstance->nugget_id}/default_version";
        try {
            return self::sanitise_json_response($naas->request_raw('GET', $url));
        } catch (\moodle_exception $e) {
            // NaaS answers 404 (empty body) when the Nugget was unpublished or deleted:
            // tell the learner that, not a generic "resource not found".
            if ($e->errorcode === 'error:naas_api:not_found') {
                throw new \moodle_exception('error_nugget_not_found', 'naas', '', null, $e->debuginfo);
            }
            throw $e;
        }
    }
}
