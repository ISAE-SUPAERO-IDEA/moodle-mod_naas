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

<style scoped>
.nugget-modal-backdrop {
  position: fixed;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
  background-color: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(3px);
  display: flex;
  justify-content: center;
  align-items: flex-start;
  z-index: 999;
  padding: 40px 0;
}

.nugget-modal {
  position: relative;
  width: 85%;
  max-width: 1140px;
  margin: 0 auto 40px;
  background: #fff;
  box-shadow: var(--naas-shadow-md, 0 4px 20px rgba(0, 0, 0, 0.15));
  border-radius: var(--naas-radius, 6px);
  display: flex;
  flex-direction: column;
  overflow: auto;
  top: 50px;
}

#nugget-preview-modal {
  height: 85%;
}

#nugget-preview-modal .nugget-modal-body {
  height: 85%;
}

.nugget-modal-header {
  border-bottom: 1px solid #e9ecef;
}

.nugget-modal-header h2 {
  padding: 18px 0 14px 28px;
  font-size: 1.25rem;
}

.nugget-modal-body {
  padding: 20px 15px;
  max-height: calc(90vh - 120px);
  overflow-y: auto;
  flex-grow: 1;
}

.btn-close {
  position: relative;
  float: right;
  padding: 12px 16px;
  top: 0;
  color: #6c757d;
  font-size: 22px;
  font-weight: bold;
  border: none;
  background: transparent;
  line-height: 1;
  border-radius: var(--naas-radius, 6px);
  transition: color var(--naas-transition, 0.18s ease), background var(--naas-transition, 0.18s ease);
}

.btn-close:hover {
  color: #212529;
  background: #f0f0f0;
}

.preview-iframe {
  border: none;
}

.nugget-view {
  margin: 0;
  padding: 0;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.25s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
