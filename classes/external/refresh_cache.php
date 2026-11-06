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
 * Web service mod_naas_refresh_cache.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
namespace mod_naas\external;

/**
 * Clear every NaaS cache and load it again from the saved connection.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
class refresh_cache extends naas_external_base {
    /**
     * Refresh cache parameters description.
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters([]);
    }

    /**
     * Refresh cache return description.
     * @return \core_external\external_value
     */
    public static function execute_returns() {
        return new \core_external\external_value(PARAM_RAW, 'Status message');
    }

    /**
     * Clear every NaaS cache and load it again from the saved connection.
     *
     * @return string
     */
    public static function execute() {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('mod/naas:admin', $context);

        return \mod_naas\cache_refresh::full();
    }
}
