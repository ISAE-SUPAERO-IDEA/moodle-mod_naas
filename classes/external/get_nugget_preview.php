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
 * Web service mod_naas_get_nugget_preview.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
namespace mod_naas\external;

/**
 * Get the preview URL of a Nugget version, only when it is a web page URL.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
class get_nugget_preview extends naas_external_base {
    /**
     * Get nugget preview parameters description.
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new \core_external\external_function_parameters(
            [
                'courseId' => new \core_external\external_value(PARAM_INT, 'Course ID'),
                'versionId' => new \core_external\external_value(PARAM_TEXT, 'Version ID'),
            ]
        );
    }

    /**
     * Get nugget preview returns description.
     * @return \core_external\external_value
     */
    public static function execute_returns() {
        return new \core_external\external_value(PARAM_RAW, 'API response');
    }

    /**
     * Get nugget preview method.
     * @param int $courseid
     * @param string $versionid
     * @return string the JSON encoded response.
     */
    public static function execute(int $courseid, string $versionid) {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['courseId' => $courseid, 'versionId' => $versionid]
        );

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:addinstance', $context);

        self::validate_id_param($params['versionId'], 'versionId');

        $config = self::naas_config();
        $naas = static::make_naas_client($config);

        $url = "/versions/{$params['versionId']}/preview_url";
        $response = self::sanitise_json_response($naas->request_raw('GET', $url));

        // The widget puts this URL in an iframe: only let a web page URL through.
        $decoded = json_decode($response, true);
        $previewurl = is_array($decoded) ? ($decoded['payload'] ?? null) : $decoded;
        if (!is_string($previewurl) || !\mod_naas\media_proxy::is_frame_url_allowed($previewurl, $config->naas_endpoint ?? '')) {
            throw new \moodle_exception('error:naas_server_unexpected', 'naas');
        }
        return $response;
    }
}
