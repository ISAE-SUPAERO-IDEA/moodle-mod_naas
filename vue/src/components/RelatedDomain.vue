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
 * Recursive tree component for the related-domains aggregation filter.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <ul class="related-domains-list">
    <li class="related-domains-list-element">
      <NuggetBadge
        :selected="bucket.selected"
        :text="bucket.caption"
        :text-length-max="20"
        @click="emit('bucket-click', bucket.query_value ?? '')"
      />
      <span
        v-if="hasChildren"
        :class="['tree-view-caret', { 'tree-view-caret-down': showChildren }]"
        @click="showChildren = !showChildren"
      />
    </li>

    <li
      v-show="showChildren"
      class="related-domains-list-element related-domains-child"
    >
      <ul
        v-for="child in bucket.children"
        :key="child.key"
        class="related-domains-list"
        style="margin: 0 0 0 20px"
      >
        <li class="related-domains-list-element">
          <RelatedDomain
            :bucket="child"
            @bucket-click="emit('bucket-click', $event)"
          />
        </li>
      </ul>
    </li>
  </ul>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import NuggetBadge from './NuggetBadge.vue'
import type { AggregationBucket } from '@/types/nugget.types'

const props = defineProps<{ bucket: AggregationBucket }>()
const emit = defineEmits<{ (e: 'bucket-click', key: string): void }>()

const hasChildren = computed(() =>
  !!props.bucket.children && Object.keys(props.bucket.children).length > 0
)

// Start expanded if any child is already selected.
function anyChildSelected(children?: Record<string, AggregationBucket>): boolean {
  if (!children) return false
  return Object.values(children).some(
    (c) => c.selected || anyChildSelected(c.children)
  )
}

const showChildren = ref(anyChildSelected(props.bucket.children))
</script>

<style scoped>
.related-domains-list {
  margin: 0;
  padding: 0;
}

.related-domains-list-element {
  list-style-type: none;
}

.tree-view-caret {
  cursor: pointer;
  user-select: none;
}

.tree-view-caret::after {
  content: "\25BC";
  color: black;
  display: inline-block;
  margin-right: 6px;
  transform: rotate(-90deg);
}

.tree-view-caret-down::after {
  transform: rotate(0deg);
}
</style>
