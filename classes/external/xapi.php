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
 * External Service to send xAPI statements to the NaaS platform.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * External Service to send xAPI statements to the NaaS platform.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
class xapi extends \external_api {

    /**
     * Parameters definition for post_xapi_statement
     */
    public static function post_xapi_statement_parameters() {
        return new \external_function_parameters([
            'verb' => new \external_value(PARAM_TEXT, 'XAPI verb'),
            'version_id' => new \external_value(PARAM_TEXT, 'Version ID'),
            'id' => new \external_value(PARAM_INT, 'Course Module ID'),
            'body' => new \external_value(PARAM_RAW, 'XAPI statement body', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Returns description
     */
    public static function post_xapi_statement_returns() {
        return new \external_single_structure([
            'statusCode' => new \external_value(PARAM_INT, 'HTTP status code of the response'),
            'statusMessage' => new \external_value(PARAM_TEXT, 'HTTP status message of the response'),
        ]);
    }

    /**
     * Post XAPI statement method
     * @param string $verb
     * @param string $versionid
     * @param int $id
     * @param string $body
     */
    public static function post_xapi_statement($verb, $versionid, $id, $body = null) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::post_xapi_statement_parameters(), [
            'verb' => $verb,
            'version_id' => $versionid,
            'id' => $id,
            'body' => $body,
        ]);

        // Get course module and check permissions.
        $cm = get_coursemodule_from_id('naas', $params['id'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/naas:view', $context);

        // Get user info.
        $config = (object) array_merge((array) \get_config('naas'), (array) $CFG);
        $user = new \stdClass();
        $user->name = $config->naas_privacy_learner_name ? $USER->firstname.' '.$USER->lastname : "Anonymous User";
        $user->email = $config->naas_privacy_learner_mail ? $USER->email : 'anonymous@naas-edu.eu';

        // Prepare data.
        $data = new \stdClass();
        $data->user = $user;
        if (!$params['body']) {
            $data->body = new \stdClass();
        } else {
            $data->body = json_decode($params['body']);
        }

        if (isset($_SESSION["resource_link_id"])) {
            $data->resource_link_id = $_SESSION["resource_link_id"];
        }

        // Send to NaaS.
        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new \mod_naas\naas_client($config);
        $response = $naas->post_xapi_statement($params['verb'], $params['version_id'], $data);

        return [
          "statusCode" => $response->statusCode,
          "statusMessage" => $response->statusMessage,
        ];
    }
}
