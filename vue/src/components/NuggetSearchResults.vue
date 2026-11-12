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
 * Search results of the widget: back-to-catalogue and layout toolbar, active
 * filter chips, loading status, the Nugget cards, and the infinite-scroll
 * sentinel that asks for the next page.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div class="search-results">
    <div class="results-toolbar">
      <button type="button" class="browse-back-btn" @click="emit('back')">
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
          :class="{ 'layout-toggle-btn--active': layout === 'card' }"
          :aria-pressed="layout === 'card'"
          @click="layout = 'card'"
        >
          <i class="icon fa fa-th-large" aria-hidden="true" />
          {{ config.labels.view_as_cards }}
        </button>
        <button
          type="button"
          class="layout-toggle-btn"
          :class="{ 'layout-toggle-btn--active': layout === 'row' }"
          :aria-pressed="layout === 'row'"
          @click="layout = 'row'"
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
      @remove="(key: string, value: string) => emit('removeFilter', key, value)"
      @clear="emit('clearFilters')"
    />

    <NuggetLoadingBanner v-if="loading || loadingMore" />
    <p
      v-else-if="revalidating"
      class="nugget-collecting-msg nugget-refreshing-msg"
      role="status"
      aria-live="polite"
    >
      <i class="icon fa fa-refresh fa-spin" aria-hidden="true" />
      {{ config.labels.checking_updates }}
    </p>

    <NuggetResultsGrid
      :nuggets="nuggets"
      :loading="loading"
      :loading-more="loadingMore"
      :layout="layout"
      :selected-id="selectedId"
      :failed="failed"
      @select="emit('select', $event)"
    />

    <div
      v-if="hasMore && !loading"
      ref="sentinel"
      class="load-more-sentinel"
      aria-hidden="true"
    />
  </div>
</template>

<script setup lang="ts">
import FilterChips from "./FilterChips.vue";
import NuggetLoadingBanner from "./NuggetLoadingBanner.vue";
import NuggetResultsGrid from "./NuggetResultsGrid.vue";
import { useNaasConfig } from "@/composables/useNaasConfig";
import { useLoadMoreSentinel } from "@/composables/useLoadMoreSentinel";
import type { Nugget } from "@/types/nugget.types";

defineProps<{
  nuggets: Nugget[];
  loading: boolean;
  loadingMore: boolean;
  /** A cached page is shown while NaaS is asked whether it changed. */
  revalidating: boolean;
  /** More pages exist: the sentinel is rendered under the cards. */
  hasMore: boolean;
  selectedId: string | null;
  filters: Record<string, string[]>;
  bucketCaptions: Record<string, string>;
  /** The search failed: the error banner explains it, so no "no results" text. */
  failed?: boolean;
}>();

/** Cards or rows; kept by the widget so it survives leaving the results. */
const layout = defineModel<"card" | "row">("layout", { required: true });

const emit = defineEmits<{
  (e: "back"): void;
  (e: "removeFilter", key: string, value: string): void;
  (e: "clearFilters"): void;
  (e: "select", nugget: Nugget): void;
  /** The sentinel came near the viewport: load the next page. */
  (e: "more"): void;
}>();

const config = useNaasConfig();

// Template ref "sentinel" binds the element below the cards.
const { sentinel, sentinelIsNear } = useLoadMoreSentinel(() => emit("more"));

defineExpose({
  /** Whether the end of the list is still within reach after a page loaded. */
  sentinelIsNear,
});
</script>

<style scoped>
.search-results {
  width: 100%;
  max-width: 100%;
  margin-bottom: 3rem;
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

/* Dark blue: primary on primary-light is only 4.36:1. */
.layout-toggle-btn--active {
  background: var(--naas-primary-light, #dce9fa);
  color: var(--naas-primary-dark, #0a4a8f);
}

/* Revalidation is background work: visible if looked for, never attention-grabbing. */
.nugget-refreshing-msg {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0 0 0.75rem;
  font-size: 0.8rem;
  color: var(--naas-text-muted, #6c757d);
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

/* Sentinel sits under the grid so scrolling near the bottom fetches the next page. */
.load-more-sentinel {
  width: 100%;
  height: 1px;
}
</style>
