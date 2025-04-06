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

namespace mod_naas;

/**
 * Represents an HTTP response from the NaaS API to send back to the client by the plugin proxy.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 */
class proxy_http_response {

    /**
     * Code of the response.
     * @var int
     */
    private $statuscode;

    /**
     * Body of the response.
     * @var string
     */
    private $body;

    /**
     * Construct the response.
     * @param int $statuscode
     * @param string $body
     */
    public function __construct(int $statuscode, string $body) {
        $this->statuscode = $statuscode;
        $this->body = $body;
    }

    /**
     * Get the status code of the response.
     * @return int
     */
    public function get_code(): int {
        return $this->statuscode;
    }

    /**
     * Get the response body.
     * @return string
     */
    public function get_body(): string {
        return $this->body;
    }

    /**
     * Test if it is a successful response.
     * @return bool
     */
    public function is_success(): bool {
        return $this->statuscode >= 200 && $this->statuscode < 299;
    }

    /**
     * Translate the NaaS API response for the client.
     * @return false|string
     */
    public function build_client_response() {
        if ($this->is_success()) {
            // Try to parse the body as JSON.
            $decoded = json_decode($this->body);
            // If body is valid JSON and contains a payload property.
            if ($decoded && property_exists($decoded, 'payload')) {
                return json_encode([
                    "success" => true,
                    "payload" => $decoded->payload,
                ]);
            } else {
                // Handle success but invalid JSON or missing payload.
                return json_encode([
                    "success" => true,
                    "payload" => $this->body,
                ]);
            }
        } else {
            // Try to parse the body as JSON first - it might already contain an error structure.
            $decoded = json_decode($this->body);
            if ($decoded && property_exists($decoded, 'error')) {
                // If we already have a structured error, pass it through.
                return json_encode([
                    "success" => false,
                    "error" => $decoded->error,
                ]);
            } else {
                // Create a generic error structure.
                $message = $this->body;
                // If the body is too large or empty, provide a more helpful message.
                if (strlen($message) > 1000) {
                    $message = "Server error (code: " . $this->statuscode . ")";
                } else if (empty($message)) {
                    $message = "Empty response from server (code: " . $this->statuscode . ")";
                }
                return json_encode([
                    "success" => false,
                    "error" => [
                        "code" => $this->statuscode,
                        "message" => $message,
                    ],
                ]);
            }
        }
    }
}
