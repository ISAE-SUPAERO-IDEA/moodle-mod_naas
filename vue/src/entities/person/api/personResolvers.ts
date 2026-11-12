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
 * Resolve a person key (an author) to a display name, through Moodle.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { unwrapNaasPayload, useMoodleService } from "@/shared/api";
import { useNaasConfig } from "@/shared/config";
import { normalizePersonKey, stringField } from "@/shared/lib";

export function usePersonResolvers() {
  const service = useMoodleService();
  const config = useNaasConfig();

  /** Name of a person in capitals; empty when NaaS has none or cannot be reached. */
  async function getPersonName(personKey: string): Promise<string> {
    const id = normalizePersonKey(personKey);
    try {
      const person = unwrapNaasPayload<Record<string, unknown> | null>(
        await service.getPerson(id, config.courseId)
      );
      const properties =
        person?.properties &&
        typeof person.properties === "object" &&
        !Array.isArray(person.properties)
          ? (person.properties as Record<string, unknown>)
          : null;
      const first =
        stringField(person, ["firstname", "first_name", "firstName"]) ||
        stringField(properties, ["person:firstname", "firstname"]);
      const last =
        stringField(person, ["lastname", "last_name", "lastName"]) ||
        stringField(properties, ["person:lastname", "lastname"]);
      const full = `${first} ${last}`.trim();
      const name =
        stringField(person, ["name", "fullname", "full_name"]) ||
        stringField(properties, ["dc:title", "name"]);
      const resolved = full || name;
      return resolved ? resolved.toUpperCase() : "";
    } catch {
      return "";
    }
  }

  return { getPersonName };
}
