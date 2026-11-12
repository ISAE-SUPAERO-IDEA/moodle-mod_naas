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
 * NaaS/Moodle webservice bodies are sometimes a JSON string, sometimes
 * `{ payload: T }`, sometimes T itself. Always return the inner entity.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export function unwrapNaasPayload<T>(response: unknown): T {
  let current: unknown = response;
  for (let depth = 0; depth < 4; depth++) {
    if (typeof current === "string") {
      const trimmed = current.trim();
      if (
        !trimmed ||
        (trimmed[0] !== "{" && trimmed[0] !== "[" && trimmed[0] !== '"')
      ) {
        break;
      }
      try {
        current = JSON.parse(trimmed);
        continue;
      } catch {
        break;
      }
    }
    if (
      current &&
      typeof current === "object" &&
      !Array.isArray(current) &&
      "payload" in current
    ) {
      const inner = (current as { payload: unknown }).payload;
      if (inner !== undefined && inner !== null) {
        current = inner;
        continue;
      }
    }
    break;
  }
  return current as T;
}
