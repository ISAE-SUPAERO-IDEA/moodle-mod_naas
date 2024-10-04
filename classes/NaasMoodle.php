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

class NaasMoodle {
    public function __construct() {
    }

    // Inspired from /mod/hvp/lib.php
    function get_hvp_file_for_course($courseid, $contextid) {
        global $DB;
        $activity = $DB->get_record('hvp', ['course' => $courseid]);
        return $this->get_hvp_file_for_course($activity->id, $contextid);
    }

    function get_hvp_file($id, $contextid) {
        global $DB;
        $activity = $DB->get_record('hvp', ['id' => $id]);
        $h5pinterface = \mod_hvp\framework::instance('interface');
        $contentid = $activity->id;
        $content = $h5pinterface->loadContent($contentid);
        $slug = $activity->slug;
        $filename = "{$slug}-{$contentid}.h5p";
        $filepath = "/";
        $itemid = 0;
        $filearea = "exports";
        $fs = get_file_storage();
        $file = $fs->get_file($contextid, 'mod_hvp', "exports", $itemid, $filepath, $filename);
        if (!$file) {
            return false; // No such file.
        }
        return $file;
    }

    function get_course_img($contextid) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'course', "overviewfiles", 0);
        foreach ($files as $file) {
            if ($file->get_filesize() > 0) {
                return $file;
            }
        }
    }

    // Returns the value of the nugget_id field for a given course
    function get_nugget_id_from_course($courseid) {
        $handler = \core_course\customfield\course_handler::create();
        $customdata = $handler->export_instance_data_object($courseid);
        return $customdata->nugget_id;
    }

    // Returns True if the user is an editing teacher in the course context
    function can_push($userid, $context) {
        $roles = get_user_roles($userid, $context);
        foreach($roles as $role) {
            if (is_siteadmin() || $role->shortname == 'manager' || $role->shortname == 'editingteacher') {
                return true;
            }
        }
        return false;
    }

    // Gets the physical path of a moodle file
    function get_stored_file_path($file) {
        $filehandle = $file->get_content_file_handle();
        $metadata = stream_get_meta_data($filehandle);
        return $metadata["uri"];
    }

    // Launch LTI content
    function lti_launch($naasinstanceid, $language="") {
        global $PAGE;
        global $DB;
        global $CFG;
        global $USER;
        $cm = get_coursemodule_from_id('naas', $naasinstanceid, 0, false, MUST_EXIST);
        $naasinstance = $DB->get_record('naas', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        // Retrieve LTI config from NaaS server
        $config = (object) array_merge((array) \get_config('naas'), (array) $CFG);
        $naas = new \NaasClient($config);
        $nuggetdata = $naas->get_nugget_data($naasinstance->nugget_id);
        $nuggetconfig = $naas->get_nugget_lti_config($naasinstance->nugget_id);
        if ($language != "" && isset($nuggetdata) && is_object($nuggetdata) && property_exists($nuggetdata, 'multilanguages')) {
            $matchingnugget = null;
            foreach ($nuggetdata->multilanguages as $item) {
                if (isset($item->language) && $item->language === $language) {
                    $matchingnugget = $item;
                    break;
                }
            }
            if ($matchingnugget != null && isset($matchingnugget->nugget_id)) {
                $nuggetconfig = $naas->get_nugget_lti_config($matchingnugget->nugget_id);
            }
        }

        if ($nuggetconfig == null || isset($nuggetconfig->error)) {
            error_log(" Cannot get nugget information from NaaS server. ");
            echo(" Cannot get nugget information from NaaS server. ");
            return;
        }

        // Configure LTI module
        $PAGE->set_course($course);

        // See: https://moodle.org/mod/forum/discuss.php?d=335734
        // Configure launch data
        $launchurl = $nuggetconfig->url;
        $key = $nuggetconfig->key;
        $secret = $nuggetconfig->secret;

        // To store in database: user id, activity id, secret
        $userid = $USER->id;
        $activityid = $cm->id;
        $sourcedid = bin2hex(random_bytes(16)); // 16 bytes to obtain a 32-character hexadecimal string

        // Insert a record in the database
        $newrecord = new \stdClass();
        $newrecord->user_id = $userid;
        $newrecord->activity_id = $activityid;
        $newrecord->sourced_id = $sourcedid;
        $newrecord->date_added = time(); // UNIX timestamp format
        $DB->insert_record('naas_activity_outcome', $newrecord);

        // Delete records longer than 45 minutes car un nugget est sensé durer 30 minutes max
        $timestamplimit = time() - (45 * 60);
        $sql = "date_added < " . $timestamplimit;
        $params = ['timestampLimit' => $timestamplimit];
        $DB->delete_records_select('naas_activity_outcome', $sql, $params);

        $now = new \DateTime();

        $secret = urlencode($secret) . "&";
        $resourcelinkid = base64_encode(hash_hmac("sha1", $_SERVER['SERVER_NAME'].$USER->email.$nuggetdata->version_id, $secret, false));

        $launchdata = [
            // LTI version
            "lti_version" => "LTI-1p0",
            "lti_message_type" => "basic-lti-launch-request",
            // OAuth parameters
            "oauth_callback" => "about:blank",
            "oauth_consumer_key" => $key,
            "oauth_version" => "1.0",
            "oauth_nonce" => uniqid('', true),
            "oauth_timestamp" => $now->getTimestamp(),
            "oauth_signature_method" => "HMAC-SHA1",

            // Context info
            "context_id" => $cm->id,
            // Return grade parameters
            "lis_result_sourcedid" => $sourcedid,
            "lis_outcome_service_url" => $CFG->wwwroot. "/mod/naas/outcome.php?id=" . $cm->id,
            "resource_link_id" => $resourcelinkid,
        ];
        // private information
        if ($config->naas_privacy_learner_name) {
            $launchdata["lis_person_name_full"] = $USER->firstname. " ".$USER->lastname;
        }
        if ($config->naas_privacy_learner_mail) {
            $launchdata["lis_person_contact_email_primary"] = $USER->email;
        }

        // LTI
        // Basic LTI uses OAuth to sign requests
        // OAuth Core 1.0 spec: http://oauth.net/core/1.0/

        // In OAuth, request parameters must be sorted by name
        $launchdatakeys = array_keys($launchdata);
        sort($launchdatakeys);

        // Compute launch parameters
        $launchparams = [];
        foreach ($launchdatakeys as $key) {
            array_push($launchparams, $key . "=" . rawurlencode($launchdata[$key]));
        }
        $basestring = "POST&" . urlencode($launchurl) . "&" . rawurlencode(implode("&", $launchparams));

        $signature = base64_encode(hash_hmac("sha1", $basestring, $secret, true));

        // session php variable avec le resource_link_id
        $_SESSION["resource_link_id"] = $resourcelinkid;

        // Generate HTML & javascript code to POST request
        ?>
        <form id="ltiLaunchForm" name="ltiLaunchForm" method="POST" action="<?php printf($launchurl); ?>">
            <?php foreach ($launchdata as $k => $v) { ?>
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
