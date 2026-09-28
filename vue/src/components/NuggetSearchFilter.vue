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
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div class="filter-bar" v-click-outside="closeAll">
    <div class="filter-bar-header">
      <h3 class="filter-title">
        {{ config.labels.metadata.filters ?? "Filters" }}
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
      <div
        v-for="aggregation in aggregations"
        :key="aggregation.name"
        class="filter-column"
      >
        <button
          type="button"
          class="filter-pill"
          :class="{
            'filter-pill--active': hasSelected(aggregation),
            'filter-pill--open': aggregation.visible,
          }"
          @click="toggle(aggregation.name)"
        >
          {{ config.labels.metadata[aggregation.name] ?? aggregation.name }}
          <span v-if="hasSelected(aggregation)" class="filter-pill-count">
            {{ selectedCount(aggregation) }}
          </span>
          <i
            class="filter-pill-chevron icon fa"
            :class="aggregation.visible ? 'fa-chevron-up' : 'fa-chevron-down'"
          />
        </button>

        <transition name="dropdown-fade">
          <div v-show="aggregation.visible" class="filter-dropdown">
            <div class="filter-dropdown-list">
              <label
                v-for="bucket in visibleBuckets(aggregation)"
                :key="bucket.key"
                class="filter-option"
                :title="bucket.title || undefined"
              >
                <input
                  type="checkbox"
                  :checked="bucket.selected"
                  @change="
                    switchFacet(
                      aggregation.name,
                      bucket.query_value ?? bucket.key
                    )
                  "
                />
                <span>{{ bucket.caption }}</span>
              </label>

              <button
                v-if="hasMoreAuthors(aggregation)"
                type="button"
                class="show-more-btn"
                @click="toggleAuthors(aggregation)"
              >
                {{
                  aggregation.showAll
                    ? `− ${config.labels.hide_authors}`
                    : `+ ${config.labels.show_more_authors}`
                }}
              </button>
            </div>
          </div>
        </transition>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue";
import FilterSkeleton from "./FilterSkeleton.vue";
import { useNaasConfig } from "@/composables/useNaasConfig";
import { useEntityResolvers } from "@/composables/useEntityResolvers";
import type { AggregationResult } from "@/types/nugget.types";
import {
  aggregationFor,
  applyResolvedLabels,
  facetNeedsNetworkLabels,
  hasMoreAuthors,
  mapAggregationBuckets,
  selectedKeysFor,
  visibleBuckets,
  type MappedAggregation,
} from "@/composables/searchAggregations";
import {
  isRawEntityLabel,
  lookupLabel,
  normalizePersonKey,
  normalizeStructureKey,
} from "@/composables/structureVisuals";

type ElWithHandler = HTMLElement & { __coh__: (e: Event) => void };

const vClickOutside = {
  mounted(el: HTMLElement, binding: { value: () => void }) {
    const handler = (e: Event) => {
      if (!el.contains(e.target as Node)) binding.value();
    };
    (el as ElWithHandler).__coh__ = handler;
    document.addEventListener("click", handler);
  },
  unmounted(el: HTMLElement) {
    document.removeEventListener("click", (el as ElWithHandler).__coh__);
  },
};

const AGG_ORDER = [
  "related_domains",
  "level",
  "language",
  "producers",
  "authors",
  "references",
  "type",
] as const;

const props = defineProps<{
  networkAggregations: Record<string, AggregationResult>;
  activeFilters: Record<string, string[]>;
  loading?: boolean;
  prefetch?: boolean;
  networkLabels?: Record<string, Record<string, string>>;
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
const {
  getDomainLabel,
  getStructureAcronym,
  getStructureVisuals,
  getPersonName,
} = useEntityResolvers();

const aggregations = ref<MappedAggregation[]>([]);
const resolving = ref<string | null>(null);
const resolvedLabels = ref<Record<string, Record<string, string>>>({});
const resolvingNames = new Set<string>();

function localLabel(aggName: string, key: string): string {
  const normalize =
    aggName === "producers"
      ? normalizeStructureKey
      : aggName === "authors"
      ? normalizePersonKey
      : (value: string) => value;
  const cached =
    lookupLabel(resolvedLabels.value[aggName], key, normalize) ||
    lookupLabel(props.networkLabels?.[aggName], key, normalize) ||
    (aggName === "related_domains"
      ? lookupLabel(props.networkLabels?.domains, key, normalize)
      : "");
  if (cached && !isRawEntityLabel(cached, key)) return cached;
  if (facetNeedsNetworkLabels(aggName)) return key;
  return config.labels.metadata[key] ?? key;
}

function hasHumanLabel(aggName: string, key: string): boolean {
  const label = localLabel(aggName, key);
  return !!label && !isRawEntityLabel(label, key);
}

function rebuild() {
  const previous = new Map(aggregations.value.map((agg) => [agg.name, agg]));
  const next: MappedAggregation[] = [];

  for (const name of AGG_ORDER) {
    const raw = aggregationFor(props.networkAggregations, name);
    if (!raw?.buckets?.length) continue;
    const buckets = raw.buckets;
    if (!buckets.length) continue;
    const prior = previous.get(name);
    const mapped = mapAggregationBuckets(
      name,
      buckets,
      selectedKeysFor(props.activeFilters, name),
      (key) => localLabel(name, key),
      {
        visible: prior?.visible ?? false,
        showAll: prior?.showAll ?? false,
        labelsResolved:
          !facetNeedsNetworkLabels(name) ||
          raw.buckets.every((b) => hasHumanLabel(name, b.key)),
      }
    );
    next.push(mapped);
  }

  aggregations.value = next;
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
  (active) => {
    for (const agg of aggregations.value) {
      const selected = selectedKeysFor(active, agg.name);
      for (const bucket of agg.buckets) {
        bucket.selected = selected.includes(bucket.key);
      }
    }
  },
  { deep: true }
);

function findAggregation(name: string): MappedAggregation | undefined {
  return aggregations.value.find((agg) => agg.name === name);
}

function switchFacet(aggKey: string, bucketKey: string) {
  const agg = findAggregation(aggKey);
  const bucket = agg?.buckets.find(
    (item) => item.key === bucketKey || item.query_value === bucketKey
  );
  if (!bucket) return;
  bucket.selected = !bucket.selected;
  const { filters, captions } = getExtraParams();
  emit("filters", filters, captions);
}

function getExtraParams(): {
  filters: Record<string, string[]>;
  captions: Record<string, string>;
} {
  const filters: Record<string, string[]> = {};
  const captions: Record<string, string> = {};
  for (const agg of aggregations.value) {
    for (const bucket of agg.buckets) {
      if (bucket.selected) {
        filters[agg.name] = [...(filters[agg.name] ?? []), bucket.key];
        captions[bucket.key] = bucket.help ?? bucket.caption ?? bucket.key;
      }
    }
  }
  return { filters, captions };
}

function clearFilters() {
  for (const agg of aggregations.value) {
    for (const bucket of agg.buckets) {
      bucket.selected = false;
    }
  }
  emit("filters", {}, {});
}

const hasAggregations = computed(() =>
  aggregations.value.some((a) => a.buckets.length > 0)
);

const hasFilters = computed(() =>
  aggregations.value.some((a) => a.buckets.some((b) => b.selected))
);

const loading = computed(() => !!props.loading);

function hasSelected(agg: MappedAggregation): boolean {
  return agg.buckets.some((b) => b.selected);
}

function selectedCount(agg: MappedAggregation): number {
  return agg.buckets.filter((b) => b.selected).length;
}

async function resolveFacetLabels(agg: MappedAggregation): Promise<void> {
  if (
    !facetNeedsNetworkLabels(agg.name) ||
    agg.labelsResolved ||
    resolvingNames.has(agg.name)
  )
    return;
  resolvingNames.add(agg.name);
  resolving.value = agg.name;
  try {
    const resolveKey =
      agg.name === "related_domains"
        ? getDomainLabel
        : agg.name === "producers"
        ? getStructureAcronym
        : getPersonName;
    const current = findAggregation(agg.name) ?? agg;
    const labels: Record<string, string> = {
      ...(props.networkLabels?.[agg.name] ?? {}),
      ...(agg.name === "related_domains"
        ? (props.networkLabels?.domains ?? {})
        : {}),
      ...(resolvedLabels.value[agg.name] ?? {}),
    };
    const keys = current.buckets
      .map((bucket) => bucket.key)
      .filter((key) => {
        const known = labels[key];
        return !known || isRawEntityLabel(known, key);
      });
    const titles: Record<string, string> = {};
    if (agg.name === "producers") {
      const visuals = await Promise.all(
        keys.map(async (key) => [key, await getStructureVisuals(key)] as const)
      );
      for (const [key, visual] of visuals) {
        const label = visual.acronym || visual.name;
        if (label && !isRawEntityLabel(label, key)) {
          labels[key] = label;
        }
        if (visual.name && visual.name !== labels[key]) {
          titles[key] = visual.name;
        }
      }
    } else {
      const entries = await Promise.all(
        keys.map(async (key) => [key, await resolveKey(key)] as const)
      );
      for (const [key, label] of entries) {
        if (label && !isRawEntityLabel(label, key)) {
          labels[key] = label;
        }
      }
    }
    resolvedLabels.value = { ...resolvedLabels.value, [agg.name]: labels };
    aggregations.value = aggregations.value.map((item) =>
      item.name === agg.name ? applyResolvedLabels(item, labels, titles) : item
    );
    const updated = findAggregation(agg.name);
    if (updated?.buckets.some((bucket) => bucket.selected)) {
      emit("captions", getExtraParams().captions);
    }
  } finally {
    resolvingNames.delete(agg.name);
    resolving.value = resolvingNames.size ? [...resolvingNames][0] : null;
  }
}

function prefetchNetworkLabels() {
  for (const agg of aggregations.value) {
    void resolveFacetLabels(agg);
  }
}

watch(
  () => props.prefetch,
  (open) => {
    if (open) prefetchNetworkLabels();
  },
  { immediate: true }
);

async function toggle(aggKey: string) {
  const target = findAggregation(aggKey);
  if (!target) return;
  const opening = !target.visible;
  for (const agg of aggregations.value) {
    agg.visible = false;
  }
  if (opening) {
    target.visible = true;
    await resolveFacetLabels(target);
  }
}

async function toggleAuthors(aggregation: MappedAggregation) {
  aggregation.showAll = !aggregation.showAll;
  if (aggregation.showAll) {
    aggregation.labelsResolved = false;
    await resolveFacetLabels(aggregation);
  }
}

function closeAll() {
  for (const agg of aggregations.value) {
    agg.visible = false;
  }
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

.filter-column {
  position: relative;
  min-width: 0;
  overflow: visible;
  z-index: 1;
}

.filter-column:has(.filter-pill--open) {
  z-index: 3;
}

/* ── Pill button (full-width inside its column) ── */
.filter-pill {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  gap: 0.35rem;
  padding: 0.4rem 0.75rem;
  border: 1.5px solid var(--naas-border, #dee2e6);
  border-radius: var(--naas-radius, 8px);
  background: var(--naas-surface, #fff);
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--naas-text, #1f2937);
  cursor: pointer;
  transition: border-color var(--naas-transition, 0.18s ease),
    background var(--naas-transition, 0.18s ease),
    color var(--naas-transition, 0.18s ease);
  line-height: 1.4;
  text-align: left;
}

.filter-pill:hover {
  border-color: var(--naas-primary, #0f6cbf);
  color: var(--naas-primary, #0f6cbf);
  background: var(--naas-surface-hover, #f0f4ff);
}

.filter-pill--active {
  background: var(--naas-primary, #0f6cbf);
  border-color: var(--naas-primary, #0f6cbf);
  color: #fff;
}

.filter-pill--active:hover {
  background: var(--naas-primary-dark, #0a4a8f);
  color: #fff;
}

.filter-pill--open {
  border-color: var(--naas-primary, #0f6cbf);
  color: var(--naas-primary, #0f6cbf);
}

.filter-pill--active.filter-pill--open {
  color: #fff;
}

.filter-pill-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.1rem;
  height: 1.1rem;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.3);
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1;
  padding: 0 0.2rem;
}

.filter-pill-chevron {
  font-size: 0.65rem;
  flex-shrink: 0;
}

/* ── Dropdown panel ── */
.filter-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  z-index: 200;
  width: 100%;
  min-width: 180px;
  max-height: min(40vh, 280px);
  overflow-y: auto;
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.16);
  padding: 0.25rem 0;
}

.filter-dropdown-list {
  display: flex;
  flex-direction: column;
}

/* ── Checkbox option row ── */
.filter-option {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.35rem 0.75rem;
  cursor: pointer;
  font-size: 0.8125rem;
  color: #343a40;
  transition: background 0.12s;
  margin: 0;
}

.filter-option:hover {
  background: #f0f4ff;
}

.filter-option input[type="checkbox"] {
  flex-shrink: 0;
  margin: 0;
  accent-color: var(--primary, #0f6cbf);
}

.filter-option--hidden {
  display: none;
}

/* ── Show more ── */
.show-more-btn {
  background: none;
  border: none;
  padding: 0.3rem 0.75rem;
  font-size: 0.8rem;
  color: var(--primary, #0f6cbf);
  cursor: pointer;
  text-align: left;
}

.show-more-btn:hover {
  text-decoration: underline;
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
  transition: background var(--naas-transition, 0.18s ease),
    color var(--naas-transition, 0.18s ease);
}

.filter-clear-btn:hover {
  background: #dc3545;
  color: #fff;
}

/* ── Dropdown slide-down transition ── */
.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: opacity 0.16s ease, transform 0.16s ease;
}
.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
