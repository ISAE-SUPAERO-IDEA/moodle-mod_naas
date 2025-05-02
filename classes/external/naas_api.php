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
 * Proxy requests from the user agent to the naas-api.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas/external
 * @author John Tranier
 * @author Bruno Ilponse
 */namespace mod_naas\external;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');


/**
 * Proxy requests from the user agent to the naas-api.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2019  ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @package mod_naas/external
 * @author John Tranier
 * @author Bruno Ilponse
 */
class naas_api extends  \external_api {

    /**
     * Test config parameters description
     */
    public static function test_config_parameters(): \external_function_parameters {
        return new \external_function_parameters([]);
    }

    /**
     * Test config return description
     */
    public static function test_config_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    /**
     * Test config method
     */
    public static function test_config() {
        global $CFG;

        // Context validation
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('mod/naas:admin', $context);

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new \mod_naas\naas_client($config);

        $url = '/nuggets/search?' . http_build_query([
                'is_default_version' => true,
                'page_size' => 2,
            ]);

        $response = $naas->request_raw('GET', $url);
        return $response->build_client_response();
    }

    /**
     * Get nugget parameters description
     */
    public static function get_nugget_parameters() {
        return new \external_function_parameters(
            array(
                'courseId' => new external_value(PARAM_INT, 'Course ID'),
                'nuggetId' => new external_value(PARAM_TEXT, 'Nugget ID')
            )
        );
    }

    /**
     * Get nugget return description
     */
    public static function get_nugget_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    /**
     * Get nugget method
     */
    public static function get_nugget($courseid, $nuggetid) {
        global $CFG;

        $params = self::validate_parameters(self::get_nugget_parameters(),
            array('courseId' => $courseid, 'nuggetId' => $nuggetid));

        // Context validation
        $context = context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:addinstance', $context);

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new \mod_naas\naas_client($config);

        $url = "/nuggets/{$params['nuggetId']}/default_version";
        $response = $naas->request_raw('GET', $url);
        return $response->build_client_response();
    }

    /**
     * View nugget parameters description
     */
    public static function view_nugget_parameters() {
        return new \external_function_parameters(
            array(
                'cmId' => new \external_value(PARAM_INT, 'Course Module ID')
            )
        );
    }

    public static function view_nugget_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    public static function view_nugget($cmid) {
        global $CFG, $DB;

        $params = self::validate_parameters(self::view_nugget_parameters(),
            array('cmId' => $cmid));

        $context = \context_module::instance($params['cmId']);
        self::validate_context($context);
        require_capability('mod/naas:view', $context);

        // Get course module and instance
        $cm = get_coursemodule_from_id('naas', $params['cmId'], 0, false, MUST_EXIST);
        $naasinstance = $DB->get_record('naas', ['id' => $cm->instance], '*', MUST_EXIST);

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new \mod_naas\naas_client($config);

        $url = "/nuggets/{$naasinstance->nugget_id}/default_version";
        $response = $naas->request_raw('GET', $url);
        return $response->build_client_response();
    }


    /**
     * Get nugget preview parameters description
     */
    public static function get_nugget_preview_parameters() {
        return new \external_function_parameters(
            array(
                'courseId' => new \external_value(PARAM_INT, 'Course ID'),
                'versionId' => new \external_value(PARAM_TEXT, 'Version ID')
            )
        );
    }

    public static function get_nugget_preview_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    public static function get_nugget_preview($courseid, $versionid) {
        global $CFG;

        $params = self::validate_parameters(self::get_nugget_preview_parameters(),
            array('courseId' => $courseid, 'versionId' => $versionid));

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:addinstance', $context);

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new \mod_naas\naas_client($config);

        $url = "/versions/{$params['versionId']}/preview_url";
        $response = $naas->request_raw('GET', $url);
        return $response->build_client_response();
    }

    /**
     * Get domain parameters description
     */
    public static function get_domain_parameters() {
        return new \external_function_parameters(
            array(
                'courseId' => new \external_value(PARAM_INT, 'Course ID'),
                'domainKey' => new \external_value(PARAM_TEXT, 'Domain Key')
            )
        );
    }

    public static function get_domain_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    public static function get_domain($courseid, $domainkey) {
        global $CFG;

        $params = self::validate_parameters(self::get_domain_parameters(),
            array('courseId' => $courseid, 'domainKey' => $domainkey));

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:view', $context);

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new \mod_naas\naas_client($config);

        $url = "/vocabularies/nugget_domains_vocabulary/{$params['domainKey']}";
        $response = $naas->request_raw('GET', $url);
        return $response->build_client_response();
    }

    /**
     * Get structure parameters description
     */
    public static function get_structure_parameters() {
        return new \external_function_parameters(
            array(
                'courseId' => new \external_value(PARAM_INT, 'Course ID'),
                'structureKey' => new \external_value(PARAM_TEXT, 'Structure Key')
            )
        );
    }

    public static function get_structure_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    public static function get_structure($courseid, $structurekey) {
        global $CFG;

        $params = self::validate_parameters(self::get_structure_parameters(),
            array('courseId' => $courseid, 'structureKey' => $structurekey));

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:view', $context);

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new \mod_naas\naas_client($config);

        $url = "/structures/{$params['structureKey']}";
        $response = $naas->request_raw('GET', $url);
        return $response->build_client_response();
    }

    /**
     * Get person parameters description
     */
    public static function get_person_parameters() {
        return new \external_function_parameters(
            array(
                'courseId' => new \external_value(PARAM_INT, 'Course ID'),
                'personKey' => new \external_value(PARAM_TEXT, 'Person Key')
            )
        );
    }

    public static function get_person_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    public static function get_person($courseid, $personkey) {
        global $CFG;

        $params = self::validate_parameters(self::get_person_parameters(),
            array('courseId' => $courseid, 'personKey' => $personkey));

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:view', $context);

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new \mod_naas\naas_client($config);

        $url = "/persons/{$params['personKey']}";
        $response = $naas->request_raw('GET', $url);
        return $response->build_client_response();
    }

    /**
     * Search nuggets parameters description
     */
    public static function search_nuggets_parameters() {
        return new \external_function_parameters(
            array(
                'courseId' => new \external_value(PARAM_INT, 'Course ID'),
                'searchOptions' => new \external_single_structure(
                    array(
                        'fulltext' => new \external_value(PARAM_TEXT, 'Full text search', VALUE_OPTIONAL),
                        'page_size' => new \external_value(PARAM_INT, 'Number of results per page', VALUE_OPTIONAL),
                        'page' => new \external_value(PARAM_INT, 'Page number', VALUE_OPTIONAL),
                        'domain' => new \external_value(PARAM_TEXT, 'Domain filter', VALUE_OPTIONAL),
                        'structure' => new \external_value(PARAM_TEXT, 'Structure filter', VALUE_OPTIONAL),
                        'language' => new \external_value(PARAM_TEXT, 'Language filter', VALUE_OPTIONAL)
                        // Ajoutez ici tous les champs possibles de vos options de recherche
                    ),
                    'Search options',
                    VALUE_DEFAULT,
                    array()
                )
            )
        );

    }

    public static function search_nuggets_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    public static function search_nuggets($courseid, $searchoptions = '{}') {
        global $CFG;

        $params = self::validate_parameters(self::search_nuggets_parameters(),
            array('courseId' => $courseid, 'searchOptions' => $searchoptions));

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:addinstance', $context);

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new \mod_naas\naas_client($config);

        $searchoptionsarray = $params['searchOptions'];

        // Set default search options
        $searchoptionsarray['is_default_version'] = true;
        if (!isset($searchoptionsarray['page_size'])) {
            $searchoptionsarray['page_size'] = 6;
        }

        // Add nql filter
        if (!empty($config->naas_filter)) {
            $searchoptionsarray['nql'] = urlencode($config->naas_filter);
        }

        $url = '/nuggets/search?' . http_build_query($searchoptionsarray, '', '&');
        $url = preg_replace('/\%5B\d+\%5D/', '', $url);

        $response = $naas->request_raw('GET', $url);
        return $response->build_client_response();
    }

}
