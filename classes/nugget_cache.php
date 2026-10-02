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
 * Cache of the full Nugget document behind get_nugget.
 *
 * Reopening an activity form always cost a `/nuggets/{id}/default_version`
 * round-trip. The widget now paints the saved selection from whatever list
 * already held that hit, and this removes the NaaS hop from the confirmation
 * that follows: Moodle answers from its own copy, then the widget revalidates
 * in the background and swaps only if `modification_date` or `version_id`
 * moved.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

/**
 * Per-Nugget cache of the default-version document.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nugget_cache {
    /** MUC area name from db/caches.php. */
    public const CACHE_AREA = 'nugget_documents';

    /**
     * Cache key for a Nugget under the current NaaS connection.
     *
     * @param string $nuggetid
     * @param object $config
     * @return string
     */
    public static function key(string $nuggetid, object $config): string {
        return sha1(catalogue_cache::fingerprint($config) . '|' . $nuggetid);
    }

    /**
     * Read a stored document, or null on a miss or a connection change.
     *
     * @param string $nuggetid
     * @param object $config
     * @return array|null
     */
    public static function get(string $nuggetid, object $config): ?array {
        $raw = \cache::make('mod_naas', self::CACHE_AREA)->get(self::key($nuggetid, $config));
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
     * Decode and store a live document.
     *
     * @param string $nuggetid
     * @param string $json Sanitised NaaS response.
     * @param object $config
     * @return array|null Null when the body could not be decoded.
     */
    public static function store(string $nuggetid, string $json, object $config): ?array {
        $document = self::decode_document($json);
        if ($document === null) {
            return null;
        }
        $entry = [
            'fingerprint' => catalogue_cache::fingerprint($config),
            'cached_at' => time(),
            'modification_date' => (string) ($document['modification_date'] ?? ''),
            'version_id' => (string) ($document['version_id'] ?? ''),
            'document' => $document,
        ];
        \cache::make('mod_naas', self::CACHE_AREA)->set(
            self::key($nuggetid, $config),
            json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        return $entry;
    }

    /**
     * Webservice body for an entry, with the vocabulary joined in.
     *
     * @param array $entry
     * @param bool $hit
     * @return string JSON
     */
    public static function response(array $entry, bool $hit): string {
        $document = $entry['document'] ?? [];
        $body = $document;
        $body['vocabulary'] = vocabulary_lookup::collect([$document]);
        $body['_cache'] = [
            'hit' => $hit,
            'cached_at' => (int) ($entry['cached_at'] ?? 0),
            'digest' => [
                'modification_date' => (string) ($entry['modification_date'] ?? ''),
                'version_id' => (string) ($entry['version_id'] ?? ''),
            ],
        ];
        return json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Forget one Nugget, or everything when no id is given.
     *
     * @param string|null $nuggetid
     * @param object|null $config
     */
    public static function purge(?string $nuggetid = null, ?object $config = null): void {
        $cache = \cache::make('mod_naas', self::CACHE_AREA);
        if ($nuggetid === null || $config === null) {
            $cache->purge();
            return;
        }
        $cache->delete(self::key($nuggetid, $config));
    }

    /**
     * Peel the `{payload: ...}` envelope NaaS sometimes wraps documents in.
     *
     * @param string $json
     * @return array|null
     */
    private static function decode_document(string $json): ?array {
        $decoded = naas_payload::decode($json);
        if ($decoded === null || !isset($decoded['nugget_id'])) {
            return null;
        }
        return $decoded;
    }
}
