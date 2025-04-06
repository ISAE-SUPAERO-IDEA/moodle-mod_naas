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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Enables interaction with a NaaS Server
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
class naas_client {

    /**
     * Config
     * @var object
     */
    protected $config;

    /**
     * Debug mode
     * @var bool
     */
    protected $debug;

    /**
     * Initialize the NaaS client
     * @param object $config
     */
    public function __construct($config) {
        global $CFG;
        $this->config = $config;
        $this->debug = property_exists($config, "naas_debug") ? $this->config->naas_debug : false;
    }

    /**
     * Makes an HTTP request and return the response.
     * @param string $protocol
     * @param string $service
     * @param object $data
     * @param array $params
     * @return proxy_http_response
     */
    public function request_raw($protocol, $service, $data = null, $params = null) {
        global $CFG, $DB;
        
        $url = $this->config->naas_endpoint.$service;
        if ($params != null) {
            // Remove indices from query params.
            $query = preg_replace('/\%5B\d+\%5D/', '', http_build_query($params));
            $url .= "?".$query;
        }

        // Log the request and config
        if ($this->debug) {
            error_log("NAAS: Connecting to " . $url);
            error_log("NAAS: Configuration dump: " . print_r($this->config, true));
        }
        

        // Create curl with the correct Moodle class
        $curl = new \curl();
        $headers = [];
        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_USERPWD' => $this->config->naas_username . ":" . $this->config->naas_password,
            'CURLOPT_CONNECTTIMEOUT' => 10, // Connection timeout
            // Explicitly disable proxy usage for all connections
            'CURLOPT_PROXY' => '',
            'CURLOPT_HTTPPROXYTUNNEL' => false,
            'CURLOPT_SSL_VERIFYPEER' => false
        ];

        // Proxy settings are now completely ignored since we're explicitly disabling the proxy

        if (property_exists($this->config, "naas_timeout")) {
            $options['CURLOPT_TIMEOUT'] = $this->config->naas_timeout;
        } else {
            // Set a default timeout of 30 seconds if not configured
            $options['CURLOPT_TIMEOUT'] = 30;
        }

        if (property_exists($this->config, "naas_impersonate")) {
            $headers[] = "X-NaaS-Impersonate:" . $this->config->naas_impersonate;
        }

        if (property_exists($this->config, "wwwroot")) {
            $headers[] = "X-Host:" . $this->config->wwwroot;
        }

        // Set custom headers.
        if (!empty($headers)) {
            $options['CURLOPT_HTTPHEADER'] = $headers;
        }

        // Set request method and data.
        if ($protocol == "FILE") {
            $response = $curl->post($url, $data, $options);
            $info = $curl->get_info();
            $code = $info['http_code'];
        } else {
            $options['CURLOPT_CUSTOMREQUEST'] = $protocol;
            
            if ($protocol == "PUT" || $protocol == "POST") {
                $datajson = json_encode($data);
                $headers[] = 'Content-Length:' . strlen($datajson);
                $options['CURLOPT_HTTPHEADER'] = $headers;
                $options['CURLOPT_POSTFIELDS'] = $datajson;
            }
            
            $curl->setopt($options);
            
            // Log before making the request
            if ($this->debug) {
                error_log("NAAS: About to make request");
            }
            
            // Make the request
            $response = $curl->get($url);
            
            // Log after making the request
            if ($this->debug) {
                error_log("NAAS: Request completed");
            }
            
            $info = $curl->get_info();
            $code = isset($info['http_code']) ? $info['http_code'] : 0;
        }

        if ($curl->get_errno()) {
            $message = 'Curl error: ' . $curl->error;
            if ($this->debug) {
                error_log("NAAS ERROR: " . $message . " (Code: " . $curl->get_errno() . ")");
            }
            
            return new proxy_http_response(500, json_encode([
                'success' => false,
                'error' => [
                    'code' => $curl->get_errno(),
                    'message' => $curl->error
                ]
            ]));
        }
        
        if ($code != 200 && $this->debug) {
            $message = "Request failed: " . $protocol . " - " . $url . " (" . $code . ")";
            error_log("NAAS ERROR: " . $message);
            if ($response) {
                error_log("NAAS ERROR RESPONSE: " . $response);
            }
        }

        return new proxy_http_response($code, $response);
    }

    /**
     * Send a request
     * @param string $protocol
     * @param string $service
     * @param array $data
     * @param array $params
     * @return mixed
     */
    public function request($protocol, $service, $data = null, $params = null) {
        $response = $this->request_raw($protocol, $service, $data, $params);
        $result = json_decode($response->get_body());
        return $this->handle_result($result);
    }

    /**
     * Handle the HTTP result
     * @param object $res
     * @return array|mixed
     */
    private function handle_result($res) {
        if ($res != null) {
            if (property_exists($res, "payload") && ($res->payload != null || is_array($res->payload))) {
                if ($this->debug) {
                    error_log("NAAS: Received payload");
                }
                return $res->payload;
            } else if (property_exists($res, "error")) {
                if ($this->debug) {
                    error_log("NAAS ERROR: " . json_encode($res->error));
                }
            } else {
                if ($this->debug) {
                    error_log("NAAS: Unexpected response");
                }
            }
        } else {
            if ($this->debug) {
                error_log("NAAS: NULL response");
            }
        }
        return $res;
    }

    /**
     * Retrieve the NAAS API info.
     * @return array|mixed
     */
    public function get_api_info() {
        $protocol = "GET";
        return $this->request($protocol, "");
    }

    /**
     * Retrieve the LTI config of a nugget from the NaaS.
     * @param int $nuggetid
     * @param int $structureid
     * @return array|mixed
     */
    public function get_nugget_lti_config($nuggetid, $structureid=null) {
        if ($structureid == null) {
            $structureid = $this->config->naas_structure_id;
        }
        $protocol = "GET";
        $params = [ "structure_id" => $structureid ];
        $service = "/nuggets/".$nuggetid."/lti";
        return $this->request($protocol, $service, null, $params);
    }

    /**
     * Retrieve data of a nugget from the NaaS.
     * @param int $nuggetid
     * @return array|mixed
     */
    public function get_nugget_data($nuggetid) {
        $protocol = "GET";
        $params = [ "nugget_id" => $nuggetid ];
        $service = "/nuggets/".$nuggetid."/default_version";
        return $this->request($protocol, $service, null, $params);
    }

    /**
     * Authenticate
     * @return array|mixed
     */
    public function get_connected_user() {
        $protocol = "GET";
        $service = "/auth";
        return $this->request($protocol, $service);
    }

    /**
     * Post an xAPI statements.
     * @param string $verb
     * @param int $versionid
     * @param object $data
     */
    public function post_xapi_statement($verb, $versionid, $data) {
        $protocol = "POST";
        $service = "/versions/{$versionid}/records/{$verb}";

        return $this->request($protocol, $service, ((array)$data));
    }
}
