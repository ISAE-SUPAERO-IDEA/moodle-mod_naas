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
 * Push to naas
 *
 * @package    naas
 * @copyright  2019 onwards ISAE-SUPAERO
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class NaasClient  {
     /**
     * @var Class that enables interaction with a NaaS Server
     */
    protected $config;
    protected $debug;
    const ROOT_STRUCTURE_ID = '00000000-0000-0000-0000-000000000000';

    public function __construct($config) { 
        $this->config = $config;
        if (property_exists($this->config, "naas_debug")) $this->debug = $this->config->naas_debug;
        else $this->debug = False;
    }
    function log($thing) {
        error_log("[NaaS] ".print_r($thing, 1));
    }
    function debug($thing) {
        if ($this->debug) $this->log($thing);
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

            $url = $url."?".$query;
        }
        $ch = curl_init();
        $headers = [];
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $this->config->naas_username.":" . $this->config->naas_password);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        if (property_exists($this->config, "naas_timeout")) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->naas_timeout);
        }
        if (property_exists($this->config, "naas_impersonate")) {
            $headers[] = "X-NaaS-Impersonate:" .$this->config->naas_impersonate;
        } 
        if ($protocol=="FILE") {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $protocol);
            if ($protocol=="PUT" || $protocol=="POST") {
              $data_json = json_encode($data);
              $headers[] = 'Content-Length:' . strlen($data_json);                                
              curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $body = curl_exec($ch);
        $code = curl_getinfo ( $ch, CURLINFO_RESPONSE_CODE );
        $content_type = curl_getinfo ($ch, CURLINFO_CONTENT_TYPE );
        if(curl_errno($ch)) {
            echo 'Curl error: ' . curl_error($ch);
        }
        if ($code!=200) {
            $this->log("Request failed: ".$protocol." - ".$url." (".$code.")");
            if(curl_errno($ch)) {
                $this->log('=> Curl error: ' . curl_error($ch));
            }
            $this->debug($body);
        }
        curl_close($ch);
      
        $result = $body;
       
        return $result;
    }
    function request($protocol, $service, $data = null, $params = null) {
        $result = $this->request_raw($protocol, $service, $data, $params);
        return json_decode($result);
    }        

    function is_error($res) {
        return ($res==null || 
            (is_object($res) && property_exists($res, "error")));
    }
    // Uploads nugget files using one of the endpoints
    function upload_file_be($file, $service) {
        $protocol = "FILE";
        $data = [ 
            "file" => $this->make_curl_file($file)
        ];

        $res = $this->request($protocol, $service, $data);
        if (!$this->is_error($res))
            return $res->payload;
        else {
            $this->log("File upload failed");
            return null;
        }
    }
    // Uploads a file to the naas for safekeeping
    function upload_file($file) {
        return $this->upload_file_be($file, "/blobs");
    }
    // Uploads a file to the naas for hashing
    /*
    function hash_file($file) {
        return $this->upload_file_be($file, "/blobs/hash");
    }*/
    //  Prepare the data to be sent to the NaaS in case of creation of update of a nugget.
    function prepare_nugget_post_data($nugget) {
        // Uploads the file on the NaaS

        if (property_exists($nugget, "file") && $nugget->file!=null) {
            $files = $nugget->file;
            // Upload h5p files
            foreach ($files as $index => $filepath) {
                $payload = $this->upload_file($filepath);
                $file_id = $payload;
                // Compute $data
                if ($file_id!=null) {
                    $nugget->file_id[$index] = $file_id;
                    $this->debug("File upload ok: ".$file_id);
                }
                else { 
                    $this->log("Data preparation failed");
                    return null;
                }
            }
        }
        if (property_exists($nugget, "thumbnail") && $nugget->thumbnail!=null) {
            $nugget->thumbnail_id = $this->upload_file($nugget->thumbnail);
            if ($nugget->thumbnail_id!=null) {
                $this->debug("Thumbnail upload ok:".$nugget->thumbnail_id);
            }
            else {
                $this->log("Thumbnail upload failed");
                return null;   
            }
        }
        $this->debug("prepare_post_data succeeded");
        return $nugget;
    }
    //  Prepare the data to be sent to the NaaS in case of creation of update of a nugget.
    function prepare_structure_post_data($structure) {
        // Uploads the file on the NaaS
        if ($structure->thumbnail) {
            $structure->thumbnail_id = $this->upload_file($structure->thumbnail);   
        }
        if ($structure->banner) {
            $structure->banner_id = $this->upload_file($structure->banner);   
        }
        return $structure;
    }
    //  Prepare the data to be sent to the NaaS in case of creation of update of a nugget.
    function prepare_person_post_data($person) {
        // Uploads the file on the NaaS
        if ($person->thumbnail) {
            $person->thumbnail_id = $this->upload_file($person->thumbnail);   
        }
        return $person;
    }
    function handle_result($res) {
        if ($res!=null) {
            if (property_exists($res, "payload") && ($res->payload!=null || is_array($res->payload))) {
                $this->debug("Payload: ".print_r($res->payload,1));
                return $res->payload;
            }
            else if (property_exists($res, "error")) {
                $this->debug($res->error);
            } else $this->debug("Unexpected_error");
        }
        else {
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
    function get_nugget_lti_config($nugget_id, $structure_id=null) {
        if ($structure_id==null) $structure_id = $this->config->naas_structure_id;
        $this->debug("Get nugget LTI config: ".$nugget_id);
        $protocol = "GET";
        $params = [ "structure_id" => $structure_id ];
        $service = "/nuggets/".$nugget_id."/lti";
        $config = $this->request($protocol, $service, null, $params);
        return $this->handle_result($config);
    }
    // Retrieve the audit information of a nugget from the NaaS
    function get_nugget_audit($nugget_id) {
        $this->debug("Get nugget audit".$nugget_id);
        $protocol = "GET";
        $service = "/nuggets/".$nugget_id."/audit";
        $config = $this->request($protocol, $service);
        return $this->handle_result($config);
    }
    // Retrieve the permissions of a nugget from the NaaS
    function get_nugget_permissions($nugget_id) {
        $this->debug("Get nugget permissions: ".$nugget_id);
        $protocol = "GET";
        $service = "/nuggets/".$nugget_id."/permissions";
        $permissions = $this->request($protocol, $service);
        return $this->handle_result($permissions);
    }
    // Retrieve nugget versions from the NaaS
    function get_nugget_versions($nugget_id) {
        $this->debug("Get nugget versions: ".$nugget_id);
        $protocol = "GET";
        $service = "/nuggets/".$nugget_id."/versions";
        $result = $this->request($protocol, $service);
        return $this->handle_result($result);
    }
    // Retrieve a nugget version from the NaaS
    function get_version($version_id) {
        $this->debug("Get nugget version: ".$version_id);
        $protocol = "GET";
        $service = "/versions/".$version_id;
        $result = $this->request($protocol, $service);
        return $this->handle_result($result);
    }
    // Retrieve a nugget version file from the NaaS
    function get_version_file($version_id, $file_id) {
        $this->debug("Get nugget version file: ".$version_id." ".$file_id);
        $protocol = "GET";
        $service = "/versions/".$version_id."/files/".$file_id;
        $data = $this->request_raw($protocol, $service);
        // write file
        if ($data!=null) {
            $temp_file = tempnam(sys_get_temp_dir(), 'naas_blob').".h5p";
            $file = fopen($temp_file, "w+");
            fputs($file, $data);
            fclose($file);
            return $temp_file;
        }
        return null;
        
    }
    // Retrieve the nugget metadata from the NaaS
    function get_nugget($nugget_id) {
        $this->debug("Get nugget: ".$nugget_id);
        $protocol = "GET";
        $service = "/nuggets/".$nugget_id;
        $result = $this->request($protocol, $service);
        return $this->handle_result($result);
    }
    // Retrieve the permissions the user has on a nugget
    function get_permissions_on_nugget($nugget_id) {
        $this->debug("Get permissions on nugget: ".$nugget_id);
        $protocol = "GET";
        $service = "/nuggets/".$nugget_id."/permissions";
        $result = $this->request($protocol, $service);
        return $this->handle_result($result);
    }
    // Adds a nugget to the NaaS
    function add_nugget($nugget) {        
        $this->debug("Adding nugget");
        $protocol = "POST";
        $service = "/nuggets";
        $data = $this->prepare_nugget_post_data($nugget);
        if ($data == null) return null;
        $result = $this->request($protocol, $service, $data);
        return $this->handle_result($result);
    }
    // Updates an existing nugget in the NaaS
    function update_nugget($nugget_id, $nugget) {
        $this->debug("Updating nugget: ".$nugget_id);
        $protocol = "PUT";
        $service = "/nuggets/".$nugget_id;
        $data = $this->prepare_nugget_post_data($nugget);
        if ($data == null) return null;
        $result = $this->request($protocol, $service, $data);
        return $this->handle_result($result); 
    }
    // Version an existing nugget in the NaaS
    function version_nugget($nugget_id, $nugget) {
        $this->debug("Making a nugget version: ".$nugget_id);
        $protocol = "POST";
        $service = "/nuggets/".$nugget_id;
        $data = $this->prepare_nugget_post_data($nugget);
        if ($data == null) return null;
        $result = $this->request($protocol, $service, $data);
        return $this->handle_result($result); 
    }
    // Deletes an existing nugget in the NaaS
    function action_nugget($action, $nugget_id) {
        $this->debug("Action on nugget(".$action."): ".$nugget_id);
        $protocol = "POST";
        $service = "/nuggets/".$nugget_id."/".$action;
        $result = $this->request($protocol, $service, null);
        return $this->handle_result($result);
    }
    // Deletes a nugget
    function delete_nugget($nugget_id) {
        $this->debug("Deleting nugget: ".$nugget_id);
        $protocol = "DELETE";
        $service = "/nuggets/".$nugget_id;
        $result = $this->request($protocol, $service, null);
        return $this->handle_result($result);
    }
    // perform a maintenance task
    function perform_maintenance($task, $id) {
        $this->debug("Perform maintenance action: $task / $id");
        $protocol = "GET";
        $service = "/admin/maintenance/$task/$id";
        $result = $this->request($protocol, $service);
        return $this->handle_result($result);
    }
    // Applies a simple action on a given nugget version
    function action_nugget_version($action, $version_id) {
        $this->debug("Action on version(".$action."): ".$version_id);
        $protocol = "POST";
        $service = "/versions/".$version_id."/".$action;
        $result = $this->request($protocol, $service, null);
        return $this->handle_result($result);
    }
    // Deletes a nugget version
    function delete_version($version_id) {
        $this->debug("Deleting version: ".$version_id);
        $protocol = "DELETE";
        $service = "/versions/".$version_id;
        $result = $this->request($protocol, $service, null);
        return $this->handle_result($result);
    }
    // Get the preview URL of a nugget version
    function get_version_preview_url($version_id) {
        $this->debug("Getting nugget version preview url: ".$version_id);
        $protocol = "GET";
        $service = "/versions/".$version_id."/preview_url";
        $result = $this->request($protocol, $service, null);
        return $this->handle_result($result);
    }    
    // Query nuggets available on the NaaS
    function query_nuggets($params) {
        $protocol = "GET";
        $service = "/nuggets";
        $nuggets = $this->request($protocol, $service, null, $params);
        return $this->handle_result($nuggets);
    }
    // Search nuggets available on the NaaS
    function search_nuggets($params) {
        $protocol = "GET";
        $service = "/nuggets/search";
        $result = $this->request($protocol, $service, null, $params);
        return $this->handle_result($result);
    }
    // Retrieve the nugget metadata from the NaaS (default version)
    function get_nugget_default_version($nugget_id) {
        $this->debug("Get nugget: ".$nugget_id);
        $protocol = "GET";
        $service = "/nuggets/".$nugget_id."/default_version";
        $result = $this->request($protocol, $service);
        return $this->handle_result($result);
    }
    // Upates a person
    function update_person($email, $person) {
        $this->debug("Updating person: ".$email);
        $protocol = "PUT";
        $service = "/persons/".$email;
        $data = $this->prepare_person_post_data($person);
        $result = $this->request($protocol, $service, $data);
        return $this->handle_result($result);
    }
    // create a person
    function create_person($person) {
        $this->debug("Creating person: ".$person->email);
        $protocol = "POST";
        $service = "/persons";
        $data = $this->prepare_person_post_data($person);
        $result = $this->request($protocol, $service, $data);
        return $this->handle_result($result);
    }
    // delete a person
    function delete_person($email) {
        $this->debug("Deleting person: ".$email);
        $protocol = "DELETE";
        $service = "/persons/".$email;
        $result = $this->request($protocol, $service);
        return $this->handle_result($result);
    }
    // Get a person information
    function get_person($email) {
        $this->debug("Get person: ".$email);
        $protocol = "GET";
        $service = "/persons/".$email;
        $result = $this->request($protocol, $service);
        return $this->handle_result($result);
    }
    // Get an account information
    function get_account($email) {
        $this->debug("Get account: ".$email);
        $protocol = "GET";
        $service = "/auth/".$email;
        $result = $this->request($protocol, $service);
        return $this->handle_result($result);
    }
    // Query persons
    function query_persons($params) {
        $protocol = "GET";
        $service = "/persons";
        $result = $this->request($protocol, $service, null, $params);
        return $this->handle_result($result);
    }
    // Structures
    function query_structures($params) {
        $protocol = "GET";
        $service = "/structures";
        $structures = $this->request($protocol, $service, null, $params);
        return $this->handle_result($structures);
    }
    // Search nuggets available on the NaaS
    function search_structures($params) {
        $protocol = "GET";
        $service = "/structures/search";
        $result = $this->request($protocol, $service, null, $params);
        return $this->handle_result($result);
    }
    function get_structure($structure_id) {
        $this->debug("Getting structure: ".$structure_id);
        $protocol = "GET";
        $service = "/structures/".$structure_id;
        $result = $this->request($protocol, $service);
        return $this->handle_result($result);
    }
    function get_structure_admins($structure_id) {
        $this->debug("Getting structure managers: ".$structure_id);
        $protocol = "GET";
        $service = "/persons";
        $result = $this->request($protocol, $service, null, [
            "relationships" => "admin_of:structure:" . $structure_id
        ]);
        return $this->handle_result($result);
    }
    function get_structure_authors($structure_id) {
        $this->debug("Getting structure authors: ".$structure_id);
        $protocol = "GET";
        $service = "/persons";
        $result = $this->request($protocol, $service, null, [
            "relationships" => "author_of:structure:" . $structure_id
        ]);
        return $this->handle_result($result);
    }
    function create_structure($structure) {
        $this->debug("Creating structure: ");
        $protocol = "POST";
        $service = "/structures";
        $data = $this->prepare_structure_post_data($structure);
        if ($data == null) return null;
        $result = $this->request($protocol, $service, $data);
        return $this->handle_result($result); 
    }
    function delete_structure($structure_id) {
        $this->debug("Deleting structure: $structure_id");
        $protocol = "DELETE";
        $service = "/structures/$structure_id";
        $result = $this->request($protocol, $service);
        return $this->handle_result($result); 
    }
    function update_structure($structure_id, $structure) {
        $this->debug("Updating structure: ".$structure_id);
        $protocol = "PUT";
        $service = "/structures/".$structure_id;
        $data = $this->prepare_structure_post_data($structure);
        if ($data == null) return null;
        $result = $this->request($protocol, $service, $data);
        return $this->handle_result($result); 
    }
    // Authentication
    function get_connected_user() {
        $this->debug("Getting user information");
        $protocol = "GET";
        $service = "/auth";
        $result = $this->request($protocol, $service);
        return $this->handle_result($result); 
    }
}
