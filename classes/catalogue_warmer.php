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
 * Rebuild the catalogue snapshot from NaaS: landing search plus the producer listing.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

/**
 * Rebuild the catalogue snapshot from NaaS: landing search plus the producer listing.
 *
 * Moved out of catalogue_cache, which keeps the MUC record itself; the requests
 * sent to NaaS and the stored snapshot are unchanged.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue_warmer {
    /** Producer listing page size. */
    public const PRODUCER_PAGE_SIZE = 100;
    /** Max producer pages to pull when warming. */
    public const PRODUCER_MAX_PAGES = 5;

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
            $search = $search ?? ['items' => [], 'aggregations' => [], 'results_count' => 0];
            $stamp = catalogue_stamp::from_search($search);
            catalogue_cache::store([
                'fingerprint' => catalogue_cache::fingerprint($config),
                'warmed_at' => time(),
                'search' => $search,
                'producers' => catalogue_producers::reconcile_producers($producers, $search['aggregations'] ?? []),
                'max_modification_date' => catalogue_cache::max_modification_date($search['items'] ?? []),
                'stamp' => $stamp,
                // A rebuild just talked to NaaS. The next page must not do it again
                // before the teacher has even clicked a producer.
                'stamp_checked_at' => catalogue_stamp::is_complete($stamp) ? time() : 0,
            ]);
        } catch (\Throwable $e) {
            debugging('NAAS catalogue cache warm failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Fetch the landing search JSON with the site licence filter.
     *
     * @param naas_client $naas
     * @param object $config
     * @return string
     */
    private static function request_landing_search(naas_client $naas, object $config): string {
        $params = catalogue_filters::apply([
            'is_default_version' => true,
            'page_size' => catalogue_cache::SEARCH_PAGE_SIZE,
        ], $config);
        $url = '/nuggets/search?' . http_build_query($params, '', '&');
        $url = preg_replace('/\%5B\d+\%5D/', '', $url);
        return catalogue_filters::constrain_search_json($naas->request_raw('GET', $url), $config);
    }

    /**
     * Load every producer the landing page needs.
     *
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
     * Walk paginated structure list responses.
     *
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
     * Extract structure rows from one list response.
     *
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
     * How many pages a structure list response spans.
     *
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
     * Keep the producer fields the picker stores.
     *
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
        } else if (isset($item->properties) && is_array($item->properties)) {
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
            'acronym' => $acronym,
            'structure_thumbnail_url' => $thumbnail,
            'structure_banner_url' => $banner,
        ];
    }

    /**
     * First non-empty string among the candidates.
     *
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
}
