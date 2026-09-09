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
  <div v-show="visible">
    <div class="nugget-modal-backdrop">
      <div class="nugget-modal">
        <div class="container">
          <div class="nugget-modal-header row justify-content-end">
            <button type="button" class="btn-close" @click="emit('close')">✕</button>
          </div>

          <div class="nugget-modal-body row">
            <div class="text-center col">
              <h2>{{ config.labels.rating.title }}</h2>
              <p class="rating saved">
                <span
                  v-for="i in MAX_SCORE"
                  :key="i"
                  class="star"
                  :class="{ checked: savedRating === MAX_SCORE + 1 - i }"
                  @click="savedRating = MAX_SCORE + 1 - i"
                >
                  <i class="icon fa fa-star" />
                </span>
              </p>
              <button
                id="send-rating"
                type="button"
                class="btn btn-sm btn-outline-success mt-2"
                :disabled="ratingSent"
                @click="rate(savedRating)"
              >
                {{ ratingSent ? config.labels.rating.sent : config.labels.rating.send }}
              </button>
              <p class="rating-description">{{ config.labels.rating.description }}</p>
            </div>
          </div>

          <div
              v-if="nugget.learning_outcomes && nugget.learning_outcomes.length"
              class="finish-learning-outcomes row"
          >
            <div class="col text-center">
              {{ config.labels.learning_outcomes_desc }}
              <span v-for="item in nugget.learning_outcomes" :key="item">• {{ item }} </span>
            </div>
          </div>

          <div class="nugget-modal-footer row">
            <div class="col d-flex justify-content-center align-items-center">
              <a :href="backLink" class="btn btn-sm btn-primary">
                ◀︎ {{ config.labels.back_to_course }}
              </a>
              <a
                v-if="nextUnitLink"
                :href="nextUnitLink"
                class="ml-2 btn btn-sm btn-primary"
                @click.prevent="goToNextResource"
              >
                {{ config.labels.next_unit }} ▶︎
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
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
