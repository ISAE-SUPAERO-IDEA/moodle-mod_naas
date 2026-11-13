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
 * Resolve a NaaS structure (producer) key to its JSON record: cache, catalogue snapshot, then NaaS.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use mod_naas\external\naas_external_base;

/**
 * Resolve a NaaS structure (producer) key to its JSON record: cache, catalogue snapshot, then NaaS.
 *
 * Moved out of the get_structure web service (which keeps parameter checks and access
 * control); the requests sent to NaaS are unchanged.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class structure_lookup {
    /** Seconds a structure NaaS could not name is not asked for again. */
    public const MISS_TTL = 3600;
    /** Cached GET /structures?is_producer=true listing. */
    private const PRODUCER_CATALOG_CACHE_KEY = 'producer_catalog_v3';

    /** Max pages to pull when resolving producer names. */
    private const PRODUCER_CATALOG_MAX_PAGES = 5;

    /** Page size for the producer catalog. */
    private const PRODUCER_CATALOG_PAGE_SIZE = 100;

    /**
     * MUC vocabulary keys must be simple: letters, digits, underscore.
     * Structure ids are UUIDs, and a hyphen makes cache->get throw before the name is fetched.
     *
     * @param string $structurekey
     * @return string
     */
    public static function cache_key(string $structurekey): string {
        return 'structurelabel_' . sha1(strtolower(trim($structurekey)));
    }

    /**
     * Vocabulary cache key remembering that NaaS could not name a structure.
     *
     * @param string $structurekey
     * @return string
     */
    public static function miss_key(string $structurekey): string {
        return 'structuremiss_' . sha1(strtolower(trim($structurekey)));
    }

    /**
     * Producer aggregation keys may be `managed_by:structure:{id}`.
     * @param string $value
     * @return string
     */
    public static function normalize_key(string $value): string {
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
    private static function media_payload(string $structurekey, string $endpoint): string {
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
     * True when the JSON already has a human-readable structure name.
     * @param string $json
     * @return bool
     */
    public static function json_has_name(string $json): bool {
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
        $record = self::normalise_record($record);
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
    private static function list_items($decoded): array {
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
                    $records[] = self::normalise_record($row);
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
    public static function normalise_record($raw): object {
        $item = is_object($raw) ? $raw : (is_array($raw) ? (object) $raw : new \stdClass());
        $props = null;
        if (isset($item->properties) && is_object($item->properties)) {
            $props = $item->properties;
        } else if (isset($item->properties) && is_array($item->properties)) {
            $props = (object) $item->properties;
        }
        if ($props) {
            if (self::blank_string($item->acronym ?? null) && isset($props->{'structure:acronym'})) {
                $item->acronym = $props->{'structure:acronym'};
            }
            if (self::blank_string($item->name ?? null)) {
                if (isset($props->{'dc:title'})) {
                    $item->name = $props->{'dc:title'};
                } else if (isset($props->{'structure:name'})) {
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
     * Number of result pages in a list/search payload.
     * @param mixed $decoded
     * @return int
     */
    public static function list_page_count($decoded): int {
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
    public static function record_matches(object $item, string $key): bool {
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
            $items = self::fetch_pages($naas, '/structures', [
                'is_producer' => 'true',
                'page_size' => self::PRODUCER_CATALOG_PAGE_SIZE,
            ]);
        } catch (\moodle_exception $e) {
            debugging('NAAS producer catalog: ' . $e->errorcode, DEBUG_DEVELOPER);
        }
        if (!$items) {
            try {
                $items = self::fetch_pages($naas, '/structures/search', [
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
    private static function fetch_pages(\mod_naas\naas_client $naas, string $path, array $query): array {
        $items = [];
        for ($page = 0; $page < self::PRODUCER_CATALOG_MAX_PAGES; $page++) {
            $raw = $naas->request_raw('GET', $path, null, $query + ['page' => $page]);
            $decoded = json_decode(naas_external_base::sanitise_json_response($raw));
            $items = array_merge($items, self::list_items($decoded));
            if ($page >= self::list_page_count($decoded) - 1) {
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
    private static function from_producer_catalog(
        \mod_naas\naas_client $naas,
        string $structurekey
    ): ?string {
        foreach (self::producer_catalog($naas) as $item) {
            $record = self::normalise_record($item);
            if (self::record_matches($record, $structurekey)) {
                return json_encode(['payload' => $record], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        foreach (['/structures', '/structures/search'] as $path) {
            try {
                $raw = $naas->request_raw('GET', $path, null, [
                    'structure_id' => $structurekey,
                    'page_size' => 5,
                ]);
                foreach (self::list_items(json_decode(naas_external_base::sanitise_json_response($raw))) as $item) {
                    $record = self::normalise_record($item);
                    $wrapped = json_encode(['payload' => $record], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (self::json_has_name($wrapped)) {
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
     * Whether the value is missing or an empty string.
     *
     * @param mixed $value
     * @return bool
     */
    private static function blank_string($value): bool {
        return !is_string($value) || trim($value) === '';
    }

    /**
     * Structure JSON for a validated key: vocabulary cache, catalogue snapshot, NaaS, then the producer catalogue.
     *
     * @param naas_client $naas
     * @param object $config Merged plugin and site configuration.
     * @param string $structurekey Normalised and validated key.
     * @return string JSON, or a media-only payload when NaaS knows nothing about the key.
     */
    public static function resolve(naas_client $naas, object $config, string $structurekey): string {
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cachekey = self::cache_key($structurekey);
        $cached = $cache->get($cachekey);
        if (is_string($cached) && self::json_has_name($cached)) {
            return $cached;
        }
        if ($cached !== false) {
            $cache->delete($cachekey);
        }

        $fromsnapshot = \mod_naas\catalogue_producers::structure_json($structurekey);
        if ($fromsnapshot !== null) {
            $cache->set($cachekey, $fromsnapshot);
            return $fromsnapshot;
        }

        // NaaS could not name this structure recently: answer as it did then, without asking again.
        $miss = json_decode((string) $cache->get(self::miss_key($structurekey)), true);
        if (is_array($miss) && (int) ($miss['at'] ?? 0) > time() - self::MISS_TTL && is_string($miss['payload'] ?? null)) {
            return $miss['payload'];
        }

        $result = null;
        $lookups = array_values(array_unique([$structurekey, strtolower($structurekey)]));
        foreach ($lookups as $lookupkey) {
            try {
                $candidate = naas_external_base::sanitise_json_response(
                    $naas->request_raw('GET', '/structures/' . rawurlencode($lookupkey))
                );
                if (self::json_has_name($candidate)) {
                    $result = $candidate;
                    break;
                }
                if ($result === null) {
                    $result = $candidate;
                }
            } catch (\moodle_exception $e) {
                if (
                    !in_array($e->errorcode, [
                        'error:naas_api:not_found',
                        'error:naas_api:unknown',
                        'error:naas_api:bad_request',
                    ], true)
                ) {
                    throw $e;
                }
            }
        }

        if ($result === null || !self::json_has_name($result)) {
            $fromcatalog = self::from_producer_catalog($naas, strtolower($structurekey));
            if ($fromcatalog !== null) {
                $result = $fromcatalog;
            }
        }

        if ($result === null) {
            $result = self::media_payload($structurekey, (string) ($config->naas_endpoint ?? ''));
        }

        if (self::json_has_name($result)) {
            $cache->set($cachekey, $result);
            $cache->delete(self::miss_key($structurekey));
        } else {
            $cache->set(self::miss_key($structurekey), json_encode(['at' => time(), 'payload' => $result]));
        }
        return $result;
    }
}
