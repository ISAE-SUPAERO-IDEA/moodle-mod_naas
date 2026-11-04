import { describe, expect, it, vi } from "vitest";
import {
  mergeProducerNames,
  namedPayload,
  namedPayloadChanged,
  producerLabelUpdates,
} from "./producerDirectory";
import type { NamedProducer } from "./producerDirectory";
import { isOpaqueEntityKey } from "./structureVisuals";
import type { CatalogueProducer } from "@/types/naas-config.types";

const ISAE_UUID = "06d37c13-6ffe-4c4a-a9e3-ac227652f98c";
const ONERA_UUID = "9a1e0b7c-2d4f-4c6e-8b1a-3f5d7e9c0a21";

/** A snapshot directory: ISAE still unnamed, ONERA already named. */
function directory(): CatalogueProducer[] {
  return [
    { structure_id: "isae-supaero", uuid: ISAE_UUID },
    { structure_id: "onera", uuid: ONERA_UUID, name: "ONERA" },
  ];
}

/** Same trim-and-drop-opaque-keys filter as NuggetBrowseLanding. */
function visibleLabel(value: string): string {
  const text = value.trim();
  return !text || isOpaqueEntityKey(text) ? "" : text;
}

/** What the landing paints from directory rows that already carry a name. */
function cardsFrom(rows: CatalogueProducer[]): NamedProducer[] {
  return rows.map((row) => ({
    id: String(row.uuid),
    acronym: row.acronym ?? "",
    name: row.name ?? "",
  }));
}

describe("producerDirectory", () => {
  describe("mergeProducerNames", () => {
    it("returns the same directory reference when no name changes", () => {
      const current = directory();
      const rows = [{ id: ONERA_UUID, acronym: "", name: "ONERA" }];
      expect(mergeProducerNames(current, rows)).toBe(current);
      expect(mergeProducerNames(current, [])).toBe(current);
      const empty: CatalogueProducer[] = [];
      expect(mergeProducerNames(empty, rows)).toBe(empty);
    });

    it("replaces only the renamed row and keeps the others' identity", () => {
      const current = directory();
      const merged = mergeProducerNames(current, [
        {
          id: `managed_by:structure:${ISAE_UUID}`,
          acronym: "ISAE",
          name: "ISAE-SUPAERO",
        },
        { id: ONERA_UUID, acronym: "", name: "ONERA" },
      ]);
      expect(merged).not.toBe(current);
      expect(merged).toHaveLength(2);
      expect(merged[0]).not.toBe(current[0]);
      expect(merged[0]).toEqual({
        structure_id: "isae-supaero",
        uuid: ISAE_UUID,
        acronym: "ISAE",
        name: "ISAE-SUPAERO",
      });
      expect(merged[1]).toBe(current[1]);
      expect(current[0]).toEqual({
        structure_id: "isae-supaero",
        uuid: ISAE_UUID,
      });
    });

    it("keeps a known label when the payload leaves that field empty", () => {
      const current: CatalogueProducer[] = [
        { uuid: ISAE_UUID, acronym: "ISAE", name: "ISAE-SUPAERO" },
      ];
      const merged = mergeProducerNames(current, [
        { id: ISAE_UUID, acronym: "", name: "ISAE-SUPAERO" },
      ]);
      expect(merged).toBe(current);
      expect(merged[0].acronym).toBe("ISAE");
    });

    it("matches rows through the injected matcher", () => {
      const current = directory();
      const matches = vi.fn(() => null);
      const rows = [{ id: ISAE_UUID, acronym: "ISAE", name: "" }];
      expect(mergeProducerNames(current, rows, matches)).toBe(current);
      expect(matches).toHaveBeenCalledWith([current[0]], ISAE_UUID);
    });
  });

  describe("namedPayload", () => {
    it("filters labels and drops cards left without one", () => {
      expect(
        namedPayload(
          [
            { id: ISAE_UUID, acronym: " ISAE ", name: "ISAE-SUPAERO" },
            { id: ONERA_UUID, acronym: "", name: ONERA_UUID },
            { id: "cnes", acronym: "  ", name: "" },
          ],
          visibleLabel
        )
      ).toEqual([{ id: ISAE_UUID, acronym: "ISAE", name: "ISAE-SUPAERO" }]);
    });
  });

  describe("namedPayloadChanged", () => {
    const payload: NamedProducer[] = [
      { id: ISAE_UUID, acronym: "ISAE", name: "ISAE-SUPAERO" },
      { id: ONERA_UUID, acronym: "", name: "ONERA" },
    ];

    it("is false for an identical payload, whatever the order", () => {
      expect(namedPayloadChanged(payload, payload)).toBe(false);
      expect(
        namedPayloadChanged(payload, [
          { id: ONERA_UUID, acronym: "", name: "ONERA" },
          { id: ISAE_UUID, acronym: "ISAE", name: "ISAE-SUPAERO" },
        ])
      ).toBe(false);
    });

    it("is true when a label, an id, or the size differs", () => {
      expect(namedPayloadChanged([], payload)).toBe(true);
      expect(namedPayloadChanged(payload, payload.slice(0, 1))).toBe(true);
      expect(
        namedPayloadChanged(payload, [
          payload[0],
          { id: ONERA_UUID, acronym: "ONERA", name: "ONERA" },
        ])
      ).toBe(true);
      expect(
        namedPayloadChanged(payload, [
          payload[0],
          { id: "cnes", acronym: "", name: "ONERA" },
        ])
      ).toBe(true);
    });
  });

  describe("producerLabelUpdates", () => {
    it("lists only labels that are new or different", () => {
      const rows: NamedProducer[] = [
        { id: ISAE_UUID, acronym: "ISAE", name: "ISAE-SUPAERO" },
        { id: ONERA_UUID, acronym: "", name: "ONERA" },
        { id: "cnes", acronym: "", name: "" },
      ];
      expect(producerLabelUpdates(undefined, rows)).toEqual({
        [ISAE_UUID]: "ISAE",
        [ONERA_UUID]: "ONERA",
      });
      expect(
        producerLabelUpdates(
          { [ISAE_UUID]: "ISAE", [ONERA_UUID]: "Onera" },
          rows
        )
      ).toEqual({ [ONERA_UUID]: "ONERA" });
      expect(
        producerLabelUpdates(
          { [ISAE_UUID]: "ISAE", [ONERA_UUID]: "ONERA" },
          rows
        )
      ).toEqual({});
    });
  });

  describe("named round trip", () => {
    it("reaches a fixed point: merge, repaint, merge again is a no-op", () => {
      const current = directory();
      const first = namedPayload(
        [
          { id: ISAE_UUID, acronym: " ISAE ", name: "ISAE-SUPAERO" },
          { id: ONERA_UUID, acronym: "", name: "ONERA" },
        ],
        visibleLabel
      );
      const merged = mergeProducerNames(current, first);
      expect(merged).not.toBe(current);

      const second = namedPayload(cardsFrom(merged), visibleLabel);
      expect(namedPayloadChanged(first, second)).toBe(false);
      expect(mergeProducerNames(merged, second)).toBe(merged);
      expect(
        producerLabelUpdates(
          { [ISAE_UUID]: "ISAE", [ONERA_UUID]: "ONERA" },
          second
        )
      ).toEqual({});
    });

    it("settles after one emit and one assignment instead of looping", () => {
      const resolved: Record<string, { acronym: string; name: string }> = {
        [ISAE_UUID]: { acronym: " ISAE ", name: "ISAE-SUPAERO" },
      };
      let dir = directory();
      let lastNamed: NamedProducer[] = [];
      let emits = 0;
      let assigns = 0;
      for (let round = 0; round < 10; round++) {
        // Child: paint from the directory, resolve what it lacks, publish.
        const cards = dir.map((row) => {
          const key = String(row.uuid);
          return {
            id: key,
            acronym: row.acronym ?? resolved[key]?.acronym ?? "",
            name: row.name ?? resolved[key]?.name ?? "",
          };
        });
        const payload = namedPayload(cards, visibleLabel);
        if (!payload.length || !namedPayloadChanged(lastNamed, payload)) {
          break;
        }
        lastNamed = payload;
        emits += 1;
        // Parent: fold the names back into the prop the child watches.
        const merged = mergeProducerNames(dir, payload);
        if (merged === dir) {
          break;
        }
        dir = merged;
        assigns += 1;
      }
      expect(emits).toBe(1);
      expect(assigns).toBe(1);
      expect(dir[0]).toMatchObject({ acronym: "ISAE", name: "ISAE-SUPAERO" });
    });
  });
});
