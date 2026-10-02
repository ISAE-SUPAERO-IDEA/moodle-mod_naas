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
 * HTTP stub used by the xAPI external service tests.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

/**
 * Test-only stub: avoids outbound HTTP from xapi::post_xapi_statement().
 */
final class stub_naas_client_for_xapi extends \mod_naas\naas_client {
    /**
     * Create the stub.
     */
    public function __construct() {
        $minimal = new \stdClass();
        $minimal->naas_endpoint = 'http://Stub.local';
        $minimal->naas_username = 'u';
        $minimal->naas_password = 'p';
        $minimal->naas_structure_id = 's';
        parent::__construct($minimal);
    }

    /**
     * Return a stubbed xAPI acceptance.
     */
    public function post_xapi_statement($verb, $versionid, $data) {
        return (object) [
            'statusCode' => 202,
            'statusMessage' => 'Accepted',
        ];
    }
}
