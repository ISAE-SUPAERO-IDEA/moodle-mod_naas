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
  const parsed: unknown = typeof response === 'string' ? JSON.parse(response) : response
  if (parsed && typeof parsed === 'object' && 'payload' in parsed) {
    const inner = (parsed as { payload: unknown }).payload
    if (inner !== undefined && inner !== null) {
      return inner as T
    }
  }
  return parsed as T
}
