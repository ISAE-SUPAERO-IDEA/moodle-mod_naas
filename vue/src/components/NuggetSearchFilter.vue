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
 * Facet filter panel — renders aggregation buckets returned by the search API.
 *
 * Each facet is a FilterFacetColumn; entity labels are resolved by
 * useFacetLabels and the selection helpers live in facetColumns.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div class="filter-bar" v-click-outside="closeAll">
    <div class="filter-bar-header">
      <h3 class="filter-title">
        {{ config.labels.metadata.filters }}
      </h3>
      <button
        v-show="hasFilters"
        type="button"
        class="filter-clear-btn"
        @click="clearFilters"
      >
        {{ config.labels.clear_filters }}
      </button>
    </div>

    <!-- 3-column grid: one column per aggregation + skeleton placeholders while loading -->
    <div class="filter-columns">
      <!-- Skeleton columns while loading -->
      <template v-if="loading && !hasAggregations">
        <div v-for="n in 3" :key="`skel-col-${n}`" class="filter-column">
          <FilterSkeleton />
        </div>
      </template>

      <!-- Aggregation columns -->
      <FilterFacetColumn
        v-for="aggregation in aggregations"
        :key="aggregation.name"
        :aggregation="aggregation"
        @toggle="toggle(aggregation)"
        @select="(value: string) => switchFacet(aggregation, value)"
        @toggle-authors="toggleAuthors(aggregation)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue";
import FilterSkeleton from "./FilterSkeleton.vue";
import FilterFacetColumn from "./FilterFacetColumn.vue";
import { useNaasConfig } from "@/composables/useNaasConfig";
import { useFacetLabels } from "@/composables/useFacetLabels";
import type {
  AggregationResult,
  CatalogueFacetLabels,
} from "@/types/nugget.types";
import type { MappedAggregation } from "@/composables/searchAggregations";
import {
  buildFacetColumns,
  clearSelection,
  closeFacets,
  collectSelection,
  hasAnySelection,
  syncSelection,
} from "@/composables/facetColumns";
import { vClickOutside } from "@/utils/clickOutside";

const props = defineProps<{
  networkAggregations: Record<string, AggregationResult>;
  activeFilters: Record<string, string[]>;
  loading?: boolean;
  prefetch?: boolean;
  networkLabels?: CatalogueFacetLabels;
}>();
const emit = defineEmits<{
  (
    e: "filters",
    filters: Record<string, string[]>,
    captions: Record<string, string>
  ): void;
  (e: "captions", captions: Record<string, string>): void;
}>();

const config = useNaasConfig();
const aggregations = ref<MappedAggregation[]>([]);
const labels = useFacetLabels({
  aggregations,
  networkLabels: () => props.networkLabels,
  onSelectedRelabel: () =>
    emit("captions", collectSelection(aggregations.value).captions),
});

function rebuild() {
  aggregations.value = buildFacetColumns(
    props.networkAggregations,
    props.activeFilters,
    aggregations.value,
    labels
  );
  if (props.prefetch) {
    prefetchNetworkLabels();
  }
}

watch(
  () => props.networkAggregations,
  () => rebuild(),
  { deep: true, immediate: true }
);

watch(
  () => props.networkLabels,
  () => rebuild(),
  { deep: true }
);

watch(
  () => props.activeFilters,
  (active) => syncSelection(aggregations.value, active),
  { deep: true }
);

function prefetchNetworkLabels() {
  for (const agg of aggregations.value) {
    void labels.resolveFacetLabels(agg);
  }
}

watch(
  () => props.prefetch,
  (open) => {
    if (open) prefetchNetworkLabels();
  },
  { immediate: true }
);

const hasAggregations = computed(() =>
  aggregations.value.some((a) => a.buckets.length > 0)
);

const hasFilters = computed(() => hasAnySelection(aggregations.value));

const loading = computed(() => !!props.loading);

function switchFacet(aggregation: MappedAggregation, bucketKey: string) {
  const bucket = aggregation.buckets.find(
    (item) => item.key === bucketKey || item.query_value === bucketKey
  );
  // The column only emits keys of this aggregation's buckets.
  /* v8 ignore next -- @preserve */
  if (!bucket) return;
  bucket.selected = !bucket.selected;
  const { filters, captions } = collectSelection(aggregations.value);
  emit("filters", filters, captions);
}

function clearFilters() {
  clearSelection(aggregations.value);
  emit("filters", {}, {});
}

async function toggle(target: MappedAggregation) {
  const opening = !target.visible;
  closeFacets(aggregations.value);
  if (opening) {
    target.visible = true;
    await labels.resolveFacetLabels(target);
  }
}

async function toggleAuthors(aggregation: MappedAggregation) {
  aggregation.showAll = !aggregation.showAll;
  if (aggregation.showAll) {
    aggregation.labelsResolved = false;
    await labels.resolveFacetLabels(aggregation);
  }
}

function closeAll() {
  closeFacets(aggregations.value);
}
</script>

<style scoped>
/* ── Bar wrapper ── */
.filter-bar {
  width: 100%;
  margin-bottom: 1.25rem;
}

.filter-bar-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 0.75rem;
}

.filter-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: var(--naas-text, #1f2937);
}

/* ── 3-column grid ── */
.filter-columns {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem;
  overflow: visible;
}

/* Also styles the FilterFacetColumn roots, which carry this scope id. */
.filter-column {
  position: relative;
  min-width: 0;
  overflow: visible;
  z-index: 1;
}

.filter-column:has(.filter-pill--open) {
  z-index: 3;
}

/* ── Clear all ── */
.filter-clear-btn {
  background: none;
  border: 1.5px solid #dc3545;
  border-radius: var(--naas-radius-pill, 999px);
  padding: 0.25rem 0.65rem;
  font-size: 0.8125rem;
  color: #dc3545;
  cursor: pointer;
  white-space: nowrap;
  transition:
    background var(--naas-transition, 0.18s ease),
    color var(--naas-transition, 0.18s ease);
}

.filter-clear-btn:hover {
  background: #dc3545;
  color: #fff;
}
</style>
