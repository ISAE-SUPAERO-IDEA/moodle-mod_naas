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
 * Readable message of a failed web service call.
 *
 * Moodle's core/ajax rejects with a plain object ({message, errorcode, ...}),
 * not an Error, so String(e) would print "[object Object]".
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The message of an Error or of a Moodle exception object, else the value as text.
 *
 * @param e Whatever a promise rejected with
 * @returns The message to show
 */
export function errorMessage(e: unknown): string {
  if (e instanceof Error) {
    return e.message;
  }
  if (e && typeof e === "object") {
    const message = (e as { message?: unknown }).message;
    if (typeof message === "string") {
      return message;
    }
  }
  return String(e);
}
