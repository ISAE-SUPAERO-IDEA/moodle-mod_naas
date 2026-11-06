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
 * HTTP stub for {@see \mod_naas\naas_client} that answers per request.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

/**
 * NaaS client whose transport is a callback, so one test can answer each route differently.
 *
 * The callback receives the protocol, the service path and the query parameters, and
 * returns the raw body or throws the exception the real client would throw. Every call
 * is recorded in $calls.
 */
class routing_naas_client extends naas_client {
    /** @var callable Answers a request: fn(string $protocol, string $service, ?array $params): string. */
    private $responder;

    /** @var array[] Requests received: ['protocol' => ..., 'service' => ..., 'params' => ...]. */
    public $calls = [];

    /**
     * Create the stub.
     *
     * @param callable $responder Answers each request.
     */
    public function __construct(callable $responder) {
        parent::__construct((object) [
            'naas_endpoint' => 'https://api.naas.example/api',
            'naas_username' => 'u',
            'naas_password' => 'p',
            'naas_structure_id' => 's',
        ]);
        $this->responder = $responder;
    }

    /**
     * Record the request and let the callback answer it.
     *
     * @param string $protocol
     * @param string $service
     * @param mixed $data
     * @param mixed $params
     * @return string
     */
    public function request_raw($protocol, $service, $data = null, $params = null): string {
        $this->calls[] = ['protocol' => $protocol, 'service' => $service, 'params' => $params];
        return ($this->responder)($protocol, $service, $params);
    }

    /**
     * Services requested so far, in order.
     *
     * @return string[]
     */
    public function services(): array {
        return array_column($this->calls, 'service');
    }

    /**
     * The exception the real client throws for a NaaS error code.
     *
     * @param string $errorcode e.g. 'error:naas_api:not_found'.
     * @return \moodle_exception
     */
    public static function naas_error(string $errorcode): \moodle_exception {
        return new \moodle_exception($errorcode, 'naas');
    }
}
