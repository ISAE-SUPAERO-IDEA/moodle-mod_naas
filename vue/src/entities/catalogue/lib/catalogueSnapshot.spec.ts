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
 * Tests for the catalogue snapshot helpers (producer directory, vocabulary, labels).
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it } from "vitest";
import {
  applyVocabulary,
  facetLabelsFromSnapshot,
  isEnriched,
  matchCachedProducer,
  mergeFacetLabels,
  mergeProducerDirectory,
  sameCatalogueStamp,
  snapshotSearch,
} from "./catalogueSnapshot";
import type { Nugget } from "@/shared/api";

describe("catalogueSnapshot", () => {
  it("matches a producer by uuid, structure_id, or managed_by prefix", () => {
    const producers = [
      {
        structure_id: "isae-supaero",
        uuid: "06d37c13-6ffe-4c4a-a9e3-ac227652f98c",
        acronym: "ISAE",
        name: "ISAE-SUPAERO",
      },
    ];
    expect(matchCachedProducer(producers, "isae-supaero")?.acronym).toBe(
      "ISAE"
    );
    expect(
      matchCachedProducer(
        producers,
        "managed_by:structure:06d37c13-6ffe-4c4a-a9e3-ac227652f98c"
      )?.name
    ).toBe("ISAE-SUPAERO");
    expect(matchCachedProducer(producers, "missing")).toBeNull();
  });

  it("keeps a known producer name when a probe row has none", () => {
    const merged = mergeProducerDirectory(
      [
        {
          structure_id: "isae-supaero",
          uuid: "06d37c13-6ffe-4c4a-a9e3-ac227652f98c",
          acronym: "ISAE",
          name: "ISAE-SUPAERO",
        },
      ],
      [
        {
          structure_id: "06d37c13-6ffe-4c4a-a9e3-ac227652f98c",
          uuid: "06d37c13-6ffe-4c4a-a9e3-ac227652f98c",
          acronym: "",
          name: "",
          count: 4,
        },
      ]
    );
    expect(merged[0].acronym).toBe("ISAE");
    expect(merged[0].name).toBe("ISAE-SUPAERO");
    expect(merged[0].count).toBe(4);
  });

  it("normalises a snapshot search payload", () => {
    expect(
      snapshotSearch({
        search: {
          items: [{ nugget_id: "n1", name: "Alpha" } as never],
          aggregations: { producers: { buckets: [] } },
          results_count: 1,
        },
      })
    ).toMatchObject({
      items: [{ nugget_id: "n1" }],
      results_count: 1,
    });
    expect(snapshotSearch(null)).toBeNull();
  });

  it("joins the vocabulary table onto the items' key arrays", () => {
    const result = snapshotSearch({
      search: {
        items: [
          {
            nugget_id: "n1",
            name: "Alpha",
            authors: ["a1"],
            domains: ["d1"],
          } as never,
        ],
        aggregations: {},
        results_count: 1,
        vocabulary: {
          persons: {
            a1: { email: "ada@example.com", firstname: "Ada", lastname: "L" },
          },
          domains: { d1: { id: "d1", label: "Math" } },
        },
      },
    });
    expect(result?.items[0].authors_data?.[0].firstname).toBe("Ada");
    expect(result?.items[0].domains_data?.[0].label).toBe("Math");
  });
});

describe("applyVocabulary", () => {
  const item = {
    nugget_id: "n1",
    authors: ["a1", "a2"],
    domains: [],
  } as unknown as Nugget;

  it("returns the items untouched when there is no table", () => {
    const items = [item];
    expect(applyVocabulary(items, undefined)).toBe(items);
    expect(applyVocabulary(items, { persons: {}, domains: {} })).toBe(items);
  });

  it("drops keys the table could not resolve", () => {
    const [resolved] = applyVocabulary([item], {
      persons: {
        a1: { email: "a@example.com", firstname: "Ada", lastname: "L" },
      },
    });
    expect(resolved.authors_data).toHaveLength(1);
  });
});

describe("isEnriched", () => {
  it("is true only when every key resolved", () => {
    expect(
      isEnriched({
        authors: ["a1"],
        domains: [],
        authors_data: [
          { email: "a@example.com", firstname: "Ada", lastname: "L" },
        ],
        domains_data: [],
      } as unknown as Nugget)
    ).toBe(true);

    // One author went unresolved, so the widget must still look it up.
    expect(
      isEnriched({
        authors: ["a1", "a2"],
        domains: [],
        authors_data: [
          { email: "a@example.com", firstname: "Ada", lastname: "L" },
        ],
        domains_data: [],
      } as unknown as Nugget)
    ).toBe(false);

    expect(
      isEnriched({ authors: ["a1"], domains: [] } as unknown as Nugget)
    ).toBe(false);
  });
});

describe("catalogue stamp helpers", () => {
  it("treats a missing or empty modification date as changed", () => {
    const stamp = {
      results_count: 4,
      newest_modification_date: "2026-09-17T08:00:00Z",
    };
    expect(sameCatalogueStamp(stamp, stamp)).toBe(true);
    expect(sameCatalogueStamp(stamp, { ...stamp, results_count: 5 })).toBe(
      false
    );
    expect(
      sameCatalogueStamp(stamp, {
        results_count: 4,
        newest_modification_date: "",
      })
    ).toBe(false);
  });

  it("builds facet labels from producers and vocabulary", () => {
    const labels = facetLabelsFromSnapshot({
      producers: [
        {
          structure_id: "isae-supaero",
          uuid: "abc",
          acronym: "ISAE",
          name: "ISAE-SUPAERO",
        },
      ],
      search: {
        items: [],
        aggregations: {},
        results_count: 0,
        vocabulary: {
          persons: {
            a1: { email: "ada@example.com", firstname: "Ada", lastname: "L" },
          },
          domains: { d1: { id: "d1", label: "Math" } },
        },
      },
    });
    expect(labels.producers?.["isae-supaero"]).toBe("ISAE");
    expect(labels.producers?.abc).toBe("ISAE");
    expect(labels.producers?.["managed_by:structure:abc"]).toBe("ISAE");
    expect(labels.related_domains?.d1).toBe("Math");
    expect(labels.authors?.a1).toBe("ADA L");
  });

  it("merges incoming facet labels onto existing ones", () => {
    expect(
      mergeFacetLabels(
        { producers: { a: "A" }, authors: { x: "X" } },
        { producers: { b: "B" }, authors: { x: "Y" } }
      )
    ).toEqual({ producers: { a: "A", b: "B" }, authors: { x: "Y" } });
  });

  it("never matches a missing stamp", () => {
    const stamp = { results_count: 1, newest_modification_date: "2026-01-01" };
    expect(sameCatalogueStamp(null, stamp)).toBe(false);
    expect(sameCatalogueStamp(stamp, undefined)).toBe(false);
  });

  it("merges onto nothing, adds new facets and skips empty ones", () => {
    expect(mergeFacetLabels(undefined, undefined)).toEqual({});
    expect(mergeFacetLabels({ producers: { a: "A" } }, undefined)).toEqual({
      producers: { a: "A" },
    });
    expect(
      mergeFacetLabels(undefined, {
        authors: { x: "X" },
        producers: undefined,
      })
    ).toEqual({ authors: { x: "X" } });
  });

  it("uses the labels stored on the snapshot when there are some", () => {
    const stored = { producers: { isae: "ISAE" } };
    const labels = facetLabelsFromSnapshot({
      labels: stored,
      producers: [{ structure_id: "other", acronym: "OTHER" }],
    });
    expect(labels).toEqual(stored);
    expect(labels.producers).not.toBe(stored.producers);
  });

  it("builds empty label maps without a snapshot", () => {
    expect(facetLabelsFromSnapshot(null)).toEqual({
      producers: {},
      related_domains: {},
      authors: {},
    });
  });

  it("skips producers, domains and persons that have nothing to show", () => {
    const labels = facetLabelsFromSnapshot({
      producers: [
        { structure_id: "unnamed" },
        { id: "by-name", uuid: "", name: "Named producer" },
      ],
      search: {
        items: [],
        aggregations: {},
        results_count: 0,
        vocabulary: {
          persons: {
            anonymous: { email: "x@example.com" } as never,
            last: { email: "y@example.com", lastname: "Hopper" } as never,
          },
          domains: { blank: { id: "blank", label: "" } },
        },
      },
    });
    expect(labels.producers).toEqual({
      "by-name": "Named producer",
      "structure:by-name": "Named producer",
      "managed_by:structure:by-name": "Named producer",
    });
    expect(labels.related_domains).toEqual({});
    expect(labels.authors).toEqual({ last: "HOPPER" });
  });
});

describe("catalogue snapshot edge cases", () => {
  it("matches nothing for a blank key or an empty directory", () => {
    const producers = [{ structure_id: "isae" }];
    expect(matchCachedProducer(producers, "  ")).toBeNull();
    expect(matchCachedProducer([], "isae")).toBeNull();
    expect(matchCachedProducer(undefined, "isae")).toBeNull();
  });

  it("keys probe rows by uuid, then id, and passes unknown rows through", () => {
    const current = [
      { uuid: "u-1", name: "By uuid" },
      { id: "i-1", acronym: "BYID" },
    ];
    const keyless = { count: 2 };
    const merged = mergeProducerDirectory(current, [
      { uuid: "u-1", count: 3 },
      { id: "i-1", count: 4 },
      keyless,
    ]);
    expect(merged[0]).toMatchObject({ name: "By uuid", count: 3 });
    expect(merged[1]).toMatchObject({ acronym: "BYID", count: 4 });
    expect(merged[2]).toBe(keyless);
  });

  it("keeps the probe's raw values when no side has a real name", () => {
    const key = "06d37c13-6ffe-4c4a-a9e3-ac227652f98c";
    const [row] = mergeProducerDirectory(
      [{ structure_id: key }],
      [{ structure_id: key, name: key, acronym: undefined }]
    );
    expect(row.name).toBe(key);
    expect(row.acronym).toBeUndefined();
  });

  it("fills in what a malformed snapshot search leaves out", () => {
    expect(
      snapshotSearch({
        search: { items: "oops", aggregations: null } as never,
      })
    ).toEqual({ items: [], aggregations: {}, results_count: 0 });
    expect(snapshotSearch({ search: "oops" as never })).toBeNull();
  });

  it("treats missing key arrays as empty", () => {
    const bare = { nugget_id: "n1" } as unknown as Nugget;
    const [joined] = applyVocabulary([bare], {
      persons: { a1: { email: "a@example.com" } as never },
    });
    expect(joined.authors_data).toEqual([]);
    expect(joined.domains_data).toEqual([]);
    expect(isEnriched(joined)).toBe(true);
  });
});
