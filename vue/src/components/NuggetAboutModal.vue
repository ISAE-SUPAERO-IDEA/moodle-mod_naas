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
 * Modal displaying full nugget metadata (description, authors, in-brief panel).
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <Teleport to="body">
    <transition name="modal-fade">
      <div v-if="visible" class="nugget-modal-backdrop" @click="emit('close')">
        <div class="nugget-modal" @click.stop.prevent>
            <div class="nugget-modal-header">
            <h2>{{ config.labels.about }} : {{ nugget.name }}</h2>
            <button type="button" class="btn-close" @click="emit('close')">✕</button>
          </div>

          <div class="nugget-modal-body">
            <div class="row metadata-field">
              <div class="col">
                <div v-if="isShown(nugget.resume)">
                  <h3>{{ config.labels.metadata.description }}</h3>
                  <span class="nugget-modal-description">{{ stripHtml(nugget.resume) }}</span>
                </div>

                <div v-if="isShown(nugget.authors_data)">
                  <h3>{{ config.labels.metadata.about_author }}</h3>
                  <div v-for="author in nugget.authors_data" :key="author.email">
                    <h5>{{ author.firstname }} {{ author.lastname }}</h5>
                    <span class="nugget-modal-description">{{ stripHtml(author.bio) }}</span>
                  </div>
                </div>
              </div>

              <div v-if="inBriefShown" class="col-4">
                <h3>{{ config.labels.metadata.in_brief }}</h3>
                <ul class="metadata-list">
                  <li v-if="isShown(nugget.duration)">
                    <i class="icon fa fa-clock-o" />
                    {{ config.labels.metadata.duration }}:
                    <strong>{{ nugget.duration }} minutes</strong>
                  </li>
                  <li v-if="isShown(nugget.language)">
                    <i class="icon fa fa-globe" />
                    {{ config.labels.metadata.language }}:
                    <strong>{{ config.labels.metadata[nugget.language] }}</strong>
                  </li>
                  <li v-if="isShown(nugget.level)">
                    <i class="icon fa fa-arrow-up" />
                    {{ config.labels.metadata.level }}:
                    <strong>{{ config.labels.metadata[nugget.level!] }}</strong>
                  </li>
                  <li v-if="isShown(nugget.domains_data)" class="metadata-list-item-wrap">
                    <i class="icon fa fa-home" />
                    <span>
                      {{ config.labels.metadata.field_of_study }}:
                      <span class="meta-tags">
                        <span v-for="item in nugget.domains_data" :key="item.id" class="meta-tag">
                          {{ item.label }}
                        </span>
                      </span>
                    </span>
                  </li>
                  <li v-if="isShown(nugget.tags)" class="metadata-list-item-wrap">
                    <i class="icon fa fa-tag" />
                    <span>
                      {{ config.labels.metadata.tags }}:
                      <span class="meta-tags">
                        <span v-for="tag in nugget.tags" :key="tag" class="meta-tag">
                          {{ tag }}
                        </span>
                      </span>
                    </span>
                  </li>
                  <li v-if="isShown(nugget.publication_date)">
                    <i class="icon fa fa-calendar" />
                    {{ config.labels.metadata.publication_date }}:
                    <strong>{{ formatDate(nugget.publication_date) }}</strong>
                  </li>
                </ul>
              </div>
            </div>

            <div v-if="isShown(nugget.prerequisites)" class="row metadata-field">
              <div class="w-100">
                <h3>{{ config.labels.metadata.prerequisites }}</h3>
                <ul class="about-list ul-position">
                  <li v-for="item in nugget.prerequisites" :key="item"><p>{{ item }}</p></li>
                </ul>
              </div>
            </div>

            <div v-if="isShown(nugget.learning_outcomes)" class="row metadata-field">
              <div class="w-100">
                <h3>{{ config.labels.metadata.learning_outcomes }}</h3>
                <ul class="about-list ul-position">
                  <li v-for="item in nugget.learning_outcomes" :key="item"><p>{{ item }}</p></li>
                </ul>
              </div>
            </div>

            <div v-if="isShown(nugget.references)" class="row metadata-field">
              <div class="w-100">
                <h3>{{ config.labels.metadata.references }}</h3>
                <ul class="about-list ul-position">
                  <li v-for="item in nugget.references" :key="item"><p>{{ item }}</p></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import moment from 'moment'
import { useNaasConfig } from '@/composables/useNaasConfig'
import type { Nugget } from '@/types/nugget.types'

defineProps<{ nugget: Nugget; visible: boolean }>()
const emit = defineEmits<{ (e: 'close'): void }>()

const config = useNaasConfig()

function stripHtml(value?: string): string {
  return new DOMParser().parseFromString(value ?? '', 'text/html').body.textContent ?? ''
}

function isShown(val: unknown): boolean {
  if (val === undefined || val === null || val === '') return false
  if (Array.isArray(val)) return val.length > 0
  if (typeof val === 'object') return Object.values(val).some(isShown)
  return true
}

function formatDate(value?: string): string {
  if (!value) return ''
  return moment(value).format('DD/MM/YYYY')
}

const inBriefShown = computed(() => {
  // props are available via the closure in script setup
  return true // resolved reactively inside template via v-if guards above
})
</script>

<style scoped>
/* ── Backdrop ── */
.nugget-modal-backdrop {
  position: fixed;
  inset: 0;
  background-color: rgba(15, 20, 30, 0.5);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  display: flex;
  justify-content: center;
  align-items: flex-start;
  z-index: 1060;
  padding: 3vh 1rem 2rem;
  overflow-y: auto;
}

/* ── Modal panel ── */
.nugget-modal {
  width: 100%;
  max-width: 1100px;
  margin: 0 auto;
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
  padding: 1.25rem 1.5rem 1rem;
  border-bottom: 1px solid var(--naas-border-light, #e9ecef);
  flex-shrink: 0;
}

.nugget-modal-header h2 {
  font-size: 1.15rem;
  font-weight: 700;
  margin: 0;
  padding: 0;
  color: var(--naas-text, #1f2937);
  line-height: 1.3;
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
  line-height: 1;
  border-radius: var(--naas-radius, 8px);
  cursor: pointer;
  transition: color var(--naas-transition, 0.18s ease),
              background var(--naas-transition, 0.18s ease);
}

.btn-close:hover {
  color: var(--naas-text, #1f2937);
  background: var(--naas-surface-muted, #f8f9fa);
}

/* ── Body ── */
.nugget-modal-body {
  padding: 1.5rem;
  max-height: calc(88vh - 100px);
  overflow-y: auto;
  flex-grow: 1;
}

.nugget-modal-description {
  display: block;
  margin-bottom: 1.5rem;
  color: var(--naas-text, #1f2937);
  line-height: 1.65;
  font-size: 0.9rem;
}

/* ── In-brief sidebar ── */
.metadata-field {
  margin: 0;
}

.metadata-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.metadata-list li {
  font-size: 0.875rem;
  color: var(--naas-text, #1f2937);
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
}

.metadata-list li .icon {
  color: var(--naas-primary, #0f6cbf);
  width: 1rem;
  flex-shrink: 0;
  margin-top: 0.1rem;
}

.metadata-list-item-wrap {
  align-items: flex-start !important; /* stylelint-disable-line declaration-no-important */
}

/* Flex-wrap row of pill tags */
.meta-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
  margin-top: 0.35rem;
}

.meta-tag {
  display: inline-block;
  padding: 0.18rem 0.6rem;
  background: var(--naas-primary-light, #dce9fa);
  color: var(--naas-primary, #0f6cbf);
  border: 1px solid var(--naas-primary, #0f6cbf);
  border-radius: var(--naas-radius-pill, 999px);
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
  line-height: 1.4;
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
  transform: translateY(16px);
}
.modal-fade-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

/* ── Responsive ── */
@media (max-width: 768px) {
  .nugget-modal-backdrop { padding: 1rem 0.5rem; }
  .nugget-modal-body { max-height: calc(92vh - 80px); padding: 1rem; }
}
</style>
