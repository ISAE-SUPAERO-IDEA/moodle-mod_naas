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
 * Modal showing a nugget preview inside an iframe (search-widget context).
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div v-show="visible">
    <transition name="modal-fade">
      <div class="nugget-modal-backdrop" @click="close">
        <div id="nugget-preview-modal" class="nugget-modal" @click.stop.prevent>
          <div class="container h-100">
            <div class="nugget-modal-header row justify-content-between align-items-start">
              <h2>{{ config.labels.metadata.preview }}{{ nugget.name }}</h2>
              <button type="button" class="btn-close" @click="close">✕</button>
            </div>
            <div class="nugget-modal-body row">
              <div class="nugget-view w-100">
                <iframe
                  v-if="previewUrl"
                  id="lti-frame"
                  :src="previewUrl"
                  class="preview-iframe h-100 w-100"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useNaasConfig } from '@/composables/useNaasConfig'
import { useMoodleService } from '@/composables/useMoodleService'
import type { Nugget } from '@/types/nugget.types'

const props = defineProps<{ nugget: Nugget; visible: boolean }>()
const emit = defineEmits<{ (e: 'close'): void }>()

const config = useNaasConfig()
const service = useMoodleService()

const previewUrl = ref<string | null>(null)

watch(
  () => props.visible,
  async (visible) => {
    if (!visible) {
      previewUrl.value = null
      return
    }
    try {
      previewUrl.value = await service.getNuggetPreview(
        props.nugget.version_id,
        config.courseId
      )
    } catch (e) {
      console.warn('[NaaS] preview load failed', e)
    }
  }
)

function close() {
  previewUrl.value = null
  emit('close')
}
</script>
