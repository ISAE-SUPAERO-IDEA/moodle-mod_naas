import { describe, expect, it } from "vitest";
import {
  AUTHOR_PREVIEW_LIMIT,
  applyResolvedLabels,
  captionForBucket,
  facetNeedsNetworkLabels,
  hasMoreAuthors,
  mapAggregationBuckets,
  siteRightsFilterActive,
  visibleBuckets,
} from "./searchAggregations";

describe("searchAggregations", () => {
  it("does not require network lookups for level language or type", () => {
    expect(facetNeedsNetworkLabels("level")).toBe(false);
    expect(facetNeedsNetworkLabels("language")).toBe(false);
    expect(facetNeedsNetworkLabels("type")).toBe(false);
    expect(facetNeedsNetworkLabels("authors")).toBe(true);
    expect(facetNeedsNetworkLabels("producers")).toBe(true);
    expect(facetNeedsNetworkLabels("related_domains")).toBe(true);
  });

  it("maps buckets synchronously so filter pills can render without a second search", () => {
    const mapped = mapAggregationBuckets(
      "level",
      [
        { key: "advanced", docCount: 4 },
        { key: "beginner", docCount: 20 },
      ],
      ["advanced"],
      (key) => ({ advanced: "Advanced", beginner: "Beginner" }[key] ?? key)
    );

    expect(mapped.labelsResolved).toBe(true);
    expect(mapped.buckets[0].key).toBe("advanced");
    expect(mapped.buckets[0].selected).toBe(true);
    expect(mapped.buckets[0].query_value).toBe("advanced");
    expect(mapped.buckets[0].caption).toBe("Advanced (4)");
    expect(mapped.buckets[1].caption).toBe("Beginner (20)");
  });

  it("keeps raw keys for author facets until labels are resolved lazily", () => {
    const mapped = mapAggregationBuckets(
      "authors",
      [{ key: "author-1", docCount: 2 }],
      [],
      (key) => key
    );
    expect(mapped.labelsResolved).toBe(false);
    expect(mapped.buckets[0].caption).toBe("author-1 (2)");

    const resolved = applyResolvedLabels(mapped, {
      "author-1": "ADA LOVELACE",
    });
    expect(resolved.labelsResolved).toBe(true);
    expect(resolved.buckets[0].caption).toBe("ADA LOVELACE (2)");

    const withTitle = applyResolvedLabels(
      mapped,
      { "author-1": "ISAE" },
      { "author-1": "ISAE-SUPAERO" }
    );
    expect(withTitle.buckets[0].caption).toBe("ISAE (2)");
    expect(withTitle.buckets[0].title).toBe("ISAE-SUPAERO");
  });

  it("reads snake_case doc_count from the API payload", () => {
    const mapped = mapAggregationBuckets(
      "level",
      [{ key: "intermediate", doc_count: 7 }],
      [],
      (key) => key
    );
    expect(mapped.buckets[0].docCount).toBe(7);
    expect(mapped.buckets[0].caption).toBe(captionForBucket("intermediate", 7));
  });

  it("previews the first author buckets until show-all", () => {
    const buckets = Array.from({ length: 8 }, (_, i) => ({
      key: `a${i}`,
      docCount: 1,
    }));
    const mapped = mapAggregationBuckets("authors", buckets, [], (key) => key);
    expect(hasMoreAuthors(mapped)).toBe(true);
    expect(visibleBuckets(mapped)).toHaveLength(AUTHOR_PREVIEW_LIMIT);
    mapped.showAll = true;
    expect(visibleBuckets(mapped)).toHaveLength(8);
  });
});

describe("site rights filter", () => {
  it("leaves the CC facet when both admin filters are all", () => {
    expect(
      siteRightsFilterActive({ commercial: "all", access: "all" })
    ).toBe(false);
    expect(siteRightsFilterActive(undefined)).toBe(false);
  });

  it("hides the CC facet when commercial or access is restricted", () => {
    expect(
      siteRightsFilterActive({ commercial: "noncommercial", access: "all" })
    ).toBe(true);
    expect(
      siteRightsFilterActive({ commercial: "all", access: "restricted" })
    ).toBe(true);
  });
});
