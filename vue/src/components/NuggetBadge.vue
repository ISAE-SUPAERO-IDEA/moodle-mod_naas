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
 * Badge pill component used by filter aggregations and domain trees.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <span
    class="badge rounded-pill badge-pill badge-margin"
    :class="selected ? 'badge-primary' : 'text-primary'"
    :title="help"
    @click="emit('click')"
  >
    {{ truncated }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    selected?: boolean
    text?: string
    help?: string
    textLengthMax?: number
  }>(),
  { textLengthMax: 25 }
)

const emit = defineEmits<{ (e: 'click'): void }>()

const truncated = computed(() => {
  if (!props.text) return ''
  return props.text.length > props.textLengthMax
    ? props.text.substring(0, props.textLengthMax) + '...'
    : props.text
})
</script>
