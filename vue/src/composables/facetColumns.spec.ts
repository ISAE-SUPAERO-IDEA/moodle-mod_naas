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
 * Tests for the facet filter panel helpers.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, it, expect } from "vitest";
import {
  buildFacetColumns,
  clearSelection,
  closeFacets,
  collectSelection,
  findFacet,
  hasAnySelection,
  hasSelectedBuckets,
  optionCaption,
  selectedBucketCount,
  syncSelection,
  type FacetLabelLookup,
} from "./facetColumns";
import type { MappedAggregation } from "./searchAggregations";
import type { AggregationBucket } from "@/types/nugget.types";

const HASH = "a".repeat(32);

const lookup: FacetLabelLookup = {
  localLabel: (_name, key) => (key === HASH ? key : key.toUpperCase()),
  hasHumanLabel: (_name, key) => key !== HASH,
};

function facet(
  name: string,
  buckets: Array<Partial<AggregationBucket> & { key: string }>,
  extra: Partial<MappedAggregation> = {}
): MappedAggregation {
  return {
    name,
    buckets: buckets.map((b) => ({ docCount: 1, ...b })),
    visible: false,
    showAll: false,
    labelsResolved: true,
    ...extra,
  };
}

describe("buildFacetColumns", () => {
  it("keeps the facet order and skips empty or unknown facets", () => {
    const columns = buildFacetColumns(
      {
        type: { buckets: [{ key: "lesson", docCount: 2 }] },
        other: { buckets: [{ key: "x", docCount: 1 }] },
        references: { buckets: [] },
        authors: undefined,
        level: { buckets: [{ key: "beginner", docCount: 1 }] },
      },
      { level: ["beginner"] },
      [],
      lookup
    );
    expect(columns.map((c) => c.name)).toEqual(["level", "type"]);
    expect(columns[0].buckets[0]).toMatchObject({
      key: "beginner",
      help: "BEGINNER",
      caption: "BEGINNER (1)",
      selected: true,
    });
    expect(columns.every((c) => !c.visible && !c.showAll)).toBe(true);
  });

  it("reads the domains alias and its selection", () => {
    const [domains] = buildFacetColumns(
      { domains: { buckets: [{ key: "01", docCount: 1 }] } },
      { domains: ["01"] },
      [],
      lookup
    );
    expect(domains.name).toBe("related_domains");
    expect(domains.buckets[0].selected).toBe(true);
  });

  it("keeps the open state and flags entity facets that still need names", () => {
    const previous = [facet("authors", [], { visible: true, showAll: true })];
    const [producers, authors] = buildFacetColumns(
      {
        authors: { buckets: [{ key: HASH, docCount: 1 }] },
        producers: { buckets: [{ key: "isae", docCount: 1 }] },
      },
      {},
      previous,
      lookup
    );
    expect(authors).toMatchObject({
      visible: true,
      showAll: true,
      labelsResolved: false,
    });
    expect(producers).toMatchObject({ visible: false, labelsResolved: true });
  });
});

describe("optionCaption", () => {
  it("uses the caption of a named value, or its label", () => {
    expect(
      optionCaption({
        key: "en",
        help: "English",
        caption: "English (2)",
        docCount: 2,
      })
    ).toBe("English (2)");
    expect(
      optionCaption({ key: "en", help: "English", caption: "", docCount: 2 })
    ).toBe("English");
  });

  it("shows an ellipsis while a value has no name", () => {
    expect(optionCaption({ key: HASH, help: HASH, docCount: 3 })).toBe("… (3)");
    expect(optionCaption({ key: "en", docCount: 0 })).toBe("…");
  });

  it("drops the count when counts are hidden", () => {
    const named = {
      key: "en",
      help: "English",
      caption: "English (2)",
      docCount: 2,
    };
    expect(optionCaption(named, false)).toBe("English");
    expect(optionCaption({ key: HASH, help: HASH, docCount: 3 }, false)).toBe(
      "…"
    );
  });
});

describe("selection helpers", () => {
  const columns = () => [
    facet("level", [
      { key: "beginner", selected: true, help: "Beginner" },
      { key: "advanced" },
    ]),
    facet("authors", [
      { key: "p1", selected: true, caption: "P1 (1)" },
      { key: "p2", selected: true },
    ]),
    facet("type", [{ key: "lesson" }]),
  ];

  it("counts and detects selected buckets", () => {
    const [level, authors, type] = columns();
    expect(hasSelectedBuckets(level)).toBe(true);
    expect(hasSelectedBuckets(type)).toBe(false);
    expect(selectedBucketCount(authors)).toBe(2);
    expect(hasAnySelection([level, type])).toBe(true);
    expect(hasAnySelection([type])).toBe(false);
    expect(findFacet([level, type], "type")).toBe(type);
    expect(findFacet([level], "type")).toBeUndefined();
  });

  it("collects filters with the best caption of each value", () => {
    expect(collectSelection(columns())).toEqual({
      filters: { level: ["beginner"], authors: ["p1", "p2"] },
      captions: { beginner: "Beginner", p1: "P1 (1)", p2: "p2" },
    });
    expect(collectSelection([])).toEqual({ filters: {}, captions: {} });
  });

  it("syncs the ticks with the active filters, domains alias included", () => {
    const cols = [
      ...columns(),
      facet("related_domains", [{ key: "01" }, { key: "02" }]),
    ];
    syncSelection(cols, { type: ["lesson"], domains: ["02"] });
    expect(collectSelection(cols).filters).toEqual({
      type: ["lesson"],
      related_domains: ["02"],
    });
  });

  it("clears every tick and closes every dropdown", () => {
    const cols = columns().map((c) => ({ ...c, visible: true }));
    clearSelection(cols);
    closeFacets(cols);
    expect(hasAnySelection(cols)).toBe(false);
    expect(cols.some((c) => c.visible)).toBe(false);
  });
});
