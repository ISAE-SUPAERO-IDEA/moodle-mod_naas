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
 * First non-blank text among several keys of a record.
 *
 * NaaS and Nuxeo name the same field in several ways (firstname, first_name,
 * person:firstname): callers list the names to try, in order.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The trimmed value of the first key that holds non-blank text.
 *
 * @param entity The record to read; may be missing
 * @param keys Field names to try, in order
 * @returns The text, or an empty string when no key holds any
 */
export function stringField(
  entity: Record<string, unknown> | null | undefined,
  keys: string[]
): string {
  if (!entity) return "";
  for (const key of keys) {
    const value = entity[key];
    if (typeof value === "string" && value.trim()) {
      return value.trim();
    }
  }
  return "";
}
