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
 * Keeps the catalogue caches warm without guessing what teachers will browse.
 *
 * Rather than prewarming every producer, this refreshes the entries teachers
 * have actually opened. The nightly run covers the whole search-cache keyspace
 * by default. On a site where nobody has browsed yet it seeds Open Access and
 * every producer on the landing so the first clicks are already warm.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas\task;

defined('MOODLE_INTERNAL') || die();

use mod_naas\catalogue_cache;
use mod_naas\catalogue_filters;
use mod_naas\external\proxy_naas_api;
use mod_naas\naas_client;
use mod_naas\search_cache;

/**
 * Daily refresh of the catalogue snapshot and the warmest search entries.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_catalogue extends \core\task\scheduled_task {
    /**
     * Default ceiling: every entry the search cache can hold.
     *
     * 0 in the admin setting means the same thing — refresh the whole index.
     */
    public const DEFAULT_LIMIT = search_cache::MAX_ENTRIES;
    /** Ignore entries nobody has read for this long. */
    public const MAX_AGE = 2592000;

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task_refresh_catalogue', 'naas');
    }

    /**
     * Run the refresh.
     */
    public function execute(): void {
        global $CFG;

        $config = (object) array_merge((array) get_config('naas'), (array) $CFG);
        if (empty($config->naas_endpoint)) {
            mtrace('mod_naas: no NaaS endpoint configured, skipping catalogue refresh.');
            return;
        }

        $naas = new naas_client($config);

        // Producer names and logos change rarely, but nothing else evicts them.
        \cache::make('mod_naas', \mod_naas\vocabulary_lookup::CACHE_AREA)->delete('producer_catalog_v3');
        catalogue_cache::warm($naas, $config);

        $limit = self::effective_limit();
        $entries = search_cache::refreshable($limit, self::MAX_AGE);
        if (!$entries) {
            $entries = $this->seed_entries($config, $limit);
        }

        $refreshed = 0;
        foreach ($entries as $key => $record) {
            $options = $record['query'] ?? null;
            if (!is_array($options) || !$options) {
                continue;
            }
            try {
                $raw = proxy_naas_api::sanitise_json_response(
                    $naas->request_raw('GET', proxy_naas_api::search_url($options))
                );
                if (search_cache::store($key, $options, $raw, $config) !== null) {
                    $refreshed++;
                }
            } catch (\Throwable $e) {
                mtrace('mod_naas: refresh failed for one query: ' . $e->getMessage());
            }
        }
        mtrace("mod_naas: refreshed {$refreshed} cached searches.");
    }

    /**
     * Admin cap, or the whole search-cache keyspace when unset or 0.
     *
     * @return int
     */
    public static function effective_limit(): int {
        $configured = get_config('naas', 'naas_refresh_limit');
        if ($configured === false || $configured === '' || $configured === null) {
            return self::DEFAULT_LIMIT;
        }
        $limit = (int) $configured;
        return $limit <= 0 ? search_cache::MAX_ENTRIES : $limit;
    }

    /**
     * On a cold site, warm every landing card: Open Access and each producer.
     *
     * Counts and membership come from the aggregations that `warm()` just
     * stored, so this costs no extra lookup. The NaaS producers aggregate is
     * capped at 10, so the seed set stays small.
     *
     * @param object $config
     * @param int $limit
     * @return array Index-shaped records keyed by cache key.
     */
    private function seed_entries(object $config, int $limit): array {
        $snapshot = catalogue_cache::export_for_widget();
        $producers = $snapshot['producers'] ?? [];
        $aggregations = $snapshot['search']['aggregations'] ?? [];
        if (!is_array($aggregations)) {
            $aggregations = [];
        }
        usort($producers, function ($a, $b) {
            return (int) ($b['count'] ?? 0) <=> (int) ($a['count'] ?? 0);
        });

        $queries = [
            [
                'page' => 0,
                'page_size' => catalogue_cache::SEARCH_PAGE_SIZE,
                'is_default_version' => true,
            ],
            [
                'is_public' => ['true'],
                'page' => 0,
                'page_size' => catalogue_cache::SEARCH_PAGE_SIZE,
                'is_default_version' => true,
            ],
        ];
        foreach ($producers as $producer) {
            $key = $this->producer_query_value($producer, $aggregations);
            if ($key === '') {
                continue;
            }
            $queries[] = [
                'producers' => [$key],
                'page' => 0,
                'page_size' => catalogue_cache::SEARCH_PAGE_SIZE,
                'is_default_version' => true,
            ];
        }

        $entries = [];
        foreach (array_slice($queries, 0, max(0, $limit)) as $options) {
            $options = catalogue_filters::apply($options, $config);
            $entries[search_cache::canonical_key($options, $config)] = ['query' => $options];
        }
        return $entries;
    }

    /**
     * The producer value the card will send: the aggregation key when we have it.
     *
     * @param array $producer
     * @param array $aggregations
     * @return string
     */
    private function producer_query_value(array $producer, array $aggregations): string {
        $candidates = [];
        foreach (['structure_id', 'uuid', 'uid', 'id'] as $field) {
            if (!empty($producer[$field]) && is_string($producer[$field])) {
                $candidates[] = strtolower(catalogue_cache::normalize_structure_key($producer[$field]));
            }
        }
        $buckets = $aggregations['producers']['buckets'] ?? [];
        if ($candidates && is_array($buckets)) {
            foreach ($buckets as $bucket) {
                if (!is_array($bucket) || !isset($bucket['key'])) {
                    continue;
                }
                $raw = (string) $bucket['key'];
                $needle = strtolower(catalogue_cache::normalize_structure_key($raw));
                if (in_array($needle, $candidates, true)) {
                    return $raw;
                }
            }
        }
        return (string) ($producer['structure_id'] ?? $producer['uuid'] ?? '');
    }
}
