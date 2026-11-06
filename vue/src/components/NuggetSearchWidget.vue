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
    <div
      v-if="formError && selectedNugget === null"
      class="alert alert-danger"
      role="alert"
    >
      {{ formError }}
    </div>
    <div v-if="error" class="naas-error-banner" role="alert">
      <span
        >{{ config.labels.error_generic_user_message }} —
        {{ error.message }}</span
      >
      <button class="btn btn-sm btn-outline-danger" @click="doSearch()">
        {{ config.labels.retry }}
      </button>
    </div>

    <!-- Search + filter + results grid -->
    <div v-if="selectedNugget === null && !selectedNuggetLoading">
      <!-- Search bar row -->
      <NuggetSearchBar
        v-model:query="typed"
        v-model:open="filtersOpen"
        :active-filter-count="activeFilterCount"
        :aggregations="facetAggregations"
        :labels="facetLabels"
        :active-filters="filters"
        :loading="loading && Object.keys(facetAggregations).length === 0"
        @typing="onInput"
        @clear="onClearSearch"
        @filters="onFilters"
        @captions="onFilterCaptions"
      />

      <NuggetBrowseLanding
        v-if="showingBrowse"
        :aggregations="facetAggregations"
        :results-count="catalogueResultsCount"
        :loading="catalogueLoading"
        :directory="producerDirectory"
        @select="onBrowseSelect"
        @named="onProducersNamed"
      />

      <!-- Filter chips + nugget grid -->
      <div v-else class="search-results">
        <div class="results-toolbar">
          <button type="button" class="browse-back-btn" @click="backToBrowse">
            ← {{ config.labels.back_to_catalogue }}
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
              {{ config.labels.view_as_cards }}
            </button>
            <button
              type="button"
              class="layout-toggle-btn"
              :class="{ 'layout-toggle-btn--active': nuggetLayout === 'row' }"
              :aria-pressed="nuggetLayout === 'row'"
              @click="nuggetLayout = 'row'"
            >
              <i class="icon fa fa-bars" aria-hidden="true" />
              {{ config.labels.view_as_list }}
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
          {{ config.labels.checking_updates }}
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
import { debounce } from "@/utils/debounce";
import NuggetSearchBar from "./NuggetSearchBar.vue";
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
  readNuggetFormError,
  restoreCguAgreement,
  setActivityDetailsVisible,
  syncMoodleNuggetFields,
} from "@/composables/moodleActivityForm";
import { useProducerDirectory } from "@/composables/useProducerDirectory";
import { useCatalogueCheck } from "@/composables/useCatalogueCheck";
import { useLoadMoreSentinel } from "@/composables/useLoadMoreSentinel";
import type { Nugget, SearchOptions } from "@/types/nugget.types";
import "@/styles/naas-activity-form.css";

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
const filtersOpen = ref(false);
const activeFilterCount = computed(() =>
  Object.values(filters.value).reduce((sum, arr) => sum + arr.length, 0)
);
const selectedNugget = ref<Nugget | null>(null);
// Moodle puts "no Nugget chosen" on the name field, hidden until a Nugget is selected.
const formError = ref(readNuggetFormError());
watch(selectedNugget, (nugget) => {
  if (nugget) formError.value = "";
});
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

const { producerDirectory, facetLabels, onProducersNamed, ...directory } =
  useProducerDirectory(config.catalogue_snapshot);
const {
  facetAggregations,
  catalogueResultsCount,
  catalogueLoading,
  runCatalogueCheck,
} = useCatalogueCheck({
  service,
  courseId: config.courseId,
  snapshot: config.catalogue_snapshot,
  directory,
  applyCatalogueStamp,
});

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

const { sentinel: loadMoreSentinel, sentinelIsNear } = useLoadMoreSentinel(
  () => void loadMore()
);
let loadMoreInFlight = false;

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
  if (nuggets.value.length > loadedBefore && sentinelIsNear()) {
    void loadMore();
  }
}

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

function onClearSearch() {
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

</style>
