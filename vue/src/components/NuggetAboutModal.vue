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
  <div v-show="visible" id="detail-modal">
    <transition name="modal-fade">
      <div class="nugget-modal-backdrop" @click="emit('close')">
        <div class="nugget-modal" @click.stop.prevent>
          <div class="container">
            <div class="nugget-modal-header row justify-content-between align-items-start">
              <h2>{{ config.labels.about }} : {{ nugget.name }}</h2>
              <button type="button" class="btn-close" @click="emit('close')">✕</button>
            </div>
          </div>

          <div class="container nugget-modal-body">
            <div class="row metadata-field">
              <div class="col">
                <div v-if="isShown(nugget.resume)">
                  <h3>{{ config.labels.metadata.description }}</h3>
                  <span v-html="nugget.resume" class="nugget-modal-description" />
                </div>

                <div v-if="isShown(nugget.authors_data)">
                  <h3>{{ config.labels.metadata.about_author }}</h3>
                  <div v-for="author in nugget.authors_data" :key="author.email">
                    <h5>{{ author.firstname }} {{ author.lastname }}</h5>
                    <span v-html="author.bio" class="nugget-modal-description" />
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
                  <li v-if="isShown(nugget.domains_data)">
                    <i class="icon fa fa-home" />
                    {{ config.labels.metadata.field_of_study }}:<br />
                    <span
                      v-for="item in nugget.domains_data"
                      :key="item.id"
                      class="metadata-list-item"
                    >
                      <span class="badge badge-pill badge-primary">{{ item.label }}</span><br />
                    </span>
                  </li>
                  <li v-if="isShown(nugget.tags)">
                    <i class="icon fa fa-tag" />
                    {{ config.labels.metadata.tags }}:<br />
                    <span v-for="tag in nugget.tags" :key="tag" class="metadata-list-item">
                      <span class="badge badge-pill badge-primary">{{ tag }}</span><br />
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
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import moment from 'moment'
import { useNaasConfig } from '@/composables/useNaasConfig'
import type { Nugget } from '@/types/nugget.types'

defineProps<{ nugget: Nugget; visible: boolean }>()
const emit = defineEmits<{ (e: 'close'): void }>()

const config = useNaasConfig()

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
