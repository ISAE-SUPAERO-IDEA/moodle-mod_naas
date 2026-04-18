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
  <div class="filters">
    <img v-show="loading" src="../../../assets/loading.gif" width="35" height="35" alt="" />

    <div v-show="hasAggregations" class="filters-inner">
      <div
        v-for="(aggregation, aggKey) in aggregations"
        :key="aggKey"
      >
        <a
          href="javascript:;"
          class="aggregation-title"
          @click="aggregation.visible = !aggregation.visible"
        >
          <h6 class="filters-title">
            {{ config.labels.metadata[aggKey] ?? aggKey }}
            <i :class="aggregation.visible ? 'icon fa fa-arrow-down' : 'icon fa fa-arrow-right'" />
          </h6>
        </a>

        <div v-show="aggregation.visible">
          <!-- Related domains use a recursive tree component -->
          <div v-if="aggKey === 'related_domains'" id="related_domains">
            <span v-for="bucket in relatedDomains" :key="bucket.key">
              <RelatedDomain
                :bucket="bucket"
                @bucket-click="switchFacet('related_domains', $event)"
              />
            </span>
          </div>

          <!-- All other aggregations render flat badges -->
          <span
            v-for="(bucket, _idx) in aggregation.buckets"
            v-else
            :key="bucket.key"
          >
            <a
              href="javascript:;"
              :class="{ 'hide-authors': aggKey === 'authors' && Number(_idx) > 5 }"
            >
              <NuggetBadge
                :selected="bucket.selected"
                :text="bucket.caption"
                :help="bucket.help"
                @click="switchFacet(aggKey, bucket.query_value ?? '')"
              />
            </a>
          </span>

          <div v-if="aggKey === 'authors' && hasMore(aggregation)">
            <a
              href="javascript:;"
              id="show-more-authors"
              class="clear-filters show-more"
              @click="showMoreAuthors"
            >
              + {{ config.labels.show_more_authors }}
            </a>
          </div>
        </div>
      </div>

      <div v-show="hasFilters" class="clear-filters">
        <a href="javascript:;" class="btn btn-primary btn-small" @click="clearFilters">
          {{ config.labels.clear_filters }}
        </a>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import RelatedDomain from './RelatedDomain.vue'
import NuggetBadge from './NuggetBadge.vue'
import { useNaasConfig } from '@/composables/useNaasConfig'
import { useNuggetSearch } from '@/composables/useNuggetSearch'
import { useEntityResolvers } from '@/composables/useEntityResolvers'
import type { SearchOptions, AggregationBucket } from '@/types/nugget.types'

interface AggregationUI {
  buckets: Record<string, AggregationBucket>
  visible: boolean
  name: string
}

const props = defineProps<{ query: SearchOptions | null }>()
const emit = defineEmits<{ (e: 'filters', filters: Record<string, string[]>): void }>()

const config = useNaasConfig()
const { search } = useNuggetSearch()
const { getDomainLabel, getStructureAcronym, getPersonName } = useEntityResolvers()

const loading = ref(false)
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

    const oldVisible = aggregations.value[def.name]?.visible ?? true
    newAggs[def.name] = { buckets: {}, visible: oldVisible, name: def.name }

    const bucketsWithCaptions = await Promise.all(
      raw.buckets.map(async (b) => {
        const caption = await def.bucket_key_to_ui(b.key)
        return {
          ...b,
          selected: aggregations.value[def.name]?.buckets[b.key]?.selected ?? false,
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
  emit('filters', getExtraParams())
}

function getExtraParams(): Record<string, string[]> {
  const query: Record<string, string[]> = {}
  for (const [aggKey, agg] of Object.entries(aggregations.value)) {
    for (const bucket of Object.values(agg.buckets)) {
      if (bucket.selected) {
        query[aggKey] = [...(query[aggKey] ?? []), bucket.key]
      }
    }
  }
  return query
}

function clearFilters() {
  for (const agg of Object.values(aggregations.value)) {
    for (const bucket of Object.values(agg.buckets)) {
      bucket.selected = false
    }
  }
  // Reset domain tree selection state
  relatedDomains.value = relatedDomains.value.map((d) => ({ ...d, selected: false }))
  emit('filters', {})
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

function showMoreAuthors() {
  const hidden = document.querySelectorAll<HTMLElement>('.hide-authors')
  const isVisible = hidden[0]?.style.display === 'inline'
  const btn = document.getElementById('show-more-authors')
  hidden.forEach((el) => { el.style.display = isVisible ? 'none' : 'inline' })
  if (btn) {
    btn.innerHTML = isVisible
      ? `+ ${config.labels.show_more_authors}`
      : `- ${config.labels.hide_authors}`
  }
}
</script>

<style scoped>
.filters {
  float: left;
  width: 200px;
}

.filters-inner {
  background-color: white;
  display: table-cell;
  padding: 10px;
  margin-bottom: 10px;
}

.filters-title {
  margin-top: 10px;
  margin-bottom: 0;
  padding-top: 0;
}

.aggregation-title {
  color: #000;
  font-weight: 400;
}

.aggregation-title:hover {
  text-decoration: none;
}

.hide-authors {
  display: none;
}

.show-more {
  margin-left: 10px;
}

.clear-filters {
  font-size: 13px;
  margin-top: 15px;
}

.filters img {
  margin-left: 40px;
}

.separator {
  height: 15px;
}
</style>
