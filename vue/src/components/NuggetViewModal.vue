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
  <Teleport to="body">
    <transition name="modal-fade">
      <div v-if="visible" class="nugget-modal-backdrop" @click="close">
        <div id="nugget-preview-modal" class="nugget-modal" @click.stop.prevent>
          <div class="container h-100">
            <div class="nugget-modal-header row justify-content-between align-items-start">
              <h2>{{ config.labels.metadata.preview }}{{ nugget.name }}</h2>
              <button type="button" class="btn-close" @click="close">✕</button>
            </div>
            <div class="nugget-modal-body">
              <div class="nugget-view">
                <iframe
                  v-if="previewUrl"
                  id="lti-frame"
                  :src="previewUrl"
                  class="preview-iframe"
                  allowfullscreen
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
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
/* ── Backdrop ── */
.nugget-modal-backdrop {
  position: fixed;
  inset: 0;
  background-color: rgba(15, 20, 30, 0.55);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  display: flex;
  justify-content: center;
  align-items: flex-start;
  z-index: 1060;
  padding: 3vh 1rem 2rem;
}

/* ── Modal panel ── */
.nugget-modal {
  width: 100%;
  max-width: 1100px;
  height: 88vh;
  background: var(--naas-surface, #fff);
  box-shadow: var(--naas-shadow-lg, 0 12px 40px rgba(0,0,0,.18));
  border-radius: var(--naas-radius-xl, 16px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* ── Header ── */
.nugget-modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.1rem 1.5rem 0.9rem;
  border-bottom: 1px solid var(--naas-border-light, #e9ecef);
  flex-shrink: 0;
}

.nugget-modal-header h2 {
  font-size: 1.05rem;
  font-weight: 700;
  margin: 0;
  padding: 0;
  color: var(--naas-text, #1f2937);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ── Close button ── */
.btn-close {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  padding: 0;
  color: var(--naas-text-muted, #6c757d);
  font-size: 1.1rem;
  font-weight: 700;
  border: none;
  background: transparent;
  border-radius: var(--naas-radius, 8px);
  cursor: pointer;
  transition: color var(--naas-transition, 0.18s ease),
              background var(--naas-transition, 0.18s ease);
}

.btn-close:hover {
  color: var(--naas-text, #1f2937);
  background: var(--naas-surface-muted, #f8f9fa);
}

/* ── Body (iframe fills it) ── */
.nugget-modal-body {
  flex: 1;
  overflow: hidden;
  padding: 0;
  margin: 0;
}

.nugget-view {
  height: 100%;
  margin: 0;
  padding: 0;
}

.preview-iframe {
  display: block;
  width: 100%;
  height: 100%;
  border: none;
}

/* ── Animation: fade + slide up ── */
.modal-fade-enter-active {
  transition: opacity 0.22s ease, transform 0.22s ease;
}
.modal-fade-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}
.modal-fade-enter-from {
  opacity: 0;
  transform: translateY(18px);
}
.modal-fade-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
</style>
