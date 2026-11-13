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
 * NaaS client stubs shared by the web service tests.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/injected_naas_client.php');
require_once(__DIR__ . '/testable_naas_client.php');
require_once(__DIR__ . '/routing_naas_client.php');

/**
 * Builds the NaaS clients a web service test injects: none of them sends an HTTP request.
 */
trait naas_client_stubs {
    /**
     * Client stub whose transport fails like an unreachable NaaS endpoint.
     *
     * It records the request it received in $captured before throwing.
     *
     * @return \mod_naas\testable_naas_client
     */
    private function failing_client(): \mod_naas\testable_naas_client {
        $client = $this->answering_client('{}');
        $client->stuberror = true;
        return $client;
    }

    /**
     * Client stub answering every request with the same body.
     *
     * @param string $body Raw HTTP body.
     * @return \mod_naas\testable_naas_client
     */
    private function answering_client(string $body): \mod_naas\testable_naas_client {
        $client = new \mod_naas\testable_naas_client((object) [
            'naas_endpoint' => 'https://Stub.example',
            'naas_username' => 'u',
            'naas_password' => 'p',
            'naas_structure_id' => 's',
        ]);
        $client->stubjson = $body;
        return $client;
    }

    /**
     * Client stub answering each request with $route($service, $params).
     *
     * @param callable $route fn(string $service, array $params): string, may throw like the real client.
     * @return \mod_naas\routing_naas_client
     */
    private function routing_client(callable $route): \mod_naas\routing_naas_client {
        return new \mod_naas\routing_naas_client(function (string $protocol, string $service, $params) use ($route): string {
            return $route($service, (array) $params);
        });
    }
}
