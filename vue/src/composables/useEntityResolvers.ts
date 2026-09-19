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
 * Helpers that resolve entity keys (domain, structure, person) to display strings.
 * Used by NuggetSearchFilter to build human-readable aggregation bucket labels.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { useMoodleService } from "./useMoodleService";
import { useNaasConfig } from "./useNaasConfig";
import { structureVisuals, type StructureVisuals } from "./structureVisuals";
import { matchCachedProducer } from "./catalogueSnapshot";

function stringField(
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

export function useEntityResolvers() {
  const service = useMoodleService();
  const config = useNaasConfig();

  async function getDomainLabel(key: string): Promise<string> {
    try {
      const domain = (await service.getDomain(key, config.courseId)) as Record<
        string,
        unknown
      > | null;
      const label = stringField(domain, ["label", "name", "title"]);
      return label || key;
    } catch {
      return key;
    }
  }

  async function getStructureAcronym(key: string): Promise<string> {
    const cached = visualsFromSnapshot(key);
    if (cached?.acronym) {
      return cached.acronym;
    }
    try {
      const structure = await service.getStructure(key, config.courseId);
      return (
        structureVisuals(structure, key, config.naas_endpoint ?? "").acronym ||
        key
      );
    } catch {
      return (
        structureVisuals(null, key, config.naas_endpoint ?? "").acronym || key
      );
    }
  }

  async function getStructureVisuals(key: string): Promise<StructureVisuals> {
    const cached = visualsFromSnapshot(key);
    if (cached && (cached.acronym || cached.name)) {
      return cached;
    }
    try {
      const structure = await service.getStructure(key, config.courseId);
      return structureVisuals(structure, key, config.naas_endpoint ?? "");
    } catch {
      return structureVisuals(null, key, config.naas_endpoint ?? "");
    }
  }

  function visualsFromSnapshot(key: string): StructureVisuals | null {
    const producer = matchCachedProducer(
      config.catalogue_snapshot?.producers,
      key
    );
    if (!producer) {
      return null;
    }
    return structureVisuals(producer, key, config.naas_endpoint ?? "");
  }

  async function getPersonName(personKey: string): Promise<string> {
    try {
      const person = (await service.getPerson(
        personKey,
        config.courseId
      )) as Record<string, unknown> | null;
      const first = stringField(person, [
        "firstname",
        "first_name",
        "firstName",
      ]);
      const last = stringField(person, ["lastname", "last_name", "lastName"]);
      const full = `${first} ${last}`.trim();
      if (full) return full.toUpperCase();
      const name = stringField(person, ["name", "fullname", "full_name"]);
      return name ? name.toUpperCase() : personKey;
    } catch {
      return personKey;
    }
  }

  return {
    getDomainLabel,
    getStructureAcronym,
    getStructureVisuals,
    getPersonName,
  };
}
