<!--
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
 * Search widget: text input, facet filters, nugget grid, and pagination.
 * Used in Moodle activity-creation forms to let teachers pick a nugget.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div>
    <div v-if="error" class="naas-error-banner" role="alert">
      <span
        >{{ config.labels.error_generic_user_message }} —
        {{ error.message }}</span
      >
      <button class="btn btn-sm btn-outline-danger" @click="doSearch()">
        {{ config.labels.retry || "Retry" }}
      </button>
    </div>

    <!-- Search + filter + results grid -->
    <div v-if="selectedNugget === null && !selectedNuggetLoading">
      <!-- Search bar row -->
      <div class="search-bar-row">
        <label class="search-label" id="nugget_search">
          {{ config.labels.search }}
        </label>
        <div class="search-input-wrap">
          <div class="search-field">
            <i class="search-field-icon icon fa fa-search" />
            <input
              v-model="typed"
              @input="onInput"
              @keydown.enter.prevent
              class="search-field-input"
              :placeholder="config.labels.nugget_search_here"
            />
            <button
              v-if="typed"
              type="button"
              class="search-field-clear"
              aria-label="Clear search"
              @click="
                typed = '';
                debouncedTyped = '';
                page = SEARCH_FIRST_PAGE;
                refreshResults();
              "
            >
              ×
            </button>
          </div>
          <button
            type="button"
            class="filters-toggle-btn"
            :class="{ 'filters-toggle-btn--open': filtersOpen }"
            :aria-expanded="filtersOpen"
            aria-controls="naas-filters-collapse"
            @click="filtersOpen = !filtersOpen"
          >
            <i class="icon fa fa-sliders" aria-hidden="true" />
            {{ config.labels.metadata.filters ?? "Filters" }}
            <span v-if="activeFilterCount > 0" class="filters-toggle-badge">{{
              activeFilterCount
            }}</span>
            <i
              class="icon fa fa-chevron-down filters-toggle-chevron"
              :class="{ 'filters-toggle-chevron--open': filtersOpen }"
              aria-hidden="true"
            />
          </button>
        </div>
      </div>

      <div
        id="naas-filters-collapse"
        class="filters-collapse"
        :class="{ 'filters-collapse--open': filtersOpen }"
        :aria-hidden="!filtersOpen"
      >
        <div class="filters-collapse-clip">
          <div class="filters-collapse-panel">
            <NuggetSearchFilter
              :network-aggregations="facetAggregations"
              :network-labels="facetLabels"
              :active-filters="filters"
              :loading="loading && Object.keys(facetAggregations).length === 0"
              :prefetch="filtersOpen"
              @filters="onFilters"
              @captions="onFilterCaptions"
            />
          </div>
        </div>
      </div>

      <NuggetBrowseLanding
        v-if="showingBrowse"
        :aggregations="facetAggregations"
        :results-count="catalogueResultsCount"
        :loading="catalogueLoading"
        :directory="producerDirectory"
        @select="onBrowseSelect"
      />

      <!-- Filter chips + nugget grid -->
      <div v-else class="search-results">
        <div class="results-toolbar">
          <button type="button" class="browse-back-btn" @click="backToBrowse">
            ← {{ config.labels.back_to_catalogue || "Back to catalogue" }}
          </button>
          <div
            class="layout-toggle"
            role="group"
            :aria-label="config.labels.view_as_cards"
          >
            <button
              type="button"
              class="layout-toggle-btn"
              :class="{ 'layout-toggle-btn--active': nuggetLayout === 'card' }"
              :aria-pressed="nuggetLayout === 'card'"
              @click="nuggetLayout = 'card'"
            >
              <i class="icon fa fa-th-large" aria-hidden="true" />
              {{ config.labels.view_as_cards || "Cards" }}
            </button>
            <button
              type="button"
              class="layout-toggle-btn"
              :class="{ 'layout-toggle-btn--active': nuggetLayout === 'row' }"
              :aria-pressed="nuggetLayout === 'row'"
              @click="nuggetLayout = 'row'"
            >
              <i class="icon fa fa-bars" aria-hidden="true" />
              {{ config.labels.view_as_list || "List" }}
            </button>
          </div>
        </div>
        <!-- Active filter chips -->
        <FilterChips
          :filters="filters"
          :labels="config.labels.metadata"
          :bucket-captions="bucketCaptions"
          @remove="removeFilter"
          @clear="clearAllFilters"
        />

        <p
          v-if="loading || loadingMore"
          class="nugget-loading-banner"
          role="status"
          aria-live="polite"
        >
          <span class="nugget-loading-spinner" aria-hidden="true" />
          {{
            config.labels.nugget_search_collecting ||
            "We are collecting nugget data, please wait."
          }}
        </p>
        <p
          v-else-if="revalidating"
          class="nugget-collecting-msg nugget-refreshing-msg"
          role="status"
          aria-live="polite"
        >
          <i class="icon fa fa-refresh fa-spin" aria-hidden="true" />
          {{ config.labels.checking_updates || "Checking for updates…" }}
        </p>

        <div
          class="nugget-grid"
          :class="{ 'nugget-grid--rows': nuggetLayout === 'row' }"
          role="listbox"
          :aria-label="config.labels.search"
          aria-multiselectable="false"
          @keydown="onGridKeydown"
        >
          <!-- Skeleton cards on initial load -->
          <template v-if="loading">
            <div
              v-for="n in skeletonCount"
              :key="`skel-${n}`"
              class="nugget-grid-item"
            >
              <NuggetSkeleton :variant="nuggetLayout" :delay="n - 1" />
            </div>
          </template>

          <template v-else>
            <div
              v-for="(nugget, index) in nuggets"
              :key="nugget.nugget_id"
              class="nugget-grid-item"
              role="option"
              :aria-selected="nugget.nugget_id === selectedId"
              :tabindex="focusedIndex === index ? 0 : -1"
              :ref="(el) => setItemRef(el, index)"
              @focus="focusedIndex = index"
              @keydown.enter.prevent="clickOnNugget(nugget)"
            >
              <NuggetPost
                :nugget="nugget"
                :selection="true"
                :variant="nuggetLayout"
                :class="{
                  'nugget-post-selected': nugget.nugget_id === selectedId,
                }"
                @SelectButton="clickOnNugget"
              />
            </div>

            <!-- Skeleton row appended while loading more -->
            <template v-if="loadingMore">
              <div
                v-for="n in 3"
                :key="`more-skel-${n}`"
                class="nugget-grid-item"
              >
                <NuggetSkeleton :variant="nuggetLayout" :delay="n - 1" />
              </div>
            </template>

            <div v-if="nuggets.length === 0" class="no-results">
              {{ config.labels.nugget_search_no_result }}
            </div>
          </template>
        </div>

        <div
          v-if="hasMore && !loading"
          ref="loadMoreSentinel"
          class="load-more-sentinel"
          aria-hidden="true"
        />
      </div>
    </div>

    <!-- Selected nugget display -->
    <div v-else class="selected-nugget-wrap">
      <div class="selected-nugget-inner">
        <div v-if="selectedNuggetLoading" class="selected-nugget-loading">
          <p class="nugget-loading-banner" role="status" aria-live="polite">
            <span class="nugget-loading-spinner" aria-hidden="true" />
            {{
              config.labels.nugget_search_collecting ||
              "We are collecting nugget data, please wait."
            }}
          </p>
          <NuggetSkeleton />
        </div>
        <div v-if="!selectedNuggetLoading && selectedNugget">
          <NuggetPost
            :nugget="selectedNugget"
            replaceable
            @ReplaceButton="clearSelection"
          />
          <div ref="cguAnchor" class="naas-cgu-anchor"></div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from "vue";
import debounce from "debounce";
import NuggetSearchFilter from "./NuggetSearchFilter.vue";
import NuggetPost from "./NuggetPost.vue";
import NuggetSkeleton from "./NuggetSkeleton.vue";
import FilterChips from "./FilterChips.vue";
import NuggetBrowseLanding from "./NuggetBrowseLanding.vue";
import { useNaasConfig } from "@/composables/useNaasConfig";
import { useMoodleService } from "@/composables/useMoodleService";
import { useNuggetSearch } from "@/composables/useNuggetSearch";
import { SEARCH_FIRST_PAGE, withSearchPage } from "@/composables/searchPaging";
import {
  concealCguAgreement,
  placeCguAgreement,
  restoreCguAgreement,
  setActivityDetailsVisible,
  syncMoodleNuggetFields,
} from "@/composables/moodleActivityForm";
import {
  facetLabelsFromSnapshot,
  mergeFacetLabels,
  mergeProducerDirectory,
  snapshotSearch,
} from "@/composables/catalogueSnapshot";
import type { CatalogueProducer } from "@/types/naas-config.types";
import type {
  AggregationResult,
  CatalogueFacetLabels,
  Nugget,
  SearchOptions,
} from "@/types/nugget.types";

const config = useNaasConfig();
const service = useMoodleService();
const {
  nuggets,
  loading,
  loadingMore,
  revalidating,
  error,
  search,
  getNuggetById,
  seedFromCache,
  hydrateFromSnapshot,
  holdRevalidation,
  applyCatalogueStamp,
  searchResult,
} = useNuggetSearch();

const skeletonCount = 9;
const PAGE_SIZE = 9;

const typed = ref("");
const debouncedTyped = ref("");
const filters = ref<Record<string, string[]>>({});
const bucketCaptions = ref<Record<string, string>>({});
const landingSnapshot = snapshotSearch(config.catalogue_snapshot);
const facetAggregations = ref<Record<string, AggregationResult>>(
  landingSnapshot?.aggregations ?? {}
);
const filtersOpen = ref(false);
const activeFilterCount = computed(() =>
  Object.values(filters.value).reduce((sum, arr) => sum + arr.length, 0)
);
const selectedNugget = ref<Nugget | null>(null);
const focusedIndex = ref(0);
const itemRefs: HTMLElement[] = [];

type CatalogueTab = "producers" | "all";
type NuggetLayout = "card" | "row";

const catalogueTab = ref<CatalogueTab>("producers");
const nuggetLayout = ref<NuggetLayout>("card");

const showingBrowse = computed(
  () =>
    catalogueTab.value === "producers" &&
    Object.keys(filters.value).length === 0 &&
    !typed.value.trim()
);

const catalogueResultsCount = ref(Number(landingSnapshot?.results_count ?? 0));

const producersDigest = ref(config.catalogue_snapshot?.producers_digest ?? "");
const producerDirectory = ref<CatalogueProducer[]>(
  config.catalogue_snapshot?.producers ?? []
);
const facetLabels = ref<CatalogueFacetLabels>(
  facetLabelsFromSnapshot(config.catalogue_snapshot)
);
const catalogueLoading = ref(
  !landingSnapshot?.aggregations?.producers?.buckets?.length
);

/**
 * Confirm the producer strip and the catalogue stamp against NaaS.
 *
 * Aggregations span the whole match set, so one hit carries every producer,
 * every count, and the newest modification date. The landing is already on
 * screen from the injected snapshot; this only repaints when the stamp moved,
 * and it tells the search composable whether cached pages can be trusted.
 */
async function runCatalogueCheck() {
  try {
    const check = await service.checkCatalogue(config.courseId);
    applyCatalogueStamp(check.stamp, config.catalogue_snapshot?.stamp);
    if (check.labels) {
      facetLabels.value = mergeFacetLabels(facetLabels.value, check.labels);
    }
    // Digest ignores display names; always absorb producer rows when present.
    const checkWithProducers = check as typeof check & {
      producers?: CatalogueProducer[];
    };
    const rows = checkWithProducers.producers;
    if (Array.isArray(rows) && rows.length > 0) {
      producerDirectory.value = mergeProducerDirectory(
        producerDirectory.value,
        rows
      );
      facetLabels.value = mergeFacetLabels(
        facetLabels.value,
        facetLabelsFromSnapshot({ producers: rows })
      );
    }
    if (
      check.producers_digest &&
      check.producers_digest === producersDigest.value
    ) {
      return;
    }
    producersDigest.value = check.producers_digest ?? "";
    if (check.aggregations && Object.keys(check.aggregations).length) {
      facetAggregations.value = check.aggregations;
    }
    if (typeof check.results_count === "number") {
      catalogueResultsCount.value = check.results_count;
    }
  } catch {
    applyCatalogueStamp(null, config.catalogue_snapshot?.stamp);
    // Keep whatever the landing already has (snapshot or empty).
  } finally {
    catalogueLoading.value = false;
  }
}

function setItemRef(el: unknown, index: number) {
  if (el instanceof HTMLElement) itemRefs[index] = el;
}

function onGridKeydown(e: KeyboardEvent) {
  if (!nuggets.value.length) return;
  if (e.key === "ArrowRight" || e.key === "ArrowDown") {
    e.preventDefault();
    focusedIndex.value = Math.min(
      focusedIndex.value + 1,
      nuggets.value.length - 1
    );
    itemRefs[focusedIndex.value]?.focus();
  } else if (e.key === "ArrowLeft" || e.key === "ArrowUp") {
    e.preventDefault();
    focusedIndex.value = Math.max(focusedIndex.value - 1, 0);
    itemRefs[focusedIndex.value]?.focus();
  }
}

const selectedNuggetLoading = ref(false);
const cguAnchor = ref<HTMLElement | null>(null);

watch(
  [selectedNugget, selectedNuggetLoading],
  ([nugget, loading]) => {
    if (!nugget || loading) {
      restoreCguAgreement();
    }
  },
  { flush: "pre" }
);

watch([selectedNugget, selectedNuggetLoading], async ([nugget, loading]) => {
  if (!nugget || loading) {
    return;
  }
  await nextTick();
  placeCguAgreement(cguAnchor.value);
});
const selectedId = ref<string | null>(null);
const page = ref(SEARCH_FIRST_PAGE);
const hasMore = ref(false);

const searchOptions = computed<SearchOptions>(() =>
  withSearchPage(
    {
      page_size: PAGE_SIZE,
      fulltext: debouncedTyped.value,
      ...filters.value,
    },
    page.value
  )
);

async function doSearch(append = false) {
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
  if (!append) {
    focusedIndex.value = 0;
    itemRefs.length = 0;
  }
}

const loadMoreSentinel = ref<HTMLElement | null>(null);
let loadMoreInFlight = false;

function sentinelIsVisible(): boolean {
  const el = loadMoreSentinel.value;
  if (!el) {
    return false;
  }
  const rect = el.getBoundingClientRect();
  return rect.top < window.innerHeight + 320;
}

async function loadMore() {
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
  if (nuggets.value.length > loadedBefore && sentinelIsVisible()) {
    void loadMore();
  }
}

watch(
  loadMoreSentinel,
  (el, _prev, onCleanup) => {
    if (!el) {
      return;
    }
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          void loadMore();
        }
      },
      { root: null, rootMargin: "320px 0px", threshold: 0 }
    );
    observer.observe(el);
    onCleanup(() => observer.disconnect());
  },
  { flush: "post" }
);

/**
 * Fetch cards only when the grid is what the teacher is looking at. Clearing
 * the last filter returns to the catalogue landing, which needs no list.
 */
function refreshResults() {
  if (showingBrowse.value) {
    return;
  }
  doSearch();
}

const onInput = debounce(() => {
  debouncedTyped.value = typed.value;
  page.value = SEARCH_FIRST_PAGE;
  if (typed.value.trim() && catalogueTab.value === "producers") {
    catalogueTab.value = "all";
  }
  refreshResults();
}, 500);

function onFilters(
  newFilters: Record<string, string[]>,
  captions?: Record<string, string>
) {
  filters.value = newFilters;
  if (captions) bucketCaptions.value = { ...bucketCaptions.value, ...captions };
  page.value = SEARCH_FIRST_PAGE;
  if (Object.keys(newFilters).length && catalogueTab.value === "producers") {
    catalogueTab.value = "all";
  }
  refreshResults();
}

function onFilterCaptions(captions: Record<string, string>) {
  bucketCaptions.value = { ...bucketCaptions.value, ...captions };
}

function removeFilter(key: string, value: string) {
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

function clearAllFilters() {
  filters.value = {};
  bucketCaptions.value = {};
  page.value = SEARCH_FIRST_PAGE;
  refreshResults();
}

function onBrowseSelect(payload: {
  facet: string;
  value: string;
  caption: string;
}) {
  page.value = SEARCH_FIRST_PAGE;
  if (payload.facet === "all") {
    catalogueTab.value = "all";
    filters.value = {};
    bucketCaptions.value = {};
    doSearch();
    return;
  }
  catalogueTab.value = "producers";
  filters.value = { [payload.facet]: [payload.value] };
  bucketCaptions.value = { [payload.value]: payload.caption };
  doSearch();
}

function backToBrowse() {
  catalogueTab.value = "producers";
  typed.value = "";
  debouncedTyped.value = "";
  filters.value = {};
  bucketCaptions.value = {};
  page.value = SEARCH_FIRST_PAGE;
}

function clickOnNugget(nugget: Nugget) {
  if (selectedId.value === nugget.nugget_id) {
    selectedId.value = null;
    selectedNugget.value = null;
    syncMoodleNuggetFields(null);
    return;
  }
  applySelection(nugget);
}

function applySelection(nugget: Nugget) {
  selectedId.value = nugget.nugget_id;
  selectedNugget.value = nugget;
  syncMoodleNuggetFields(nugget);
}

function clearSelection() {
  selectedNugget.value = null;
  selectedId.value = null;
  syncMoodleNuggetFields(null);
  refreshResults();
}

onBeforeUnmount(() => {
  restoreCguAgreement();
});

onMounted(async () => {
  concealCguAgreement();
  holdRevalidation();
  const nuggetIdField = document.getElementsByName(
    "nugget_id"
  )[0] as HTMLInputElement | null;
  const storedId = nuggetIdField?.value;
  const catalogueCheck = runCatalogueCheck();

  if (storedId && storedId !== "nugget_id" && storedId !== "") {
    selectedId.value = storedId;
    setActivityDetailsVisible(true);
    // A hit already cached for any query paints the selection with no wait.
    const seeded = seedFromCache(storedId);
    selectedNugget.value = seeded;
    selectedNuggetLoading.value = seeded === null;
    const found = await getNuggetById(storedId, (fresh) => {
      // Only fires when NaaS reported a newer document than the cached one.
      selectedNugget.value = fresh;
    });
    if (found) {
      selectedNugget.value = found;
    }
    selectedNuggetLoading.value = false;
    await catalogueCheck;
    return;
  }

  setActivityDetailsVisible(false);
  // Snapshot aggregations already sit on the landing. This call only seeds
  // the search composable, then the digest check may refresh producers.
  if (
    hydrateFromSnapshot(searchOptions.value) &&
    searchResult.value?.aggregations
  ) {
    facetAggregations.value = searchResult.value.aggregations;
    catalogueResultsCount.value = Number(
      searchResult.value.results_count ?? catalogueResultsCount.value
    );
    if (searchResult.value.aggregations.producers?.buckets?.length) {
      catalogueLoading.value = false;
    }
  }
  await catalogueCheck;
});
</script>

<style scoped>
/* ── Search bar ─────────────────────────────────────────────────────────── */
.search-bar-row {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.search-label {
  flex-shrink: 0;
  font-weight: 600;
  white-space: nowrap;
  margin: 0;
  color: var(--naas-text, #1f2937);
}

.search-input-wrap {
  display: flex;
  align-items: center;
  flex: 1;
  gap: 0.5rem;
}

/* Pill-shaped search field */
.search-field {
  position: relative;
  flex: 1;
  display: flex;
  align-items: center;
}

.search-field-icon {
  position: absolute;
  left: 0.85rem;
  color: var(--naas-text-muted, #6c757d);
  font-size: 0.875rem;
  pointer-events: none;
  z-index: 1;
}

.search-field-input {
  width: 100%;
  padding: 0.5rem 2.25rem 0.5rem 2.25rem;
  border: 1.5px solid var(--naas-border, #dee2e6);
  border-radius: var(--naas-radius-pill, 999px);
  background: var(--naas-surface, #fff);
  font-size: 0.875rem;
  color: var(--naas-text, #1f2937);
  transition: border-color var(--naas-transition, 0.18s ease),
    box-shadow var(--naas-transition, 0.18s ease);
  outline: none;
  line-height: 1.5;
}

.search-field-input::placeholder {
  color: var(--naas-text-subtle, #adb5bd);
}

.search-field-input:focus {
  border-color: var(--naas-primary, #0f6cbf);
  box-shadow: 0 0 0 3px rgba(15, 108, 191, 0.15);
}

.search-field-clear {
  position: absolute;
  right: 0.75rem;
  background: var(--naas-text-subtle, #adb5bd);
  color: #fff;
  border: none;
  border-radius: 50%;
  width: 1.1rem;
  height: 1.1rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  line-height: 1;
  cursor: pointer;
  padding: 0;
  transition: background var(--naas-transition, 0.18s ease);
}

.search-field-clear:hover {
  background: var(--naas-text-muted, #6c757d);
}

.search-results {
  width: 100%;
  max-width: 100%;
  margin-bottom:3rem;
  min-width: 0;
}

.results-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.layout-toggle {
  display: inline-flex;
  border: 1.5px solid var(--naas-border, #dee2e6);
  border-radius: var(--naas-radius-pill, 999px);
  overflow: hidden;
  background: var(--naas-surface, #fff);
}

.layout-toggle-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  margin: 0;
  padding: 0.32rem 0.75rem;
  border: none;
  background: transparent;
  color: var(--naas-text-muted, #6c757d);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.layout-toggle-btn .icon {
  margin: 0;
}

.layout-toggle-btn--active {
  background: var(--naas-primary-light, #dce9fa);
  color: var(--naas-primary, #0f6cbf);
}

.nugget-loading-banner {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  margin: 0 0 1rem;
  padding: 0.7rem 0.95rem;
  border: 1px solid rgba(15, 108, 191, 0.18);
  border-radius: var(--naas-radius, 8px);
  background: var(--naas-primary-light, #dce9fa);
  color: var(--naas-primary-dark, #0a4a8f);
  font-size: 0.9rem;
  font-weight: 600;
  line-height: 1.35;
}

.nugget-loading-spinner {
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
  border: 2px solid rgba(15, 108, 191, 0.25);
  border-top-color: var(--naas-primary, #0f6cbf);
  border-radius: 50%;
  animation: nugget-loading-spin 0.7s linear infinite;
}

@keyframes nugget-loading-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .nugget-loading-spinner {
    animation: none;
    border-top-color: rgba(15, 108, 191, 0.25);
    background: var(--naas-primary, #0f6cbf);
  }
}

/* Revalidation is background work: visible if looked for, never attention-grabbing. */
.nugget-refreshing-msg {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0 0 0.75rem;
  opacity: 0.65;
  font-size: 0.8rem;
  color: var(--naas-text-muted, #6c757d);
}

/* ── Nugget grid: 3 cards per row ──────────────────────────────────────── */
.nugget-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1.25rem;
  width: 100%;
  box-sizing: border-box;
}

.nugget-grid-item {
  display: flex;
  min-width: 0;
  width: 100%;
  max-width: 100%;
  cursor: default;
}

.nugget-grid:not(.nugget-grid--rows) .nugget-grid-item :deep(.nugget-post) {
  width: 100%;
  height: 100%;
}

.nugget-grid--rows {
  grid-template-columns: minmax(0, 1fr);
  gap: 0.5rem;
}

.no-results {
  grid-column: 1 / -1;
  padding: 1.5rem 0;
  color: #6c757d;
  text-align: center;
}

.browse-back-btn {
  display: inline-flex;
  align-items: center;
  margin: 0;
  padding: 0;
  border: none;
  background: none;
  color: var(--naas-primary, #0f6cbf);
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
}

.browse-back-btn:hover {
  text-decoration: underline;
}

/* ── Selected state ────────────────────────────────────────────────────── */
.nugget-post-selected :deep(.nugget-post) {
  border-color: var(--naas-primary, #0f6cbf);
  box-shadow: 0 0 0 2px rgba(15, 108, 191, 0.22),
    0 8px 20px rgba(15, 108, 191, 0.16);
}

/* ── Selected nugget view ──────────────────────────────────────────────── */
.selected-nugget-wrap {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 75px;
}

.selected-nugget-inner {
  width: 75%;
}

.naas-cgu-anchor {
  margin-top: 0.85rem;
}

/* ── Error banner ──────────────────────────────────────────────────────── */
.naas-error-banner {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
  background: #fff3cd;
  border: 1px solid #ffc107;
  border-radius: var(--naas-radius, 6px);
  color: #856404;
}

/* Sentinel sits under the grid so scrolling near the bottom fetches the next page. */
.load-more-sentinel {
  width: 100%;
  height: 1px;
}

/* ── Filters toggle button ─────────────────────────────────────────────── */
.filters-toggle-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.9rem;
  border: 1.5px solid var(--naas-border, #dee2e6);
  border-radius: var(--naas-radius-pill, 999px);
  background: var(--naas-surface, #fff);
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--naas-text, #1f2937);
  cursor: pointer;
  white-space: nowrap;
  transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease,
    box-shadow 0.15s ease;
  flex-shrink: 0;
}

.filters-toggle-btn .icon {
  margin: 0;
  font-size: 0.85rem;
}

.filters-toggle-btn:hover,
.filters-toggle-btn:focus-visible {
  border-color: var(--naas-primary, #0f6cbf);
  color: var(--naas-primary, #0f6cbf);
  outline: none;
}

.filters-toggle-btn--open {
  border-color: var(--naas-primary, #0f6cbf);
  background: var(--naas-primary-light, #dce9fa);
  color: var(--naas-primary, #0f6cbf);
  box-shadow: 0 0 0 3px rgba(15, 108, 191, 0.12);
}

.filters-toggle-chevron {
  transition: transform 0.2s ease;
}

.filters-toggle-chevron--open {
  transform: rotate(180deg);
}

.filters-toggle-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.1rem;
  height: 1.1rem;
  padding: 0 0.2rem;
  border-radius: 50%;
  background: var(--primary, #0f6cbf);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1;
}

.filters-collapse {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows 0.28s ease;
  pointer-events: none;
  position: relative;
  z-index: 5;
}

.filters-collapse--open {
  grid-template-rows: 1fr;
  pointer-events: auto;
  z-index: 30;
}

.filters-collapse-clip {
  overflow: hidden;
  min-height: 0;
}

.filters-collapse--open .filters-collapse-clip {
  overflow: visible;
}

.filters-collapse-panel {
  padding: 0 0 1rem;
  overflow: visible;
}

.filters-collapse-panel :deep(.filter-bar) {
  margin-bottom: 0;
  padding: 0.85rem 1rem 1rem;
  border: 1.5px solid var(--naas-border, #dee2e6);
  border-radius: var(--naas-radius, 8px);
  background: var(--naas-surface-muted, #f8f9fa);
  overflow: visible;
}

.filters-collapse-panel :deep(.filter-title) {
  display: none;
}

.filters-collapse-panel :deep(.filter-bar-header) {
  justify-content: flex-end;
  margin-bottom: 0;
}

.filters-collapse-panel :deep(.filter-clear-btn) {
  margin-bottom: 0.65rem;
}
</style>

<style>
/* Moodle form fields live outside the Vue root. Hide them until a nugget
   is selected — never wrap those fitems in a split HTML <div>. */
.mform[data-naas-details="hidden"] #id_generalcontainer > .fitem,
.mform[data-naas-details="hidden"] #fitem_id_name,
.mform[data-naas-details="hidden"] #fitem_id_introeditor,
.mform[data-naas-details="hidden"] #fitem_id_intro,
.mform[data-naas-details="hidden"] #fitem_id_showdescription,
.mform[data-naas-details="hidden"] #fitem_id_cgu_agreement,
.fitem.naas-cgu-field {
  display: none !important;
}

/* The same Moodle checkbox, parked under the selected nugget. */
.naas-cgu-anchor .fitem.naas-cgu-field,
.mform .naas-cgu-anchor #fitem_id_cgu_agreement.naas-cgu-field {
  display: block !important;
  margin: 0;
  padding: 0.95rem 1.05rem;
  border: 1.5px solid rgba(15, 108, 191, 0.28);
  border-radius: var(--naas-radius, 8px);
  background: #f3f8fd;
  box-shadow: none;
}

.naas-cgu-anchor .fitem.naas-cgu-field > .col-md-3,
.naas-cgu-anchor .fitemtitle {
  display: none;
}

.naas-cgu-anchor .col-md-9,
.naas-cgu-anchor .felement,
.naas-cgu-anchor .checkbox {
  width: 100%;
  max-width: 100%;
  flex: 1 1 100%;
  margin: 0;
  padding: 0;
}

.naas-cgu-anchor .form-check {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  min-height: 0;
}

.naas-cgu-anchor .form-check-input {
  position: static;
  float: none;
  width: 1.15rem;
  height: 1.15rem;
  margin: 0.2rem 0 0;
  flex: 0 0 auto;
  accent-color: var(--naas-primary, #0f6cbf);
}

.naas-cgu-anchor label,
.naas-cgu-anchor #id_cgu_agreement_description {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1.45;
  color: var(--naas-text, #1f2937);
}

.naas-cgu-anchor a {
  color: var(--naas-primary-dark, #0a4a8f);
  font-weight: 700;
  text-decoration: underline;
  text-underline-offset: 0.12em;
}

.naas-cgu-anchor a:hover,
.naas-cgu-anchor a:focus-visible {
  color: var(--naas-primary, #0f6cbf);
}

.naas-cgu-anchor .invalid-feedback:not(:empty),
.naas-cgu-anchor .error:not(:empty) {
  display: block;
  margin: 0.5rem 0 0 1.9rem;
  font-size: 0.85rem;
  font-weight: 600;
  line-height: 1.4;
  color: #9b1c1c;
}
#naas_widget,
.naas-widget-host {
  display: block;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  box-sizing: border-box;
}
</style>
