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
    /** Cached GET /structures?is_producer=true listing. */
    private const PRODUCER_CATALOG_CACHE_KEY = 'producer_catalog_v3';
    /** Max pages to pull when resolving producer names. */
    private const PRODUCER_CATALOG_MAX_PAGES = 5;
    /** Page size for the producer catalog. */
    private const PRODUCER_CATALOG_PAGE_SIZE = 100;

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
     * Producer aggregation keys may be `managed_by:structure:{id}`.
     * @param string $value
     * @return string
     */
    private static function normalize_structure_key(string $value): string {
        $value = trim($value);
        if (preg_match('/^(?:managed_by:)?structure:(.+)$/i', $value, $matches)) {
            return trim($matches[1]);
        }
        return $value;
    }

    /**
     * Public thumbnail/banner URLs for a structure when /structures/{id} is missing.
     * @param string $structurekey
     * @param string $endpoint
     * @return string
     */
    private static function structure_media_payload(string $structurekey, string $endpoint): string {
        $root = rtrim($endpoint, '/');
        $id = rawurlencode($structurekey);
        $payload = [
            'structure_id' => $structurekey,
            'structure_thumbnail_url' => $root . '/thumbnails/structure/' . $id . '/thumbnail',
            'structure_banner_url' => $root . '/thumbnails/structure/' . $id . '/banner',
        ];
        return json_encode(['payload' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
     * True when the JSON already has a human-readable structure name.
     * @param string $json
     * @return bool
     */
    private static function structure_json_has_name(string $json): bool {
        $decoded = json_decode($json);
        if (!is_object($decoded)) {
            return false;
        }
        $record = $decoded;
        if (isset($record->payload)) {
            $payload = $record->payload;
            if (is_string($payload)) {
                $payload = json_decode($payload);
            }
            if (is_object($payload)) {
                $record = $payload;
            }
        }
        $opaque = '/^(?:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|[0-9a-f]{32,64})$/i';
        foreach (['acronym', 'name', 'title', 'label'] as $field) {
            if (!isset($record->{$field}) || !is_string($record->{$field})) {
                continue;
            }
            $text = trim($record->{$field});
            if ($text === '' || preg_match($opaque, $text)) {
                continue;
            }
            return true;
        }
        return false;
    }

    /**
     * Pull structure objects out of a list/search payload.
     * @param mixed $decoded
     * @return array
     */
    private static function structure_list_items($decoded): array {
        if (!is_object($decoded)) {
            return [];
        }
        $root = $decoded;
        if (isset($decoded->payload)) {
            $payload = $decoded->payload;
            if (is_string($payload)) {
                $payload = json_decode($payload);
            }
            if (is_array($payload)) {
                return $payload;
            }
            if (is_object($payload)) {
                $root = $payload;
            }
        }
        foreach (['items', 'entries', 'results'] as $listkey) {
            if (isset($root->{$listkey}) && is_array($root->{$listkey})) {
                $records = [];
                foreach ($root->{$listkey} as $row) {
                    $records[] = self::normalise_structure_record($row);
                }
                return $records;
            }
        }
        return [];
    }

    /**
     * Flatten Nuxeo/document fields onto acronym, name, structure_id, uuid.
     * @param mixed $raw
     * @return object
     */
    private static function normalise_structure_record($raw): object {
        $item = is_object($raw) ? $raw : (is_array($raw) ? (object) $raw : new \stdClass());
        $props = null;
        if (isset($item->properties) && is_object($item->properties)) {
            $props = $item->properties;
        } elseif (isset($item->properties) && is_array($item->properties)) {
            $props = (object) $item->properties;
        }
        if ($props) {
            if (self::blank_string($item->acronym ?? null) && isset($props->{'structure:acronym'})) {
                $item->acronym = $props->{'structure:acronym'};
            }
            if (self::blank_string($item->name ?? null)) {
                if (isset($props->{'dc:title'})) {
                    $item->name = $props->{'dc:title'};
                } elseif (isset($props->{'structure:name'})) {
                    $item->name = $props->{'structure:name'};
                }
            }
            if (self::blank_string($item->structure_id ?? null) && isset($props->{'structure:structure_id'})) {
                $item->structure_id = $props->{'structure:structure_id'};
            }
        }
        if (self::blank_string($item->name ?? null) && isset($item->title) && is_string($item->title)) {
            $item->name = $item->title;
        }
        if (self::blank_string($item->uuid ?? null) && isset($item->uid) && is_string($item->uid)) {
            $item->uuid = $item->uid;
        }
        return $item;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private static function blank_string($value): bool {
        return !is_string($value) || trim($value) === '';
    }

    /**
     * Number of result pages in a list/search payload.
     * @param mixed $decoded
     * @return int
     */
    private static function structure_list_page_count($decoded): int {
        if (!is_object($decoded)) {
            return 1;
        }
        $root = $decoded;
        if (isset($decoded->payload) && is_object($decoded->payload)) {
            $root = $decoded->payload;
        }
        if (isset($root->pages) && is_numeric($root->pages)) {
            return max(1, (int) $root->pages);
        }
        return 1;
    }

    /**
     * Match a catalog row by structure_id, uuid, or id (case-insensitive).
     * @param object $item
     * @param string $key
     * @return bool
     */
    private static function structure_record_matches(object $item, string $key): bool {
        $needle = strtolower($key);
        foreach (['structure_id', 'structureId', 'uuid', 'uid', 'id'] as $field) {
            if (!isset($item->{$field}) || !is_string($item->{$field})) {
                continue;
            }
            if (strtolower($item->{$field}) === $needle) {
                return true;
            }
        }
        return false;
    }

    /**
     * Producer structures visible via GET /structures?is_producer=true.
     * GET /structures/{id} 404s for some API users even when the listing works.
     * @param \mod_naas\naas_client $naas
     * @return array
     */
    private static function producer_catalog(\mod_naas\naas_client $naas): array {
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cached = $cache->get(self::PRODUCER_CATALOG_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            $items = json_decode($cached);
            if (is_array($items)) {
                return $items;
            }
        }

        $items = [];
        try {
            $items = self::fetch_structure_pages($naas, '/structures', [
                'is_producer' => 'true',
                'page_size' => self::PRODUCER_CATALOG_PAGE_SIZE,
            ]);
        } catch (\moodle_exception $e) {
            debugging('NAAS producer catalog: ' . $e->errorcode, DEBUG_DEVELOPER);
        }
        if (!$items) {
            try {
                $items = self::fetch_structure_pages($naas, '/structures/search', [
                    'is_producer' => 'true',
                    'page_size' => self::PRODUCER_CATALOG_PAGE_SIZE,
                ]);
            } catch (\moodle_exception $e) {
                debugging('NAAS producer catalog search: ' . $e->errorcode, DEBUG_DEVELOPER);
            }
        }

        if ($items) {
            $cache->set(
                self::PRODUCER_CATALOG_CACHE_KEY,
                json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }
        return $items;
    }

    /**
     * Page through a structures list or search endpoint.
     * @param \mod_naas\naas_client $naas
     * @param string $path
     * @param array $query
     * @return array
     */
    private static function fetch_structure_pages(\mod_naas\naas_client $naas, string $path, array $query): array {
        $items = [];
        for ($page = 0; $page < self::PRODUCER_CATALOG_MAX_PAGES; $page++) {
            $raw = $naas->request_raw('GET', $path, null, $query + ['page' => $page]);
            $decoded = json_decode(self::sanitise_json_response($raw));
            $items = array_merge($items, self::structure_list_items($decoded));
            if ($page >= self::structure_list_page_count($decoded) - 1) {
                break;
            }
        }
        return $items;
    }

    /**
     * Find one producer in the catalog and wrap it as a get_structure payload.
     * @param \mod_naas\naas_client $naas
     * @param string $structurekey
     * @return string|null
     */
    private static function structure_from_producer_catalog(
        \mod_naas\naas_client $naas,
        string $structurekey
    ): ?string {
        foreach (self::producer_catalog($naas) as $item) {
            $record = self::normalise_structure_record($item);
            if (self::structure_record_matches($record, $structurekey)) {
                return json_encode(['payload' => $record], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        foreach (['/structures', '/structures/search'] as $path) {
            try {
                $raw = $naas->request_raw('GET', $path, null, [
                    'structure_id' => $structurekey,
                    'page_size' => 5,
                ]);
                foreach (self::structure_list_items(json_decode(self::sanitise_json_response($raw))) as $item) {
                    $record = self::normalise_structure_record($item);
                    $wrapped = json_encode(['payload' => $record], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (self::structure_json_has_name($wrapped)) {
                        return $wrapped;
                    }
                }
            } catch (\moodle_exception $e) {
                debugging('NAAS structure query: ' . $e->errorcode, DEBUG_DEVELOPER);
            }
        }
        return null;
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
        $naas = new \mod_naas\naas_client($config);

        $url = '/nuggets/search?' . http_build_query([
                'is_default_version' => true,
                'page_size' => 2,
            ]);

        $result = self::sanitise_json_response($naas->request_raw('GET', $url));
        \mod_naas\catalogue_cache::warm($naas, $config);
        return $result;
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

        $naas = self::make_naas_client($config);
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
        $coursecontext = \context_course::instance($cm->course);
        if (!is_enrolled($coursecontext, null, '', true)) {
            throw new \moodle_exception('error:not_enrolled', 'naas');
        }

        $naasinstance = $DB->get_record('naas', ['id' => $cm->instance], '*', MUST_EXIST);

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new \mod_naas\naas_client($config);

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
        $naas = new \mod_naas\naas_client($config);

        $url = "/versions/{$params['versionId']}/preview_url";
        return self::sanitise_json_response($naas->request_raw('GET', $url));
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

        self::validate_id_param($params['domainKey'], 'domainKey');

        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cachekey = 'domain_' . $params['domainKey'];
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new \mod_naas\naas_client($config);

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

        $structurekey = self::normalize_structure_key($params['structureKey']);
        self::validate_id_param($structurekey, 'structureKey');

        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cachekey = 'structurelabel_' . $structurekey;
        $cached = $cache->get($cachekey);
        if (is_string($cached) && self::structure_json_has_name($cached)) {
            return $cached;
        }

        $fromsnapshot = \mod_naas\catalogue_cache::structure_json($structurekey);
        if ($fromsnapshot !== null) {
            $cache->set($cachekey, $fromsnapshot);
            return $fromsnapshot;
        }

        if ($cached !== false) {
            return $cached;
        }

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = self::make_naas_client($config);

        $result = null;
        $lookups = array_values(array_unique([$structurekey, strtolower($structurekey)]));
        foreach ($lookups as $lookupkey) {
            try {
                $candidate = self::sanitise_json_response(
                    $naas->request_raw('GET', '/structures/' . rawurlencode($lookupkey))
                );
                if (self::structure_json_has_name($candidate)) {
                    $result = $candidate;
                    break;
                }
                if ($result === null) {
                    $result = $candidate;
                }
            } catch (\moodle_exception $e) {
                if (!in_array($e->errorcode, [
                    'error:naas_api:not_found',
                    'error:naas_api:unknown',
                    'error:naas_api:bad_request',
                ], true)) {
                    throw $e;
                }
            }
        }

        if ($result === null || !self::structure_json_has_name($result)) {
            $fromcatalog = self::structure_from_producer_catalog($naas, strtolower($structurekey));
            if ($fromcatalog !== null) {
                $result = $fromcatalog;
            }
        }

        if ($result === null) {
            return self::structure_media_payload($structurekey, (string) ($config->naas_endpoint ?? ''));
        }

        $cache->set($cachekey, $result);
        return $result;
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

        self::validate_id_param($params['personKey'], 'personKey');

        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cachekey = 'person_' . $params['personKey'];
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        $naas = new \mod_naas\naas_client($config);

        $url = "/persons/{$params['personKey']}";
        $result = self::sanitise_json_response($naas->request_raw('GET', $url));
        $cache->set($cachekey, $result);
        return $result;
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
            if ($cached !== null) {
                \mod_naas\search_cache::touch($cachekey);
                return \mod_naas\search_cache::response($cached, true);
            }
        }

        $naas = self::make_naas_client($config);
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

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);

        $cached = \mod_naas\catalogue_cache::cached_probe($config);
        if ($cached !== null) {
            return json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $naas = self::make_naas_client($config);

        $searchoptions = \mod_naas\catalogue_filters::apply([
            'is_default_version' => true,
            'page_size' => 1,
            'sort_by' => 'modification_date',
            'sort_order' => 'DESC',
        ], $config);

        $raw = \mod_naas\catalogue_filters::constrain_search_json(
            self::sanitise_json_response($naas->request_raw('GET', self::search_url($searchoptions))),
            $config
        );
        \mod_naas\catalogue_cache::remember_aggregations($raw, $config);
        $snapshot = \mod_naas\catalogue_cache::get() ?? [];

        return json_encode(
            \mod_naas\catalogue_cache::probe_payload($snapshot, false),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
