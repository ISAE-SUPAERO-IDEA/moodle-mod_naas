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
 * NaaS /nuggets/search pagination.
 *
 * The API is 0-indexed (Elasticsearch-style). Vue 2 never sent `page`, so the
 * server defaulted to the first page. Vue 3 sent `page: 1`, which skipped the
 * first page and returned an empty hit list for any facet with fewer results
 * than `page_size` (e.g. 4 "advanced" nuggets).
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const SEARCH_FIRST_PAGE = 0

/**
 * Attach `page` only after the first page so the initial request matches Vue 2.
 */
export function withSearchPage<T extends Record<string, unknown>>(
  options: T,
  page: number
): T & { page?: number } {
  if (page <= SEARCH_FIRST_PAGE) {
    return { ...options }
  }
  return { ...options, page }
}
