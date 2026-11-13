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
 * Test proxy for the protected helpers of the NaaS web services.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

/**
 * Calls the protected helpers of {@see naas_external_base} from tests without reflection (PCOV-friendly).
 */
final class naas_external_base_test_proxy extends naas_external_base {
    /**
     * Call the UUID/slug check.
     *
     * @param string $value
     * @param string $paramname
     */
    public static function invoke_validate_id_param(string $value, string $paramname): void {
        self::validate_id_param($value, $paramname);
    }

    /**
     * Build the merged plugin and site configuration.
     *
     * @return object
     */
    public static function invoke_naas_config(): object {
        return self::naas_config();
    }

    /**
     * Build the real NaaS client.
     *
     * @param object $config
     * @return \mod_naas\naas_client
     */
    public static function invoke_make_naas_client(object $config): \mod_naas\naas_client {
        return self::make_naas_client($config);
    }

    /**
     * Call the active enrolment gate.
     *
     * @param int $courseid
     */
    public static function invoke_require_active_course_enrolment(int $courseid): void {
        self::require_active_course_enrolment($courseid);
    }
}
