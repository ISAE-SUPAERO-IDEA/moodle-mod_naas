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
 * Nugget card displayed in search results and selected-nugget views.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div class="nugget-post h-100">
    <div class="h-100" style="position: relative; padding-bottom: 2em">
      <img
        class="w-100"
        :src="nugget.nugget_thumbnail_url + '?width=700&height=394'"
        alt=""
      />
      <h4>{{ truncate(nugget.name, 50) }}</h4>
      <h5>{{ authorsNames }}</h5>
      <div class="description" v-html="nugget.resume" />
      <h5>{{ nugget.displayinfo }}</h5>

      <div class="nugget-buttons">
        <a
          v-if="selection"
          href="javascript:;"
          class="btn btn-primary nugget-button nugget-button-selection"
          @click="emit('SelectButton', nugget)"
        >
          {{ config.labels.select_button }}
        </a>
        <a
          href="javascript:;"
          class="btn btn-primary nugget-button"
          :class="{ 'nugget-button-selection': selection }"
          @click="showAbout = true"
        >
          {{ config.labels.about }}
        </a>
        <a
          href="javascript:;"
          class="btn btn-primary nugget-button"
          :class="{ 'nugget-button-selection': selection }"
          @click="showPreview = true"
        >
          {{ config.labels.preview_button }}
        </a>
      </div>
    </div>

    <NuggetAboutModal :visible="showAbout" :nugget="nugget" @close="showAbout = false" />
    <NuggetViewModal :visible="showPreview" :nugget="nugget" @close="showPreview = false" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import NuggetAboutModal from './NuggetAboutModal.vue'
import NuggetViewModal from './NuggetViewModal.vue'
import { useNaasConfig } from '@/composables/useNaasConfig'
import type { Nugget } from '@/types/nugget.types'

const props = defineProps<{ nugget: Nugget; selection?: boolean }>()
const emit = defineEmits<{ (e: 'SelectButton', nugget: Nugget): void }>()

const config = useNaasConfig()

const showAbout = ref(false)
const showPreview = ref(false)

const authorsNames = computed(() =>
  (props.nugget.authors_data ?? [])
    .filter(Boolean)
    .map((a) => `${a.firstname} ${a.lastname}`)
    .join(', ')
)

function truncate(text: string, length: number): string {
  return text && text.length > length ? text.substring(0, length) + '...' : text
}
</script>


<style scoped>
.nugget-post {
  border-radius: var(--naas-radius, 6px);
  box-shadow: var(--naas-shadow-sm, 0 2px 6px rgba(0, 0, 0, 0.12));
  padding: 10px;
  margin: 10px 10px 25px 10px;
  transition: box-shadow var(--naas-transition, 0.18s ease);
}

.nugget-post:hover {
  box-shadow: var(--naas-shadow-md, 0 4px 16px rgba(0, 0, 0, 0.15));
}

.nugget-buttons {
  position: absolute;
  right: 0;
  bottom: 0;
}

.nugget-button {
  margin-right: 5px;
}

.nugget-button-selection {
  padding: 0 5px 0 5px;
}

.nugget-button:focus {
  outline: none;
  box-shadow: none;
}
</style>
