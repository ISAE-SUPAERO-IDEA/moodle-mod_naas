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
 * What the search widget asks NaaS for: typed text, facet filters, the
 * catalogue landing versus the results, and infinite-scroll paging.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { computed, nextTick, ref } from "vue";
import type { Ref } from "vue";
import { debounce } from "@/utils/debounce";
import { SEARCH_FIRST_PAGE, withSearchPage } from "./searchPaging";
import type {
  AggregationResult,
  Nugget,
  SearchOptions,
  SearchResult,
} from "@/types/nugget.types";

/** Cards per page, and per skeleton grid. */
export const SEARCH_PAGE_SIZE = 9;
/** Pause in typing, in milliseconds, before the text is searched. */
export const TYPING_DEBOUNCE_MS = 500;

export interface SearchWidgetQueryOptions {
  /** useNuggetSearch().search */
  search: (
    options: SearchOptions,
    append?: boolean
  ) => Promise<SearchResult | null>;
  nuggets: Ref<Nugget[]>;
  loading: Ref<boolean>;
  loadingMore: Ref<boolean>;
  /** Landing aggregations: refreshed by unfiltered searches. */
  facetAggregations: Ref<Record<string, AggregationResult>>;
  /** Whether the end of the list is still in reach after a page loaded. */
  sentinelIsNear: () => boolean;
}

/** A producer card (or "All Nuggets") picked on the catalogue landing. */
export interface BrowsePick {
  facet: string;
  value: string;
  caption: string;
}

type CatalogueTab = "producers" | "all";

export function useSearchWidgetQuery(options: SearchWidgetQueryOptions) {
  const { search, nuggets, loading, loadingMore, facetAggregations } = options;

  const typed = ref("");
  const debouncedTyped = ref("");
  const filters = ref<Record<string, string[]>>({});
  const bucketCaptions = ref<Record<string, string>>({});
  const catalogueTab = ref<CatalogueTab>("producers");
  const page = ref(SEARCH_FIRST_PAGE);
  const hasMore = ref(false);

  const activeFilterCount = computed(() =>
    Object.values(filters.value).reduce((sum, arr) => sum + arr.length, 0)
  );

  /** The landing shows until the teacher types, filters or opens "All". */
  const showingBrowse = computed(
    () =>
      catalogueTab.value === "producers" &&
      Object.keys(filters.value).length === 0 &&
      !typed.value.trim()
  );

  const searchOptions = computed<SearchOptions>(() =>
    withSearchPage(
      {
        page_size: SEARCH_PAGE_SIZE,
        fulltext: debouncedTyped.value,
        ...filters.value,
      },
      page.value
    )
  );

  async function doSearch(append = false): Promise<void> {
    const result = await search(searchOptions.value, append);
    if (result) {
      if (
        !append &&
        Object.keys(filters.value).length === 0 &&
        result.aggregations
      ) {
        facetAggregations.value = result.aggregations;
      }
      const loaded = append ? nuggets.value.length : result.items?.length ?? 0;
      hasMore.value = loaded < result.results_count;
    }
  }

  let loadMoreInFlight = false;

  /** Append the next page; keep going while the sentinel stays in view. */
  async function loadMore(): Promise<void> {
    if (
      loadMoreInFlight ||
      !hasMore.value ||
      loading.value ||
      loadingMore.value ||
      showingBrowse.value
    ) {
      return;
    }
    loadMoreInFlight = true;
    const loadedBefore = nuggets.value.length;
    page.value += 1;
    try {
      await doSearch(true);
    } finally {
      loadMoreInFlight = false;
    }
    await nextTick();
    if (nuggets.value.length > loadedBefore && options.sentinelIsNear()) {
      void loadMore();
    }
  }

  /**
   * Fetch cards only when the grid is what the teacher is looking at. Clearing
   * the last filter returns to the catalogue landing, which needs no list.
   */
  function refreshResults(): void {
    if (showingBrowse.value) {
      return;
    }
    void doSearch();
  }

  function onClearSearch(): void {
    typed.value = "";
    debouncedTyped.value = "";
    page.value = SEARCH_FIRST_PAGE;
    refreshResults();
  }

  const onInput = debounce(() => {
    debouncedTyped.value = typed.value;
    page.value = SEARCH_FIRST_PAGE;
    if (typed.value.trim() && catalogueTab.value === "producers") {
      catalogueTab.value = "all";
    }
    refreshResults();
  }, TYPING_DEBOUNCE_MS);

  function onFilters(
    newFilters: Record<string, string[]>,
    captions?: Record<string, string>
  ): void {
    filters.value = newFilters;
    if (captions) {
      bucketCaptions.value = { ...bucketCaptions.value, ...captions };
    }
    page.value = SEARCH_FIRST_PAGE;
    if (Object.keys(newFilters).length && catalogueTab.value === "producers") {
      catalogueTab.value = "all";
    }
    refreshResults();
  }

  function onFilterCaptions(captions: Record<string, string>): void {
    bucketCaptions.value = { ...bucketCaptions.value, ...captions };
  }

  function removeFilter(key: string, value: string): void {
    const current = filters.value[key] ?? [];
    const updated = current.filter((v) => v !== value);
    if (updated.length) {
      filters.value = { ...filters.value, [key]: updated };
    } else {
      const { [key]: _, ...rest } = filters.value;
      filters.value = rest;
    }
    page.value = SEARCH_FIRST_PAGE;
    refreshResults();
  }

  function clearAllFilters(): void {
    filters.value = {};
    bucketCaptions.value = {};
    page.value = SEARCH_FIRST_PAGE;
    refreshResults();
  }

  function onBrowseSelect(pick: BrowsePick): void {
    page.value = SEARCH_FIRST_PAGE;
    if (pick.facet === "all") {
      catalogueTab.value = "all";
      filters.value = {};
      bucketCaptions.value = {};
    } else {
      catalogueTab.value = "producers";
      filters.value = { [pick.facet]: [pick.value] };
      bucketCaptions.value = { [pick.value]: pick.caption };
    }
    void doSearch();
  }

  function backToBrowse(): void {
    catalogueTab.value = "producers";
    typed.value = "";
    debouncedTyped.value = "";
    filters.value = {};
    bucketCaptions.value = {};
    page.value = SEARCH_FIRST_PAGE;
  }

  return {
    typed,
    filters,
    bucketCaptions,
    activeFilterCount,
    showingBrowse,
    searchOptions,
    hasMore,
    doSearch,
    loadMore,
    refreshResults,
    onClearSearch,
    onInput,
    onFilters,
    onFilterCaptions,
    removeFilter,
    clearAllFilters,
    onBrowseSelect,
    backToBrowse,
  };
}
