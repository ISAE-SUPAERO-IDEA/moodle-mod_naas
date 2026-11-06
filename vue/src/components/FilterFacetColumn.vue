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
 * One facet column of the filter panel: the pill button and its checkbox
 * dropdown. State changes are emitted to NuggetSearchFilter.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div class="filter-column">
    <button
      type="button"
      class="filter-pill"
      :class="{
        'filter-pill--active': active,
        'filter-pill--open': aggregation.visible,
      }"
      @click="emit('toggle')"
    >
      {{ config.labels.metadata[aggregation.name] ?? aggregation.name }}
      <span v-if="active" class="filter-pill-count">
        {{ selectedBucketCount(aggregation) }}
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
              @change="emit('select', bucket.query_value ?? bucket.key)"
            />
            <span>{{ optionCaption(bucket) }}</span>
          </label>

          <button
            v-if="hasMoreAuthors(aggregation)"
            type="button"
            class="show-more-btn"
            @click="emit('toggleAuthors')"
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
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useNaasConfig } from "@/composables/useNaasConfig";
import {
  hasMoreAuthors,
  visibleBuckets,
  type MappedAggregation,
} from "@/composables/searchAggregations";
import {
  hasSelectedBuckets,
  optionCaption,
  selectedBucketCount,
} from "@/composables/facetColumns";

const props = defineProps<{
  aggregation: MappedAggregation;
}>();
const emit = defineEmits<{
  (e: "toggle"): void;
  (e: "select", value: string): void;
  (e: "toggleAuthors"): void;
}>();

const config = useNaasConfig();
const active = computed(() => hasSelectedBuckets(props.aggregation));
</script>

<style scoped>
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
  background: var(--naas-surface, #fff);
  color: var(--naas-primary, #0f6cbf);
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
