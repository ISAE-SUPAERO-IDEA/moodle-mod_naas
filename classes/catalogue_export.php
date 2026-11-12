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
 * Widget-facing views of the catalogue snapshot: the injected page copy and the landing probe.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

/**
 * Widget-facing views of the catalogue snapshot: the injected page copy and the landing probe.
 *
 * Moved out of catalogue_cache, which keeps the MUC record itself; the payloads
 * handed to window.NAAS and to the probe web service are unchanged.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue_export {
    /**
     * Résumé cap for the injected snapshot only.
     *
     * The full text stays in the MUC record and in what the webservice returns;
     * this bound applies to the copy embedded in the page so the HTML payload
     * does not grow with the catalogue.
     */
    public const RESUME_MAX_LENGTH = 600;
    /** Reuse a catalogue probe this long so opening several forms in a row costs one NaaS hit. */
    public const STAMP_TTL = 60;

    /**
     * Widget-safe slice: aggregations, producers, and slim nugget cards.
     *
     * @return array|null
     */
    public static function export_for_widget(): ?array {
        $config = (object) get_config('naas');
        $snapshot = catalogue_cache::get();
        if (!$snapshot || ($snapshot['fingerprint'] ?? '') !== catalogue_cache::fingerprint($config)) {
            return null;
        }
        $producers = catalogue_producers::hydrate_producer_names(array_values($snapshot['producers'] ?? []));
        $search = self::slim_search($snapshot['search'] ?? null);
        return [
            'warmed_at' => (int) ($snapshot['warmed_at'] ?? 0),
            'max_modification_date' => (string) ($snapshot['max_modification_date'] ?? ''),
            'search' => $search,
            'producers' => $producers,
            'producers_digest' => catalogue_producers::producers_digest($producers),
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
        $snapshot = catalogue_cache::get();
        if (!$snapshot || ($snapshot['fingerprint'] ?? '') !== catalogue_cache::fingerprint($config)) {
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
        $producers = catalogue_producers::hydrate_producer_names(array_values($snapshot['producers'] ?? []));
        $aggregations = $snapshot['search']['aggregations'] ?? [];
        if (!is_array($aggregations)) {
            $aggregations = [];
        }
        return [
            'stamp' => $snapshot['stamp'] ?? null,
            'producers_digest' => catalogue_producers::producers_digest($producers),
            'producers' => $producers,
            'aggregations' => $aggregations,
            'results_count' => (int) ($snapshot['search']['results_count'] ?? 0),
            'labels' => vocabulary_lookup::labels_for_aggregations($aggregations, $producers),
            'from_cache' => $fromcache,
        ];
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
                // About sheet: the licence rights and the partner (live search results carry them too).
                'access_licences' => self::slim_licences($row['access_licences'] ?? []),
                'partner' => [
                    'name' => (string) ($row['partner']['name'] ?? ''),
                    'url' => (string) ($row['partner']['url'] ?? ''),
                ],
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
     * Access licences reduced to what the About sheet reads (no creator, no dates).
     *
     * @param mixed $licences A Nugget's access_licences
     * @return array
     */
    private static function slim_licences($licences): array {
        if (!is_array($licences)) {
            return [];
        }
        $slim = [];
        foreach ($licences as $licence) {
            if (!is_array($licence)) {
                continue;
            }
            $slim[] = [
                'structure_id' => (string) ($licence['structure_id'] ?? ''),
                'is_open_access' => !empty($licence['is_open_access']),
                'rights' => array_values(array_filter((array) ($licence['rights'] ?? []), 'is_string')),
            ];
        }
        return $slim;
    }
}
