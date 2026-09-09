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
      <span>{{ config.labels.error_generic_user_message }} — {{ error.message }}</span>
      <button class="btn btn-sm btn-outline-danger" @click="doSearch()">
        {{ config.labels.retry || 'Retry' }}
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
              @click="typed = ''; debouncedTyped = ''; page = 1; doSearch()"
            >×</button>
          </div>
          <button type="button" class="filters-toggle-btn" @click="filtersOpen = true">
            <i class="icon fa fa-sliders" />
            {{ config.labels.metadata.filters ?? 'Filters' }}
            <span v-if="activeFilterCount > 0" class="filters-toggle-badge">{{ activeFilterCount }}</span>
          </button>
        </div>
      </div>

      <!-- Filters modal panel (teleported to body to escape Moodle stacking) -->
      <Teleport to="body">
        <transition name="panel-slide">
          <div v-if="filtersOpen" class="filters-panel-backdrop" @click.self="filtersOpen = false">
            <div class="filters-panel">
              <div class="filters-panel-header">
                <span class="filters-panel-title">{{ config.labels.metadata.filters ?? 'Filters' }}</span>
                <button type="button" class="filters-panel-close" @click="filtersOpen = false">✕</button>
              </div>
              <div class="filters-panel-body">
                <NuggetSearchFilter
                  :query="filterQuery"
                  :active-filters="filters"
                  @filters="onFilters"
                />
              </div>
            </div>
          </div>
        </transition>
      </Teleport>

      <!-- Filter chips + nugget grid -->
      <div class="search-results">
          <!-- Active filter chips -->
          <FilterChips
            :filters="filters"
            :labels="config.labels.metadata"
            :bucket-captions="bucketCaptions"
            @remove="removeFilter"
            @clear="clearAllFilters"
          />

          <div
            class="nugget-grid"
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
                <NuggetSkeleton />
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
                  :class="{ 'nugget-post-selected': nugget.nugget_id === selectedId }"
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
                  <NuggetSkeleton />
                </div>
              </template>

              <div v-if="nuggets.length === 0" class="no-results">
                {{ config.labels.nugget_search_no_result }}
              </div>
            </template>
          </div>

          <!-- Load more -->
          <div v-if="hasMore" class="load-more-bar">
            <button
              class="btn btn-sm btn-outline-primary"
              :disabled="loadingMore"
              @click="loadMore"
            >
              {{ config.labels.load_more || 'Load more' }}
            </button>
        </div>
      </div>
    </div>

    <!-- Selected nugget display -->
    <div v-else class="selected-nugget-wrap">
      <div class="selected-nugget-inner">
        <NuggetSkeleton v-if="selectedNuggetLoading" />
        <div v-if="!selectedNuggetLoading && selectedNugget">
          <NuggetPost :nugget="selectedNugget" />
          <a
            href="javascript:;"
            class="btn btn-primary btn-replace"
            @click="clearSelection"
          >
            {{ config.labels.click_to_replace }}
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import debounce from 'debounce'
import NuggetSearchFilter from './NuggetSearchFilter.vue'
import NuggetPost from './NuggetPost.vue'
import NuggetSkeleton from './NuggetSkeleton.vue'
import FilterChips from './FilterChips.vue'
import { useNaasConfig } from '@/composables/useNaasConfig'
import { useNuggetSearch } from '@/composables/useNuggetSearch'
import type { Nugget, SearchOptions } from '@/types/nugget.types'

const config = useNaasConfig()
const { nuggets, loading, loadingMore, error, search, getNuggetById } = useNuggetSearch({ initialLoading: true })

const skeletonCount = 9
const PAGE_SIZE = 9

const typed = ref('')
const debouncedTyped = ref('')
const filters = ref<Record<string, string[]>>({})
const bucketCaptions = ref<Record<string, string>>({})
const filtersOpen = ref(false)
const activeFilterCount = computed(() =>
  Object.values(filters.value).reduce((sum, arr) => sum + arr.length, 0)
)
const selectedNugget = ref<Nugget | null>(null)
const focusedIndex = ref(0)
const itemRefs: HTMLElement[] = []

function setItemRef(el: unknown, index: number) {
  if (el instanceof HTMLElement) itemRefs[index] = el
}

function onGridKeydown(e: KeyboardEvent) {
  if (!nuggets.value.length) return
  if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
    e.preventDefault()
    focusedIndex.value = Math.min(focusedIndex.value + 1, nuggets.value.length - 1)
    itemRefs[focusedIndex.value]?.focus()
  } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
    e.preventDefault()
    focusedIndex.value = Math.max(focusedIndex.value - 1, 0)
    itemRefs[focusedIndex.value]?.focus()
  }
}

const selectedNuggetLoading = ref(false)
const selectedId = ref<string | null>(null)
const page = ref(1)
const hasMore = ref(false)

// Query object forwarded to NuggetSearchFilter for aggregation display.
const filterQuery = computed<SearchOptions>(() => ({
  page_size: PAGE_SIZE,
  fulltext: debouncedTyped.value,
}))

// Full search options including active facet filters and current page.
const searchOptions = computed<SearchOptions>(() => ({
  ...filterQuery.value,
  ...filters.value,
  page: page.value,
}))

async function doSearch(append = false) {
  const result = await search(searchOptions.value, append)
  if (result) {
    const loaded = append
      ? nuggets.value.length
      : (result.items?.length ?? 0)
    hasMore.value = loaded < result.results_count
  }
  if (!append) {
    focusedIndex.value = 0
    itemRefs.length = 0
  }
}

async function loadMore() {
  page.value += 1
  await doSearch(true)
}

const onInput = debounce(() => {
  debouncedTyped.value = typed.value
  page.value = 1
  doSearch()
}, 500)

function onFilters(newFilters: Record<string, string[]>, captions?: Record<string, string>) {
  filters.value = newFilters
  if (captions) bucketCaptions.value = { ...bucketCaptions.value, ...captions }
  page.value = 1
  doSearch()
}

function removeFilter(key: string, value: string) {
  const current = filters.value[key] ?? []
  const updated = current.filter((v) => v !== value)
  if (updated.length) {
    filters.value = { ...filters.value, [key]: updated }
  } else {
    const { [key]: _, ...rest } = filters.value
    filters.value = rest
  }
  page.value = 1
  doSearch()
}

function clearAllFilters() {
  filters.value = {}
  bucketCaptions.value = {}
  page.value = 1
  doSearch()
}

function clickOnNugget(nugget: Nugget) {
  if (selectedId.value === nugget.nugget_id) {
    selectedId.value = null
    selectedNugget.value = null
    syncMoodleForm(null)
  } else {
    selectedId.value = nugget.nugget_id
    selectedNugget.value = nugget
    syncMoodleForm(nugget)
  }
}

function clearSelection() {
  selectedNugget.value = null
  doSearch()
}

// Writes the selected nugget_id into the hidden Moodle form field so
// the form submission carries the correct value.
function syncMoodleForm(nugget: Nugget | null) {
  const nameField = document.getElementById('id_name') as HTMLInputElement | null
  const nuggetIdField = document.getElementsByName('nugget_id')[0] as HTMLInputElement | null

  if (nameField) nameField.value = nugget?.name ?? ''
  if (nuggetIdField) nuggetIdField.value = nugget?.nugget_id ?? ''
}

onMounted(async () => {
  const nuggetIdField = document.getElementsByName('nugget_id')[0] as HTMLInputElement | null
  const storedId = nuggetIdField?.value

  if (storedId && storedId !== 'nugget_id' && storedId !== '') {
    selectedNuggetLoading.value = true
    selectedId.value = storedId
    const found = await getNuggetById(storedId)
    selectedNugget.value = found
    selectedNuggetLoading.value = false
  } else {
    doSearch()
  }
})
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
              box-shadow   var(--naas-transition, 0.18s ease);
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
  min-width: 0;
}

.no-results {
  grid-column: 1 / -1;
  padding: 1.5rem 0;
  color: #6c757d;
  text-align: center;
}

/* ── Selected state ────────────────────────────────────────────────────── */
.nugget-post-selected :deep(.nugget-post) {
  box-shadow: 0 0 0 2px var(--primary, #0f6cbf);
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

.btn-replace {
  margin-top: 0.75rem;
  display: inline-block;
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

/* ── Load more ─────────────────────────────────────────────────────────── */
.load-more-bar {
  display: flex;
  justify-content: center;
  margin: 1.25rem 0 0.5rem;
}

/* ── Filters toggle button ─────────────────────────────────────────────── */
.filters-toggle-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.375rem 0.85rem;
  border: 1px solid #ced4da;
  border-radius: 4px;
  background: #fff;
  font-size: 0.875rem;
  font-weight: 500;
  color: #495057;
  cursor: pointer;
  white-space: nowrap;
  transition: border-color 0.15s, color 0.15s;
  flex-shrink: 0;
}

.filters-toggle-btn:hover {
  border-color: var(--primary, #0f6cbf);
  color: var(--primary, #0f6cbf);
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

/* ── Filters panel backdrop ────────────────────────────────────────────── */
.filters-panel-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.35);
  z-index: 1050;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 60px;
}

/* ── Filters panel ─────────────────────────────────────────────────────── */
.filters-panel {
  width: 100%;
  max-width: 900px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18);
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 80px);
  overflow: hidden;
}

.filters-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #e9ecef;
  flex-shrink: 0;
}

.filters-panel-title {
  font-size: 1rem;
  font-weight: 600;
  color: #343a40;
}

.filters-panel-close {
  background: none;
  border: none;
  font-size: 1.2rem;
  line-height: 1;
  color: #6c757d;
  cursor: pointer;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  transition: color 0.15s, background 0.15s;
}

.filters-panel-close:hover {
  color: #212529;
  background: #f0f0f0;
}

.filters-panel-body {
  padding: 1.25rem;
  overflow-y: auto;
}

/* ── Panel slide transition ────────────────────────────────────────────── */
.panel-slide-enter-active,
.panel-slide-leave-active {
  transition: opacity 0.2s ease;
}
.panel-slide-enter-active .filters-panel,
.panel-slide-leave-active .filters-panel {
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.panel-slide-enter-from,
.panel-slide-leave-to {
  opacity: 0;
}
.panel-slide-enter-from .filters-panel,
.panel-slide-leave-to .filters-panel {
  transform: translateY(-12px);
  opacity: 0;
}
</style>
