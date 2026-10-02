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
 * Test proxy for the xAPI enrolment gate.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

/**
 * Calls {@see xapi::require_active_course_enrolment()} from tests without reflection.
 *
 * PCOV / Xdebug often do not attribute coverage for code executed only through
 * {@see \ReflectionMethod::invoke()}, so this thin subclass keeps the enrolment gate fully covered.
 */
final class xapi_enrol_test_proxy extends xapi {
    /**
     * Call the active enrolment gate.
     *
     * @param int $courseid
     */
    public static function invoke_require_active_course_enrolment(int $courseid): void {
        self::require_active_course_enrolment($courseid);
    }
}
