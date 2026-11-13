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
 * Resolve a NaaS person (author) key to its JSON record: cache, NaaS, then the person catalogue.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use mod_naas\external\naas_external_base;

/**
 * Resolve a NaaS person (author) key to its JSON record: cache, NaaS, then the person catalogue.
 *
 * Moved out of the get_person web service (which keeps parameter checks and access
 * control); the requests sent to NaaS are unchanged.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class person_lookup {
    /** Seconds a person NaaS could not name is not asked for again. */
    public const MISS_TTL = 3600;
    /** Cached GET /persons listing. Aggregation keys are encrypted emails, not names. */
    private const PERSON_CATALOG_CACHE_KEY = 'person_catalog_v1';

    /** Max pages to pull when resolving author names. */
    private const PERSON_CATALOG_MAX_PAGES = 5;

    /** Page size for the person catalog. */
    private const PERSON_CATALOG_PAGE_SIZE = 100;

    /**
     * True when the JSON already has a human-readable person name.
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
        foreach (['firstname', 'first_name', 'firstName', 'lastname', 'last_name', 'lastName'] as $field) {
            if (isset($record->{$field}) && is_string($record->{$field}) && trim($record->{$field}) !== '') {
                return true;
            }
        }
        $opaque = '/^[0-9a-f]{16,}$/i';
        foreach (['name', 'fullname', 'full_name', 'title'] as $field) {
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
     * Lift Nuxeo person:firstname / person:lastname onto the record the widget reads.
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
            if (self::blank_string($item->firstname ?? null) && isset($props->{'person:firstname'})) {
                $item->firstname = $props->{'person:firstname'};
            }
            if (self::blank_string($item->lastname ?? null) && isset($props->{'person:lastname'})) {
                $item->lastname = $props->{'person:lastname'};
            }
            if (self::blank_string($item->email ?? null) && isset($props->{'person:email'})) {
                $item->email = $props->{'person:email'};
            }
        }
        foreach (
            [
                'first_name' => 'firstname',
                'firstName' => 'firstname',
                'last_name' => 'lastname',
                'lastName' => 'lastname',
            ] as $from => $to
        ) {
            if (self::blank_string($item->{$to} ?? null) && isset($item->{$from}) && is_string($item->{$from})) {
                $item->{$to} = $item->{$from};
            }
        }
        if (self::blank_string($item->firstname ?? null) && self::blank_string($item->lastname ?? null)) {
            $title = null;
            if ($props && isset($props->{'dc:title'}) && is_string($props->{'dc:title'})) {
                $title = $props->{'dc:title'};
            } else if (isset($item->title) && is_string($item->title)) {
                $title = $item->title;
            }
            if (is_string($title)) {
                $text = trim($title);
                $opaque = '/^[0-9a-f]{16,}$/i';
                if ($text !== '' && !preg_match($opaque, $text) && self::blank_string($item->name ?? null)) {
                    $item->name = $text;
                }
            }
        }
        return $item;
    }

    /**
     * Copy a named person onto the per-key cache used by the author filter.
     * @param \cache $cache
     * @param object $record
     */
    private static function remember_record(\cache $cache, object $record): void {
        $wrapped = json_encode(['payload' => $record], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!self::json_has_name($wrapped)) {
            return;
        }
        foreach (['email', 'id', 'uid', 'uuid'] as $field) {
            if (!isset($record->{$field}) || !is_string($record->{$field})) {
                continue;
            }
            $key = \mod_naas\catalogue_cache::normalize_person_key($record->{$field});
            if ($key === '') {
                continue;
            }
            $cache->set(\mod_naas\vocabulary_lookup::cache_key('person_', $key), $wrapped);
        }
    }

    /**
     * Persons visible via GET /persons. GET /persons/{id} 404s for some API users.
     * @param \mod_naas\naas_client $naas
     * @return array
     */
    private static function catalog(\mod_naas\naas_client $naas): array {
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cached = $cache->get(self::PERSON_CATALOG_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            $items = json_decode($cached);
            if (is_array($items)) {
                return $items;
            }
        }

        $items = [];
        try {
            $items = self::fetch_pages($naas, '/persons');
        } catch (\moodle_exception $e) {
            debugging('NAAS person catalog: ' . $e->errorcode, DEBUG_DEVELOPER);
        }
        if (!$items) {
            try {
                $items = self::fetch_pages($naas, '/persons/search');
            } catch (\moodle_exception $e) {
                debugging('NAAS person catalog search: ' . $e->errorcode, DEBUG_DEVELOPER);
            }
        }

        if ($items) {
            $normalised = [];
            foreach ($items as $item) {
                $record = self::normalise_record($item);
                $normalised[] = $record;
                self::remember_record($cache, $record);
            }
            $cache->set(
                self::PERSON_CATALOG_CACHE_KEY,
                json_encode($normalised, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            return $normalised;
        }
        return [];
    }

    /**
     * Load the person list into the vocabulary cache so author hashes resolve to names.
     *
     * @param \mod_naas\naas_client $naas
     * @return int Number of person records stored.
     */
    public static function warm_catalog(\mod_naas\naas_client $naas): int {
        return count(self::catalog($naas));
    }

    /**
     * Page through a persons list or search endpoint.
     * @param \mod_naas\naas_client $naas
     * @param string $path
     * @return array
     */
    private static function fetch_pages(\mod_naas\naas_client $naas, string $path): array {
        $items = [];
        for ($page = 0; $page < self::PERSON_CATALOG_MAX_PAGES; $page++) {
            $raw = $naas->request_raw('GET', $path, null, [
                'page_size' => self::PERSON_CATALOG_PAGE_SIZE,
                'page' => $page,
            ]);
            $decoded = json_decode(naas_external_base::sanitise_json_response($raw));
            $items = array_merge($items, self::list_items($decoded));
            if ($page >= structure_lookup::list_page_count($decoded) - 1) {
                break;
            }
        }
        return $items;
    }

    /**
     * Pull person objects out of a list/search payload.
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
     * Match a catalog row by encrypted email, uuid, or id.
     * @param object $item
     * @param string $key
     * @return bool
     */
    private static function record_matches(object $item, string $key): bool {
        $needle = strtolower(\mod_naas\catalogue_cache::normalize_person_key($key));
        if ($needle === '') {
            return false;
        }
        foreach (['email', 'id', 'uid', 'uuid'] as $field) {
            if (!isset($item->{$field}) || !is_string($item->{$field})) {
                continue;
            }
            $candidate = strtolower(\mod_naas\catalogue_cache::normalize_person_key($item->{$field}));
            if ($candidate === $needle) {
                return true;
            }
        }
        return false;
    }

    /**
     * Find one author in the person catalog and wrap it as a get_person payload.
     * @param \mod_naas\naas_client $naas
     * @param string $personkey
     * @return string|null
     */
    private static function from_catalog(\mod_naas\naas_client $naas, string $personkey): ?string {
        foreach (self::catalog($naas) as $item) {
            $record = self::normalise_record($item);
            if (!self::record_matches($record, $personkey)) {
                continue;
            }
            $wrapped = json_encode(['payload' => $record], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (self::json_has_name($wrapped)) {
                return $wrapped;
            }
        }
        return null;
    }

    /**
     * True when firstname / lastname / name are already on the object itself.
     * @param object $record
     * @return bool
     */
    private static function has_top_level_name(object $record): bool {
        foreach (['firstname', 'first_name', 'firstName', 'lastname', 'last_name', 'lastName'] as $field) {
            if (isset($record->{$field}) && is_string($record->{$field}) && trim($record->{$field}) !== '') {
                return true;
            }
        }
        $opaque = '/^[0-9a-f]{16,}$/i';
        foreach (['name', 'fullname', 'full_name'] as $field) {
            if (!isset($record->{$field}) || !is_string($record->{$field})) {
                continue;
            }
            $text = trim($record->{$field});
            if ($text !== '' && !preg_match($opaque, $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Rewrite a person JSON body so Nuxeo properties become firstname / lastname.
     * Leaves an already-named body untouched.
     * @param string $json
     * @return string
     */
    public static function enrich_json(string $json): string {
        $decoded = json_decode($json);
        if (!is_object($decoded)) {
            return $json;
        }
        $target = $decoded;
        $wrapped = false;
        if (isset($decoded->payload)) {
            $payload = $decoded->payload;
            if (is_string($payload)) {
                $payload = json_decode($payload);
            }
            if (!is_object($payload)) {
                return $json;
            }
            $target = $payload;
            $wrapped = true;
        }
        if (self::has_top_level_name($target)) {
            return $json;
        }
        $normalised = self::normalise_record($target);
        if ($wrapped) {
            $decoded->payload = $normalised;
            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $encoded = json_encode($normalised, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return is_string($encoded) ? $encoded : $json;
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
     * Person JSON for a validated key: vocabulary cache, NaaS, then the person catalogue.
     *
     * @param naas_client $naas
     * @param string $personkey Normalised and validated key (e-mail or encrypted e-mail).
     * @return string JSON
     * @throws \moodle_exception error:naas_api:not_found when NaaS has no such person.
     */
    public static function resolve(naas_client $naas, string $personkey): string {
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $cachekey = \mod_naas\vocabulary_lookup::cache_key('person_', $personkey);
        $cached = $cache->get($cachekey);
        if (is_string($cached)) {
            $enriched = self::enrich_json($cached);
            if (self::json_has_name($enriched)) {
                if ($enriched !== $cached) {
                    $cache->set($cachekey, $enriched);
                }
                return $enriched;
            }
            $cache->delete($cachekey);
        }

        // NaaS could not name this person recently: answer as it did then, without asking again.
        $misskey = \mod_naas\vocabulary_lookup::cache_key('personmiss_', $personkey);
        $miss = json_decode((string) $cache->get($misskey), true);
        if (is_array($miss) && (int) ($miss['at'] ?? 0) > time() - self::MISS_TTL) {
            if (!is_string($miss['payload'] ?? null)) {
                throw new \moodle_exception('error:naas_api:not_found', 'naas');
            }
            return $miss['payload'];
        }

        $result = null;
        try {
            $result = self::enrich_json(naas_external_base::sanitise_json_response(
                $naas->request_raw('GET', '/persons/' . rawurlencode($personkey))
            ));
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
        if ($result === null || !self::json_has_name($result)) {
            $fromcatalog = self::from_catalog($naas, $personkey);
            if ($fromcatalog !== null) {
                $result = $fromcatalog;
            }
        }
        $hasname = is_string($result) && self::json_has_name($result);
        if ($hasname) {
            $cache->set($cachekey, $result);
            $cache->delete($misskey);
        } else {
            $cache->set($misskey, json_encode(['at' => time(), 'payload' => $result]));
        }
        if ($result === null) {
            throw new \moodle_exception('error:naas_api:not_found', 'naas');
        }
        return $result;
    }
}
