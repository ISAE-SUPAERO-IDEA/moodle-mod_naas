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
 * Skeleton placeholder card — mirrors the NuggetPost layout to prevent layout shift.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <VCard
    class="nugget-skeleton"
    :class="{ 'nugget-skeleton--row': variant === 'row' }"
    :border="false"
    elevation="0"
    aria-hidden="true"
  >
    <VSkeletonLoader class="skel-thumb" type="image" />
    <VCardText class="skel-body">
      <VSkeletonLoader type="heading, text" />
    </VCardText>
    <VCardActions class="skel-footer">
      <VSkeletonLoader class="skel-btn" type="button" />
      <VSkeletonLoader class="skel-btn" type="button" />
      <VSkeletonLoader class="skel-btn" type="button" />
    </VCardActions>
  </VCard>
</template>

<script setup lang="ts">
defineProps<{
  variant?: "card" | "row";
}>();
</script>

<style scoped>
.nugget-skeleton {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  box-sizing: border-box;
  background: var(--naas-surface, #fff);
  border: 1.5px solid var(--naas-border, #dee2e6);
  border-radius: var(--naas-radius, 8px);
  box-shadow: var(--naas-shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.08));
  overflow: hidden;
  height: 100%;
}

.skel-thumb {
  width: 100%;
  aspect-ratio: 16 / 9;
  flex-shrink: 0;
}

.skel-thumb :deep(.v-skeleton-loader__image),
.skel-thumb :deep(.v-skeleton-loader__bone) {
  height: 100%;
  border-radius: 0;
}

.skel-body {
  flex: 1;
  padding: 0.875rem 1rem 0.5rem !important;
}

.skel-footer {
  display: flex;
  gap: 0.35rem;
  padding: 0.6rem 0.875rem !important;
  border-top: 1px solid var(--naas-border-light, #e9ecef);
  background: var(--naas-surface-muted, #f8f9fa);
  flex-shrink: 0;
}

.skel-btn {
  flex: 1;
  height: 27px;
}

.nugget-skeleton--row {
  flex-direction: row;
  min-height: 7.25rem;
  height: auto;
}

.nugget-skeleton--row .skel-thumb {
  flex: 0 1 13rem;
  width: 13rem;
  max-width: 38%;
  min-width: 0;
  height: auto;
}

.nugget-skeleton--row .skel-body {
  flex: 1 1 auto;
  min-width: 0;
}

.nugget-skeleton--row .skel-footer {
  flex-direction: column;
  width: 8.75rem;
  max-width: 30%;
  flex: 0 0 auto;
  border-top: none;
  border-left: 1px solid var(--naas-border-light, #e9ecef);
}
</style>
