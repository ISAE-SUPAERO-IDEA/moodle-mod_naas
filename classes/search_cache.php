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
 * One cache entry per canonical Nugget search, so a producer section or a
 * filtered list paints without a NaaS round-trip.
 *
 * Freshness is not a clock. Each entry carries the triple
 * (results_count, id_digest, max_modification_date); the widget paints the
 * cached page, asks for a live one in the background, and only repaints when
 * the triple moved. Because a query is always compared against itself at the
 * same page size, this detects additions, deletions and edits without needing
 * the NaaS API to support sorting.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

/**
 * Per-query cache of Nugget search responses.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_cache {
    /** MUC area name from db/caches.php. */
    public const CACHE_AREA = 'search_results';
    /** Key holding the bounded most-recently-used index. */
    public const INDEX_KEY = 'index';
    /** Hard bound on the keyspace; MUC application stores have no LRU. */
    public const MAX_ENTRIES = 64;
    /** Do not rewrite the index on every read; once an hour per key is enough. */
    public const TOUCH_INTERVAL = 3600;

    /**
     * Stable identity of a search, independent of key or value ordering.
     *
     * Blank and empty values are dropped so `['tags' => []]` and a missing
     * `tags` describe the same query. `page` is always present so that an
     * absent page and page 0 collapse onto one entry.
     *
     * @param array $options Search options after defaults have been applied.
     * @return string Canonical JSON.
     */
    public static function canonical_query(array $options): string {
        $normalised = [];
        foreach ($options as $name => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            if (is_array($value)) {
                $copy = [];
                foreach ($value as $entry) {
                    if (is_scalar($entry) && (string) $entry !== '') {
                        $copy[] = (string) $entry;
                    }
                }
                if (!$copy) {
                    continue;
                }
                sort($copy, SORT_STRING);
                $normalised[$name] = $copy;
            } else if (is_bool($value)) {
                $normalised[$name] = $value ? 'true' : 'false';
            } else if (is_scalar($value)) {
                $normalised[$name] = (string) $value;
            }
        }
        $normalised['page'] = (string) (int) ($options['page'] ?? 0);
        ksort($normalised, SORT_STRING);
        return json_encode($normalised, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Cache key for a query under the current NaaS connection.
     *
     * @param array $options
     * @param object $config
     * @return string
     */
    public static function canonical_key(array $options, object $config): string {
        return sha1(catalogue_cache::fingerprint($config) . '|' . self::canonical_query($options));
    }

    /**
     * Read an entry, or null on a miss or a connection change.
     *
     * @param string $key
     * @param object $config
     * @return array|null
     */
    public static function get(string $key, object $config): ?array {
        $cache = \cache::make('mod_naas', self::CACHE_AREA);
        $raw = $cache->get($key);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $entry = json_decode($raw, true);
        if (!is_array($entry) || ($entry['fingerprint'] ?? '') !== catalogue_cache::fingerprint($config)) {
            return null;
        }
        return $entry;
    }

    /**
     * Decode a live search response, store it, and return the new entry.
     *
     * A response that cannot be decoded is not cached; the caller still hands
     * the raw body back to the browser.
     *
     * @param string $key
     * @param array $options Canonical options, kept so the refresh task can replay the query.
     * @param string $json Sanitised NaaS response.
     * @param object $config
     * @return array|null
     */
    public static function store(string $key, array $options, string $json, object $config): ?array {
        $search = naas_payload::decode_search($json);
        if ($search === null) {
            return null;
        }
        $entry = [
            'fingerprint' => catalogue_cache::fingerprint($config),
            'cached_at' => time(),
            'results_count' => (int) $search['results_count'],
            'max_modification_date' => self::max_modification_date($search['items']),
            'id_digest' => self::id_digest($search['items']),
            'search' => $search,
        ];
        $cache = \cache::make('mod_naas', self::CACHE_AREA);
        $cache->set($key, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        self::index_put($key, $options, $entry['cached_at']);
        return $entry;
    }

    /**
     * The freshness triple the widget compares.
     *
     * @param array $entry
     * @return array
     */
    public static function digest(array $entry): array {
        return [
            'results_count' => (int) ($entry['results_count'] ?? 0),
            'id_digest' => (string) ($entry['id_digest'] ?? ''),
            'max_modification_date' => (string) ($entry['max_modification_date'] ?? ''),
        ];
    }

    /**
     * Build the webservice response body for an entry.
     *
     * The vocabulary side table is resolved at read time, not write time, so
     * an entry stored before its authors were cached still gains their names
     * once another page has warmed them.
     *
     * @param array $entry
     * @param bool $hit Whether this came from the cache or from a live call.
     * @return string JSON
     */
    public static function response(array $entry, bool $hit): string {
        $search = $entry['search'] ?? ['items' => [], 'aggregations' => [], 'results_count' => 0];
        $body = [
            'items' => $search['items'] ?? [],
            'aggregations' => $search['aggregations'] ?? [],
            'results_count' => (int) ($search['results_count'] ?? 0),
            'vocabulary' => vocabulary_lookup::collect($search['items'] ?? []),
            '_cache' => [
                'hit' => $hit,
                'cached_at' => (int) ($entry['cached_at'] ?? 0),
                'digest' => self::digest($entry),
            ],
        ];
        return json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Note that a key was read, so the refresh task can rank by real usage.
     *
     * @param string $key
     */
    public static function touch(string $key): void {
        $index = self::index();
        if (!isset($index[$key])) {
            return;
        }
        $now = time();
        if ($now - (int) ($index[$key]['last_read_at'] ?? 0) < self::TOUCH_INTERVAL) {
            return;
        }
        $index[$key]['last_read_at'] = $now;
        self::index_store($index);
    }

    /**
     * The most-recently-used index: key => {query, cached_at, last_read_at}.
     *
     * @return array
     */
    public static function index(): array {
        $raw = \cache::make('mod_naas', self::CACHE_AREA)->get(self::INDEX_KEY);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $index = json_decode($raw, true);
        return is_array($index) ? $index : [];
    }

    /**
     * Entries worth refreshing, most recently read first.
     *
     * @param int $limit
     * @param int $maxage Skip anything unread for longer than this.
     * @return array Key => index record.
     */
    public static function refreshable(int $limit, int $maxage): array {
        $index = self::index();
        $cutoff = time() - $maxage;
        $index = array_filter($index, function ($record) use ($cutoff) {
            return (int) ($record['last_read_at'] ?? 0) >= $cutoff;
        });
        uasort($index, function ($a, $b) {
            return (int) ($b['last_read_at'] ?? 0) <=> (int) ($a['last_read_at'] ?? 0);
        });
        return array_slice($index, 0, max(0, $limit), true);
    }

    /**
     * Forget every cached query (credential change, admin purge).
     */
    public static function purge(): void {
        $cache = \cache::make('mod_naas', self::CACHE_AREA);
        foreach (array_keys(self::index()) as $key) {
            $cache->delete($key);
        }
        $cache->delete(self::INDEX_KEY);
    }

    /**
     * Record a key in the index and evict the least recently used overflow.
     *
     * @param string $key
     * @param array $options
     * @param int $now
     */
    private static function index_put(string $key, array $options, int $now): void {
        $index = self::index();
        $index[$key] = [
            'query' => $options,
            'cached_at' => $now,
            'last_read_at' => (int) ($index[$key]['last_read_at'] ?? $now),
        ];
        if (count($index) > self::MAX_ENTRIES) {
            uasort($index, function ($a, $b) {
                return (int) ($b['last_read_at'] ?? 0) <=> (int) ($a['last_read_at'] ?? 0);
            });
            $cache = \cache::make('mod_naas', self::CACHE_AREA);
            foreach (array_slice($index, self::MAX_ENTRIES, null, true) as $stalekey => $unused) {
                $cache->delete($stalekey);
                unset($index[$stalekey]);
            }
        }
        self::index_store($index);
    }

    /**
     * @param array $index
     */
    private static function index_store(array $index): void {
        \cache::make('mod_naas', self::CACHE_AREA)->set(
            self::INDEX_KEY,
            json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * sha1 of the ordered nugget id list — catches a deletion or a reshuffle
     * that leaves results_count untouched.
     *
     * @param array $items
     * @return string
     */
    public static function id_digest(array $items): string {
        $ids = [];
        foreach ($items as $row) {
            if (is_array($row) && isset($row['nugget_id'])) {
                $ids[] = (string) $row['nugget_id'];
            }
        }
        return sha1(implode('|', $ids));
    }

    /**
     * Newest modification_date on the page (ISO-8601 strings compare lexically).
     *
     * @param array $items
     * @return string
     */
    public static function max_modification_date(array $items): string {
        $max = '';
        foreach ($items as $row) {
            if (is_array($row) && isset($row['modification_date'])) {
                $value = (string) $row['modification_date'];
                if ($value > $max) {
                    $max = $value;
                }
            }
        }
        return $max;
    }

}
