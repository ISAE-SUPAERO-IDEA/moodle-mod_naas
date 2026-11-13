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
 * Web service mod_naas_get_nugget.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
namespace mod_naas\external;

/**
 * Get the default version of a Nugget, from the Moodle cache or from NaaS.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
class get_nugget extends naas_external_base {
    /**
     * Get nugget parameters description.
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters(
            [
                'courseId' => new \core_external\external_value(PARAM_INT, 'Course ID'),
                'nuggetId' => new \core_external\external_value(PARAM_TEXT, 'Nugget ID'),
                'mode' => new \core_external\external_value(
                    PARAM_ALPHAEXT,
                    'cache_first to serve a stored document, revalidate to force a live call',
                    VALUE_DEFAULT,
                    'cache_first'
                ),
            ]
        );
    }

    /**
     * Get nugget return description.
     * @return \core_external\external_value
     */
    public static function execute_returns() {
        return new \core_external\external_value(PARAM_RAW, 'API response');
    }

    /**
     * Get nugget method.
     *
     * Reopening an activity form used to cost a NaaS round-trip every time.
     * In cache_first mode Moodle answers from its own copy and flags
     * `_cache.hit`; the widget then revalidates in the background and only
     * swaps the card when `modification_date` or `version_id` moved.
     *
     * @param int $courseid
     * @param string $nuggetid
     * @param string $mode
     * @return string the JSON encoded response.
     */
    public static function execute(int $courseid, string $nuggetid, string $mode = 'cache_first') {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['courseId' => $courseid, 'nuggetId' => $nuggetid, 'mode' => $mode]
        );

        // Context validation.
        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:addinstance', $context);

        self::validate_id_param($params['nuggetId'], 'nuggetId');

        $config = self::naas_config();

        if ($params['mode'] !== 'revalidate') {
            $cached = \mod_naas\nugget_cache::get($params['nuggetId'], $config);
            if ($cached !== null) {
                return \mod_naas\nugget_cache::response($cached, true);
            }
        }

        $naas = static::make_naas_client($config);
        $url = "/nuggets/{$params['nuggetId']}/default_version";
        $result = self::sanitise_json_response($naas->request_raw('GET', $url));

        $entry = \mod_naas\nugget_cache::store($params['nuggetId'], $result, $config);
        if ($entry === null) {
            return $result;
        }
        return \mod_naas\nugget_cache::response($entry, false);
    }
}
