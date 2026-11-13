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
 * Tests for one facet column of the filter panel.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, it, expect, afterEach, vi } from "vitest";
import FilterFacetColumn from "./FilterFacetColumn.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import type { MappedAggregation } from "@/composables/searchAggregations";
import type { AggregationBucket } from "@/types/nugget.types";

const naasConfig = {
  labels: {
    show_more_authors: "Show more authors",
    hide_authors: "Hide authors",
    metadata: { level: "Level", authors: "Authors" },
  },
};

function bucket(
  key: string,
  extra: Partial<AggregationBucket> = {}
): AggregationBucket {
  return {
    key,
    docCount: 1,
    help: key.toUpperCase(),
    caption: `${key.toUpperCase()} (1)`,
    ...extra,
  };
}

function facet(
  name: string,
  buckets: AggregationBucket[],
  extra: Partial<MappedAggregation> = {}
): MappedAggregation {
  return {
    name,
    buckets,
    visible: false,
    showAll: false,
    labelsResolved: true,
    ...extra,
  };
}

let mounted: Mounted | null = null;

function mountColumn(
  aggregation: MappedAggregation,
  config: Record<string, unknown> = naasConfig
) {
  mounted = mountComponent(
    FilterFacetColumn,
    { aggregation },
    { naasConfig: config }
  );
  return mounted;
}

afterEach(() => {
  mounted?.unmount();
  mounted = null;
});

describe("FilterFacetColumn", () => {
  it("renders a closed pill with the lang label of the facet", () => {
    const { el } = mountColumn(facet("level", [bucket("beginner")]));
    const pill = el.querySelector(".filter-pill") as HTMLElement;
    expect(el.firstElementChild?.classList.contains("filter-column")).toBe(
      true
    );
    expect(pill.textContent?.trim()).toBe("Level");
    expect(pill.className).toBe("filter-pill");
    expect(el.querySelector(".fa-chevron-down")).not.toBeNull();
    expect(el.querySelector(".filter-pill-count")).toBeNull();
    expect(
      (el.querySelector(".filter-dropdown") as HTMLElement).style.display
    ).toBe("none");
    expect(el.querySelector(".show-more-btn")).toBeNull();
  });

  it("falls back to the facet name without a lang label", () => {
    const { el } = mountColumn(facet("type", [bucket("lesson")]));
    expect(el.querySelector(".filter-pill")?.textContent?.trim()).toBe("type");
  });

  it("shows the open and active state with the selected count", () => {
    const { el } = mountColumn(
      facet(
        "level",
        [
          bucket("a", { selected: true }),
          bucket("b", { selected: true }),
          bucket("c"),
        ],
        { visible: true }
      )
    );
    const pill = el.querySelector(".filter-pill") as HTMLElement;
    expect(pill.classList.contains("filter-pill--open")).toBe(true);
    expect(pill.classList.contains("filter-pill--active")).toBe(true);
    expect(el.querySelector(".filter-pill-count")?.textContent?.trim()).toBe(
      "2"
    );
    expect(el.querySelector(".fa-chevron-up")).not.toBeNull();
    const boxes = [
      ...el.querySelectorAll<HTMLInputElement>("input[type=checkbox]"),
    ];
    expect(boxes.map((b) => b.checked)).toEqual([true, true, false]);
  });

  it("renders captions, tooltips and placeholders for unnamed values", () => {
    const hash = "a".repeat(32);
    const { el } = mountColumn(
      facet("producers", [
        bucket("isae", { title: "ISAE-SUPAERO" }),
        bucket(hash, { help: hash, caption: `${hash} (4)`, docCount: 4 }),
      ])
    );
    const rows = [...el.querySelectorAll(".filter-option")];
    expect(rows.map((r) => r.textContent?.trim())).toEqual([
      "ISAE (1)",
      "… (4)",
    ]);
    expect(rows.map((r) => r.getAttribute("title"))).toEqual([
      "ISAE-SUPAERO",
      null,
    ]);
  });

  it("lists option names without counts while a licence filter is set", () => {
    const { el } = mountColumn(facet("producers", [bucket("isae")]), {
      ...naasConfig,
      license_filter: { commercial: "all", access: "unrestricted" },
    });
    const rows = [...el.querySelectorAll(".filter-option")];
    expect(rows.map((r) => r.textContent?.trim())).toEqual(["ISAE"]);
  });

  it("closes its open list with Escape and gives the focus back to the pill", () => {
    const { el, emitted } = mountColumn(
      facet("level", [bucket("a")], { visible: true })
    );
    const option = el.querySelector<HTMLInputElement>(".filter-option input")!;
    option.focus();
    const escape = new KeyboardEvent("keydown", {
      key: "Escape",
      bubbles: true,
    });
    const outer = vi.fn();
    document.body.addEventListener("keydown", outer);
    option.dispatchEvent(escape);
    document.body.removeEventListener("keydown", outer);

    expect(emitted.toggle).toHaveLength(1);
    expect(document.activeElement).toBe(el.querySelector(".filter-pill"));
    // The Escape stays inside the list: an enclosing panel does not close.
    expect(outer).not.toHaveBeenCalled();
  });

  it("leaves Escape alone while its list is closed", () => {
    const { el, emitted } = mountColumn(facet("level", [bucket("a")]));
    el.querySelector<HTMLElement>(".filter-pill")!.dispatchEvent(
      new KeyboardEvent("keydown", { key: "Escape", bubbles: true })
    );
    expect(emitted.toggle).toBeUndefined();
  });

  it("emits toggle when the pill is clicked", () => {
    const { el, emitted } = mountColumn(facet("level", [bucket("a")]));
    (el.querySelector(".filter-pill") as HTMLElement).click();
    expect(emitted.toggle).toEqual([[]]);
  });

  it("emits the query value of a ticked option, or its key", () => {
    const { el, emitted } = mountColumn(
      facet("level", [
        bucket("a", { query_value: "query-a" }),
        bucket("b", { query_value: undefined }),
      ])
    );
    const boxes = el.querySelectorAll<HTMLInputElement>("input[type=checkbox]");
    boxes[0].click();
    boxes[1].click();
    expect(emitted.select).toEqual([["query-a"], ["b"]]);
  });

  it("previews six authors and emits toggleAuthors from the show-more button", () => {
    const authors = Array.from({ length: 8 }, (_, i) => bucket(`p${i}`));
    const { el, emitted } = mountColumn(facet("authors", authors));
    expect(el.querySelectorAll(".filter-option")).toHaveLength(6);
    const more = el.querySelector(".show-more-btn") as HTMLElement;
    expect(more.textContent?.trim()).toBe("+ Show more authors");
    more.click();
    expect(emitted.toggleAuthors).toEqual([[]]);
  });

  it("lists every author with a hide button once expanded", () => {
    const authors = Array.from({ length: 8 }, (_, i) => bucket(`p${i}`));
    const { el } = mountColumn(facet("authors", authors, { showAll: true }));
    expect(el.querySelectorAll(".filter-option")).toHaveLength(8);
    expect(el.querySelector(".show-more-btn")?.textContent?.trim()).toBe(
      "− Hide authors"
    );
  });
});
