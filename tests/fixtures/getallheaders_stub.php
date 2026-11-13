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
 * Stand-in for getallheaders(), which the PHP CLI does not provide.
 *
 * Only for tests that run in a separate process: once declared, the global
 * function stays for the rest of the process. The headers it returns are read
 * from $GLOBALS['mod_naas_test_request_headers'].
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!function_exists('getallheaders')) {
    /**
     * Request headers of the simulated HTTP request.
     *
     * @return array Header name => value.
     */
    function getallheaders(): array {
        return $GLOBALS['mod_naas_test_request_headers'] ?? [];
    }
}
