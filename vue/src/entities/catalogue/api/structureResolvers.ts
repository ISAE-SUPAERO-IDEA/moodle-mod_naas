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
 * Resolve a structure key (a producer) to its acronym, name and images.
 * The catalogue snapshot answers first; Moodle is asked only for what it lacks.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { useMoodleService, createMediaRouter } from "@/shared/api";
import { useNaasConfig } from "@/shared/config";
import {
  structureVisuals,
  type StructureVisuals,
} from "../lib/structureVisuals";
import { matchCachedProducer } from "../lib/catalogueSnapshot";

export function useStructureResolvers() {
  const service = useMoodleService();
  const config = useNaasConfig();
  const media = createMediaRouter(config.moodle_url, config.courseId);

  async function getStructureAcronym(key: string): Promise<string> {
    const fromVisuals = (visuals: StructureVisuals) =>
      visuals.acronym || visuals.name || key;
    const cached = visualsFromSnapshot(key);
    if (cached && (cached.acronym || cached.name)) {
      return fromVisuals(cached);
    }
    try {
      const structure = await service.getStructure(key, config.courseId);
      return fromVisuals(structureVisuals(structure, key, media));
    } catch {
      return fromVisuals(structureVisuals(null, key, media));
    }
  }

  async function getStructureVisuals(key: string): Promise<StructureVisuals> {
    const cached = visualsFromSnapshot(key);
    if (cached && (cached.acronym || cached.name)) {
      return cached;
    }
    try {
      const structure = await service.getStructure(key, config.courseId);
      const visuals = structureVisuals(structure, key, media);
      return visuals;
    } catch {
      return structureVisuals(null, key, media);
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
    return structureVisuals(producer, key, media);
  }

  return { getStructureAcronym, getStructureVisuals };
}
