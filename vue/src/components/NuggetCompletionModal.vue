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
 * Completion modal: star rating + back-to-course / next-unit navigation.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <Teleport to="body">
    <transition name="modal-fade">
      <div v-if="visible" class="nugget-modal-backdrop" @click.self="emit('close')">
        <div class="nugget-modal">
          <!-- Close -->
          <div class="nugget-modal-header">
            <button type="button" class="btn-close" @click="emit('close')">✕</button>
          </div>

          <!-- Rating body -->
          <div class="nugget-modal-body">
            <h2 class="rating-title">{{ config.labels.rating.title }}</h2>
            <div class="rating" role="group" :aria-label="config.labels.rating.title">
              <span
                v-for="i in MAX_SCORE"
                :key="i"
                class="star"
                :class="{ checked: savedRating === MAX_SCORE + 1 - i }"
                role="radio"
                :aria-checked="savedRating === MAX_SCORE + 1 - i"
                :aria-label="`${MAX_SCORE + 1 - i} star`"
                tabindex="0"
                @click="savedRating = MAX_SCORE + 1 - i"
                @keydown.enter.space.prevent="savedRating = MAX_SCORE + 1 - i"
              >
                <i class="icon fa fa-star" />
              </span>
            </div>
            <button
              id="send-rating"
              type="button"
              class="rating-submit-btn"
              :class="{ 'rating-submit-btn--sent': ratingSent }"
              :disabled="ratingSent || savedRating === null"
              @click="rate(savedRating)"
            >
              <i v-if="ratingSent" class="icon fa fa-check" />
              {{ ratingSent ? config.labels.rating.sent : config.labels.rating.send }}
            </button>
            <p class="rating-description">{{ config.labels.rating.description }}</p>
          </div>

          <!-- Learning outcomes -->
          <div
            v-if="nugget.learning_outcomes && nugget.learning_outcomes.length"
            class="learning-outcomes"
          >
            <p class="learning-outcomes-label">{{ config.labels.learning_outcomes_desc }}</p>
            <ul class="learning-outcomes-list">
              <li v-for="item in nugget.learning_outcomes" :key="item">{{ item }}</li>
            </ul>
          </div>

          <!-- Footer nav -->
          <div class="nugget-modal-footer">
            <a :href="backLink" class="nav-btn nav-btn-back">
              <i class="icon fa fa-arrow-left" />
              {{ config.labels.back_to_course }}
            </a>
            <a
              v-if="nextUnitLink"
              :href="nextUnitLink"
              class="nav-btn nav-btn-next"
              @click.prevent="goToNextResource"
            >
              {{ config.labels.next_unit }}
              <i class="icon fa fa-arrow-right" />
            </a>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useNaasConfig } from '@/composables/useNaasConfig'
import { useXapi } from '@/composables/useXapi'
import type { Nugget } from '@/types/nugget.types'

const MAX_SCORE = 5
const MIN_SCORE = 1

const props = defineProps<{ nugget: Nugget; visible: boolean }>()
const emit = defineEmits<{ (e: 'close'): void }>()

const config = useNaasConfig()
const { postStatement } = useXapi()

const savedRating = ref<number | null>(null)
const ratingSent = ref(false)
const backLink = ref('#')
const nextUnitLink = ref<string | null>(null)

onMounted(() => {
  const backEl = document.querySelector<HTMLAnchorElement>('.course-button a')
  if (backEl) backLink.value = backEl.href

  const nextEl = document.querySelector<HTMLAnchorElement>('.next-activity a, #next-activity-link')
  if (nextEl) nextUnitLink.value = nextEl.href
})

function rate(score: number | null) {
  if (!score) return
  postStatement({
    id: config.cm_id,
    verb: 'rated',
    version_id: props.nugget.version_id,
    body: JSON.stringify({ raw: score, min: MIN_SCORE, max: MAX_SCORE }),
  })
  ratingSent.value = true
}

function goToNextResource() {
  emit('close')
  if (!nextUnitLink.value) return

  const idMatch = nextUnitLink.value.match(/id=(\d+)/)
  const anchorId = idMatch ? `module-${idMatch[1]}` : nextUnitLink.value.match(/#([^&]*)/)?.[1]

  if (anchorId) {
    setTimeout(() => {
      const el = document.getElementById(anchorId)
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
      else window.location.hash = `#${anchorId}`
    }, 100)
  } else {
    window.location.href = backLink.value
  }
}
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
  align-items: center;
  z-index: 1060;
  padding: 1.5rem;
}

/* ── Modal panel ── */
.nugget-modal {
  width: 100%;
  max-width: 520px;
  background: var(--naas-surface, #fff);
  box-shadow: var(--naas-shadow-lg, 0 12px 40px rgba(0,0,0,.18));
  border-radius: var(--naas-radius-xl, 16px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* ── Header (close only) ── */
.nugget-modal-header {
  display: flex;
  justify-content: flex-end;
  padding: 0.75rem 1rem 0;
  flex-shrink: 0;
}

.btn-close {
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

/* ── Rating body ── */
.nugget-modal-body {
  padding: 1rem 2rem 1.5rem;
  text-align: center;
}

.rating-title {
  font-size: 1.2rem;
  font-weight: 700;
  margin: 0 0 1.25rem;
  color: var(--naas-text, #1f2937);
}

.rating {
  display: flex;
  flex-direction: row-reverse;
  justify-content: center;
  gap: 0.25rem;
  margin-bottom: 1.25rem;
}

.star {
  color: #d1d5db;
  padding: 0 2px;
  cursor: pointer;
  transition: color 0.12s ease, transform 0.12s ease;
  outline: none;
}

.star:focus-visible {
  outline: 2px solid var(--naas-primary, #0f6cbf);
  border-radius: 2px;
}

.star i {
  font-size: 2.2rem;
}

.star:hover,
.star:hover ~ .star {
  color: #f59e0b;
  transform: scale(1.12);
}

.star.checked,
.star.checked ~ span {
  color: #f59e0b;
}

/* Submit button */
.rating-submit-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.55rem 2rem;
  background: var(--naas-primary, #0f6cbf);
  color: #fff;
  border: none;
  border-radius: var(--naas-radius-pill, 999px);
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  transition: background var(--naas-transition, 0.18s ease),
              opacity   var(--naas-transition, 0.18s ease);
}

.rating-submit-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.rating-submit-btn:not(:disabled):hover {
  background: var(--naas-primary-hover, #0a58ca);
}

.rating-submit-btn--sent {
  background: #16a34a;
}

.rating-submit-btn--sent:not(:disabled):hover {
  background: #15803d;
}

.rating-description {
  color: var(--naas-text-muted, #6c757d);
  font-size: 0.825rem;
  margin: 0.75rem 0 0;
}

/* ── Learning outcomes ── */
.learning-outcomes {
  background: var(--naas-surface-muted, #f8f9fa);
  border-top: 1px solid var(--naas-border-light, #e9ecef);
  padding: 1rem 2rem;
}

.learning-outcomes-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--naas-text-muted, #6c757d);
  margin: 0 0 0.5rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.learning-outcomes-list {
  margin: 0;
  padding-left: 1.25rem;
  font-size: 0.85rem;
  color: var(--naas-text, #1f2937);
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

/* ── Footer nav ── */
.nugget-modal-footer {
  display: flex;
  justify-content: center;
  gap: 0.75rem;
  padding: 1rem 1.5rem 1.5rem;
  border-top: 1px solid var(--naas-border-light, #e9ecef);
  flex-wrap: wrap;
}

.nav-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem 1.25rem;
  border-radius: var(--naas-radius-pill, 999px);
  font-size: 0.875rem;
  font-weight: 600;
  text-decoration: none;
  transition: background var(--naas-transition, 0.18s ease),
              box-shadow var(--naas-transition, 0.18s ease);
}

.nav-btn-back {
  background: var(--naas-surface-muted, #f8f9fa);
  color: var(--naas-text, #1f2937);
  border: 1.5px solid var(--naas-border, #dee2e6);
}

.nav-btn-back:hover {
  background: var(--naas-surface-hover, #f0f4ff);
  border-color: var(--naas-primary, #0f6cbf);
  color: var(--naas-primary, #0f6cbf);
}

.nav-btn-next {
  background: var(--naas-primary, #0f6cbf);
  color: #fff;
  border: 1.5px solid var(--naas-primary, #0f6cbf);
}

.nav-btn-next:hover {
  background: var(--naas-primary-hover, #0a58ca);
  box-shadow: 0 2px 8px rgba(15, 108, 191, 0.3);
}

/* ── Animation: fade + scale in ── */
.modal-fade-enter-active {
  transition: opacity 0.22s ease, transform 0.22s ease;
}
.modal-fade-leave-active {
  transition: opacity 0.16s ease, transform 0.16s ease;
}
.modal-fade-enter-from {
  opacity: 0;
  transform: scale(0.96) translateY(12px);
}
.modal-fade-leave-to {
  opacity: 0;
  transform: scale(0.97);
}
</style>
