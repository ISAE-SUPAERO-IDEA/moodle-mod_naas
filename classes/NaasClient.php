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
 * Enables interaction with a NaaS Server
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
class NaasClient {

    /**
     * Config
     * @var array
     */
    protected $config;

    /**
     * Debug mode
     * @var bool
     */
    protected $debug;

    /**
     * Initialize the NaaS client
     * @param $config
     */
    public function __construct($config) {
        $this->config = $config;
        $this->debug = property_exists($config, "naas_debug") ? $this->config->naas_debug : false;
    }

    /**
     * Log
     * @param $message
     * @return void
     */
    private function log($message) {
        debugging("[NaaS] {$message}", DEBUG_DEVELOPER);
    }

    /**
     * Log when debug mode is activated
     * @param $message
     * @return void
     */
    private function debug($message) {
        if ($this->debug) {
            $this->log($message);
        }
    }

    /**
     * Makes an HTTP request returns the json decoded body or null in case of http error.
     * @param $protocol
     * @param $service
     * @param $data
     * @param $params
     * @return bool|string
     */
    private function request_raw($protocol, $service, $data = null, $params = null) {
        $url = $this->config->naas_endpoint.$service;
        if ($params != null) {
            // Remove indices from query params.
            $query = preg_replace('/\%5B\d+\%5D/', '', http_build_query($params));
            $url .= "?".$query;
        }
        $ch = curl_init();
        $headers = [];
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $this->config->naas_username.":" . $this->config->naas_password);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (property_exists($this->config, "proxyhost") && !empty($this->config->proxyhost)) {
            curl_setopt($ch, CURLOPT_PROXY, $this->config->proxyhost);
            curl_setopt($ch, CURLOPT_NOPROXY, $this->config->proxybypass);
            curl_setopt($ch, CURLOPT_PROXYPORT, $this->config->proxyport);
        }
        if (property_exists($this->config, "naas_timeout")) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->naas_timeout);
        }
        if (property_exists($this->config, "naas_impersonate")) {
            $headers[] = "X-NaaS-Impersonate:" .$this->config->naas_impersonate;
        }
        if (property_exists($this->config, "wwwroot")) {
            $headers[] = "X-Host:" .$this->config->wwwroot;
        }
        if ($protocol == "FILE") {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $protocol);
            if ($protocol == "PUT" || $protocol == "POST") {
                $datajson = json_encode($data);
                $headers[] = 'Content-Length:' . strlen($datajson);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $datajson);
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $body = curl_exec($ch);
        $code = curl_getinfo ( $ch, CURLINFO_RESPONSE_CODE );
        $contenttype = curl_getinfo ($ch, CURLINFO_CONTENT_TYPE );
        if (curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        }
        if ($code != 200) {
            $this->log("Request failed: ".$protocol." - ".$url." (".$code.")");
            if (curl_errno($ch)) {
                $this->log('=> Curl error: ' . curl_error($ch));
            }
            $this->debug($body);
        }
        curl_close($ch);

        return $body;
    }

    /**
     * Send a request
     * @param $protocol
     * @param $service
     * @param $data
     * @param $params
     * @return mixed
     */
    public function request($protocol, $service, $data = null, $params = null) {
        $result = $this->request_raw($protocol, $service, $data, $params);
        return json_decode($result);
    }

    /**
     * Handle the HTTP result
     * @param $res
     * @return array|mixed
     */
    private function handle_result($res) {
        if ($res != null) {
            if (property_exists($res, "payload") && ($res->payload != null || is_array($res->payload))) {
                $this->debug("Payload: ".json_encode($res->payload, JSON_PRETTY_PRINT));
                return $res->payload;
            } else if (property_exists($res, "error")) {
                $this->debug(json_encode($res->error, JSON_PRETTY_PRINT));
            } else {
                $this->debug("Unexpected_error");
            }
        } else {
            $this->debug("Unexpected_error");
        }
        return $res;
    }

    /**
     * Retrieve the NAAS API info.
     * @return array|mixed
     */
    public function get_api_info() {
        $this->debug("Get API info");
        $protocol = "GET";
        $config = $this->request($protocol, "");
        return $this->handle_result($config);
    }

    /**
     * Retrieve the LTI config of a nugget from the NaaS.
     * @param $nuggetid
     * @param $structureid
     * @return array|mixed
     */
    public function get_nugget_lti_config($nuggetid, $structureid=null) {
        if ($structureid == null) {
            $structureid = $this->config->naas_structure_id;
        }
        $this->debug("Get nugget LTI config: ".$nuggetid);
        $protocol = "GET";
        $params = [ "structure_id" => $structureid ];
        $service = "/nuggets/".$nuggetid."/lti";
        $config = $this->request($protocol, $service, null, $params);
        return $this->handle_result($config);
    }

    /**
     * Retrieve data of a nugget from the NaaS.
     * @param $nuggetid
     * @return array|mixed
     */
    public function get_nugget_data($nuggetid) {
        $this->debug("Get nugget data: ".$nuggetid);
        $protocol = "GET";
        $params = [ "nugget_id" => $nuggetid ];
        $service = "/nuggets/".$nuggetid."/default_version";
        $config = $this->request($protocol, $service, null, $params);
        return $this->handle_result($config);
    }

    /**
     * Authenticate
     * @return array|mixed
     */
    public function get_connected_user() {
        $this->debug("Getting user information");
        $protocol = "GET";
        $service = "/auth";
        $result = $this->request($protocol, $service);
        return $this->handle_result($result);
    }

    /**
     * Post an xAPI statements.
     */
    public function post_xapi_statement($verb, $versionid, $data) {
        $this->debug("Send xAPI statement to LRS");
        $protocol = "POST";
        $service = "/versions/{$versionid}/records/{$verb}";

        $result = $this->request($protocol, $service, ((array)$data));
        return $this->handle_result($result);
    }
}
