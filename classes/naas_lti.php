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
 * Moodle Nugget Plugin : LTI connector
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
namespace mod_naas;

/**
 * LTI connector
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 */
class naas_lti {

    /**
     * Launch LTI content.
     * @param int $naasinstanceid
     * @param string $language
     * @return void
     * @throws \Random\RandomException
     */
    public static function lti_launch($naasinstanceid, $language="") {
        global $PAGE;
        global $DB;
        global $CFG;
        global $USER;
        $cm = get_coursemodule_from_id('naas', $naasinstanceid, 0, false, MUST_EXIST);
        $naasinstance = $DB->get_record('naas', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        // Retrieve LTI config from NaaS server.
        $config = (object) array_merge((array) \get_config('naas'), (array) $CFG);
        $naas = new \mod_naas\naas_client($config);
        $nuggetdata = $naas->get_nugget_data($naasinstance->nugget_id);
        $nuggetconfig = $naas->get_nugget_lti_config($naasinstance->nugget_id);
        if ($language != ""
            && isset($nuggetdata)
            && is_object($nuggetdata)
            && property_exists($nuggetdata, 'multilanguages')) {
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
            $errormessage = get_string("cannot_get_nugget", "naas");
            echo <<<HTML
<style>
.error-message {
  color: #721c24;
  background-color: #f8d7da;
  border: 1px solid #f5c6cb;
  padding: 12px;
  border-radius: 4px;
  font-size: 14px;
  font-weight: bold;
  margin: 10px 0;
  display: flex;
  align-items: center;
}

.error-message::before {
  content: "⚠️";
  margin-right: 8px;
}
</style>
    <div class="error-message">$errormessage</div>
HTML;
            return;
        }

        // Configure LTI module.
        $PAGE->set_course($course);

        // See: https://moodle.org/mod/forum/discuss.php?d=335734.
        // Configure launch data.
        $launchurl = $nuggetconfig->url;
        $key = $nuggetconfig->key;
        $secret = $nuggetconfig->secret;

        // To store in database: user id, activity id, secret.
        $userid = $USER->id;
        $activityid = $cm->id;

        // Clean up any existing sourced_id records for this user-activity combination.
        // to ensure each new attempt gets a fresh sourced_id.
        $DB->delete_records('naas_activity_outcome', [
            'user_id' => $userid,
            'activity_id' => $activityid,
        ]);

        $sourcedid = bin2hex(random_bytes(16)); // 16 bytes to obtain a 32-character hexadecimal string.

        // Insert a record in the database.
        $newrecord = new \stdClass();
        $newrecord->user_id = $userid;
        $newrecord->activity_id = $activityid;
        $newrecord->sourced_id = $sourcedid;
        $newrecord->date_added = time(); // UNIX timestamp format.
        $DB->insert_record('naas_activity_outcome', $newrecord);

        $now = new \DateTime();

        $secret = urlencode($secret) . "&";
        $resourcelinkid = base64_encode(
                hash_hmac(
                        "sha1",
                        $_SERVER['SERVER_NAME'].$USER->email.$nuggetdata->version_id,
                        $secret,
                        false
                )
        );

        $custom = [
            "hostname" => $CFG->wwwroot, // Used by feedback feature.
            "is_authenticated" => true, // Required by feedback feature.
            "css" => $config->naas_css,
            "feedback" => ($config->naas_feedback === "1") ? "on" : "off",
        ];

        $launchdata = [
            "lti_version" => "LTI-1p0",
            "lti_message_type" => "basic-lti-launch-request",

            "oauth_callback" => "about:blank",
            "oauth_consumer_key" => $key,
            "oauth_version" => "1.0",
            "oauth_nonce" => uniqid('', true),
            "oauth_timestamp" => $now->getTimestamp(),
            "oauth_signature_method" => "HMAC-SHA1",

            "context_id" => $cm->id,

            "lis_result_sourcedid" => $sourcedid,
            "lis_outcome_service_url" => $CFG->wwwroot. "/mod/naas/outcome.php?id=" . $cm->id,
            "resource_link_id" => $resourcelinkid,
            "custom_naas" => json_encode($custom),
        ];

        if ($config->naas_privacy_learner_name) {
            $launchdata["lis_person_name_full"] = $USER->firstname. " ".$USER->lastname;
        }
        if ($config->naas_privacy_learner_mail) {
            $launchdata["lis_person_contact_email_primary"] = $USER->email;
        }

        // LTI.
        // Basic LTI uses OAuth to sign requests.
        // OAuth Core 1.0 spec: http://oauth.net/core/1.0/.

        // In OAuth, request parameters must be sorted by name.
        $launchdatakeys = array_keys($launchdata);
        sort($launchdatakeys);

        // Compute launch parameters.
        $launchparams = [];
        foreach ($launchdatakeys as $key) {
            array_push($launchparams, $key . "=" . rawurlencode($launchdata[$key]));
        }
        $basestring = "POST&" . urlencode($launchurl) . "&" . rawurlencode(implode("&", $launchparams));

        $signature = base64_encode(hash_hmac("sha1", $basestring, $secret, true));

        // Session php variable avec le resource_link_id.
        $_SESSION["resource_link_id"] = $resourcelinkid;

        // Generate HTML & javascript code to POST request.
        $html = <<<HTML
    <form id="ltiLaunchForm" name="ltiLaunchForm" method="POST" action="$launchurl">
HTML;

        foreach ($launchdata as $key => $value) {
            $key = htmlspecialchars($key, ENT_COMPAT);
            $value = htmlspecialchars($value, ENT_COMPAT);
            $html .= "  <input type=\"hidden\" name=\"{$key}\" value=\"{$value}\"/>\n";
        }

        $html .= <<<HTML
    <input type="hidden" name="oauth_signature" value="$signature">
    </form>
    <script>
        window.addEventListener("load", () => {
            document.getElementById("ltiLaunchForm").submit();
        });
    </script>
HTML;
        echo $html;
    }
}
