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
  <div class="nugget-post">
    <!-- Thumbnail -->
    <div class="nugget-thumb-wrap">
      <img
        class="nugget-thumb"
        :src="nugget.nugget_thumbnail_url + '?width=700&height=394'"
        alt=""
      />
    </div>

    <!-- Body -->
    <div class="nugget-body">
      <h4 class="nugget-title">{{ truncate(nugget.name, 60) }}</h4>
      <p v-if="authorsNames" class="nugget-authors">{{ authorsNames }}</p>
      <div class="nugget-desc">{{ truncatedResume }}</div>
      <p v-if="nugget.displayinfo" class="nugget-displayinfo">{{ nugget.displayinfo }}</p>
    </div>

    <!-- Footer actions -->
    <div class="nugget-footer">
      <button
        v-if="selection"
        type="button"
        class="nugget-btn nugget-btn-select"
        @click="emit('SelectButton', nugget)"
      >
        {{ config.labels.select_button }}
      </button>
      <button
        type="button"
        class="nugget-btn nugget-btn-outline"
        @click="showAbout = true"
      >
        {{ config.labels.about }}
      </button>
      <button
        type="button"
        class="nugget-btn nugget-btn-ghost"
        @click="showPreview = true"
      >
        {{ config.labels.preview_button }}
      </button>
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
  return text && text.length > length ? text.substring(0, length) + '…' : text
}

const truncatedResume = computed(() => {
  const raw = props.nugget.resume ?? ''
  const stripped = raw.replace(/<[^>]*>/g, '')
  return stripped.length > 100 ? stripped.substring(0, 100) + '…' : stripped
})
</script>

<style scoped>
.nugget-post {
  display: flex;
  flex-direction: column;
  height: 100%;
  width: 100%;
  box-sizing: border-box;
  background: #fff;
  border-radius: var(--naas-radius, 6px);
  box-shadow: var(--naas-shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.10));
  overflow: hidden;
  transition: box-shadow var(--naas-transition, 0.18s ease), transform var(--naas-transition, 0.18s ease);
}

.nugget-post:hover {
  box-shadow: var(--naas-shadow-md, 0 6px 20px rgba(0, 0, 0, 0.14));
  transform: translateY(-2px);
}

/* ── Thumbnail ── */
.nugget-thumb-wrap {
  width: 100%;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  background: #f0f2f5;
}

.nugget-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.3s ease;
}

.nugget-post:hover .nugget-thumb {
  transform: scale(1.03);
}

/* ── Body ── */
.nugget-body {
  flex: 1;
  padding: 0.875rem 1rem 0.5rem;
}

.nugget-title {
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1.35;
  margin: 0 0 0.35rem;
  color: #1a1a2e;
}

.nugget-authors {
  font-size: 0.78rem;
  color: #6c757d;
  margin: 0 0 0.4rem;
  font-style: italic;
}

.nugget-desc {
  font-size: 0.8rem;
  color: #495057;
  line-height: 1.45;
  margin: 0;
}

.nugget-displayinfo {
  font-size: 0.75rem;
  color: #adb5bd;
  margin: 0.35rem 0 0;
}

/* ── Footer ── */
.nugget-footer {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.625rem 1rem;
  border-top: 1px solid #f0f2f5;
  background: #fafafa;
}

.nugget-btn {
  flex: 1;
  padding: 0.35rem 0.5rem;
  font-size: 0.78rem;
  font-weight: 600;
  border-radius: var(--naas-radius, 6px);
  border: none;
  cursor: pointer;
  white-space: nowrap;
  transition: background var(--naas-transition, 0.18s ease),
              color var(--naas-transition, 0.18s ease),
              box-shadow var(--naas-transition, 0.18s ease);
  text-align: center;
}

/* Select — filled primary */
.nugget-btn-select {
  background: var(--naas-primary, #0f6cbf);
  color: #fff;
  border: 1.5px solid var(--naas-primary, #0f6cbf);
}

.nugget-btn-select:hover {
  background: var(--naas-primary-hover, #0a58ca);
  border-color: var(--naas-primary-hover, #0a58ca);
  box-shadow: 0 2px 8px rgba(15, 108, 191, 0.35);
}

/* About — outlined primary */
.nugget-btn-outline {
  background: transparent;
  color: var(--naas-primary, #0f6cbf);
  border: 1.5px solid var(--naas-primary, #0f6cbf);
}

.nugget-btn-outline:hover {
  background: var(--naas-primary-light, #dce9fa);
}

/* Preview — ghost */
.nugget-btn-ghost {
  background: transparent;
  color: #6c757d;
  border: 1.5px solid #dee2e6;
}

.nugget-btn-ghost:hover {
  background: #f0f2f5;
  color: #495057;
  border-color: #ced4da;
}
</style>
