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
      <h3 class="filter-title">{{ config.labels.metadata.filters ?? 'Filters' }}</h3>
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
        v-for="(aggregation, aggKey) in aggregations"
        :key="aggKey"
        class="filter-column"
      >
        <button
          type="button"
          class="filter-pill"
          :class="{ 'filter-pill--active': hasSelected(aggregation), 'filter-pill--open': aggregation.visible }"
          @click="toggle(aggKey)"
        >
          {{ config.labels.metadata[aggKey] ?? aggKey }}
          <span v-if="hasSelected(aggregation)" class="filter-pill-count">
            {{ selectedCount(aggregation) }}
          </span>
          <i class="filter-pill-chevron icon fa" :class="aggregation.visible ? 'fa-chevron-up' : 'fa-chevron-down'" />
        </button>

        <div v-show="aggregation.visible" class="filter-dropdown">
          <!-- Related domains -->
          <div v-if="aggKey === 'related_domains'">
            <span v-for="bucket in relatedDomains" :key="bucket.key">
              <RelatedDomain
                :bucket="bucket"
                @bucket-click="switchFacet('related_domains', $event)"
              />
            </span>
          </div>

          <!-- All other aggregations -->
          <div v-else class="filter-dropdown-list">
            <label
              v-for="(bucket, _idx) in aggregation.buckets"
              :key="bucket.key"
              class="filter-option"
              :class="{ 'filter-option--hidden': aggKey === 'authors' && Number(_idx) > 5 && !aggregation.showAll }"
            >
              <input
                type="checkbox"
                :checked="bucket.selected"
                @change="switchFacet(aggKey, bucket.query_value ?? '')"
              />
              <span>{{ bucket.caption }}</span>
            </label>

            <button
              v-if="aggKey === 'authors' && hasMore(aggregation)"
              type="button"
              class="show-more-btn"
              @click="aggregation.showAll = !aggregation.showAll"
            >
              {{ aggregation.showAll ? `− ${config.labels.hide_authors}` : `+ ${config.labels.show_more_authors}` }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import RelatedDomain from './RelatedDomain.vue'
import FilterSkeleton from './FilterSkeleton.vue'
import { useNaasConfig } from '@/composables/useNaasConfig'
import { useNuggetSearch } from '@/composables/useNuggetSearch'
import { useEntityResolvers } from '@/composables/useEntityResolvers'
import type { SearchOptions, AggregationBucket } from '@/types/nugget.types'

interface AggregationUI {
  buckets: Record<string, AggregationBucket>
  visible: boolean
  showAll: boolean
  name: string
}

type ElWithHandler = HTMLElement & { __coh__: (e: Event) => void }

const vClickOutside = {
  mounted(el: HTMLElement, binding: { value: () => void }) {
    const handler = (e: Event) => {
      if (!el.contains(e.target as Node)) binding.value()
    }
    ;(el as ElWithHandler).__coh__ = handler
    document.addEventListener('click', handler)
  },
  unmounted(el: HTMLElement) {
    document.removeEventListener('click', (el as ElWithHandler).__coh__)
  },
}

const props = defineProps<{
  query: SearchOptions | null
  activeFilters: Record<string, string[]>
}>()
const emit = defineEmits<{
  (e: 'filters', filters: Record<string, string[]>, captions: Record<string, string>): void
}>()

const config = useNaasConfig()
const { search } = useNuggetSearch()
const { getDomainLabel, getStructureAcronym, getPersonName } = useEntityResolvers()

const loading = ref(true)
const aggregations = ref<Record<string, AggregationUI>>({})
const relatedDomains = ref<AggregationBucket[]>([])

// Ordered display definition for aggregation panels.
type AggDef = {
  name: string
  aggregation_key: string
  bucket_key_to_ui: (key: string) => Promise<string>
  bucket_key_to_query: (key: string) => string
}

const aggDefinitions: AggDef[] = [
  {
    name: 'related_domains',
    aggregation_key: 'related_domains',
    bucket_key_to_ui: getDomainLabel,
    bucket_key_to_query: (k) => k,
  },
  {
    name: 'level',
    aggregation_key: 'level',
    bucket_key_to_ui: async (k) => config.labels.metadata[k] ?? k,
    bucket_key_to_query: (k) => k,
  },
  {
    name: 'language',
    aggregation_key: 'language',
    bucket_key_to_ui: async (k) => config.labels.metadata[k] ?? k,
    bucket_key_to_query: (k) => k,
  },
  {
    name: 'tags',
    aggregation_key: 'tags',
    bucket_key_to_ui: async (k) => k,
    bucket_key_to_query: (k) => k,
  },
  {
    name: 'producers',
    aggregation_key: 'producers',
    bucket_key_to_ui: getStructureAcronym,
    bucket_key_to_query: (k) => k,
  },
  {
    name: 'authors',
    aggregation_key: 'authors',
    bucket_key_to_ui: getPersonName,
    bucket_key_to_query: (k) => k,
  },
  {
    name: 'references',
    aggregation_key: 'references',
    bucket_key_to_ui: async (k) => k,
    bucket_key_to_query: (k) => k,
  },
  {
    name: 'type',
    aggregation_key: 'type',
    bucket_key_to_ui: async (k) => config.labels.metadata[k] ?? k,
    bucket_key_to_query: (k) => k,
  },
]

watch(
  () => props.query,
  async (q) => {
    if (!q) { aggregations.value = {}; return }
    await load(q)
  },
  { deep: true, immediate: true }
)

// When the widget removes a chip, sync bucket selected state from the prop.
watch(
  () => props.activeFilters,
  (active) => {
    for (const [aggKey, agg] of Object.entries(aggregations.value)) {
      for (const [bucketKey, bucket] of Object.entries(agg.buckets)) {
        bucket.selected = (active[aggKey] ?? []).includes(bucketKey)
      }
    }
  },
  { deep: true }
)

async function load(query: SearchOptions) {
  loading.value = true
  try {
    const result = await search(query)
    if (!result) return
    await handleAggregations(result.aggregations)
  } catch {
    aggregations.value = {}
  } finally {
    loading.value = false
  }
}

async function handleAggregations(
  networkAgg: Record<string, { buckets: Array<{ key: string; docCount: number }> }>
) {
  const newAggs: Record<string, AggregationUI> = {}
  const newRelatedDomains: Record<string, AggregationBucket> = {}

  for (const def of aggDefinitions) {
    const raw = networkAgg[def.aggregation_key]
    if (!raw?.buckets?.length) continue

    const oldVisible = aggregations.value[def.name]?.visible ?? false
    const oldShowAll = aggregations.value[def.name]?.showAll ?? false
    newAggs[def.name] = { buckets: {}, visible: oldVisible, showAll: oldShowAll, name: def.name }

    const bucketsWithCaptions = await Promise.all(
      raw.buckets.map(async (b) => {
        const caption = await def.bucket_key_to_ui(b.key)
        return {
          ...b,
          selected: (props.activeFilters[def.name] ?? []).includes(b.key),
          caption: `${caption} (${b.docCount})`,
          help: caption,
          query_value: def.bucket_key_to_query(b.key),
          children: {} as Record<string, AggregationBucket>,
        } as AggregationBucket
      })
    )

    const sorted = [...bucketsWithCaptions].sort((a, b) =>
      (a.caption ?? '').localeCompare(b.caption ?? '')
    )

    for (const bucket of sorted) {
      newAggs[def.name].buckets[bucket.key] = bucket
      if (def.name === 'related_domains') {
        createChildren(newRelatedDomains, bucket, 2)
      }
    }
  }

  relatedDomains.value = Object.values(newRelatedDomains).sort((a, b) =>
    (a.caption ?? '').localeCompare(b.caption ?? '')
  )
  aggregations.value = newAggs
}

function createChildren(
  map: Record<string, AggregationBucket>,
  bucket: AggregationBucket,
  index: number
) {
  const parentKey = bucket.key.slice(0, index)
  if (!map[parentKey]) {
    map[bucket.key] = { ...bucket, children: {} }
  } else {
    createChildren(map[parentKey].children!, bucket, index + 1)
  }
}

function switchFacet(aggKey: string, bucketKey: string) {
  const agg = aggregations.value[aggKey]
  if (!agg?.buckets[bucketKey]) return
  agg.buckets[bucketKey].selected = !agg.buckets[bucketKey].selected
  const { filters, captions } = getExtraParams()
  emit('filters', filters, captions)
}

function getExtraParams(): { filters: Record<string, string[]>; captions: Record<string, string> } {
  const filters: Record<string, string[]> = {}
  const captions: Record<string, string> = {}
  for (const [aggKey, agg] of Object.entries(aggregations.value)) {
    for (const bucket of Object.values(agg.buckets)) {
      if (bucket.selected) {
        filters[aggKey] = [...(filters[aggKey] ?? []), bucket.key]
        captions[bucket.key] = bucket.help ?? bucket.caption ?? bucket.key
      }
    }
  }
  return { filters, captions }
}

function clearFilters() {
  for (const agg of Object.values(aggregations.value)) {
    for (const bucket of Object.values(agg.buckets)) {
      bucket.selected = false
    }
  }
  relatedDomains.value = relatedDomains.value.map((d) => ({ ...d, selected: false }))
  emit('filters', {}, {})
}

const hasAggregations = computed(() =>
  Object.values(aggregations.value).some((a) => Object.keys(a.buckets).length > 0)
)

const hasFilters = computed(() =>
  Object.values(aggregations.value).some((a) =>
    Object.values(a.buckets).some((b) => b.selected)
  )
)

function hasMore(agg: AggregationUI): boolean {
  return Object.keys(agg.buckets).length > 5
}

function hasSelected(agg: AggregationUI): boolean {
  return Object.values(agg.buckets).some((b) => b.selected)
}

function selectedCount(agg: AggregationUI): number {
  return Object.values(agg.buckets).filter((b) => b.selected).length
}

function toggle(aggKey: string) {
  const opening = !aggregations.value[aggKey].visible
  for (const key of Object.keys(aggregations.value)) {
    aggregations.value[key].visible = false
  }
  if (opening) aggregations.value[aggKey].visible = true
}

function closeAll() {
  for (const key of Object.keys(aggregations.value)) {
    aggregations.value[key].visible = false
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
  color: #343a40;
}

/* ── 3-column grid ── */
.filter-columns {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem;
}

.filter-column {
  position: relative;
  min-width: 0;
}

/* ── Pill button (full-width inside its column) ── */
.filter-pill {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  gap: 0.35rem;
  padding: 0.4rem 0.75rem;
  border: 1px solid #ced4da;
  border-radius: 6px;
  background: #fff;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #495057;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s, color 0.15s;
  line-height: 1.4;
  text-align: left;
}

.filter-pill:hover {
  border-color: var(--primary, #0f6cbf);
  color: var(--primary, #0f6cbf);
}

.filter-pill--active {
  background: var(--primary, #0f6cbf);
  border-color: var(--primary, #0f6cbf);
  color: #fff;
}

.filter-pill--active:hover {
  background: var(--primary-dark, #0a5499);
  color: #fff;
}

.filter-pill--open {
  border-color: var(--primary, #0f6cbf);
  color: var(--primary, #0f6cbf);
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
  background: rgba(255,255,255,0.3);
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
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  box-shadow: 0 4px 16px rgba(0,0,0,0.12);
  padding: 0.25rem 0;
}

.filter-dropdown-list {
  display: flex;
  flex-direction: column;
  max-height: 240px;
  overflow-y: auto;
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

.show-more-btn:hover { text-decoration: underline; }

/* ── Clear all ── */
.filter-clear-btn {
  background: none;
  border: 1px solid #dc3545;
  border-radius: 20px;
  padding: 0.25rem 0.65rem;
  font-size: 0.8125rem;
  color: #dc3545;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s, color 0.15s;
}

.filter-clear-btn:hover {
  background: #dc3545;
  color: #fff;
}
</style>
