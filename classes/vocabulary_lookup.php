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
 * Denormalises author and domain names onto cached Nugget cards.
 *
 * Reads the vocabulary_entries MUC only. A cold entry is simply omitted, which
 * degrades to the pre-existing behaviour: the widget shows the raw key until
 * its own lookup resolves it. This never issues an HTTP request, so building a
 * cache entry can never block on the NaaS API.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the persons/domains side table shipped alongside cached search hits.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vocabulary_lookup {
    /** MUC area shared with get_person / get_domain in the proxy. */
    public const CACHE_AREA = 'vocabulary_entries';
    /** Author biographies are the only unbounded field; cap them. */
    public const BIO_MAX_LENGTH = 1200;

    /**
     * Resolve every author and domain key used by the given items.
     *
     * Keys are deduplicated across items, so a person appearing on nine cards
     * is stored once. Items keep their raw key arrays; the widget joins them
     * against this table.
     *
     * @param array $items Search hits, as associative arrays.
     * @return array{persons: array, domains: array}
     */
    public static function collect(array $items): array {
        $personkeys = [];
        $domainkeys = [];
        foreach ($items as $item) {
            foreach (self::key_list($item, 'authors') as $key) {
                $personkeys[$key] = true;
            }
            foreach (self::key_list($item, 'domains') as $key) {
                $domainkeys[$key] = true;
            }
        }
        return [
            'persons' => self::resolve(array_keys($personkeys), 'person_', 'normalise_person'),
            'domains' => self::resolve(array_keys($domainkeys), 'domain_', 'normalise_domain'),
        ];
    }

    /**
     * Read a batch of keys from the MUC and normalise the hits.
     *
     * @param array $keys
     * @param string $prefix MUC key prefix used by the proxy endpoints.
     * @param string $normaliser Method name on this class.
     * @return array Keyed by the original (unprefixed) key.
     */
    private static function resolve(array $keys, string $prefix, string $normaliser): array {
        if (!$keys) {
            return [];
        }
        $cache = \cache::make('mod_naas', self::CACHE_AREA);
        $prefixed = [];
        $lookupkeys = [];
        foreach ($keys as $key) {
            $lookup = $prefix === 'person_'
                ? \mod_naas\catalogue_cache::normalize_person_key((string) $key)
                : (string) $key;
            $lookupkeys[$key] = $lookup;
            $prefixed[] = $prefix . $lookup;
        }
        $raw = $cache->get_many(array_values(array_unique($prefixed)));
        $resolved = [];
        foreach ($keys as $key) {
            $lookup = $lookupkeys[$key];
            $value = $raw[$prefix . $lookup] ?? false;
            if (!is_string($value) || $value === '') {
                continue;
            }
            $record = naas_payload::unwrap(json_decode($value, true));
            if (!is_array($record)) {
                continue;
            }
            $entry = self::{$normaliser}($record, $key);
            if ($entry !== null) {
                $resolved[$key] = $entry;
            }
        }
        return $resolved;
    }

    /**
     * Resolve facet bucket keys to display labels from the warm MUC.
     *
     * Cold keys are omitted, never fetched. The widget keeps its per-key
     * fallback for anything missing here.
     *
     * @param array $aggregations Search aggregations keyed by facet name.
     * @param array $producers Cached producer rows (acronym / name / ids).
     * @return array{producers: array, related_domains: array, authors: array}
     */
    public static function labels_for_aggregations(array $aggregations, array $producers = []): array {
        $table = self::collect([
            [
                'authors' => self::bucket_keys($aggregations, 'authors'),
                'domains' => array_values(array_unique(array_merge(
                    self::bucket_keys($aggregations, 'related_domains'),
                    self::bucket_keys($aggregations, 'domains')
                ))),
            ],
        ]);
        $labels = [
            'producers' => self::producer_labels(self::bucket_keys($aggregations, 'producers'), $producers),
            'related_domains' => [],
            'authors' => [],
        ];
        foreach ($table['domains'] as $key => $row) {
            if (is_array($row) && !empty($row['label'])) {
                $labels['related_domains'][$key] = (string) $row['label'];
            }
        }
        foreach ($table['persons'] as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['firstname'] ?? '') . ' ' . (string) ($row['lastname'] ?? ''));
            if ($name === '') {
                continue;
            }
            $labels['authors'][$key] = \core_text::strtoupper($name);
        }
        return $labels;
    }

    /**
     * Flatten a person record to the fields the card and About modal render.
     *
     * @param array $record
     * @param string $key
     * @return array|null Null when the record carries no usable name.
     */
    private static function normalise_person(array $record, string $key): ?array {
        $first = self::first_string($record, ['firstname', 'first_name', 'firstName']);
        $last = self::first_string($record, ['lastname', 'last_name', 'lastName']);
        if ($first === '' && $last === '') {
            $full = self::first_string($record, ['name', 'fullname', 'full_name']);
            if ($full === '') {
                return null;
            }
            $last = $full;
        }
        $bio = self::first_string($record, ['bio', 'biography', 'description']);
        return [
            'email' => self::first_string($record, ['email', 'mail']) ?: $key,
            'firstname' => $first,
            'lastname' => $last,
            'bio' => \core_text::substr($bio, 0, self::BIO_MAX_LENGTH),
        ];
    }

    /**
     * Flatten a domain vocabulary entry to id and label.
     *
     * @param array $record
     * @param string $key
     * @return array|null Null when the entry carries no label.
     */
    private static function normalise_domain(array $record, string $key): ?array {
        $label = self::first_string($record, ['label', 'name', 'title']);
        if ($label === '') {
            return null;
        }
        return [
            'id' => self::first_string($record, ['id', 'key']) ?: $key,
            'label' => $label,
        ];
    }

    /**
     * String values of an item's key array, ignoring blanks.
     *
     * @param mixed $item
     * @param string $field
     * @return array
     */
    private static function key_list($item, string $field): array {
        $values = [];
        if (!is_array($item) || !isset($item[$field]) || !is_array($item[$field])) {
            return $values;
        }
        foreach ($item[$field] as $value) {
            if (is_string($value) && trim($value) !== '') {
                $values[] = trim($value);
            }
        }
        return $values;
    }

    /**
     * @param array $aggregations
     * @param string $name
     * @return array
     */
    private static function bucket_keys(array $aggregations, string $name): array {
        $keys = [];
        foreach ($aggregations[$name]['buckets'] ?? [] as $bucket) {
            if (!is_array($bucket) || !isset($bucket['key'])) {
                continue;
            }
            $key = trim((string) $bucket['key']);
            if ($key !== '') {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * @param array $keys
     * @param array $producers
     * @return array
     */
    private static function producer_labels(array $keys, array $producers): array {
        $labels = [];
        foreach ($keys as $key) {
            $needle = strtolower(catalogue_cache::normalize_structure_key((string) $key));
            if ($needle === '') {
                continue;
            }
            foreach ($producers as $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach (['structure_id', 'uuid', 'uid', 'id'] as $field) {
                    $value = isset($row[$field]) ? (string) $row[$field] : '';
                    if ($value === '') {
                        continue;
                    }
                    if (strtolower(catalogue_cache::normalize_structure_key($value)) !== $needle) {
                        continue;
                    }
                    $text = trim((string) ($row['acronym'] ?? ''));
                    if ($text === '') {
                        $text = trim((string) ($row['name'] ?? ''));
                    }
                    if ($text !== '') {
                        $labels[$key] = $text;
                    }
                    break 2;
                }
            }
        }
        return $labels;
    }

    /**
     * First non-blank string among the candidate fields.
     *
     * @param array $record
     * @param array $fields
     * @return string
     */
    private static function first_string(array $record, array $fields): string {
        foreach ($fields as $field) {
            if (isset($record[$field]) && is_string($record[$field]) && trim($record[$field]) !== '') {
                return trim($record[$field]);
            }
        }
        return '';
    }
}
