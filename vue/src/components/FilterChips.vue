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
    <VChip
      v-for="chip in chips"
      :key="`${chip.key}-${chip.value}`"
      class="filter-chip"
      color="primary"
      variant="outlined"
      closable
      size="small"
      :aria-label="`Remove filter ${chip.label}`"
      @click:close="emit('remove', chip.key, chip.value)"
    >
      {{ chip.label }}
    </VChip>

    <VChip
      v-if="chips.length > 1"
      class="filter-chip filter-chip-clear"
      color="error"
      variant="outlined"
      size="small"
      @click="emit('clear')"
    >
      {{ config.labels.clear_filters }}
    </VChip>
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

.filter-chip {
  font-weight: 600;
}

.filter-chip-clear {
  font-weight: 500;
}
</style>
