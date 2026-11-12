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
 * Search field, filters toggle and the collapsible filter panel of the search widget.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div class="naas-search-bar">
    <div class="search-bar-row">
      <label class="search-label" id="nugget_search">
        {{ config.labels.search }}
      </label>
      <div class="search-input-wrap">
        <div class="search-field">
          <i class="search-field-icon icon fa fa-search" />
          <input
            v-model="query"
            @input="emit('typing')"
            @keydown.enter.prevent
            class="search-field-input"
            :placeholder="config.labels.nugget_search_here"
          />
          <button
            v-if="query"
            type="button"
            class="search-field-clear"
            :aria-label="config.labels.clear_search"
            @click="emit('clear')"
          >
            ×
          </button>
        </div>
        <button
          type="button"
          class="filters-toggle-btn"
          :class="{ 'filters-toggle-btn--open': open }"
          :aria-expanded="open"
          aria-controls="naas-filters-collapse"
          @click="open = !open"
        >
          <i class="icon fa fa-sliders" aria-hidden="true" />
          {{ config.labels.metadata.filters }}
          <span v-if="activeFilterCount > 0" class="filters-toggle-badge">{{
            activeFilterCount
          }}</span>
          <i
            class="icon fa fa-chevron-down filters-toggle-chevron"
            :class="{ 'filters-toggle-chevron--open': open }"
            aria-hidden="true"
          />
        </button>
      </div>
    </div>

    <div
      id="naas-filters-collapse"
      class="filters-collapse"
      :class="{ 'filters-collapse--open': open }"
      :inert="!open || undefined"
    >
      <div class="filters-collapse-clip">
        <div class="filters-collapse-panel">
          <NuggetSearchFilter
            :network-aggregations="aggregations"
            :network-labels="labels"
            :active-filters="activeFilters"
            :loading="loading"
            :prefetch="open"
            @filters="(f, c) => emit('filters', f, c)"
            @captions="(c) => emit('captions', c)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import NuggetSearchFilter from "./NuggetSearchFilter.vue";
import { useNaasConfig } from "@/shared/config";
import type { AggregationResult, CatalogueFacetLabels } from "@/shared/api";

defineProps<{
  activeFilterCount: number;
  aggregations: Record<string, AggregationResult>;
  labels: CatalogueFacetLabels;
  activeFilters: Record<string, string[]>;
  loading: boolean;
}>();

/** Text typed in the search field. */
const query = defineModel<string>("query", { required: true });
/** Whether the filter panel is open. */
const open = defineModel<boolean>("open", { required: true });

const emit = defineEmits<{
  (e: "typing"): void;
  (e: "clear"): void;
  (
    e: "filters",
    filters: Record<string, string[]>,
    captions: Record<string, string>
  ): void;
  (e: "captions", captions: Record<string, string>): void;
}>();

const config = useNaasConfig();
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
  padding: 0.5rem 2.75rem 0.5rem 2.25rem;
  border: 1.5px solid var(--naas-border, #dee2e6);
  border-radius: var(--naas-radius-pill, 999px);
  background: var(--naas-surface, #fff);
  font-size: 0.875rem;
  color: var(--naas-text, #1f2937);
  transition:
    border-color var(--naas-transition, 0.18s ease),
    box-shadow var(--naas-transition, 0.18s ease);
  outline: none;
  line-height: 1.5;
}

.search-field-input::placeholder {
  color: var(--naas-text-muted, #6c757d);
}

.search-field-input:focus {
  border-color: var(--naas-primary, #0f6cbf);
  box-shadow: 0 0 0 3px rgba(15, 108, 191, 0.15);
}

.search-field-clear {
  position: absolute;
  right: 0.75rem;
  background: var(--naas-text-muted, #6c757d);
  color: #fff;
  border: none;
  border-radius: 50%;
  /* 24 px: the minimum touch target size (WCAG 2.5.8). */
  width: 1.5rem;
  height: 1.5rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  line-height: 1;
  cursor: pointer;
  padding: 0;
  transition: background var(--naas-transition, 0.18s ease);
}

.search-field-clear:hover {
  background: #495057;
}

/* ── Filters toggle button ─────────────────────────────────────────────── */
.filters-toggle-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.9rem;
  border: 1.5px solid var(--naas-border, #dee2e6);
  border-radius: var(--naas-radius-pill, 999px);
  background: var(--naas-surface, #fff);
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--naas-text, #1f2937);
  cursor: pointer;
  white-space: nowrap;
  transition:
    border-color 0.15s ease,
    color 0.15s ease,
    background 0.15s ease,
    box-shadow 0.15s ease;
  flex-shrink: 0;
}

.filters-toggle-btn .icon {
  margin: 0;
  font-size: 0.85rem;
}

.filters-toggle-btn:hover,
.filters-toggle-btn:focus-visible {
  border-color: var(--naas-primary, #0f6cbf);
  color: var(--naas-primary, #0f6cbf);
}

/* Keyboard focus needs a ring of its own: the colour change alone is too faint. */
.filters-toggle-btn:focus-visible {
  outline: 2px solid var(--naas-primary, #0f6cbf);
  outline-offset: 2px;
}

/* Doubled class: stays dark blue on hover too (4.36:1 for primary on primary-light). */
.filters-toggle-btn.filters-toggle-btn--open {
  border-color: var(--naas-primary, #0f6cbf);
  background: var(--naas-primary-light, #dce9fa);
  color: var(--naas-primary-dark, #0a4a8f);
  box-shadow: 0 0 0 3px rgba(15, 108, 191, 0.12);
}

.filters-toggle-chevron {
  transition: transform 0.2s ease;
}

.filters-toggle-chevron--open {
  transform: rotate(180deg);
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

.filters-collapse {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows 0.28s ease;
  pointer-events: none;
  position: relative;
  z-index: 5;
}

.filters-collapse--open {
  grid-template-rows: 1fr;
  pointer-events: auto;
  z-index: 30;
}

.filters-collapse-clip {
  overflow: hidden;
  min-height: 0;
}

.filters-collapse--open .filters-collapse-clip {
  overflow: visible;
}

.filters-collapse-panel {
  padding: 0 0 1rem;
  overflow: visible;
}

.filters-collapse-panel :deep(.filter-bar) {
  margin-bottom: 0;
  padding: 0.85rem 1rem 1rem;
  border: 1.5px solid var(--naas-border, #dee2e6);
  border-radius: var(--naas-radius, 8px);
  background: var(--naas-surface-muted, #f8f9fa);
  overflow: visible;
}

.filters-collapse-panel :deep(.filter-title) {
  display: none;
}

.filters-collapse-panel :deep(.filter-bar-header) {
  justify-content: flex-end;
  margin-bottom: 0;
}

.filters-collapse-panel :deep(.filter-clear-btn) {
  margin-bottom: 0.65rem;
}
</style>
