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
 * This component wires the pieces together: NuggetSearchResults (cards),
 * NuggetSelectedPanel (the pick), useSearchWidgetQuery (what is searched)
 * and useNuggetSelection (the Moodle form fields).
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
    <div
      v-else-if="catalogueError && showingBrowse && selectedNugget === null"
      class="naas-error-banner"
      role="alert"
    >
      <span
        >{{ config.labels.error_generic_user_message }} —
        {{ catalogueError.message }}</span
      >
      <button class="btn btn-sm btn-outline-danger" @click="retryCatalogue()">
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
        v-if="showingBrowse && !catalogueError"
        :aggregations="facetAggregations"
        :results-count="catalogueResultsCount"
        :loading="catalogueLoading"
        :directory="producerDirectory"
        @select="onBrowseSelect"
        @named="onProducersNamed"
      />

      <!-- Filter chips + nugget grid -->
      <NuggetSearchResults
        v-else-if="!showingBrowse"
        :failed="!!error"
        ref="results"
        v-model:layout="nuggetLayout"
        :nuggets="nuggets"
        :loading="loading"
        :loading-more="loadingMore"
        :revalidating="revalidating"
        :has-more="hasMore"
        :selected-id="selectedId"
        :filters="filters"
        :bucket-captions="bucketCaptions"
        @back="backToBrowse"
        @remove-filter="removeFilter"
        @clear-filters="clearAllFilters"
        @select="onPick"
        @more="loadMore"
      />
    </div>

    <!-- Selected nugget display -->
    <NuggetSelectedPanel
      v-else
      ref="selectedPanel"
      :nugget="selectedNugget"
      :loading="selectedNuggetLoading"
      @replace="onReplace"
    />
    <IntegrationCodeModal
      :nugget="codeNugget"
      :visible="codeNugget !== null"
      @confirm="onCodeConfirmed"
      @cancel="cancelIntegrationCode"
    />
  </div>
</template>

<script setup lang="ts">
import { nextTick, ref, onMounted, onBeforeUnmount } from "vue";
import type { Nugget } from "@/shared/api";
import { NuggetSearchBar } from "@/widgets/search-bar";
import {
  NuggetBrowseLanding,
  useCatalogueCheck,
} from "@/widgets/catalogue-landing";
import { NuggetSearchResults } from "@/widgets/search-results";
import { NuggetSelectedPanel } from "@/widgets/selected-nugget";
import { useNaasConfig } from "@/shared/config";
import { useMoodleService } from "@/shared/api";
import {
  useNuggetSearch,
  useSearchWidgetQuery,
} from "@/features/search-nuggets";
import {
  concealCguAgreement,
  restoreCguAgreement,
  setActivityDetailsVisible,
  readStoredNuggetId,
  useNuggetSelection,
  IntegrationCodeModal,
} from "@/features/pick-nugget";
import { useProducerDirectory } from "@/entities/catalogue";
import "./naas-activity-form.css";

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

const filtersOpen = ref(false);
const nuggetLayout = ref<"card" | "row">("card");

const { producerDirectory, facetLabels, onProducersNamed, ...directory } =
  useProducerDirectory(config.catalogue_snapshot);
const {
  facetAggregations,
  catalogueResultsCount,
  catalogueLoading,
  catalogueError,
  runCatalogueCheck,
} = useCatalogueCheck({
  service,
  courseId: config.courseId,
  snapshot: config.catalogue_snapshot,
  directory,
  applyCatalogueStamp,
});

/** The results list, which owns the infinite-scroll sentinel. */
const results = ref<{ sentinelIsNear: () => boolean } | null>(null);

const {
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
} = useSearchWidgetQuery({
  search,
  nuggets,
  loading,
  loadingMore,
  facetAggregations,
  sentinelIsNear: () => results.value?.sentinelIsNear() ?? false,
});

const {
  selectedNugget,
  selectedId,
  selectedNuggetLoading,
  formError,
  codeNugget,
  toggleNugget,
  confirmIntegrationCode,
  cancelIntegrationCode,
  clearSelection,
  restoreSavedNugget,
} = useNuggetSelection({
  requireIntegrationCode: config.require_integration_code,
  savedNuggetId: config.saved_nugget_id,
});

/** Retry the catalogue after NaaS could not be reached: show the skeleton meanwhile. */
function retryCatalogue() {
  catalogueLoading.value = true;
  return runCatalogueCheck();
}

const selectedPanel = ref<{ focusCard: () => void } | null>(null);

/**
 * Select (or unselect) a result. A teacher's pick moves the focus onto the
 * chosen card, so keyboard users do not land back at the top of the page.
 */
async function onPick(nugget: Nugget) {
  toggleNugget(nugget);
  // Unselected, or waiting for its integration code in the dialog.
  if (selectedNugget.value === null) {
    return;
  }
  await focusChosenCard();
}

async function focusChosenCard() {
  await nextTick();
  selectedPanel.value?.focusCard();
}

/** The code dialog accepted the code: the Nugget is now the pick. */
async function onCodeConfirmed(code: string) {
  confirmIntegrationCode(code);
  await focusChosenCard();
}

/** Replace on the chosen card: back to the results it was picked from. */
function onReplace() {
  clearSelection();
  refreshResults();
}

onBeforeUnmount(() => {
  restoreCguAgreement();
});

onMounted(async () => {
  concealCguAgreement();
  holdRevalidation();
  const storedId = readStoredNuggetId();
  const catalogueCheck = runCatalogueCheck();

  if (storedId) {
    await restoreSavedNugget(storedId, { seedFromCache, getNuggetById });
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
      // The snapshot always carries results_count.
      /* v8 ignore next -- @preserve */
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
</style>
