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

// TODO Doc
namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');


// TODO Doc
class api extends  \external_api {

    /**
     * Test config parameters description
     */
    public static function test_config_parameters(): \external_function_parameters {
        return new \external_function_parameters([]);
    }

    /**
     * Test config return description
     */
    public static function test_config_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    /**
     * Test config method
     */
    public static function test_config() {
        global $CFG;

        // Context validation
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('mod/naas:admin', $context);

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new \mod_naas\naas_client($config);

        $url = '/nuggets/search?' . http_build_query([
                'is_default_version' => true,
                'page_size' => 2,
            ]);

        $response = $naas->request_raw('GET', $url);
        return $response->build_client_response();
    }

    /**
     * Get nugget parameters description
     */
    public static function get_nugget_parameters() {
        return new \external_function_parameters(
            array(
                'courseId' => new external_value(PARAM_INT, 'Course ID'),
                'nuggetId' => new external_value(PARAM_TEXT, 'Nugget ID')
            )
        );
    }

    /**
     * Get nugget return description
     */
    public static function get_nugget_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    /**
     * Get nugget method
     */
    public static function get_nugget($courseid, $nuggetid) {
        global $CFG;

        $params = self::validate_parameters(self::get_nugget_parameters(),
            array('courseId' => $courseid, 'nuggetId' => $nuggetid));

        // Context validation
        $context = context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:addinstance', $context);

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new \mod_naas\naas_client($config);

        $url = "/nuggets/{$params['nuggetId']}/default_version";
        $response = $naas->request_raw('GET', $url);
        return $response->build_client_response();
    }

    // TODO ... Implementer les autres méthodes de la même façon

}
