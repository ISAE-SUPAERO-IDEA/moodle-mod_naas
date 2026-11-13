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
 * Nugget cards of the search widget: skeletons on the first load, then the
 * list of cards (arrow keys move between them), or a "no results" line.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <!-- Skeleton cards on initial load -->
  <div
    v-if="loading"
    class="nugget-grid"
    :class="{ 'nugget-grid--rows': layout === 'row' }"
    aria-hidden="true"
  >
    <div
      v-for="n in SKELETON_COUNT"
      :key="`skel-${n}`"
      class="nugget-grid-item"
    >
      <NuggetSkeleton :variant="layout" :delay="n - 1" />
    </div>
  </div>

  <!--
    A plain list of cards: each card is an article named after its
    Nugget, its actions are real buttons, and the current pick shows
    as the pressed state of its Select button.
  -->
  <ul
    v-else-if="nuggets.length || loadingMore"
    class="nugget-grid"
    :class="{ 'nugget-grid--rows': layout === 'row' }"
    role="list"
    :aria-label="config.labels.search"
    @keydown="onCardGridKeydown"
  >
    <li
      v-for="nugget in nuggets"
      :key="nugget.nugget_id"
      class="nugget-grid-item"
    >
      <NuggetPost
        :nugget="nugget"
        :selection="true"
        :selected="nugget.nugget_id === selectedId"
        :variant="layout"
        :class="{
          'nugget-post-selected': nugget.nugget_id === selectedId,
        }"
        @SelectButton="emit('select', $event)"
      />
    </li>

    <!-- Skeleton row appended while loading more -->
    <template v-if="loadingMore">
      <li
        v-for="n in 3"
        :key="`more-skel-${n}`"
        class="nugget-grid-item"
        aria-hidden="true"
      >
        <NuggetSkeleton :variant="layout" :delay="n - 1" />
      </li>
    </template>
  </ul>

  <p v-else-if="!failed" class="no-results">
    {{ config.labels.nugget_search_no_result }}
  </p>
</template>

<script setup lang="ts">
import NuggetPost from "./NuggetPost.vue";
import NuggetSkeleton from "./NuggetSkeleton.vue";
import { useNaasConfig } from "@/composables/useNaasConfig";
import { onCardGridKeydown } from "@/composables/cardGridKeys";
import type { Nugget } from "@/types/nugget.types";

/** Skeleton cards painted while the first page loads: one full page. */
const SKELETON_COUNT = 9;

defineProps<{
  nuggets: Nugget[];
  loading: boolean;
  loadingMore: boolean;
  layout: "card" | "row";
  /** Id of the chosen Nugget: its Select button shows as pressed. */
  selectedId: string | null;
  /** The search failed: an error banner is shown instead of "no results". */
  failed?: boolean;
}>();

const emit = defineEmits<{
  (e: "select", nugget: Nugget): void;
}>();

const config = useNaasConfig();
</script>

<style scoped>
/* ── Nugget grid: 3 cards per row ──────────────────────────────────────── */
.nugget-grid {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1.25rem;
  width: 100%;
  box-sizing: border-box;
}

.nugget-grid-item {
  display: flex;
  min-width: 0;
  width: 100%;
  max-width: 100%;
  cursor: default;
}

.nugget-grid:not(.nugget-grid--rows) .nugget-grid-item :deep(.nugget-post) {
  width: 100%;
  height: 100%;
}

.nugget-grid--rows {
  grid-template-columns: minmax(0, 1fr);
  gap: 0.5rem;
}

.no-results {
  grid-column: 1 / -1;
  padding: 1.5rem 0;
  color: #6c757d;
  text-align: center;
}

/* ── Selected state ────────────────────────────────────────────────────── */
.nugget-post-selected :deep(.nugget-post) {
  border-color: var(--naas-primary, #0f6cbf);
  box-shadow: 0 0 0 2px rgba(15, 108, 191, 0.22),
    0 8px 20px rgba(15, 108, 191, 0.16);
}
</style>
