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
 * Tests for the search aggregation helpers (bucket mapping, labels, aliases).
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it } from "vitest";
import {
  withFallbackLabels,
  AUTHOR_PREVIEW_LIMIT,
  aggregationFor,
  applyResolvedLabels,
  captionForBucket,
  facetNeedsNetworkLabels,
  hasMoreAuthors,
  mapAggregationBuckets,
  selectedKeysFor,
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

  it("counts a bucket without any document count as empty", () => {
    const mapped = mapAggregationBuckets(
      "level",
      [{ key: "beginner" }],
      [],
      (key) => key
    );
    expect(mapped.buckets[0].docCount).toBe(0);
    expect(mapped.buckets[0].caption).toBe("beginner (0)");
  });

  it("keeps the open and show-all state of a facet being remapped", () => {
    const mapped = mapAggregationBuckets(
      "authors",
      [{ key: "a1", docCount: 1 }],
      [],
      (key) => key,
      { visible: true, showAll: true, labelsResolved: true }
    );
    expect(mapped).toMatchObject({
      visible: true,
      showAll: true,
      labelsResolved: true,
    });
  });

  it("falls back to the bucket key when neither a label nor a help exists", () => {
    const resolved = applyResolvedLabels(
      {
        name: "producers",
        buckets: [{ key: "isae", docCount: 3, title: "ISAE-SUPAERO" }],
        visible: false,
        showAll: false,
        labelsResolved: false,
      },
      {}
    );
    expect(resolved.buckets[0]).toMatchObject({
      help: "isae",
      caption: "isae (3)",
      title: "ISAE-SUPAERO",
    });
    // The caption is still the raw key, so the facet waits for a real name.
    expect(resolved.labelsResolved).toBe(false);
  });

  it("keeps resolved author names ahead of leftover hashes in the preview", () => {
    const hash = "0123456789abcdef0123456789abcdef";
    const mapped = mapAggregationBuckets(
      "authors",
      [
        { key: hash, docCount: 9 },
        { key: "author-1", docCount: 2 },
      ],
      [],
      (key) => key
    );
    const resolved = applyResolvedLabels(mapped, {
      "author-1": "ADA LOVELACE",
    });
    expect(resolved.buckets[0].caption).toBe("ADA LOVELACE (2)");
    expect(visibleBuckets(resolved)[0].help).toBe("ADA LOVELACE");
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

  it("reads field-of-study buckets from related_domains or the domains alias", () => {
    const related = {
      buckets: [{ key: "d1", docCount: 3 }],
    };
    const aliased = {
      buckets: [{ key: "d2", docCount: 8 }],
    };

    expect(
      aggregationFor({ related_domains: related }, "related_domains")
    ).toBe(related);
    expect(
      aggregationFor({ domains: aliased }, "related_domains")?.buckets[0].key
    ).toBe("d2");
    expect(
      aggregationFor({ level: related }, "related_domains")
    ).toBeUndefined();
    expect(selectedKeysFor({ domains: ["d2"] }, "related_domains")).toEqual([
      "d2",
    ]);
    expect(
      selectedKeysFor(
        { related_domains: ["d1"], domains: ["d2"] },
        "related_domains"
      )
    ).toEqual(["d1"]);
  });

  it("sorts unnamed buckets after named ones and finds no alias for other facets", () => {
    const hash = "0123456789abcdef0123456789abcdef";
    const mapped = mapAggregationBuckets(
      "authors",
      [
        { key: "author-1", docCount: 2 },
        { key: hash, docCount: 9 },
      ],
      [],
      (key) => (key === "author-1" ? "ADA LOVELACE" : key)
    );
    expect(mapped.buckets.map((b) => b.key)).toEqual(["author-1", hash]);
    expect(aggregationFor({ domains: { buckets: [] } }, "authors")).toBe(
      undefined
    );
    expect(selectedKeysFor({}, "related_domains")).toEqual([]);
  });

  describe("withFallbackLabels", () => {
    const hash = "a3f5c2e19b7d4e6f8a0b1c2d3e4f5a6b";

    it("names unresolved and raw keys with the fallback, keeps real names", () => {
      expect(
        withFallbackLabels(
          { "jane@example.test": "Jane Doe", [hash]: hash },
          ["jane@example.test", hash, "missing"],
          "Unknown author"
        )
      ).toEqual({
        "jane@example.test": "Jane Doe",
        [hash]: "Unknown author",
        missing: "Unknown author",
      });
    });

    it("does not modify the labels it receives", () => {
      const labels = { k: "" };
      withFallbackLabels(labels, ["k"], "Unknown producer");
      expect(labels).toEqual({ k: "" });
    });
  });
});
