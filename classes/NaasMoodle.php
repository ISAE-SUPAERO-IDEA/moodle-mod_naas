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
 * Moodle Nugget Plugin : Push to NaaS
 *
 * @package    mod_naas
 * @copyright  2019 onwards ISAE-SUPAERO
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;
#require_once($CFG->dirroot.'/mod/hvp/autoloader.php');

class NaasMoodle  {
    public function __construct() { }

    // Inspired from /mod/hvp/lib.php
    function get_hvp_file_for_course($course_id, $context_id) {
        global $DB;
        $activity = $DB->get_record('hvp', array('course' => $course_id));
        return $this->get_hvp_file_for_course($activity->id, $context_id);
    }

    function get_hvp_file($id, $context_id) {
        global $DB;
        $activity = $DB->get_record('hvp', array('id' => $id));
        $h5pinterface = \mod_hvp\framework::instance('interface');
        // $h5pcore = \mod_hvp\framework::instance('core');
        $contentid = $activity->id;
        $content = $h5pinterface->loadContent($contentid);
        $slug = $activity->slug;
        $filename = "{$slug}-{$contentid}.h5p";
        //$filepath = (!$args ? '/' : '/' .implode('/', $args) . '/');
        $filepath = "/";
        $itemid = 0;
        $filearea = "exports";
        $fs = get_file_storage();
        $file = $fs->get_file($context_id, 'mod_hvp', "exports", $itemid, $filepath, $filename);
        if (!$file) {
            return false; // No such file.
        }    
        return $file;
    }

    function get_course_img($context_id) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context_id, 'course', "overviewfiles", 0);
        foreach ($files as $file) {
            if ($file->get_filesize() > 0) return $file;
        }
    }

    // Returns the value of the nugget_id field for a given course
    function get_nugget_id_from_course($course_id) {
        $handler = \core_course\customfield\course_handler::create();
        $custom_data = $handler-> export_instance_data_object($course_id);
        return $custom_data->nugget_id;
    }

    // Returns True if the user is an editing teacher in the course context
    function can_push($userid, $context) {
        $roles = get_user_roles($userid, $context);
        foreach($roles as $role) {
            if (is_siteadmin() || $role->shortname == 'manager' || $role->shortname == 'editingteacher') return true;
        }
        return false;
    }

    // Gets the physical path of a moodle file
    function get_stored_file_path($file) {
        $file_handle = $file->get_content_file_handle();
        $meta_data = stream_get_meta_data($file_handle);
        return $meta_data["uri"];
    }

    // Launch LTI content
    function lti_launch($naas_instance_id, $language="") {
        global $PAGE;
        global $DB;
        global $CFG;
        global $USER;
        $cm = get_coursemodule_from_id('naas', $naas_instance_id, 0, false, MUST_EXIST);
        $naas_instance = $DB->get_record('naas', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

        /*
        echo print_r($cm);
        echo "<br><br>";
        echo print_r($naas_instance);
        echo "<br><br>";
        echo print_r($context);
        echo "<br><br>";
        echo print_r($course);
        echo "<br><br>";
        */

        // Retrieve LTI config from NaaS server
        $config = (object) array_merge((array) \get_config('naas'), (array) $CFG);
        $naas = new \NaasClient($config);
        $nugget_data = $naas->get_nugget_data($naas_instance->nugget_id);
        $nugget_config = $naas->get_nugget_lti_config($naas_instance->nugget_id);
        if ($language != "" && isset($nugget_data) && is_object($nugget_data) && property_exists($nugget_data, 'multilanguages')) {
            $matchingNugget = null;
            foreach ($nugget_data->multilanguages as $item) {
                if (isset($item->language) && $item->language === $language) {
                    $matchingNugget = $item;
                    break;
                }
            }
            if ($matchingNugget != null && isset($matchingNugget->nugget_id)) $nugget_config = $naas->get_nugget_lti_config($matchingNugget->nugget_id);
        }

        if ($nugget_config == null || isset($nugget_config->error)) {
            error_log(" Cannot get nugget information from NaaS server. ");
            echo(" Cannot get nugget information from NaaS server. ");
            return;
        }

        // Configure LTI module
        $PAGE->set_course($course);

        // See: https://moodle.org/mod/forum/discuss.php?d=335734
        // Configure launch data
        $launch_url = $nugget_config->url;
        $key = $nugget_config->key;
        $secret = $nugget_config->secret;

        // To store in database: user id, activity id, secret
        $userId = $USER->id;
        $activityId = $cm->id;
        $sourcedId = bin2hex(random_bytes(16)); // 16 bytes to obtain a 32-character hexadecimal string

        // Insert a record in the database
        $newRecord = new \stdClass();
        $newRecord->user_id = $userId;
        $newRecord->activity_id = $activityId;
        $newRecord->sourced_id = $sourcedId;
        $newRecord->date_added = time(); // UNIX timestamp format
        $DB->insert_record('naas_activity_outcome', $newRecord);

        /* // Display records for logs
        $conditions = array('user_id' => $userId);
        $records = $DB->get_records('naas_activity_outcome', $conditions);
        if ($records) {
            foreach ($records as $record) {
                $recordData = array(
                    "user_id" => $record->user_id,
                    "activity_id" => $record->activity_id,
                    "sourced_id" => $record->sourced_id,
                    "date_added" => date('Y-m-d H:i:s', $record->date_added)
                );
                echo json_encode($recordData, JSON_PRETTY_PRINT)."<br>";
            }
        }
        else {
            echo "No records found for this user.<br><br>";
        }
        */

        // Delete records longer than 45 minutes car un nugget est sensé durer 30 minutes max
        $timestampLimit = time() - (45 * 60);
        $sql = "date_added < " . $timestampLimit;
        $params = array('timestampLimit' => $timestampLimit);
        $DB->delete_records_select('naas_activity_outcome', $sql, $params);

        $now = new \DateTime();

        $launch_data = [
            // LTI version
            "lti_version" => "LTI-1p0",
            "lti_message_type" => "basic-lti-launch-request",
            // OAuth parameters
            "oauth_callback" => "about:blank",
            "oauth_consumer_key"=> $key,
            "oauth_version" => "1.0",
            "oauth_nonce" => uniqid('', true),
            "oauth_timestamp" => $now->getTimestamp(),
            "oauth_signature_method" => "HMAC-SHA1",

            // Context info
            "context_id" => $cm->id,
            // Return grade parameters
            "lis_result_sourcedid" => $sourcedId,
            "lis_outcome_service_url" => $CFG->wwwroot. "/mod/naas/outcome.php?id=" . $cm->id
        ];
        // private information
        if ($config->naas_privacy_learner_name) {
            $launch_data["lis_person_name_full"] = $USER->firstname. " ".$USER->lastname; 
        }
        if ($config->naas_privacy_learner_mail) {
            $launch_data["lis_person_contact_email_primary"] = $USER->email; 
        }

        // LTI 
        # Basic LTI uses OAuth to sign requests
        # OAuth Core 1.0 spec: http://oauth.net/core/1.0/

        # In OAuth, request parameters must be sorted by name
        $launch_data_keys = array_keys($launch_data);
        sort($launch_data_keys);

        // Compute launch parameters
        $launch_params = array();
        foreach ($launch_data_keys as $key) {
            array_push($launch_params, $key . "=" . rawurlencode($launch_data[$key]));
        }
        $base_string = "POST&" . urlencode($launch_url) . "&" . rawurlencode(implode("&", $launch_params));
        $secret = urlencode($secret) . "&";
        $signature = base64_encode(hash_hmac("sha1", $base_string, $secret, true));

        // Generate HTML & javascript code to POST request
        ?>
        <form id="ltiLaunchForm" name="ltiLaunchForm" method="POST" action="<?php printf($launch_url); ?>">
            <?php foreach ($launch_data as $k => $v ) { ?>
                <input type="hidden" name="<?php echo $k ?>" value="<?php echo $v ?>">
            <?php } ?>
                <input type="hidden" name="oauth_signature" value="<?php echo $signature ?>">
        </form>
        <script>
            window.addEventListener("load", (event) => {
                document.getElementById("ltiLaunchForm").submit();
            });
        </script>
        <?php
    }
}
