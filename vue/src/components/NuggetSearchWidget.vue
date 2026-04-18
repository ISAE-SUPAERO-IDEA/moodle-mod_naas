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
 * Search widget: text input, facet filters, nugget grid, and load-more.
 * Used in Moodle activity-creation forms to let teachers pick a nugget.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div>
    <div v-if="error" class="naas-error-banner" role="alert">
      <span>{{ config.labels.error_generic_user_message }}</span>
      <button class="btn btn-sm btn-outline-danger" @click="doSearch">
        {{ config.labels.retry || 'Retry' }}
      </button>
    </div>

    <!-- Search + filter + results grid -->
    <div
      v-if="selectedNugget === null && !selectedNuggetLoading"
      class="row"
    >
      <!-- Label -->
      <div class="col-md-3 col-form-label d-flex pb-0 pr-md-0">
        <label class="d-inline word-break" id="nugget_search">
          {{ config.labels.search }}
        </label>
      </div>

      <!-- Search input -->
      <div
        class="col-md-9 form-inline align-items-start felement"
        data-fieldtype="text"
      >
        <input
          v-model="typed"
          @input="onInput"
          @keydown="$event.keyCode === 13 ? $event.preventDefault() : false"
          size="43"
          class="form-control"
          :placeholder="config.labels.nugget_search_here"
        />
        <img
          src="../../../assets/search_icon.png"
          class="search-center"
          width="35"
          height="35"
          alt=""
        />
      </div>

      <!-- Filter panel -->
      <div class="col-md-3">
        <NuggetSearchFilter
          :query="filterQuery"
          @filters="onFilters"
        />
      </div>

      <!-- Nugget grid -->
      <div class="col-md-9">
        <div
          class="row"
          role="listbox"
          :aria-label="config.labels.search"
          aria-multiselectable="false"
          @keydown="onGridKeydown"
        >
          <!-- Skeleton cards while loading -->
          <template v-if="loading">
            <div
              v-for="n in skeletonCount"
              :key="`skel-${n}`"
              class="col-6 col-lg-4 col-xl-3 nugget-post-selection"
              style="min-width: 400px"
            >
              <NuggetSkeleton />
            </div>
          </template>

          <template v-else>
            <div
              v-for="(nugget, index) in nuggets"
              :key="index"
              class="col-6 col-lg-4 col-xl-3 nugget-post-selection"
              style="min-width: 400px"
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

            <div
              v-if="nuggets.length === 0"
              class="col-md-9 form-inline align-items-start felement"
            >
              {{ config.labels.nugget_search_no_result }}
            </div>
          </template>
        </div>

        <!-- Pagination controls -->
        <div v-if="totalPages > 1" class="pagination-bar">
          <button
            class="btn btn-sm btn-outline-primary"
            :disabled="page === 1"
            @click="goToPage(page - 1)"
          >
            ◀ {{ config.labels.previous_page || 'Previous' }}
          </button>
          <span class="pagination-info">{{ page }} / {{ totalPages }}</span>
          <button
            class="btn btn-sm btn-outline-primary"
            :disabled="page === totalPages"
            @click="goToPage(page + 1)"
          >
            {{ config.labels.next_page || 'Next' }} ▶
          </button>
        </div>
      </div>
    </div>

    <!-- Selected nugget display -->
    <div v-else class="row">
      <div class="col-md-3" />
      <div class="col-md-9 nugget-selected">
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
import { useNaasConfig } from '@/composables/useNaasConfig'
import { useNuggetSearch } from '@/composables/useNuggetSearch'
import type { Nugget, SearchOptions } from '@/types/nugget.types'

const config = useNaasConfig()
const { nuggets, loading, error, search, getNuggetById } = useNuggetSearch()

const skeletonCount = 6
const PAGE_SIZE = 6

const typed = ref('')
const debouncedTyped = ref('')
const filters = ref<Record<string, string[]>>({})
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
const totalPages = ref(1)

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

async function doSearch() {
  const result = await search(searchOptions.value)
  if (result) {
    totalPages.value = Math.max(1, Math.ceil(result.results_count / PAGE_SIZE))
  }
  focusedIndex.value = 0
  itemRefs.length = 0
}

function goToPage(n: number) {
  page.value = n
  doSearch()
}

const onInput = debounce(() => {
  debouncedTyped.value = typed.value
  page.value = 1
  doSearch()
}, 500)

function onFilters(newFilters: Record<string, string[]>) {
  filters.value = newFilters
  page.value = 1
  doSearch()
}

function clickOnNugget(nugget: Nugget) {
  if (selectedId.value === nugget.nugget_id) {
    selectedId.value = null
    syncMoodleForm(null)
  } else {
    selectedId.value = nugget.nugget_id
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
.nugget-selected {
  margin-bottom: 75px;
}

.nugget-post-selection {
  margin-bottom: 20px;
}

.nugget-post-selected :deep(.nugget-post) {
  background: var(--primary-light, #dce9fa);
  outline: 2px solid var(--primary, #0f6cbf);
}

.show-more-nugget {
  margin-left: 25px;
  margin-bottom: 20px;
}

.search-center {
  display: block;
  margin: auto 0 auto 10px;
  border: 1px solid;
}

.btn-replace {
  margin-left: 10px;
}

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

.pagination-bar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 0.75rem 0 1rem 25px;
}

.pagination-info {
  font-size: 0.875rem;
  color: #6c757d;
  min-width: 4rem;
  text-align: center;
}
</style>
