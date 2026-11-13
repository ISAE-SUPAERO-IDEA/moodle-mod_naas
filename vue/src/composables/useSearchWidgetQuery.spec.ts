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
 * Tests for useSearchWidgetQuery: text, filters, landing and paging.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it, vi } from "vitest";
import { ref } from "vue";
import {
  SEARCH_PAGE_SIZE,
  TYPING_DEBOUNCE_MS,
  useSearchWidgetQuery,
} from "./useSearchWidgetQuery";
import { makeNugget, makeSearchResult } from "@/test/widgetFixtures";
import type {
  AggregationResult,
  Nugget,
  SearchOptions,
  SearchResult,
} from "@/types/nugget.types";

const aircraft = makeNugget();
const orbital = makeNugget({ nugget_id: "n-orbital" });
const levels: Record<string, AggregationResult> = {
  level: { buckets: [{ key: "beginner", docCount: 1 }] },
} as unknown as Record<string, AggregationResult>;

/** A query wired to a fake search that appends pages like useNuggetSearch. */
function setup(results: Array<SearchResult | null> = [], near = false) {
  const nuggets = ref<Nugget[]>([]);
  const loading = ref(false);
  const loadingMore = ref(false);
  const facetAggregations = ref<Record<string, AggregationResult>>({});
  const sentinelIsNear = vi.fn(() => near);
  const search = vi.fn(async (_options: SearchOptions, append?: boolean) => {
    const result = results.length > 1 ? results.shift()! : results[0] ?? null;
    if (result) {
      nuggets.value = append
        ? [...nuggets.value, ...(result.items ?? [])]
        : result.items ?? [];
    }
    return result;
  });
  const query = useSearchWidgetQuery({
    search,
    nuggets,
    loading,
    loadingMore,
    facetAggregations,
    sentinelIsNear,
  });
  return {
    query,
    search,
    nuggets,
    loading,
    loadingMore,
    facetAggregations,
    sentinelIsNear,
  };
}

function lastOptions(search: ReturnType<typeof setup>["search"]) {
  return search.mock.lastCall?.[0];
}

const flush = () => new Promise((r) => setTimeout(r, 0));

afterEach(() => {
  vi.useRealTimers();
});

describe("useSearchWidgetQuery", () => {
  it("starts on the landing with an empty first-page query", () => {
    const { query } = setup();
    expect(query.showingBrowse.value).toBe(true);
    expect(query.activeFilterCount.value).toBe(0);
    expect(query.searchOptions.value).toEqual({
      page_size: SEARCH_PAGE_SIZE,
      fulltext: "",
    });
  });

  it("searches typed text once typing pauses, leaving the landing", async () => {
    vi.useFakeTimers();
    const { query, search } = setup([makeSearchResult([aircraft])]);
    query.typed.value = "wing";
    query.onInput();
    expect(query.showingBrowse.value).toBe(false);
    await vi.advanceTimersByTimeAsync(TYPING_DEBOUNCE_MS);
    expect(lastOptions(search)).toEqual({ page_size: 9, fulltext: "wing" });

    // Once on "All", blank text still refreshes the results.
    query.typed.value = "  ";
    query.onInput();
    await vi.advanceTimersByTimeAsync(TYPING_DEBOUNCE_MS);
    expect(search).toHaveBeenCalledTimes(2);
    expect(query.showingBrowse.value).toBe(false);
  });

  it("stays on the landing when only spaces are typed", async () => {
    vi.useFakeTimers();
    const { query, search } = setup();
    query.typed.value = "  ";
    query.onInput();
    await vi.advanceTimersByTimeAsync(TYPING_DEBOUNCE_MS);
    expect(query.showingBrowse.value).toBe(true);
    expect(search).not.toHaveBeenCalled();
  });

  it("clears the text, searching only when the results are shown", async () => {
    const { query, search } = setup([makeSearchResult([aircraft])]);
    query.typed.value = "wing";
    query.onClearSearch();
    expect(query.typed.value).toBe("");
    expect(search).not.toHaveBeenCalled();

    query.onBrowseSelect({ facet: "all", value: "", caption: "" });
    query.onClearSearch();
    expect(search).toHaveBeenCalledTimes(2);
  });

  it("applies panel filters, merging their captions", () => {
    const { query, search } = setup();
    query.onFilterCaptions({ fr: "French" });
    query.onFilters({ level: ["beginner"] }, { beginner: "Beginner" });
    expect(query.bucketCaptions.value).toEqual({
      fr: "French",
      beginner: "Beginner",
    });
    expect(query.activeFilterCount.value).toBe(1);
    expect(query.showingBrowse.value).toBe(false);
    expect(lastOptions(search)).toEqual({
      page_size: 9,
      fulltext: "",
      level: ["beginner"],
    });

    // No captions, no filters: the "All" tab stays open.
    query.onFilters({});
    expect(query.bucketCaptions.value).toEqual({
      fr: "French",
      beginner: "Beginner",
    });
    expect(query.showingBrowse.value).toBe(false);
    expect(search).toHaveBeenCalledTimes(2);
  });

  it("removes one value of a filter, then the whole filter", () => {
    const { query, search } = setup();
    query.onFilters({ level: ["beginner", "advanced"], language: ["fr"] });
    query.removeFilter("level", "beginner");
    expect(query.filters.value).toEqual({
      level: ["advanced"],
      language: ["fr"],
    });
    query.removeFilter("level", "advanced");
    expect(query.filters.value).toEqual({ language: ["fr"] });
    query.removeFilter("producers", "absent");
    expect(query.filters.value).toEqual({ language: ["fr"] });
    expect(search).toHaveBeenCalledTimes(4);
  });

  it("clears all filters back to the landing", () => {
    const { query, search } = setup();
    query.onBrowseSelect({
      facet: "producers",
      value: "isae",
      caption: "ISAE",
    });
    expect(query.bucketCaptions.value).toEqual({ isae: "ISAE" });
    query.clearAllFilters();
    expect(query.filters.value).toEqual({});
    expect(query.bucketCaptions.value).toEqual({});
    expect(query.showingBrowse.value).toBe(true);
    expect(search).toHaveBeenCalledTimes(1);
  });

  it("opens a producer or everything from the landing, and goes back", () => {
    const { query, search } = setup();
    query.onBrowseSelect({ facet: "producers", value: "isae", caption: "I" });
    expect(lastOptions(search)).toEqual({
      page_size: 9,
      fulltext: "",
      producers: ["isae"],
    });
    query.onBrowseSelect({ facet: "all", value: "", caption: "" });
    expect(query.filters.value).toEqual({});
    expect(query.showingBrowse.value).toBe(false);

    query.typed.value = "wing";
    query.backToBrowse();
    expect(query.typed.value).toBe("");
    expect(query.showingBrowse.value).toBe(true);
  });

  it("refreshes landing aggregations from unfiltered first pages only", async () => {
    const withAggs = makeSearchResult([aircraft], { aggregations: levels });
    const { query, facetAggregations } = setup([withAggs]);
    await query.doSearch();
    expect(facetAggregations.value).toEqual(levels);

    facetAggregations.value = {};
    query.onFilters({ level: ["beginner"] });
    await flush();
    expect(facetAggregations.value).toEqual({});

    const noAggs = setup([
      { items: [aircraft], results_count: 1 } as SearchResult,
    ]);
    await noAggs.query.doSearch();
    expect(noAggs.facetAggregations.value).toEqual({});
  });

  it("works out whether more pages exist", async () => {
    const { query } = setup([
      { results_count: 3 } as SearchResult,
      makeSearchResult([aircraft], { results_count: 1 }),
    ]);
    await query.doSearch();
    expect(query.hasMore.value).toBe(true);
    await query.doSearch();
    expect(query.hasMore.value).toBe(false);

    // A failed search (null) leaves the flag as it was.
    const failed = setup([null]);
    failed.query.hasMore.value = true;
    await failed.query.doSearch();
    expect(failed.query.hasMore.value).toBe(true);
  });

  describe("loadMore", () => {
    async function onResults(results: SearchResult[], near = false) {
      const ctx = setup(results, near);
      ctx.query.onBrowseSelect({ facet: "all", value: "", caption: "" });
      await flush();
      return ctx;
    }

    it("appends the next page and stops at the last one", async () => {
      const ctx = await onResults([
        makeSearchResult([aircraft], { results_count: 2 }),
        makeSearchResult([orbital], { results_count: 2 }),
      ]);
      expect(ctx.query.hasMore.value).toBe(true);
      await ctx.query.loadMore();
      expect(lastOptions(ctx.search)).toEqual({
        page_size: 9,
        fulltext: "",
        page: 1,
      });
      expect(ctx.search.mock.lastCall?.[1]).toBe(true);
      expect(ctx.nuggets.value).toEqual([aircraft, orbital]);
      expect(ctx.query.hasMore.value).toBe(false);
      // Not near: no chained page. Nothing more: no call at all.
      await ctx.query.loadMore();
      expect(ctx.search).toHaveBeenCalledTimes(2);
    });

    it("keeps loading while the end of the list stays in view", async () => {
      const ctx = await onResults(
        [
          makeSearchResult([aircraft], { results_count: 3 }),
          makeSearchResult([orbital], { results_count: 3 }),
          makeSearchResult([makeNugget({ nugget_id: "n3" })], {
            results_count: 3,
          }),
        ],
        true
      );
      await ctx.query.loadMore();
      await flush();
      expect(ctx.search).toHaveBeenCalledTimes(3);
      expect(ctx.nuggets.value).toHaveLength(3);
      expect(ctx.sentinelIsNear).toHaveBeenCalled();
    });

    it("does not chain when a page brought nothing new", async () => {
      const ctx = await onResults(
        [
          makeSearchResult([aircraft], { results_count: 5 }),
          makeSearchResult([], { results_count: 5 }),
        ],
        true
      );
      await ctx.query.loadMore();
      await flush();
      expect(ctx.search).toHaveBeenCalledTimes(2);
      expect(ctx.sentinelIsNear).not.toHaveBeenCalled();
    });

    it("waits while a load runs, and never runs on the landing", async () => {
      const ctx = await onResults([
        makeSearchResult([aircraft], { results_count: 9 }),
      ]);
      ctx.loading.value = true;
      await ctx.query.loadMore();
      ctx.loading.value = false;
      ctx.loadingMore.value = true;
      await ctx.query.loadMore();
      ctx.loadingMore.value = false;
      expect(ctx.search).toHaveBeenCalledTimes(1);

      // Two calls in a row: the second one waits for the first.
      const first = ctx.query.loadMore();
      await ctx.query.loadMore();
      await first;
      expect(ctx.search).toHaveBeenCalledTimes(2);

      ctx.query.backToBrowse();
      await ctx.query.loadMore();
      expect(ctx.search).toHaveBeenCalledTimes(2);
    });

    it("releases the guard when the search throws", async () => {
      const ctx = await onResults([
        makeSearchResult([aircraft], { results_count: 9 }),
      ]);
      ctx.search.mockRejectedValueOnce(new Error("down"));
      await expect(ctx.query.loadMore()).rejects.toThrow("down");
      await ctx.query.loadMore();
      expect(ctx.search).toHaveBeenCalledTimes(3);
    });
  });
});
