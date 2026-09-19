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

defined('MOODLE_INTERNAL') || die();

/**
 * Application cache of the unfiltered catalogue (search + producers).
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
    /** Producer listing page size. */
    public const PRODUCER_PAGE_SIZE = 100;
    /** Max producer pages to pull when warming. */
    public const PRODUCER_MAX_PAGES = 5;
    /**
     * Résumé cap for the injected snapshot only.
     *
     * The full text stays in the MUC record and in what the webservice returns;
     * this bound applies to the copy embedded in the page so the HTML payload
     * does not grow with the catalogue.
     */
    public const RESUME_MAX_LENGTH = 600;
    /** Forget a producer that has been absent from the aggregations this long. */
    public const PRODUCER_MAX_AGE = 2592000;
    /** Reuse a catalogue probe this long so opening several forms in a row costs one NaaS hit. */
    public const STAMP_TTL = 60;

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
     * Widget-safe slice: aggregations, producers, and slim nugget cards.
     *
     * @return array|null
     */
    public static function export_for_widget(): ?array {
        $config = (object) get_config('naas');
        $snapshot = self::get();
        if (!$snapshot || ($snapshot['fingerprint'] ?? '') !== self::fingerprint($config)) {
            return null;
        }
        $producers = array_values($snapshot['producers'] ?? []);
        $search = self::slim_search($snapshot['search'] ?? null);
        return [
            'warmed_at' => (int) ($snapshot['warmed_at'] ?? 0),
            'max_modification_date' => (string) ($snapshot['max_modification_date'] ?? ''),
            'search' => $search,
            'producers' => $producers,
            'producers_digest' => self::producers_digest($producers),
            'stamp' => $snapshot['stamp'] ?? null,
            'labels' => vocabulary_lookup::labels_for_aggregations(
                $search['aggregations'] ?? [],
                $producers
            ),
        ];
    }

    /**
     * Return the last catalogue probe if it is still inside the short TTL.
     *
     * @param object $config
     * @return array|null
     */
    public static function cached_probe(object $config): ?array {
        $snapshot = self::get();
        if (!$snapshot || ($snapshot['fingerprint'] ?? '') !== self::fingerprint($config)) {
            return null;
        }
        $checked = (int) ($snapshot['stamp_checked_at'] ?? 0);
        if ($checked < time() - self::STAMP_TTL) {
            return null;
        }
        if (!catalogue_stamp::is_complete($snapshot['stamp'] ?? null)) {
            return null;
        }
        return self::probe_payload($snapshot, true);
    }

    /**
     * Webservice body for the landing probe.
     *
     * @param array $snapshot
     * @param bool $fromcache
     * @return array
     */
    public static function probe_payload(array $snapshot, bool $fromcache = false): array {
        $producers = array_values($snapshot['producers'] ?? []);
        $aggregations = $snapshot['search']['aggregations'] ?? [];
        if (!is_array($aggregations)) {
            $aggregations = [];
        }
        return [
            'stamp' => $snapshot['stamp'] ?? null,
            'producers_digest' => self::producers_digest($producers),
            'producers' => $producers,
            'aggregations' => $aggregations,
            'results_count' => (int) ($snapshot['search']['results_count'] ?? 0),
            'labels' => vocabulary_lookup::labels_for_aggregations($aggregations, $producers),
            'from_cache' => $fromcache,
        ];
    }

    /**
     * Fingerprint of the producer strip: identity plus Nugget count.
     *
     * The widget repaints the landing only when this moves, so an unchanged
     * catalogue costs no re-render.
     *
     * @param array $producers
     * @return string
     */
    public static function producers_digest(array $producers): string {
        $parts = [];
        foreach ($producers as $row) {
            if (!is_array($row) || !isset($row['count'])) {
                continue;
            }
            $key = (string) ($row['structure_id'] ?? $row['uuid'] ?? '');
            if ($key === '') {
                continue;
            }
            $parts[] = strtolower($key) . ':' . (int) $row['count'];
        }
        sort($parts, SORT_STRING);
        return sha1(implode('|', $parts));
    }

    /**
     * Merge the producer aggregation into the cached producer table.
     *
     * Membership on the landing is always bucket-driven, so this only keeps
     * the visuals lookup in step: buckets with no cached row get a placeholder
     * carrying the derived media URLs, and rows unseen for PRODUCER_MAX_AGE are
     * dropped. Resolving a placeholder's real name is left to the widget's
     * existing get_structure path, so this stays free of network calls.
     *
     * @param array $existing
     * @param array $aggregations
     * @return array
     */
    public static function reconcile_producers(array $existing, array $aggregations): array {
        $buckets = $aggregations['producers']['buckets'] ?? null;
        if (!is_array($buckets) || !$buckets) {
            return $existing;
        }
        $now = time();
        $rows = [];
        foreach ($existing as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        foreach ($buckets as $bucket) {
            if (!is_array($bucket) || !isset($bucket['key'])) {
                continue;
            }
            $key = self::normalize_structure_key((string) $bucket['key']);
            if ($key === '') {
                continue;
            }
            $count = (int) ($bucket['docCount'] ?? $bucket['doc_count'] ?? 0);
            $found = false;
            foreach ($rows as $position => $row) {
                if (!self::producer_matches($row, $key)) {
                    continue;
                }
                $rows[$position]['count'] = $count;
                $rows[$position]['seen_at'] = $now;
                $found = true;
                break;
            }
            if (!$found) {
                $rows[] = self::placeholder_producer($key, $count, $now);
            }
        }
        $cutoff = $now - self::PRODUCER_MAX_AGE;
        return array_values(array_filter($rows, function ($row) use ($cutoff) {
            return (int) ($row['seen_at'] ?? PHP_INT_MAX) >= $cutoff;
        }));
    }

    /**
     * A producer known only by its aggregation key, pending name resolution.
     *
     * @param string $key
     * @param int $count
     * @param int $now
     * @return array
     */
    private static function placeholder_producer(string $key, int $count, int $now): array {
        $endpoint = rtrim((string) get_config('naas', 'naas_endpoint'), '/');
        $mediaid = rawurlencode($key);
        return [
            'structure_id' => $key,
            'uuid' => $key,
            'name' => '',
            'acronym' => '',
            'structure_thumbnail_url' => $endpoint === ''
                ? '' : $endpoint . '/thumbnails/structure/' . $mediaid . '/thumbnail',
            'structure_banner_url' => $endpoint === ''
                ? '' : $endpoint . '/thumbnails/structure/' . $mediaid . '/banner',
            'count' => $count,
            'seen_at' => $now,
            'needs_resolve' => true,
        ];
    }

    /**
     * @param array $row
     * @param string $key
     * @return bool
     */
    private static function producer_matches(array $row, string $key): bool {
        $needle = strtolower($key);
        foreach (['structure_id', 'uuid', 'uid', 'id'] as $field) {
            if (!isset($row[$field]) || !is_string($row[$field])) {
                continue;
            }
            if (strtolower(self::normalize_structure_key($row[$field])) === $needle) {
                return true;
            }
        }
        return false;
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
        $existing['producers'] = self::reconcile_producers(
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
        $existing['producers'] = self::reconcile_producers(
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
        return self::producers_digest($existing['producers']);
    }

    /**
     * Pull landing search + producer records after credentials are confirmed.
     *
     * Failures are swallowed so a slow/empty producer list cannot fail Test connection.
     *
     * @param naas_client $naas
     * @param object $config
     */
    public static function warm(naas_client $naas, object $config): void {
        try {
            $searchjson = self::request_landing_search($naas, $config);
            $search = naas_payload::decode_search($searchjson);
            $producers = self::fetch_producers($naas);
            if ($search === null && !$producers) {
                return;
            }
            self::store([
                'fingerprint' => self::fingerprint($config),
                'warmed_at' => time(),
                'search' => $search ?? ['items' => [], 'aggregations' => [], 'results_count' => 0],
                'producers' => self::reconcile_producers($producers, $search['aggregations'] ?? []),
                'max_modification_date' => self::max_modification_date($search['items'] ?? []),
            ]);
        } catch (\Throwable $e) {
            debugging('NAAS catalogue cache warm failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Slim producer matching a search aggregation key, or null.
     *
     * @param string $key
     * @return array|null
     */
    public static function find_producer(string $key): ?array {
        $snapshot = self::get();
        if (!$snapshot) {
            return null;
        }
        $config = (object) get_config('naas');
        if (($snapshot['fingerprint'] ?? '') !== self::fingerprint($config)) {
            return null;
        }
        $needle = strtolower(self::normalize_structure_key($key));
        if ($needle === '') {
            return null;
        }
        foreach ($snapshot['producers'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach (['structure_id', 'uuid', 'uid', 'id'] as $field) {
                if (!isset($row[$field]) || !is_string($row[$field])) {
                    continue;
                }
                if (strtolower(self::normalize_structure_key($row[$field])) === $needle) {
                    return $row;
                }
            }
        }
        return null;
    }

    /**
     * Wrap a cached producer as a get_structure JSON payload, or null when unnamed.
     *
     * @param string $key
     * @return string|null
     */
    public static function structure_json(string $key): ?string {
        $producer = self::find_producer($key);
        if ($producer === null) {
            return null;
        }
        $opaque = '/^(?:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|[0-9a-f]{32,64})$/i';
        $named = false;
        foreach (['acronym', 'name'] as $field) {
            $text = trim((string) ($producer[$field] ?? ''));
            if ($text !== '' && !preg_match($opaque, $text)) {
                $named = true;
                break;
            }
        }
        if (!$named) {
            return null;
        }
        return json_encode(['payload' => $producer], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
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
     * @param naas_client $naas
     * @param object $config
     * @return string
     */
    private static function request_landing_search(naas_client $naas, object $config): string {
        $params = [
            'is_default_version' => true,
            'page_size' => self::SEARCH_PAGE_SIZE,
        ];
        if (!empty($config->naas_filter)) {
            $params['nql'] = urlencode($config->naas_filter);
        }
        $url = '/nuggets/search?' . http_build_query($params, '', '&');
        $url = preg_replace('/\%5B\d+\%5D/', '', $url);
        return $naas->request_raw('GET', $url);
    }

    /**
     * @param naas_client $naas
     * @return array
     */
    private static function fetch_producers(naas_client $naas): array {
        $items = self::fetch_structure_pages($naas, '/structures', ['is_producer' => 'true']);
        if (!$items) {
            $items = self::fetch_structure_pages($naas, '/structures/search', ['is_producer' => 'true']);
        }
        $slim = [];
        foreach ($items as $item) {
            $row = self::slim_producer($item);
            if ($row !== null) {
                $slim[] = $row;
            }
        }
        return $slim;
    }

    /**
     * @param naas_client $naas
     * @param string $path
     * @param array $query
     * @return array
     */
    private static function fetch_structure_pages(naas_client $naas, string $path, array $query): array {
        $items = [];
        $query['page_size'] = self::PRODUCER_PAGE_SIZE;
        for ($page = 0; $page < self::PRODUCER_MAX_PAGES; $page++) {
            try {
                $raw = $naas->request_raw('GET', $path, null, $query + ['page' => $page]);
            } catch (\moodle_exception $e) {
                debugging('NAAS catalogue cache structures: ' . $e->errorcode, DEBUG_DEVELOPER);
                break;
            }
            $decoded = json_decode($raw);
            $items = array_merge($items, self::structure_list_items($decoded));
            if ($page >= self::structure_list_page_count($decoded) - 1) {
                break;
            }
        }
        return $items;
    }

    /**
     * @param mixed $decoded
     * @return array
     */
    private static function structure_list_items($decoded): array {
        $root = naas_payload::unwrap($decoded);
        if (is_object($root)) {
            $root = json_decode(json_encode($root), true);
        }
        if (is_array($root)) {
            if (isset($root['items']) && is_array($root['items'])) {
                return $root['items'];
            }
            if (isset($root['entries']) && is_array($root['entries'])) {
                return $root['entries'];
            }
            if (isset($root['results']) && is_array($root['results'])) {
                return $root['results'];
            }
            return $root;
        }
        return [];
    }

    /**
     * @param mixed $decoded
     * @return int
     */
    private static function structure_list_page_count($decoded): int {
        $root = naas_payload::unwrap($decoded);
        if (is_object($root)) {
            $root = json_decode(json_encode($root), true);
        }
        if (is_array($root) && isset($root['pages']) && is_numeric($root['pages'])) {
            return max(1, (int) $root['pages']);
        }
        return 1;
    }

    /**
     * @param mixed $raw
     * @return array|null
     */
    private static function slim_producer($raw): ?array {
        $item = is_object($raw) ? $raw : (is_array($raw) ? (object) $raw : null);
        if (!$item) {
            return null;
        }
        $props = null;
        if (isset($item->properties) && is_object($item->properties)) {
            $props = $item->properties;
        } elseif (isset($item->properties) && is_array($item->properties)) {
            $props = (object) $item->properties;
        }
        $acronym = self::first_string([
            $item->acronym ?? null,
            $props->{'structure:acronym'} ?? null,
        ]);
        $name = self::first_string([
            $item->name ?? null,
            $item->title ?? null,
            $props->{'dc:title'} ?? null,
            $props->{'structure:name'} ?? null,
        ]);
        $structureid = self::first_string([
            $item->structure_id ?? null,
            $item->structureId ?? null,
            $props->{'structure:structure_id'} ?? null,
        ]);
        $uuid = self::first_string([
            $item->uuid ?? null,
            $item->uid ?? null,
            $item->id ?? null,
        ]);
        if ($structureid === '' && $uuid === '') {
            return null;
        }
        $endpoint = rtrim((string) get_config('naas', 'naas_endpoint'), '/');
        $mediaid = rawurlencode($structureid !== '' ? $structureid : $uuid);
        $thumbnail = self::first_string([
            $item->structure_thumbnail_url ?? null,
            $item->structureThumbnailUrl ?? null,
        ]);
        $banner = self::first_string([
            $item->structure_banner_url ?? null,
            $item->structureBannerUrl ?? null,
        ]);
        if ($thumbnail === '' && $endpoint !== '') {
            $thumbnail = $endpoint . '/thumbnails/structure/' . $mediaid . '/thumbnail';
        }
        if ($banner === '' && $endpoint !== '') {
            $banner = $endpoint . '/thumbnails/structure/' . $mediaid . '/banner';
        }
        return [
            'structure_id' => $structureid !== '' ? $structureid : $uuid,
            'uuid' => $uuid !== '' ? $uuid : $structureid,
            'name' => $name,
            'acronym' => $acronym !== '' ? $acronym : $name,
            'structure_thumbnail_url' => $thumbnail,
            'structure_banner_url' => $banner,
        ];
    }

    /**
     * @param array $candidates
     * @return string
     */
    private static function first_string(array $candidates): string {
        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }
        return '';
    }

    /**
     * Drop HTML-heavy fields before injecting search hits into the page.
     *
     * @param mixed $search
     * @return array
     */
    private static function slim_search($search): array {
        if (!is_array($search)) {
            return ['items' => [], 'aggregations' => [], 'results_count' => 0, 'vocabulary' => []];
        }
        $items = [];
        foreach ($search['items'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = [
                'nugget_id' => (string) ($row['nugget_id'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'resume' => \core_text::substr((string) ($row['resume'] ?? ''), 0, self::RESUME_MAX_LENGTH),
                'nugget_thumbnail_url' => (string) ($row['nugget_thumbnail_url'] ?? ''),
                'modification_date' => (string) ($row['modification_date'] ?? ''),
                'publication_date' => (string) ($row['publication_date'] ?? ''),
                'version_id' => (string) ($row['version_id'] ?? ''),
                'is_public' => $row['is_public'] ?? null,
                'authors' => $row['authors'] ?? [],
                'domains' => $row['domains'] ?? [],
                'producers' => $row['producers'] ?? ($row['managing_structures'] ?? []),
                'license' => $row['license'] ?? null,
                'duration' => $row['duration'] ?? null,
                'language' => $row['language'] ?? '',
                'level' => $row['level'] ?? '',
                'tags' => $row['tags'] ?? [],
            ];
        }
        return [
            'items' => $items,
            'aggregations' => is_array($search['aggregations'] ?? null) ? $search['aggregations'] : [],
            'results_count' => (int) ($search['results_count'] ?? 0),
            'vocabulary' => vocabulary_lookup::collect($items),
        ];
    }

    /**
     * Newest modification_date among cached search hits (ISO-8601 comparable strings).
     *
     * @param array $items
     * @return string
     */
    private static function max_modification_date(array $items): string {
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
