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
 * Moodle Nugget Plugin : Push to NaaS
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */

class NaasClient {
     /**
      * @var Class that enables interaction with a NaaS Server
      */
    protected $config;
    protected $debug;

    public function __construct($config) {
        $this->config = $config;
        $this->debug = property_exists($config, "naas_debug") ? $this->config->naas_debug : false;
    }
    function log($thing) {
        error_log("[NaaS] ".print_r($thing, 1));
    }
    function debug($thing) {
        if ($this->debug) {
            $this->log($thing);
        }
    }
    // Makes a curl file from a moodle file
    function make_curl_file($file) {
        $mime = mime_content_type($file);
        $name = basename($file);
        return new \CURLFile($file, $mime, $name);
    }
    // Makes an HTTP request returns the json decoded body or null in case of http error
    function request_raw($protocol, $service, $data = null, $params = null) {
        $url = $this->config->naas_endpoint.$service;
        if ($params != null) {
            // Remove indices from query params
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
    function request($protocol, $service, $data = null, $params = null) {
        $result = $this->request_raw($protocol, $service, $data, $params);
        return json_decode($result);
    }
    function handle_result($res) {
        if ($res != null) {
            if (property_exists($res, "payload") && ($res->payload != null || is_array($res->payload))) {
                $this->debug("Payload: ".print_r($res->payload, 1));
                return $res->payload;
            } else if (property_exists($res, "error")) {
                $this->debug($res->error);
            } else {
                $this->debug("Unexpected_error");
            }
        } else {
            $this->debug("Unexpected_error");
        }
        return $res;
    }
    // Retrieve the NAAS API info
    function get_api_info() {
        $this->debug("Get API info");
        $protocol = "GET";
        $config = $this->request($protocol, "");
        return $this->handle_result($config);
    }
    // Retrieve the LTI config of a nugget from the NaaS
    function get_nugget_lti_config($nuggetid, $structureid=null) {
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
    // Retrieve data of a nugget from the NaaS
    function get_nugget_data($nuggetid) {
        $this->debug("Get nugget data: ".$nuggetid);
        $protocol = "GET";
        $params = [ "nugget_id" => $nuggetid ];
        $service = "/nuggets/".$nuggetid."/default_version";
        $config = $this->request($protocol, $service, null, $params);
        return $this->handle_result($config);
    }
    // Authentication
    function get_connected_user() {
        $this->debug("Getting user information");
        $protocol = "GET";
        $service = "/auth";
        $result = $this->request($protocol, $service);
        return $this->handle_result($result);
    }
    // xAPI statements
    function post_xapi_statement($verb, $versionid, $data) {
        $this->debug("Send xAPI statement to LRS");
        $protocol = "POST";
        $service = "/versions/${version_id}/records/${verb}";

        $result = $this->request($protocol, $service, ((array)$data));
        return $this->handle_result($result);
    }
}
