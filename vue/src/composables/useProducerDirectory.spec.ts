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
 * Tests for useProducerDirectory.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it } from "vitest";
import { useProducerDirectory } from "./useProducerDirectory";
import type { CatalogueSnapshot } from "@/types/naas-config.types";

const ISAE_UUID = "06d37c13-6ffe-4c4a-a9e3-ac227652f98c";
const ONERA_UUID = "9a1e0b7c-2d4f-4c6e-8b1a-3f5d7e9c0a21";

function snapshot(): CatalogueSnapshot {
  return {
    producers: [
      { structure_id: "isae-supaero", uuid: ISAE_UUID },
      { structure_id: "onera", uuid: ONERA_UUID, name: "ONERA" },
    ],
  };
}

describe("useProducerDirectory", () => {
  it("starts from the snapshot producers", () => {
    const { producerDirectory } = useProducerDirectory(snapshot());
    expect(producerDirectory.value).toHaveLength(2);
    expect(useProducerDirectory(null).producerDirectory.value).toEqual([]);
  });

  it("keeps the same directory reference when the landing reports no new name (B4 loop guard)", () => {
    const { producerDirectory, onProducersNamed } =
      useProducerDirectory(snapshot());
    const before = producerDirectory.value;

    onProducersNamed([{ id: ONERA_UUID, acronym: "", name: "ONERA" }]);
    onProducersNamed([]);

    expect(producerDirectory.value).toBe(before);
  });

  it("stores a newly resolved name in the directory and the producer labels", () => {
    const { producerDirectory, facetLabels, onProducersNamed } =
      useProducerDirectory(snapshot());
    const before = producerDirectory.value;

    onProducersNamed([
      { id: ISAE_UUID, acronym: "ISAE", name: "ISAE-SUPAERO" },
    ]);

    expect(producerDirectory.value).not.toBe(before);
    expect(producerDirectory.value[0]).toMatchObject({
      uuid: ISAE_UUID,
      acronym: "ISAE",
      name: "ISAE-SUPAERO",
    });
    expect(Object.values(facetLabels.value.producers ?? {})).toContain("ISAE");
  });

  it("absorbs producer rows from the catalogue check and ignores an empty list", () => {
    const { producerDirectory, absorbProducers } = useProducerDirectory(null);

    absorbProducers([]);
    expect(producerDirectory.value).toEqual([]);

    absorbProducers([{ uuid: ISAE_UUID, acronym: "ISAE", name: "ISAE" }]);
    expect(producerDirectory.value).toHaveLength(1);
  });
});
