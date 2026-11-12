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
 * Tests for the facet filter panel.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, it, expect, vi, afterEach } from "vitest";
import { reactive } from "vue";
import NuggetSearchFilter from "./NuggetSearchFilter.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import { NAAS_API_KEY } from "@/shared/api";
import type { AggregationResult, CatalogueFacetLabels } from "@/shared/api";

const naasConfig = {
  courseId: 7,
  labels: {
    clear_filters: "Clear filters",
    show_more_authors: "Show more authors",
    hide_authors: "Hide authors",
    unknown_author: "Unknown author",
    unknown_producer: "Unknown producer",
    metadata: {
      filters: "Filters",
      level: "Level",
      language: "Language",
      producers: "Producers",
      authors: "Authors",
      related_domains: "Field of study",
      beginner: "Beginner",
      advanced: "Advanced",
    },
  },
};

const ISAE = "06d37c13-6ffe-4c4a-a9e3-ac227652f98c";
const GHOST = "16d37c13-6ffe-4c4a-a9e3-ac227652f98c";
const hash = (n: number) => n.toString(16).padStart(32, "0");

interface FilterProps {
  networkAggregations: Record<string, AggregationResult>;
  activeFilters: Record<string, string[]>;
  loading?: boolean;
  prefetch?: boolean;
  networkLabels?: CatalogueFacetLabels;
}

function agg(...buckets: Array<[string, number]>): AggregationResult {
  return { buckets: buckets.map(([key, docCount]) => ({ key, docCount })) };
}

let mounted: Mounted | null = null;

function mountFilter(props: Partial<FilterProps>, service: object = {}) {
  const state = reactive<FilterProps>({
    networkAggregations: {},
    activeFilters: {},
    ...props,
  });
  mounted = mountComponent(NuggetSearchFilter, state, {
    [NAAS_API_KEY]: service,
    naasConfig,
  });
  return { ...mounted, state };
}

afterEach(() => {
  mounted?.unmount();
  mounted = null;
});

const texts = (root: ParentNode, selector: string) =>
  [...root.querySelectorAll(selector)].map((n) => n.textContent?.trim());

function column(el: HTMLElement, label: string): HTMLElement {
  const pill = [...el.querySelectorAll<HTMLElement>(".filter-pill")].find((p) =>
    p.textContent?.trim().startsWith(label)
  );
  if (!pill) throw new Error(`no facet ${label}`);
  return pill.closest(".filter-column") as HTMLElement;
}

const pill = (col: HTMLElement) =>
  col.querySelector(".filter-pill") as HTMLButtonElement;
const options = (col: HTMLElement) => texts(col, ".filter-option span");
const checkboxes = (col: HTMLElement) => [
  ...col.querySelectorAll<HTMLInputElement>("input[type=checkbox]"),
];
const clearButton = (el: HTMLElement) =>
  el.querySelector(".filter-clear-btn") as HTMLButtonElement;

describe("NuggetSearchFilter", () => {
  it("shows three skeleton columns while the first search is loading", () => {
    const { el } = mountFilter({ loading: true });
    expect(el.querySelectorAll(".filter-column")).toHaveLength(3);
    expect(el.querySelectorAll(".filter-skeleton")).toHaveLength(3);
    expect(el.querySelector(".filter-pill")).toBeNull();
  });

  it("renders an empty panel when there is nothing to filter", () => {
    const { el } = mountFilter({
      networkAggregations: { level: { buckets: [] } },
    });
    expect(el.querySelector(".filter-title")?.textContent?.trim()).toBe(
      "Filters"
    );
    expect(el.querySelectorAll(".filter-column")).toHaveLength(0);
    expect(clearButton(el).style.display).toBe("none");
  });

  it("renders one column per facet in a fixed order with lang labels", async () => {
    const { el, flush } = mountFilter({
      loading: true,
      networkAggregations: {
        type: agg(["lesson", 4]),
        unknown_facet: agg(["x", 1]),
        level: {
          buckets: [
            { key: "beginner", docCount: 3 },
            { key: "advanced", doc_count: 1 } as never,
          ],
        },
      },
    });
    expect(el.querySelector(".filter-skeleton")).toBeNull();
    expect(texts(el, ".filter-pill")).toEqual(["Level", "type"]);
    const level = column(el, "Level");
    expect(level.querySelector(".filter-dropdown")).toHaveProperty(
      "style.display",
      "none"
    );
    pill(level).click();
    await flush();
    expect(options(level)).toEqual(["Advanced (1)", "Beginner (3)"]);
    // A value without a lang string is shown as "…", like an unnamed entity.
    expect(options(column(el, "type"))).toEqual(["… (4)"]);
  });

  it("opens one facet at a time and closes on an outside click", async () => {
    const { el, flush } = mountFilter({
      networkAggregations: {
        level: agg(["beginner", 1]),
        language: agg(["en", 1]),
      },
    });
    const level = column(el, "Level");
    const language = column(el, "Language");

    pill(level).click();
    await flush();
    expect(pill(level).classList.contains("filter-pill--open")).toBe(true);
    expect(level.querySelector(".filter-dropdown")).toHaveProperty(
      "style.display",
      ""
    );
    expect(level.querySelector(".fa-chevron-up")).not.toBeNull();

    pill(language).click();
    await flush();
    expect(pill(level).classList.contains("filter-pill--open")).toBe(false);
    expect(pill(language).classList.contains("filter-pill--open")).toBe(true);

    pill(language).click();
    await flush();
    expect(pill(language).classList.contains("filter-pill--open")).toBe(false);

    pill(level).click();
    await flush();
    document.body.click();
    await flush();
    expect(el.querySelector(".filter-pill--open")).toBeNull();
  });

  it("emits the selection when values are ticked, and clears it", async () => {
    const { el, emitted, flush } = mountFilter({
      networkAggregations: {
        level: agg(["beginner", 3], ["advanced", 1]),
        language: agg(["en", 2]),
      },
    });
    const level = column(el, "Level");
    const [advanced, beginner] = checkboxes(level);

    beginner.click();
    await flush();
    expect(emitted.filters).toEqual([
      [{ level: ["beginner"] }, { beginner: "Beginner" }],
    ]);
    expect(pill(level).classList.contains("filter-pill--active")).toBe(true);
    expect(level.querySelector(".filter-pill-count")?.textContent?.trim()).toBe(
      "1"
    );
    expect(clearButton(el).style.display).toBe("");

    advanced.click();
    checkboxes(column(el, "Language"))[0].click();
    await flush();
    expect(emitted.filters[2]).toEqual([
      { level: ["advanced", "beginner"], language: ["en"] },
      { beginner: "Beginner", advanced: "Advanced", en: "en" },
    ]);
    expect(level.querySelector(".filter-pill-count")?.textContent?.trim()).toBe(
      "2"
    );

    clearButton(el).click();
    await flush();
    expect(emitted.filters[3]).toEqual([{}, {}]);
    expect(checkboxes(level).map((c) => c.checked)).toEqual([false, false]);
    expect(el.querySelector(".filter-pill--active")).toBeNull();
    expect(clearButton(el).style.display).toBe("none");
  });

  it("ticks the values of activeFilters and follows their changes", async () => {
    const { el, state, flush } = mountFilter({
      networkAggregations: {
        level: agg(["beginner", 3], ["advanced", 1]),
        domains: agg(["01.02", 2]),
      },
      networkLabels: { domains: { "01.02": "Aerospace" } },
      activeFilters: { level: ["advanced"], domains: ["01.02"] },
    });
    expect(checkboxes(column(el, "Level")).map((c) => c.checked)).toEqual([
      true,
      false,
    ]);
    expect(checkboxes(column(el, "Field of study"))[0].checked).toBe(true);

    state.activeFilters = { level: ["beginner"] };
    await flush();
    expect(checkboxes(column(el, "Level")).map((c) => c.checked)).toEqual([
      false,
      true,
    ]);
    expect(checkboxes(column(el, "Field of study"))[0].checked).toBe(false);
  });

  it("uses catalogue labels for domains without asking NaaS", async () => {
    const getDomain = vi.fn();
    const { el, flush } = mountFilter(
      {
        networkAggregations: { domains: agg(["01.02", 2]) },
        networkLabels: { domains: { "01.02": "Aerospace" } },
      },
      { getDomain }
    );
    const domains = column(el, "Field of study");
    pill(domains).click();
    await flush();
    expect(options(domains)).toEqual(["Aerospace (2)"]);
    expect(getDomain).not.toHaveBeenCalled();
  });

  it("resolves domain labels when the facet is first opened", async () => {
    const getDomain = vi.fn().mockResolvedValue({ label: "Aerospace" });
    const { el, flush } = mountFilter(
      { networkAggregations: { related_domains: agg(["01.02", 2]) } },
      { getDomain }
    );
    const domains = column(el, "Field of study");
    expect(options(domains)).toEqual(["… (2)"]);

    pill(domains).click();
    await flush();
    expect(options(domains)).toEqual(["Aerospace (2)"]);

    pill(domains).click();
    pill(domains).click();
    await flush();
    expect(getDomain).toHaveBeenCalledTimes(1);
  });

  it("names producers with a tooltip and a fallback for unknown ones", async () => {
    const getStructure = vi.fn(async (key: string) => {
      if (key === ISAE) return { acronym: "ISAE", name: "ISAE-SUPAERO" };
      throw new Error("404");
    });
    const { el, flush } = mountFilter(
      { networkAggregations: { producers: agg([ISAE, 3], [GHOST, 1]) } },
      { getStructure }
    );
    const producers = column(el, "Producers");
    pill(producers).click();
    await flush();
    expect(options(producers)).toEqual(["ISAE (3)", "Unknown producer (1)"]);
    const titles = [...producers.querySelectorAll(".filter-option")].map((o) =>
      o.getAttribute("title")
    );
    expect(titles).toEqual(["ISAE-SUPAERO", null]);
  });

  it("previews six authors, shows the rest on demand and names them", async () => {
    const keys = Array.from({ length: 8 }, (_, i) => hash(i + 1));
    const getPerson = vi.fn(async (key: string) =>
      key === hash(8) ? {} : { firstname: "Ada", lastname: `N${key.slice(-1)}` }
    );
    const { el, flush } = mountFilter(
      {
        networkAggregations: {
          authors: agg(...keys.map((k) => [k, 1] as [string, number])),
        },
      },
      { getPerson }
    );
    const authors = column(el, "Authors");
    pill(authors).click();
    await flush();
    expect(options(authors)).toEqual(
      [1, 2, 3, 4, 5, 6].map((n) => `ADA N${n} (1)`)
    );
    const more = authors.querySelector(".show-more-btn") as HTMLButtonElement;
    expect(more.textContent?.trim()).toBe("+ Show more authors");

    more.click();
    await flush();
    expect(options(authors)).toHaveLength(8);
    expect(options(authors)[7]).toBe("Unknown author (1)");
    expect(more.textContent?.trim()).toBe("− Hide authors");
    // Every key was named (or given the fallback) on the first opening.
    expect(getPerson).toHaveBeenCalledTimes(8);

    more.click();
    await flush();
    expect(options(authors)).toHaveLength(6);
  });

  it("emits fresh captions when a selected value gets its name", async () => {
    const getPerson = vi
      .fn()
      .mockResolvedValue({ firstname: "Ada", lastname: "Lovelace" });
    const { el, emitted, flush } = mountFilter(
      {
        networkAggregations: { authors: agg([hash(1), 2]) },
        activeFilters: { authors: [hash(1)] },
      },
      { getPerson }
    );
    pill(column(el, "Authors")).click();
    await flush();
    expect(emitted.captions).toEqual([[{ [hash(1)]: "ADA LOVELACE" }]]);
  });

  it("does not emit captions when nothing selected was renamed", async () => {
    const getPerson = vi.fn().mockResolvedValue({ firstname: "Ada" });
    const { el, emitted, flush } = mountFilter(
      { networkAggregations: { authors: agg([hash(1), 2]) } },
      { getPerson }
    );
    pill(column(el, "Authors")).click();
    await flush();
    expect(emitted.captions).toBeUndefined();
  });

  it("prefetches entity labels once per key when the panel opens", async () => {
    const getPerson = vi.fn().mockResolvedValue({ name: "Ada" });
    const getStructure = vi.fn().mockResolvedValue({ name: "ISAE-SUPAERO" });
    const { el, state, flush } = mountFilter(
      {
        prefetch: true,
        networkAggregations: {
          authors: agg([hash(1), 1]),
          producers: agg([ISAE, 1]),
          level: agg(["beginner", 1]),
        },
      },
      { getPerson, getStructure }
    );
    await flush();
    expect(getPerson).toHaveBeenCalledTimes(1);
    expect(getStructure).toHaveBeenCalledTimes(1);
    expect(options(column(el, "Producers"))).toEqual(["ISAE-SUPAERO (1)"]);

    state.prefetch = false;
    await flush();
    state.prefetch = true;
    await flush();
    expect(getPerson).toHaveBeenCalledTimes(1);

    // New aggregations are prefetched as they arrive.
    state.networkAggregations = { authors: agg([hash(1), 1], [hash(2), 1]) };
    await flush();
    expect(getPerson).toHaveBeenCalledTimes(2);
  });

  it("relabels the buckets when catalogue labels arrive", async () => {
    const { el, state, flush } = mountFilter({
      networkAggregations: {
        producers: agg([`managed_by:structure:${ISAE}`, 2]),
      },
    });
    expect(options(column(el, "Producers"))).toEqual(["… (2)"]);
    state.networkLabels = { producers: { [ISAE]: "ISAE" } };
    await flush();
    expect(options(column(el, "Producers"))).toEqual(["ISAE (2)"]);
  });

  it("keeps a facet open when new aggregations arrive", async () => {
    const { el, state, flush } = mountFilter({
      networkAggregations: { level: agg(["beginner", 1]) },
    });
    pill(column(el, "Level")).click();
    await flush();
    state.networkAggregations = { level: agg(["beginner", 5]) };
    await flush();
    const level = column(el, "Level");
    expect(pill(level).classList.contains("filter-pill--open")).toBe(true);
    expect(options(level)).toEqual(["Beginner (5)"]);
  });
});
