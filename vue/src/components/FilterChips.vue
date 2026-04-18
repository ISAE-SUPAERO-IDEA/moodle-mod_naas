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
  <div v-if="chips.length" class="filter-chips" role="group" :aria-label="config.labels.active_filters || 'Active filters'">
    <span
      v-for="chip in chips"
      :key="`${chip.key}-${chip.value}`"
      class="filter-chip"
    >
      <span class="filter-chip-label">{{ chip.label }}</span>
      <button
        type="button"
        class="filter-chip-remove"
        :aria-label="`Remove filter ${chip.label}`"
        @click="emit('remove', chip.key, chip.value)"
      >×</button>
    </span>

    <button
      v-if="chips.length > 1"
      type="button"
      class="filter-chip filter-chip-clear"
      @click="emit('clear')"
    >
      {{ config.labels.clear_filters }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useNaasConfig } from '@/composables/useNaasConfig'

const props = defineProps<{
  filters: Record<string, string[]>
  labels: Record<string, string>
}>()

const emit = defineEmits<{
  (e: 'remove', key: string, value: string): void
  (e: 'clear'): void
}>()

const config = useNaasConfig()

const chips = computed(() => {
  const result: { key: string; value: string; label: string }[] = []
  for (const [key, values] of Object.entries(props.filters)) {
    for (const value of values) {
      result.push({
        key,
        value,
        label: props.labels[value] ?? props.labels[key] ?? value,
      })
    }
  }
  return result
})
</script>

<style scoped>
.filter-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-bottom: 0.75rem;
}

.filter-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.2rem 0.6rem;
  background: var(--naas-primary-light, #dce9fa);
  color: var(--naas-primary, #0f6cbf);
  border: 1px solid var(--naas-primary, #0f6cbf);
  border-radius: 50px;
  font-size: 0.8rem;
  font-weight: 500;
  white-space: nowrap;
}

.filter-chip-remove {
  background: none;
  border: none;
  color: inherit;
  font-size: 1rem;
  line-height: 1;
  padding: 0 0 0 0.15rem;
  cursor: pointer;
  opacity: 0.7;
  transition: opacity 0.15s ease;
}

.filter-chip-remove:hover {
  opacity: 1;
}

.filter-chip-clear {
  background: #f8d7da;
  color: #842029;
  border-color: #f5c2c7;
  cursor: pointer;
}

.filter-chip-clear:hover {
  background: #f5c2c7;
}
</style>
