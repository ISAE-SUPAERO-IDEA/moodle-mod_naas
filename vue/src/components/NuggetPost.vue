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
    <!-- Thumbnail with overlay badges -->
    <div class="nugget-thumb-wrap">
      <img
        class="nugget-thumb"
        :src="nugget.nugget_thumbnail_url + '?width=700&height=394'"
        alt=""
        loading="lazy"
      />
      <div class="nugget-thumb-badges">
        <span v-if="nugget.duration" class="nugget-badge nugget-badge-duration">
          <i class="icon fa fa-clock-o" />
          {{ nugget.duration }}&thinsp;min
        </span>
        <span v-if="nugget.level" class="nugget-badge nugget-badge-level">
          {{ config.labels.metadata[nugget.level] ?? nugget.level }}
        </span>
      </div>
    </div>

    <!-- Body -->
    <div class="nugget-body">
      <h4 class="nugget-title" :title="nugget.name">{{ truncate(nugget.name, 60) }}</h4>
      <p v-if="authorsNames" class="nugget-authors">{{ authorsNames }}</p>
      <p class="nugget-desc">{{ truncatedResume }}</p>
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
        <i class="icon fa fa-play-circle" />
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
  const text = new DOMParser().parseFromString(raw, 'text/html').body.textContent ?? ''
  return truncate(text, 110)
})
</script>

<style scoped>
/* ── Card shell ── */
.nugget-post {
  display: flex;
  flex-direction: column;
  height: 100%;
  background: var(--naas-surface, #fff);
  border-radius: var(--naas-radius, 8px);
  box-shadow: var(--naas-shadow-sm, 0 2px 8px rgba(0,0,0,.10));
  overflow: hidden;
  transition:
    box-shadow var(--naas-transition-slow, 0.28s ease),
    transform  var(--naas-transition-slow, 0.28s ease);
  will-change: transform;
}

.nugget-post:hover {
  box-shadow: var(--naas-shadow-md, 0 6px 20px rgba(0,0,0,.14));
  transform: translateY(-3px);
}

/* ── Thumbnail ── */
.nugget-thumb-wrap {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  background: var(--naas-surface-muted, #f8f9fa);
  flex-shrink: 0;
}

.nugget-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.35s ease;
}

.nugget-post:hover .nugget-thumb {
  transform: scale(1.04);
}

/* Overlay badges bottom-left of thumbnail */
.nugget-thumb-badges {
  position: absolute;
  bottom: 0.5rem;
  left: 0.5rem;
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
}

.nugget-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.18rem 0.55rem;
  font-size: 0.72rem;
  font-weight: 600;
  border-radius: var(--naas-radius-pill, 999px);
  line-height: 1.4;
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
}

.nugget-badge-duration {
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
}

.nugget-badge-level {
  background: var(--naas-primary, #0f6cbf);
  color: #fff;
  text-transform: capitalize;
}

/* ── Body ── */
.nugget-body {
  flex: 1;
  padding: 0.875rem 1rem 0.4rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-height: 0;
}

.nugget-title {
  font-size: 0.9rem;
  font-weight: 700;
  line-height: 1.35;
  margin: 0;
  color: var(--naas-text, #1f2937);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.nugget-authors {
  font-size: 0.775rem;
  color: var(--naas-text-muted, #6c757d);
  margin: 0;
  font-style: italic;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.nugget-desc {
  font-size: 0.8rem;
  color: var(--naas-text-muted, #6c757d);
  line-height: 1.5;
  margin: 0;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* ── Footer ── */
.nugget-footer {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.6rem 0.875rem;
  border-top: 1px solid var(--naas-border-light, #e9ecef);
  background: var(--naas-surface-muted, #f8f9fa);
  flex-shrink: 0;
}

.nugget-btn {
  flex: 1;
  padding: 0.32rem 0.4rem;
  font-size: 0.77rem;
  font-weight: 600;
  border-radius: var(--naas-radius, 8px);
  border: 1.5px solid transparent;
  cursor: pointer;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  transition:
    background var(--naas-transition, 0.18s ease),
    color      var(--naas-transition, 0.18s ease),
    box-shadow var(--naas-transition, 0.18s ease);
}

.nugget-btn-select {
  background: var(--naas-primary, #0f6cbf);
  color: #fff;
  border-color: var(--naas-primary, #0f6cbf);
}

.nugget-btn-select:hover {
  background: var(--naas-primary-hover, #0a58ca);
  border-color: var(--naas-primary-hover, #0a58ca);
  box-shadow: 0 2px 8px rgba(15, 108, 191, 0.3);
}

.nugget-btn-outline {
  background: transparent;
  color: var(--naas-primary, #0f6cbf);
  border-color: var(--naas-primary, #0f6cbf);
}

.nugget-btn-outline:hover {
  background: var(--naas-primary-light, #dce9fa);
}

.nugget-btn-ghost {
  background: transparent;
  color: var(--naas-text-muted, #6c757d);
  border-color: var(--naas-border, #dee2e6);
}

.nugget-btn-ghost:hover {
  background: var(--naas-surface-hover, #f0f4ff);
  color: var(--naas-text, #1f2937);
  border-color: var(--naas-text-muted, #6c757d);
}
</style>
