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
    <div v-if="error" class="alert alert-danger">
      {{ config.labels.error_generic_user_message }}
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
        <Loading :loading="loading" />
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
        <div class="row">
          <div
            v-for="(nugget, index) in nuggets"
            :key="index"
            class="col-6 col-lg-4 col-xl-3 nugget-post-selection"
            style="min-width: 400px"
          >
            <NuggetPost
              :nugget="nugget"
              :selection="true"
              :class="{ 'nugget-post-selected': nugget.nugget_id === selectedId }"
              @SelectButton="clickOnNugget"
            />
          </div>

          <div
            v-if="nuggets.length === 0 && !loading"
            class="col-md-9 form-inline align-items-start felement"
          >
            {{ config.labels.nugget_search_no_result }}
          </div>
        </div>

        <div class="row">
          <div class="show-more-nugget">
            <a
              v-if="showMoreButton"
              href="javascript:;"
              class="btn btn-primary nugget-button"
              @click="showMore"
            >
              {{ config.labels.show_more_nugget_button }}
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Selected nugget display -->
    <div v-else class="row">
      <div class="col-md-3" />
      <div class="col-md-9 nugget-selected">
        <Loading :loading="selectedNuggetLoading" />
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
import Loading from './Loading.vue'
import { useNaasConfig } from '@/composables/useNaasConfig'
import { useNuggetSearch } from '@/composables/useNuggetSearch'
import type { Nugget, SearchOptions } from '@/types/nugget.types'

const config = useNaasConfig()
const { nuggets, loading, error, search, getNuggetById } = useNuggetSearch()

const typed = ref('')
const debouncedTyped = ref('')
const filters = ref<Record<string, string[]>>({})
const selectedNugget = ref<Nugget | null>(null)
const selectedNuggetLoading = ref(false)
const selectedId = ref<string | null>(null)
const showMoreButton = ref(false)
const pageSize = ref(6)
const ADD_PAGE = 6

// Query object forwarded to NuggetSearchFilter for aggregation display.
const filterQuery = computed<SearchOptions>(() => ({
  page_size: pageSize.value,
  fulltext: debouncedTyped.value,
}))

// Full search options including active facet filters.
const searchOptions = computed<SearchOptions>(() => ({
  ...filterQuery.value,
  ...filters.value,
}))

async function doSearch() {
  showMoreButton.value = false
  const result = await search(searchOptions.value)
  if (result && result.results_count > pageSize.value) {
    showMoreButton.value = true
  }
}

const onInput = debounce(() => {
  debouncedTyped.value = typed.value
  pageSize.value = 6
  doSearch()
}, 500)

function onFilters(newFilters: Record<string, string[]>) {
  filters.value = newFilters
  doSearch()
}

function showMore() {
  pageSize.value += ADD_PAGE
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
