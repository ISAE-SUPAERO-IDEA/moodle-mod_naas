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
 * Catalogue-wide freshness: total size plus the newest modification date.
 *
 * One sorted page_size=1 search is enough. Unchanged stamp means no add,
 * delete, or edit anywhere, so every cached page is still valid.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_naas;

defined('MOODLE_INTERNAL') || die();

/**
 * Value object for the catalogue probe stamp.
 *
 * @package   mod_naas
 * @copyright 2026 ISAE-SUPAERO
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue_stamp {
    /**
     * Build a stamp from a search body.
     *
     * The probe asks NaaS for `sort_by=modification_date&sort_order=DESC`, so
     * items[0] is the newest document. If that field is missing we fall back
     * to the max on the page, which is the same thing for page_size=1.
     *
     * @param array $search
     * @return array{results_count: int, newest_modification_date: string}
     */
    public static function from_search(array $search): array {
        $items = is_array($search['items'] ?? null) ? $search['items'] : [];
        $newest = '';
        if (isset($items[0]) && is_array($items[0])) {
            $newest = (string) ($items[0]['modification_date'] ?? '');
        }
        if ($newest === '') {
            $newest = search_cache::max_modification_date($items);
        }
        return [
            'results_count' => (int) ($search['results_count'] ?? 0),
            'newest_modification_date' => $newest,
        ];
    }

    /**
     * True when both halves are present, so the stamp can gate revalidation.
     *
     * An empty modification date means sorting did not yield a usable hit and
     * the widget must keep the per-query revalidate path.
     *
     * @param mixed $stamp
     * @return bool
     */
    public static function is_complete($stamp): bool {
        return is_array($stamp)
            && array_key_exists('results_count', $stamp)
            && (string) ($stamp['newest_modification_date'] ?? '') !== '';
    }

    /**
     * @param mixed $left
     * @param mixed $right
     * @return bool
     */
    public static function equals($left, $right): bool {
        if (!self::is_complete($left) || !self::is_complete($right)) {
            return false;
        }
        return (int) $left['results_count'] === (int) $right['results_count']
            && (string) $left['newest_modification_date'] === (string) $right['newest_modification_date'];
    }
}
