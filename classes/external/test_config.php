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
 * Web service mod_naas_test_config.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
namespace mod_naas\external;

/**
 * Test the plugin configuration: run a small search on NaaS and warm the catalogue.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
class test_config extends naas_external_base {
    /**
     * Test config parameters description.
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([]);
    }

    /**
     * Test config return description.
     * @return \core_external\external_value
     */
    public static function execute_returns() {
        return new \core_external\external_value(PARAM_RAW, 'API response');
    }

    /**
     * Test config method.
     * @return string the JSON encoded response.
     */
    public static function execute() {
        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('mod/naas:admin', $context);

        $config = self::naas_config();
        $naas = static::make_naas_client($config);

        $url = '/nuggets/search?' . http_build_query([
                'is_default_version' => true,
                'page_size' => 2,
            ], '', '&');

        $result = self::sanitise_json_response($naas->request_raw('GET', $url));
        \mod_naas\catalogue_warmer::warm($naas, $config);
        return $result;
    }
}
