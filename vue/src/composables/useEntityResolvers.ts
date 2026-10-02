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

import { unwrapNaasPayload } from "@/service/unwrapNaasPayload";
import { useMoodleService } from "./useMoodleService";
import { useNaasConfig } from "./useNaasConfig";
import {
  normalizePersonKey,
  structureVisuals,
  type StructureVisuals,
} from "./structureVisuals";
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
      const domain = unwrapNaasPayload<Record<string, unknown> | null>(
        await service.getDomain(key, config.courseId)
      );
      const label = stringField(domain, ["label", "name", "title"]);
      return label || key;
    } catch {
      return key;
    }
  }

  async function getStructureAcronym(key: string): Promise<string> {
    const fromVisuals = (visuals: StructureVisuals) =>
      visuals.acronym || visuals.name || key;
    const cached = visualsFromSnapshot(key);
    if (cached && (cached.acronym || cached.name)) {
      return fromVisuals(cached);
    }
    try {
      const structure = await service.getStructure(key, config.courseId);
      return fromVisuals(
        structureVisuals(structure, key, config.naas_endpoint ?? "")
      );
    } catch {
      return fromVisuals(
        structureVisuals(null, key, config.naas_endpoint ?? "")
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
      const visuals = structureVisuals(
        structure,
        key,
        config.naas_endpoint ?? ""
      );
      return visuals;
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

  return {
    getDomainLabel,
    getStructureAcronym,
    getStructureVisuals,
    getPersonName,
  };
}
