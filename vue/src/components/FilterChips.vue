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
 * Dismissible chips showing active search filters above the results grid.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div
    v-if="chips.length"
    class="filter-chips"
    role="group"
    :aria-label="config.labels.active_filters || 'Active filters'"
  >
    <span
      v-for="chip in chips"
      :key="`${chip.key}-${chip.value}`"
      class="naas-chip filter-chip"
    >
      <span class="naas-chip__label">{{ chip.label }}</span>
      <button
        type="button"
        class="naas-chip__close"
        :aria-label="`Remove filter ${chip.label}`"
        @click="emit('remove', chip.key, chip.value)"
      >
        <i class="fa fa-times" aria-hidden="true"></i>
      </button>
    </span>

    <button
      v-if="chips.length > 1"
      type="button"
      class="naas-chip naas-chip--clear filter-chip filter-chip-clear"
      @click="emit('clear')"
    >
      {{ config.labels.clear_filters }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { useNaasConfig } from "@/composables/useNaasConfig";

const props = defineProps<{
  filters: Record<string, string[]>;
  labels: Record<string, string>;
  bucketCaptions?: Record<string, string>;
}>();

const emit = defineEmits<{
  (e: "remove", key: string, value: string): void;
  (e: "clear"): void;
}>();

const config = useNaasConfig();

const chips = computed(() => {
  const result: { key: string; value: string; label: string }[] = [];
  for (const [key, values] of Object.entries(props.filters)) {
    for (const value of values) {
      result.push({
        key,
        value,
        label: props.bucketCaptions?.[value] ?? value,
      });
    }
  }
  return result;
});
</script>

<style scoped>
.filter-chips {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.naas-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.15rem 0.65rem;
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.5;
  color: var(--naas-primary, #0f6cbf);
  background: var(--naas-surface, #fff);
  border: 1px solid currentColor;
  border-radius: var(--naas-radius-pill, 999px);
}

.naas-chip__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.25rem;
  height: 1.25rem;
  margin-right: -0.35rem;
  padding: 0;
  font-size: 0.75rem;
  line-height: 1;
  color: inherit;
  background: transparent;
  border: 0;
  border-radius: 50%;
  cursor: pointer;
}

.naas-chip__close:hover,
.naas-chip__close:focus-visible {
  background: var(--naas-primary-light, #dce9fa);
}

.naas-chip--clear {
  font-family: inherit;
  font-weight: 500;
  color: #dc3545; /* Bootstrap danger */
  cursor: pointer;
}

.naas-chip--clear:hover,
.naas-chip--clear:focus-visible {
  color: #fff;
  background: #dc3545;
}

.naas-chip__close:focus-visible,
.naas-chip--clear:focus-visible {
  outline: 2px solid var(--naas-primary, #0f6cbf);
  outline-offset: 1px;
}
</style>
