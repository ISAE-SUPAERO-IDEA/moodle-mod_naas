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
import type { Nugget } from "@/types/nugget.types";

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
});
