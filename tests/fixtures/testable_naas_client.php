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
 * HTTP stub for {@see \mod_naas\naas_client}.
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

/**
 * Subclass of naas_client that replaces request_raw() with a controllable stub.
 *
 * Tests set $stubjson and $stuberror before calling the public API methods,
 * and inspect $captured to verify what arguments were passed to the transport.
 */
class testable_naas_client extends naas_client {
    /** @var string JSON string returned by the stub transport. */
    public string $stubjson = '{}';

    /** @var bool When true, request_raw() throws a moodle_exception. */
    public bool $stuberror = false;

    /** @var array Arguments received by the last request_raw() call. */
    public array $captured = [];

    /**
     * Override: capture arguments and return the stub response.
     *
     * @param string      $protocol
     * @param string      $service
     * @param object|null $data
     * @param array|null  $params
     * @return string
     * @throws \moodle_exception when $stuberror is true
     */
    public function request_raw($protocol, $service, $data = null, $params = null): string {
        $this->captured = [
            'protocol' => $protocol,
            'service' => $service,
            'data' => $data,
            'params' => $params,
        ];

        if ($this->stuberror) {
            throw new \moodle_exception(
                'error:proxy_naas_api:curl',
                'naas',
                '',
                'stub curl error',
                json_encode(['errno' => 1, 'error' => 'stub curl error', 'url' => ''])
            );
        }

        return $this->stubjson;
    }
}
