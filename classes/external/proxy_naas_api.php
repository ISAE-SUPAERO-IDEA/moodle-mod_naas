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
 * @package mod_naas
 * @author John Tranier
 * @author Bruno Ilponse
 */
namespace mod_naas\external;

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
class proxy_naas_api extends \external_api {
    /** Regex matching RFC 4122 UUIDs and simple alphanumeric slugs (no path separators). */
    private const UUID_SLUG_PATTERN = '/^[a-zA-Z0-9_\-]{1,128}$/';
    /**
     * Reject a parameter value that does not match the UUID/slug allowlist.
     * @param string $value
     * @param string $paramname used in the exception message
     */
    private static function validate_id_param(string $value, string $paramname): void {
        if (!preg_match(self::UUID_SLUG_PATTERN, $value)) {
            throw new \invalid_parameter_exception(get_string('error:invalid_param', 'naas', $paramname));
        }
    }

    /**
     * Domain vocabulary keys may include dots (hierarchical codes).
     *
     * @param string $value
     */
    private static function validate_domain_key(string $value): void {
        if (!preg_match('/^[a-zA-Z0-9_\-.]{1,128}$/', $value)) {
            throw new \invalid_parameter_exception(get_string('error:invalid_param', 'naas', 'domainKey'));
        }
    }

    /**
     * Person keys are encrypted-email hex hashes, emails, or relationship-prefixed ids.
     *
     * @param string $value
     */
    private static function validate_person_key(string $value): void {
        if (preg_match('/^[a-zA-Z0-9_\-]{1,256}$/', $value)) {
            return;
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        throw new \invalid_parameter_exception(get_string('error:invalid_param', 'naas', 'personKey'));
    }

    /**
     * Re-encode a raw JSON string to strip unexpected fields and control characters.
     * @param string $json
     * @return string
     */
    public static function sanitise_json_response(string $json): string {
        $decoded = json_decode($json);
        if ($decoded === null) {
            return $json;
        }
        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Factory so tests can stub the NaaS HTTP client.
     * @param object $config
     * @return \mod_naas\naas_client
     */
    protected static function make_naas_client(object $config): \mod_naas\naas_client {
        return new \mod_naas\naas_client($config);
    }

    /**
     * Reject callers who hold no active enrolment in the course.
     *
     * @param int $courseid Course the activity belongs to.
     * @throws \moodle_exception error:not_enrolled when the current user is not actively enrolled.
     */
    protected static function require_active_course_enrolment(int $courseid): void {
        $coursecontext = \context_course::instance($courseid);
        if (!is_enrolled($coursecontext, null, '', true)) {
            throw new \moodle_exception('error:not_enrolled', 'naas');
        }
    }

    /**
     * Test config parameters description.
     */
    public static function test_config_parameters(): \external_function_parameters {
        return new \external_function_parameters([]);
    }

    /**
     * Test config return description.
     */
    public static function test_config_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    /**
     * Test config method.
     */
    public static function test_config() {
        global $CFG;

        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('mod/naas:admin', $context);

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = static::make_naas_client($config);

        $url = '/nuggets/search?' . http_build_query([
                'is_default_version' => true,
                'page_size' => 2,
            ]);

        $result = self::sanitise_json_response($naas->request_raw('GET', $url));
        \mod_naas\catalogue_cache::warm($naas, $config);
        return $result;
    }

    /**
     * Refresh cache parameters description.
     */
    public static function refresh_cache_parameters(): \external_function_parameters {
        return new \external_function_parameters([]);
    }

    /**
     * Refresh cache return description.
     */
    public static function refresh_cache_returns() {
        return new \external_value(PARAM_RAW, 'Status message');
    }

    /**
     * Clear every NaaS cache and load it again from the saved connection.
     *
     * @return string
     */
    public static function refresh_cache() {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('mod/naas:admin', $context);

        return \mod_naas\cache_refresh::full();
    }

    /**
     * Get nugget parameters description.
     */
    public static function get_nugget_parameters() {
        return new \external_function_parameters(
            [
                'courseId' => new \external_value(PARAM_INT, 'Course ID'),
                'nuggetId' => new \external_value(PARAM_TEXT, 'Nugget ID'),
                'mode' => new \external_value(
                    PARAM_ALPHAEXT,
                    'cache_first to serve a stored document, revalidate to force a live call',
                    VALUE_DEFAULT,
                    'cache_first'
                ),
            ]
        );
    }

    /**
     * Get nugget return description.
     */
    public static function get_nugget_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    /**
     * Get nugget method.
     *
     * Reopening an activity form used to cost a NaaS round-trip every time.
     * In cache_first mode Moodle answers from its own copy and flags
     * `_cache.hit`; the widget then revalidates in the background and only
     * swaps the card when `modification_date` or `version_id` moved.
     *
     * @param int $courseid
     * @param string $nuggetid
     * @param string $mode
     */
    public static function get_nugget(int $courseid, string $nuggetid, string $mode = 'cache_first') {
        global $CFG;

        $params = self::validate_parameters(
            self::get_nugget_parameters(),
            ['courseId' => $courseid, 'nuggetId' => $nuggetid, 'mode' => $mode]
        );

        // Context validation.
        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:addinstance', $context);

        self::validate_id_param($params['nuggetId'], 'nuggetId');

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);

        if ($params['mode'] !== 'revalidate') {
            $cached = \mod_naas\nugget_cache::get($params['nuggetId'], $config);
            if ($cached !== null) {
                return \mod_naas\nugget_cache::response($cached, true);
            }
        }

        $naas = static::make_naas_client($config);
        $url = "/nuggets/{$params['nuggetId']}/default_version";
        $result = self::sanitise_json_response($naas->request_raw('GET', $url));

        $entry = \mod_naas\nugget_cache::store($params['nuggetId'], $result, $config);
        if ($entry === null) {
            return $result;
        }
        return \mod_naas\nugget_cache::response($entry, false);
    }

    /**
     * View nugget parameters description.
     */
    public static function view_nugget_parameters() {
        return new \external_function_parameters(
            [
                'cmId' => new \external_value(PARAM_INT, 'Course Module ID'),
            ]
        );
    }

    /**
     * View nugget returns description.
     * @return \external_value
     */
    public static function view_nugget_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    /**
     * View nugget method.
     * @param int $cmid
     * @return string the JSON encoded response.
     */
    public static function view_nugget(int $cmid) {
        global $CFG, $DB;

        $params = self::validate_parameters(
            self::view_nugget_parameters(),
            ['cmId' => $cmid]
        );

        $context = \context_module::instance($params['cmId']);
        self::validate_context($context);
        require_capability('mod/naas:view', $context);

        // Get course module and instance.
        $cm = get_coursemodule_from_id('naas', $params['cmId'], 0, false, MUST_EXIST);

        // Verify the calling user is actually enrolled in the module's course.
        self::require_active_course_enrolment((int) $cm->course);

        $naasinstance = $DB->get_record('naas', ['id' => $cm->instance], '*', MUST_EXIST);
        // The stored id comes from the activity form (PARAM_TEXT), so check it before it reaches the URL path.
        self::validate_id_param((string) $naasinstance->nugget_id, 'nugget_id');

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = static::make_naas_client($config);

        $url = "/nuggets/{$naasinstance->nugget_id}/default_version";
        return self::sanitise_json_response($naas->request_raw('GET', $url));
    }

    /**
     * Get nugget preview parameters description.
     */
    public static function get_nugget_preview_parameters() {
        return new \external_function_parameters(
            [
                'courseId' => new \external_value(PARAM_INT, 'Course ID'),
                'versionId' => new \external_value(PARAM_TEXT, 'Version ID'),
            ]
        );
    }

    /**
     * Get nugget preview returns description.
     * @return \external_value
     */
    public static function get_nugget_preview_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    /**
     * Get nugget preview method.
     * @param int $courseid
     * @param string $versionid
     * @return string the JSON encoded response.
     */
    public static function get_nugget_preview(int $courseid, string $versionid) {
        global $CFG;

        $params = self::validate_parameters(
            self::get_nugget_preview_parameters(),
            ['courseId' => $courseid, 'versionId' => $versionid]
        );

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:addinstance', $context);

        self::validate_id_param($params['versionId'], 'versionId');

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
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

    /**
     * Get domain parameters description.
     */
    public static function get_domain_parameters() {
        return new \external_function_parameters(
            [
                'courseId' => new \external_value(PARAM_INT, 'Course ID'),
                'domainKey' => new \external_value(PARAM_TEXT, 'Domain Key'),
            ]
        );
    }

    /**
     * Get domain returns description.
     * @return \external_value
     */
    public static function get_domain_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    /**
     * Get domain method.
     * @param int $courseid
     * @param string $domainkey
     * @return string the JSON encoded response.
     */
    public static function get_domain(int $courseid, string $domainkey) {
        global $CFG;

        $params = self::validate_parameters(
            self::get_domain_parameters(),
            ['courseId' => $courseid, 'domainKey' => $domainkey]
        );

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:view', $context);
        // Vocabulary lookups only read caches and NaaS: do not hold the session lock.
        \core\session\manager::write_close();

        self::validate_domain_key($params['domainKey']);

        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cachekey = \mod_naas\vocabulary_lookup::cache_key('domain_', $params['domainKey']);
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = static::make_naas_client($config);

        $url = "/vocabularies/nugget_domains_vocabulary/{$params['domainKey']}";
        $result = self::sanitise_json_response($naas->request_raw('GET', $url));
        $cache->set($cachekey, $result);
        return $result;
    }

    /**
     * Get structure parameters description.
     */
    public static function get_structure_parameters() {
        return new \external_function_parameters(
            [
                'courseId' => new \external_value(PARAM_INT, 'Course ID'),
                'structureKey' => new \external_value(PARAM_TEXT, 'Structure Key'),
            ]
        );
    }

    /**
     * Get structure returns description.
     * @return \external_value
     */
    public static function get_structure_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    /**
     * Get structure method.
     * @param int $courseid
     * @param string $structurekey
     * @return string the JSON encoded response.
     */
    public static function get_structure(int $courseid, string $structurekey) {
        global $CFG;

        $params = self::validate_parameters(
            self::get_structure_parameters(),
            ['courseId' => $courseid, 'structureKey' => $structurekey]
        );

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:view', $context);
        // Vocabulary lookups only read caches and NaaS: do not hold the session lock.
        \core\session\manager::write_close();

        $structurekey = \mod_naas\structure_lookup::normalize_key($params['structureKey']);
        self::validate_id_param($structurekey, 'structureKey');

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        return \mod_naas\structure_lookup::resolve(static::make_naas_client($config), $config, $structurekey);
    }

    /**
     * Get person parameters description.
     */
    public static function get_person_parameters() {
        return new \external_function_parameters(
            [
                'courseId' => new \external_value(PARAM_INT, 'Course ID'),
                'personKey' => new \external_value(PARAM_TEXT, 'Person Key'),
            ]
        );
    }

    /**
     * Get person returns description.
     * @return \external_value
     */
    public static function get_person_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    /**
     * Get person method.
     * @param int $courseid
     * @param string $personkey
     * @return string the JSON encoded response.
     */
    public static function get_person(int $courseid, string $personkey) {
        global $CFG;

        $params = self::validate_parameters(
            self::get_person_parameters(),
            ['courseId' => $courseid, 'personKey' => $personkey]
        );

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:view', $context);
        // Vocabulary lookups only read caches and NaaS: do not hold the session lock.
        \core\session\manager::write_close();

        $personkey = \mod_naas\catalogue_cache::normalize_person_key($params['personKey']);
        self::validate_person_key($personkey);

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        return \mod_naas\person_lookup::resolve(static::make_naas_client($config), $personkey);
    }

    /**
     * Search nuggets parameters description.
     */
    public static function search_nuggets_parameters() {
        return new \external_function_parameters(
            [
                'courseId' => new \external_value(PARAM_INT, 'Course ID'),
                'searchOptions' => new \external_single_structure(
                    [
                        'fulltext' => new \external_value(PARAM_TEXT, 'Full text search', VALUE_OPTIONAL),
                        'page_size' => new \external_value(PARAM_INT, 'Number of results per page', VALUE_OPTIONAL),
                        'page' => new \external_value(PARAM_INT, 'Page number', VALUE_OPTIONAL),
                        'related_domains' => new \external_multiple_structure(
                            new \external_value(PARAM_TEXT, 'Single domain value'),
                            'Domain filter',
                            VALUE_OPTIONAL
                        ),
                        'structure' => new \external_value(PARAM_TEXT, 'Structure filter', VALUE_OPTIONAL),
                        'language' => new \external_multiple_structure(
                            new \external_value(PARAM_TEXT, 'Single language value'),
                            'Language filter',
                            VALUE_OPTIONAL
                        ),
                        'level' => new \external_multiple_structure(
                            new \external_value(PARAM_TEXT, 'Single level value', VALUE_OPTIONAL),
                            'Level filter',
                            VALUE_OPTIONAL
                        ),
                        'tags' => new \external_multiple_structure(
                            new \external_value(PARAM_TEXT, 'Single tag value'),
                            'Tags filter',
                            VALUE_OPTIONAL
                        ),
                        'producers' => new \external_multiple_structure(
                            new \external_value(PARAM_TEXT, 'Single producer value'),
                            'Producers filter',
                            VALUE_OPTIONAL
                        ),
                        'authors' => new \external_multiple_structure(
                            new \external_value(PARAM_TEXT, 'Single author value'),
                            'Authors filter',
                            VALUE_OPTIONAL
                        ),
                        'type' => new \external_multiple_structure(
                            new \external_value(PARAM_TEXT, 'Single type value'),
                            'Type filter',
                            VALUE_OPTIONAL
                        ),
                        'license' => new \external_multiple_structure(
                            new \external_value(PARAM_TEXT, 'Single licence value'),
                            'Licence filter',
                            VALUE_OPTIONAL
                        ),
                    ],
                    'Search options',
                    VALUE_DEFAULT,
                    []
                ),
                'mode' => new \external_value(
                    PARAM_ALPHAEXT,
                    'cache_first to serve a cached page when one exists, revalidate to force a live call',
                    VALUE_DEFAULT,
                    'cache_first'
                ),
            ]
        );
    }

    /**
     * Search nuggets returns description.
     * @return \external_value
     */
    public static function search_nuggets_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    /**
     * Search nuggets method.
     *
     * In cache_first mode a stored page is returned without touching NaaS, and
     * the response is flagged `_cache.hit`. The widget paints that immediately
     * and then calls again in revalidate mode; it only repaints when the
     * freshness digest moved. A cold key falls through to a live call, reports
     * `hit: false`, and the widget skips the revalidation as redundant.
     *
     * @param int $courseid
     * @param array $searchoptions
     * @param string $mode
     * @return string the JSON encoded response.
     */
    public static function search_nuggets(int $courseid, array $searchoptions, string $mode = 'cache_first') {
        global $CFG;

        $params = self::validate_parameters(
            self::search_nuggets_parameters(),
            ['courseId' => $courseid, 'searchOptions' => $searchoptions, 'mode' => $mode]
        );

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:addinstance', $context);
        // A catalogue probe may already be calling NaaS. Release the session
        // so this read can return the saved page without waiting for it.
        \core\session\manager::write_close();

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);

        $searchoptionsarray = $params['searchOptions'];

        // Set default search options.
        $searchoptionsarray['is_default_version'] = true;
        if (!isset($searchoptionsarray['page_size'])) {
            $searchoptionsarray['page_size'] = 6;
        }

        $searchoptionsarray = \mod_naas\catalogue_filters::apply($searchoptionsarray, $config);

        $cachekey = \mod_naas\search_cache::canonical_key($searchoptionsarray, $config);
        if ($params['mode'] !== 'revalidate') {
            $cached = \mod_naas\search_cache::get($cachekey, $config);
            if ($cached === null) {
                foreach (\mod_naas\catalogue_cache::producer_search_aliases($searchoptionsarray) as $alias) {
                    $aliaskey = \mod_naas\search_cache::canonical_key($alias, $config);
                    $cached = \mod_naas\search_cache::get($aliaskey, $config);
                    if ($cached !== null) {
                        $cachekey = $aliaskey;
                        break;
                    }
                }
            }
            if ($cached !== null) {
                \mod_naas\search_cache::touch($cachekey);
                return \mod_naas\search_cache::response($cached, true);
            }
        }

        $naas = static::make_naas_client($config);
        $result = \mod_naas\catalogue_filters::constrain_search_json(
            self::sanitise_json_response($naas->request_raw('GET', self::search_url($searchoptionsarray))),
            $config
        );

        if (\mod_naas\catalogue_cache::is_landing_search($searchoptionsarray)) {
            \mod_naas\catalogue_cache::remember_search($result, $config);
        }
        $entry = \mod_naas\search_cache::store($cachekey, $searchoptionsarray, $result, $config);
        if ($entry === null) {
            return $result;
        }
        return \mod_naas\search_cache::response($entry, false);
    }

    /**
     * Build the NaaS search URL, flattening PHP's indexed array syntax.
     * @param array $searchoptions
     * @return string
     */
    public static function search_url(array $searchoptions): string {
        $url = '/nuggets/search?' . http_build_query($searchoptions, '', '&');
        return preg_replace('/\%5B\d+\%5D/', '', $url);
    }

    /**
     * Check catalogue parameters description.
     */
    public static function check_catalogue_parameters() {
        return new \external_function_parameters([
            'courseId' => new \external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Check catalogue returns description.
     * @return \external_value
     */
    public static function check_catalogue_returns() {
        return new \external_value(PARAM_RAW, 'API response');
    }

    /**
     * Landing probe: refresh producers, facet counts, and the catalogue stamp.
     *
     * Aggregations are computed over the whole match set rather than the page,
     * so page_size=1 returns every producer bucket and every count. Sorted by
     * modification_date DESC, items[0] is the newest document in the catalogue.
     * Together those two numbers are a sound change detector: unchanged stamp
     * means every cached page is still valid.
     *
     * @param int $courseid
     * @return string the JSON encoded response.
     */
    public static function check_catalogue(int $courseid) {
        global $CFG;

        $params = self::validate_parameters(
            self::check_catalogue_parameters(),
            ['courseId' => $courseid]
        );

        $context = \context_course::instance($params['courseId']);
        self::validate_context($context);
        require_capability('mod/naas:addinstance', $context);
        \core\session\manager::write_close();

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);

        $cached = \mod_naas\catalogue_cache::cached_probe($config);
        if ($cached !== null) {
            return json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $naas = static::make_naas_client($config);

        $searchoptions = \mod_naas\catalogue_filters::apply([
            'is_default_version' => true,
            'page_size' => 1,
            'sort_by' => 'modification_date',
            'sort_order' => 'DESC',
        ], $config);

        try {
            $raw = \mod_naas\catalogue_filters::constrain_search_json(
                self::sanitise_json_response($naas->request_raw('GET', self::search_url($searchoptions))),
                $config
            );
        } catch (\moodle_exception $e) {
            // NaaS unreachable: answer with the last known catalogue instead of failing,
            // so the landing keeps working from the cache.
            $snapshot = \mod_naas\catalogue_cache::get();
            if (!$snapshot || ($snapshot['fingerprint'] ?? '') !== \mod_naas\catalogue_cache::fingerprint($config)) {
                throw $e;
            }
            return json_encode(
                \mod_naas\catalogue_cache::probe_payload($snapshot, true),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }
        \mod_naas\catalogue_cache::remember_aggregations($raw, $config);
        $snapshot = \mod_naas\catalogue_cache::get() ?? [];

        return json_encode(
            \mod_naas\catalogue_cache::probe_payload($snapshot, false),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
