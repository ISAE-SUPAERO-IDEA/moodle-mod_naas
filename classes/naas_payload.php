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
 * Peel the `{payload: …}` envelope NaaS sometimes wraps around responses.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared JSON envelope handling for NaaS bodies stored in MUC.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class naas_payload {
    /**
     * Unwrap nested `{payload: …}` wrappers and JSON strings.
     *
     * @param mixed $decoded Already-decoded JSON, or a JSON string.
     * @param int $maxdepth
     * @return mixed
     */
    public static function unwrap($decoded, int $maxdepth = 4) {
        $current = $decoded;
        for ($depth = 0; $depth < $maxdepth; $depth++) {
            if (is_string($current)) {
                $trimmed = trim($current);
                if ($trimmed === '' || !self::looks_like_json($trimmed)) {
                    return $current;
                }
                $parsed = json_decode($trimmed, true);
                if ($parsed === null && json_last_error() !== JSON_ERROR_NONE) {
                    return $current;
                }
                $current = $parsed;
                continue;
            }
            if (is_array($current) && array_key_exists('payload', $current) && $current['payload'] !== null) {
                $current = $current['payload'];
                continue;
            }
            if (is_object($current) && isset($current->payload) && $current->payload !== null) {
                $current = $current->payload;
                continue;
            }
            break;
        }
        return $current;
    }

    /**
     * Decode a JSON body to an associative array, peeling any envelope.
     *
     * @param string $json
     * @return array|null
     */
    public static function decode(string $json): ?array {
        $decoded = self::unwrap(json_decode($json, true));
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Normalise a NaaS search body to items / aggregations / results_count.
     *
     * @param string $json
     * @return array|null
     */
    public static function decode_search(string $json): ?array {
        $decoded = self::decode($json);
        if ($decoded === null) {
            return null;
        }
        $items = [];
        foreach ($decoded['items'] ?? [] as $row) {
            if (is_array($row)) {
                $items[] = $row;
            }
        }
        return [
            'items' => $items,
            'aggregations' => is_array($decoded['aggregations'] ?? null) ? $decoded['aggregations'] : [],
            'results_count' => (int) ($decoded['results_count'] ?? 0),
        ];
    }

    /**
     * @param string $value
     * @return bool
     */
    private static function looks_like_json(string $value): bool {
        $first = $value[0];
        return $first === '{' || $first === '[' || $first === '"';
    }
}
