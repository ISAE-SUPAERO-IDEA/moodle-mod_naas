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
 * Nightly revalidation of the cached Nugget documents.
 *
 * The activity form shows the selected Nugget from the `nugget_documents`
 * cache. Instead of one `/nuggets/{id}/default_version` call per form, the
 * nightly task refreshes the Nuggets of every activity of the site with
 * `GET /nuggets?nugget_ids=…&is_default_version=true`: each item has the same
 * shape as the single-document answer (same NaaS query, same serializer).
 *
 * NaaS instances older than the `nugget_ids` filter ignore it and return any
 * default version. That is detected (an item nobody asked for, or more
 * results than ids) and the task then falls back, for a day, to the single
 * call, limited to the documents already in the cache.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

use mod_naas\external\proxy_naas_api;

/**
 * Refreshes the `nugget_documents` cache for the Nuggets used by activities.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nugget_revalidator {
    /** Ids per request: keeps the URL far below the usual 8 KB limits. */
    public const BATCH_SIZE = 50;

    /** Cache key of the "this NaaS ignores nugget_ids" marker. */
    public const UNSUPPORTED_KEY = 'nugget_ids_unsupported';

    /** How long to keep using single calls once the filter was found missing. */
    public const UNSUPPORTED_TTL = 86400;

    /** Same rule as the web services apply to Nugget ids before they reach a URL. */
    private const ID_PATTERN = '/^[a-zA-Z0-9_\-]{1,128}$/';

    /**
     * Distinct, well-formed Nugget ids of the activities of the site.
     *
     * @return string[]
     */
    public static function site_nugget_ids(): array {
        global $DB;
        $ids = $DB->get_fieldset_sql("SELECT DISTINCT nugget_id FROM {naas} WHERE nugget_id <> ''");
        $valid = array_values(array_filter(array_map('strval', $ids), function (string $id): bool {
            return (bool) preg_match(self::ID_PATTERN, $id);
        }));
        sort($valid);
        return $valid;
    }

    /**
     * Refresh the cached documents of the site's Nuggets.
     *
     * @param naas_client $naas
     * @param object $config Merged plugin and site configuration.
     * @param string[]|null $ids Nugget ids; the site's activities when null.
     * @return array Counts: mode ('batch' or 'single'), stored, missing, failed.
     */
    public static function run(naas_client $naas, object $config, ?array $ids = null): array {
        $ids = $ids ?? self::site_nugget_ids();
        $result = ['mode' => 'batch', 'stored' => 0, 'missing' => 0, 'failed' => 0];
        if (!$ids) {
            return $result;
        }

        if (!self::batch_unsupported()) {
            $done = [];
            foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
                try {
                    $items = self::fetch_batch($naas, $chunk);
                } catch (\Throwable $e) {
                    // NaaS down or refusing: keep these cached copies, the next run retries.
                    $result['failed'] += count($chunk);
                    $done += array_fill_keys($chunk, true);
                    continue;
                }
                if ($items === null) {
                    self::mark_unsupported();
                    break;
                }
                foreach ($chunk as $id) {
                    if (isset($items[$id]) && nugget_cache::store($id, (string) json_encode($items[$id]), $config)) {
                        $result['stored']++;
                    } else {
                        // Gone from NaaS (or no default version): the form must ask NaaS again.
                        nugget_cache::purge($id, $config);
                        $result['missing']++;
                    }
                    $done[$id] = true;
                }
            }
            if (count($done) === count($ids)) {
                return $result;
            }
            $ids = array_values(array_diff($ids, array_keys($done)));
        }

        $result['mode'] = 'single';
        foreach ($ids as $id) {
            // Without the batch filter, only refresh what a form actually opened recently.
            if (nugget_cache::get($id, $config) === null) {
                continue;
            }
            try {
                $json = proxy_naas_api::sanitise_json_response(
                    $naas->request_raw('GET', '/nuggets/' . $id . '/default_version')
                );
                if (nugget_cache::store($id, $json, $config)) {
                    $result['stored']++;
                } else {
                    nugget_cache::purge($id, $config);
                    $result['missing']++;
                }
            } catch (\Throwable $e) {
                // Keep the cached copy: the widget can still show it, and the next run retries.
                $result['failed']++;
            }
        }
        return $result;
    }

    /**
     * Nightly entry point: refresh the site's documents and say what happened.
     *
     * NaaS failures are counted by run(), never thrown.
     *
     * @param naas_client $naas
     * @param object $config Merged plugin and site configuration.
     * @param bool $trace Print a summary for the cron log when something was refreshed.
     * @return array The counts returned by run().
     */
    public static function refresh_documents(naas_client $naas, object $config, bool $trace): array {
        $result = self::run($naas, $config);
        if ($trace && $result['stored'] + $result['missing'] + $result['failed'] > 0) {
            mtrace("mod_naas: revalidated {$result['stored']} Nugget documents ({$result['mode']}), "
                . "{$result['missing']} missing, {$result['failed']} failed.");
        }
        return $result;
    }

    /**
     * One batch request, keyed by Nugget id; null when NaaS ignored the filter.
     *
     * @param naas_client $naas
     * @param string[] $chunk
     * @return array<string, array>|null
     */
    private static function fetch_batch(naas_client $naas, array $chunk): ?array {
        $json = proxy_naas_api::sanitise_json_response($naas->request_raw('GET', '/nuggets', null, [
            'nugget_ids' => $chunk,
            'is_default_version' => 'true',
            'page_size' => count($chunk),
        ]));
        $body = naas_payload::decode($json);
        if ($body === null || !isset($body['items']) || !is_array($body['items'])) {
            return null;
        }
        if ((int) ($body['results_count'] ?? 0) > count($chunk)) {
            return null;
        }
        $wanted = array_flip($chunk);
        $items = [];
        foreach ($body['items'] as $item) {
            $id = is_array($item) ? (string) ($item['nugget_id'] ?? '') : '';
            if (!isset($wanted[$id])) {
                return null;
            }
            $items[$id] = $item;
        }
        return $items;
    }

    /**
     * Whether a recent run found that NaaS ignores `nugget_ids`.
     *
     * @return bool
     */
    public static function batch_unsupported(): bool {
        $until = \cache::make('mod_naas', nugget_cache::CACHE_AREA)->get(self::UNSUPPORTED_KEY);
        return is_numeric($until) && (int) $until > time();
    }

    /**
     * Remember for a day that NaaS ignores `nugget_ids`.
     */
    private static function mark_unsupported(): void {
        $cache = \cache::make('mod_naas', nugget_cache::CACHE_AREA);
        $cache->set(self::UNSUPPORTED_KEY, (string) (time() + self::UNSUPPORTED_TTL));
    }
}
