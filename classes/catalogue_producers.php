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
 * Producer table of the catalogue snapshot: reconcile, name, look up.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

/**
 * Producer table of the catalogue snapshot: reconcile, name, look up.
 *
 * Moved out of catalogue_cache, which keeps the MUC record itself; the stored
 * rows and every lookup result are unchanged.
 *
 * @package    mod_naas
 * @copyright  2026 onwards ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue_producers {
    /** Forget a producer that has been absent from the aggregations this long. */
    public const PRODUCER_MAX_AGE = 2592000;

    /** Row fields that may carry a producer identifier, in match order. */
    private const ID_FIELDS = ['structure_id', 'uuid', 'uid', 'id'];

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
            $key = catalogue_cache::normalize_structure_key((string) $bucket['key']);
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
     * Whether a producer row matches the requested key.
     *
     * @param array $row
     * @param string $key
     * @return bool
     */
    private static function producer_matches(array $row, string $key): bool {
        $needle = strtolower(catalogue_cache::normalize_structure_key($key));
        foreach (self::ID_FIELDS as $field) {
            if (!isset($row[$field]) || !is_string($row[$field])) {
                continue;
            }
            if (strtolower(catalogue_cache::normalize_structure_key($row[$field])) === $needle) {
                return true;
            }
        }
        return false;
    }

    /**
     * Copy acronym and name from the structure vocabulary cache onto producer rows.
     *
     * Aggregation keys are hashes. The readable name is learned by get_structure
     * and stored as structurelabel_{sha1}. Folding it back here lets the next
     * page paint producer cards without another lookup, and a catalogue probe
     * cannot hand the widget a nameless copy of the same rows.
     *
     * @param array $producers
     * @return array
     */
    public static function hydrate_producer_names(array $producers): array {
        $cache = \cache::make('mod_naas', 'vocabulary_entries');
        $changed = false;
        foreach ($producers as $position => $row) {
            if (!is_array($row) || self::producer_row_has_name($row)) {
                continue;
            }
            $visuals = null;
            foreach (self::ID_FIELDS as $field) {
                $key = isset($row[$field]) ? (string) $row[$field] : '';
                if ($key === '') {
                    continue;
                }
                $visuals = self::names_from_structure_cache($cache, $key);
                if ($visuals !== null) {
                    break;
                }
            }
            if ($visuals === null) {
                continue;
            }
            if ($visuals['name'] !== '') {
                $producers[$position]['name'] = $visuals['name'];
            }
            if ($visuals['acronym'] !== '') {
                $producers[$position]['acronym'] = $visuals['acronym'];
            }
            unset($producers[$position]['needs_resolve']);
            $changed = true;
        }
        if ($changed) {
            $snapshot = catalogue_cache::get();
            if (is_array($snapshot)) {
                $snapshot['producers'] = $producers;
                catalogue_cache::store($snapshot);
            }
        }
        return $producers;
    }

    /**
     * Whether a producer row includes a display name.
     *
     * @param array $row
     * @return bool
     */
    private static function producer_row_has_name(array $row): bool {
        $opaque = '/^(?:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|[0-9a-f]{16,})$/i';
        foreach (['acronym', 'name'] as $field) {
            $text = trim((string) ($row[$field] ?? ''));
            if ($text !== '' && !preg_match($opaque, $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Read a cached structure name and acronym.
     *
     * @param \cache $cache
     * @param string $key
     * @return array{name: string, acronym: string}|null
     */
    private static function names_from_structure_cache(\cache $cache, string $key): ?array {
        $cachekey = 'structurelabel_' . sha1(strtolower(catalogue_cache::normalize_structure_key($key)));
        $raw = $cache->get($cachekey);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw);
        if (!is_object($decoded)) {
            return null;
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
        $acronym = '';
        $name = '';
        if (isset($record->acronym) && is_string($record->acronym)) {
            $acronym = trim($record->acronym);
        }
        foreach (['name', 'title', 'label'] as $field) {
            if (!isset($record->{$field}) || !is_string($record->{$field})) {
                continue;
            }
            $text = trim($record->{$field});
            if ($text !== '') {
                $name = $text;
                break;
            }
        }
        if (!self::producer_row_has_name(['name' => $name, 'acronym' => $acronym])) {
            return null;
        }
        return ['name' => $name, 'acronym' => $acronym];
    }

    /**
     * Slim producer matching a search aggregation key, or null.
     *
     * @param string $key
     * @return array|null
     */
    public static function find_producer(string $key): ?array {
        $snapshot = catalogue_cache::get();
        if (!$snapshot) {
            return null;
        }
        $config = (object) get_config('naas');
        if (($snapshot['fingerprint'] ?? '') !== catalogue_cache::fingerprint($config)) {
            return null;
        }
        if (catalogue_cache::normalize_structure_key($key) === '') {
            return null;
        }
        foreach ($snapshot['producers'] ?? [] as $row) {
            if (is_array($row) && self::producer_matches($row, $key)) {
                return $row;
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
     * Other producer ids that may already have a cached search for this click.
     *
     * A rebuild can store the list under the structure slug while the card
     * sends `managed_by:structure:{uuid}`. Both belong to the same row.
     *
     * @param array $options Search options about to be looked up.
     * @return array Copies of $options, one per alternate producer value.
     */
    public static function producer_search_aliases(array $options): array {
        $requested = $options['producers'] ?? [];
        if (!is_array($requested) || count($requested) !== 1) {
            return [];
        }
        $snapshot = catalogue_cache::get();
        $rows = is_array($snapshot['producers'] ?? null) ? $snapshot['producers'] : [];
        $variants = [];
        foreach ($requested as $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }
            foreach ($rows as $row) {
                if (!is_array($row) || !self::producer_matches($row, $value)) {
                    continue;
                }
                foreach (self::ID_FIELDS as $field) {
                    if (!empty($row[$field]) && is_string($row[$field])) {
                        $variants[$row[$field]] = true;
                    }
                }
            }
        }
        $out = [];
        foreach (array_keys($variants) as $variant) {
            if ((string) $variant === (string) $requested[0]) {
                continue;
            }
            $copy = $options;
            $copy['producers'] = [$variant];
            $out[] = $copy;
        }
        return $out;
    }
}
