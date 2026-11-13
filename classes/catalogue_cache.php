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
 * Site-wide catalogue snapshot used to paint Nugget search immediately.
 *
 * Warmed when "Test connection" succeeds. The search widget reads it from
 * window.NAAS, then refreshes from NaaS in the background.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

/**
 * Application cache of the unfiltered catalogue (search + producers).
 *
 * Owns the MUC record, the connection fingerprint, the key helpers and the
 * writes that follow a live landing search or probe. The producer table lives
 * in {@see catalogue_producers}, the page/probe payloads in
 * {@see catalogue_export}, and the rebuild from NaaS in {@see catalogue_warmer}.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue_cache {
    /** MUC area name from db/caches.php. */
    public const CACHE_AREA = 'catalogue_snapshot';
    /** Single-slot key; fingerprint inside the payload invalidates stale tenants. */
    public const CACHE_KEY = 'current';
    /** Match the Vue search widget first page. */
    public const SEARCH_PAGE_SIZE = 9;

    /**
     * Hash of the saved connection so a tenant change drops the old snapshot.
     *
     * @param object $config
     * @return string
     */
    public static function fingerprint(object $config): string {
        $password = getenv('NAAS_API_PASSWORD') ?: (string) ($config->naas_password ?? '');
        return sha1(implode('|', [
            (string) ($config->naas_endpoint ?? ''),
            (string) ($config->naas_username ?? ''),
            (string) ($config->naas_structure_id ?? ''),
            (string) ($config->naas_filter ?? ''),
            catalogue_filters::commercial_mode($config),
            catalogue_filters::access_mode($config),
            sha1($password),
        ]));
    }

    /**
     * Decode the stored snapshot, or null when empty/corrupt.
     *
     * @return array|null
     */
    public static function get(): ?array {
        $cache = \cache::make('mod_naas', self::CACHE_AREA);
        $raw = $cache->get(self::CACHE_KEY);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Persist a snapshot array.
     *
     * @param array $snapshot
     */
    public static function store(array $snapshot): void {
        $cache = \cache::make('mod_naas', self::CACHE_AREA);
        $cache->set(
            self::CACHE_KEY,
            json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Drop the snapshot (tests / credential change).
     */
    public static function purge(): void {
        \cache::make('mod_naas', self::CACHE_AREA)->delete(self::CACHE_KEY);
    }

    /**
     * True when this search is the empty catalogue landing query.
     *
     * @param array $options
     * @return bool
     */
    public static function is_landing_search(array $options): bool {
        if (trim((string) ($options['fulltext'] ?? '')) !== '') {
            return false;
        }
        if (!empty($options['page']) && (int) $options['page'] > 0) {
            return false;
        }
        $facets = [
            'producers', 'authors', 'related_domains', 'language', 'level',
            'tags', 'type', 'structure', 'is_public',
        ];
        foreach ($facets as $facet) {
            if (!array_key_exists($facet, $options)) {
                continue;
            }
            $value = $options[$facet];
            if ($value === '' || $value === [] || $value === null) {
                continue;
            }
            return false;
        }
        return true;
    }

    /**
     * After a live landing search, keep aggregations/nuggets fresh without dropping producers.
     *
     * @param string $json
     * @param object $config
     */
    public static function remember_search(string $json, object $config): void {
        $search = naas_payload::decode_search($json);
        if ($search === null) {
            return;
        }
        $existing = self::get() ?? [];
        $fingerprint = self::fingerprint($config);
        if (($existing['fingerprint'] ?? '') !== $fingerprint) {
            $existing = ['fingerprint' => $fingerprint, 'producers' => []];
        }
        $existing['search'] = $search;
        $existing['max_modification_date'] = self::max_modification_date($search['items'] ?? []);
        $existing['warmed_at'] = time();
        $existing['fingerprint'] = $fingerprint;
        $existing['producers'] = catalogue_producers::reconcile_producers(
            $existing['producers'] ?? [],
            $search['aggregations'] ?? []
        );
        self::store($existing);
    }

    /**
     * Refresh aggregations and producers from a probe, leaving the cards alone.
     *
     * The landing probe runs with page_size=1, so its `items` must never
     * replace the cached page — only the facet counts and the producer table
     * are authoritative here.
     *
     * @param string $json
     * @param object $config
     * @return string The producer digest after reconciliation.
     */
    public static function remember_aggregations(string $json, object $config): string {
        $search = naas_payload::decode_search($json);
        if ($search === null) {
            return '';
        }
        $fingerprint = self::fingerprint($config);
        $existing = self::get() ?? [];
        if (($existing['fingerprint'] ?? '') !== $fingerprint) {
            $existing = ['fingerprint' => $fingerprint, 'producers' => []];
        }
        $existing['fingerprint'] = $fingerprint;
        $existing['producers'] = catalogue_producers::reconcile_producers(
            $existing['producers'] ?? [],
            $search['aggregations'] ?? []
        );
        if (!empty($search['aggregations'])) {
            $existing['search']['aggregations'] = $search['aggregations'];
        }
        $existing['search']['items'] = $existing['search']['items'] ?? [];
        $existing['search']['results_count'] = (int) $search['results_count'];
        $existing['stamp'] = catalogue_stamp::from_search($search);
        $existing['stamp_checked_at'] = time();
        self::store($existing);
        return catalogue_producers::producers_digest($existing['producers']);
    }

    /**
     * Canonical form of a structure cache key.
     *
     * @param string $value
     * @return string
     */
    public static function normalize_structure_key(string $value): string {
        $value = trim($value);
        if (preg_match('/^(?:managed_by:)?structure:(.+)$/i', $value, $matches)) {
            return trim($matches[1]);
        }
        return $value;
    }

    /**
     * Author aggregation keys may be `authored_by:person:{hash}`.
     *
     * @param string $value
     * @return string
     */
    public static function normalize_person_key(string $value): string {
        $value = trim($value);
        if (preg_match('/^(?:authored_by:|designed_by:|reviewed_by:)?person:(.+)$/i', $value, $matches)) {
            return trim($matches[1]);
        }
        return $value;
    }

    /**
     * Newest modification_date among cached search hits (ISO-8601 comparable strings).
     *
     * @param array $items
     * @return string
     */
    public static function max_modification_date(array $items): string {
        $max = '';
        foreach ($items as $row) {
            $value = '';
            if (is_array($row) && isset($row['modification_date'])) {
                $value = (string) $row['modification_date'];
            } else if (is_object($row) && isset($row->modification_date)) {
                $value = (string) $row->modification_date;
            }
            if ($value !== '' && $value > $max) {
                $max = $value;
            }
        }
        return $max;
    }
}
