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
 * Single-nugget view: renders the LTI iframe with language picker,
 * About modal, and Completion modal.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div class="container">
    <!-- Error banner with retry -->
    <div v-if="error" class="naas-error-banner" role="alert">
      <span>{{ config.labels.error_generic_user_message }}</span>
      <button class="btn btn-sm btn-outline-danger" @click="load">
        {{ config.labels.retry || 'Retry' }}
      </button>
    </div>

    <div id="nugget-info-button">
      <div>
        <a
          v-if="aboutButton"
          href="javascript:;"
          class="btn btn-primary"
          @click="showAbout = true"
        >
          {{ config.labels.about }}
        </a>
        <select
          v-if="nugget"
          class="language-select"
          :value="language"
          @change="language = ($event.target as HTMLSelectElement).value"
        >
          <option :value="nugget.language">
            {{ config.labels.metadata[nugget.language] }}
          </option>
          <option
            v-for="item in nugget.multilanguages"
            :key="item.language"
            :value="item.language"
          >
            {{ config.labels.about }}
          </a>
          <select
            class="language-select"
            @change="language = $event.target.value"
          >
            <option selected :value="this.nugget.language">
              {{ config.labels.metadata[nugget.language] }}
            </option>
            <option
              v-for="item in this.nugget.multilanguages"
              :key="item.language"
              :value="item.language"
            >
              {{ config.labels.metadata[item.language] }}
            </option>
          </select>
        </div>
        <NuggetAboutModal
          :visible="aboutModal"
          :nugget="nugget"
          @close="aboutModal = false"
        />
      </div>

      <NuggetAboutModal
        v-if="nugget"
        :visible="showAbout"
        :nugget="nugget"
        @close="showAbout = false"
      />
    </div>

    <!-- Skeleton while the nugget API call is in flight -->
    <NuggetViewSkeleton v-if="loading" />

    <div class="text-center gallery row" id="nugget-learn">
      <!--
        src starts as the preload URL (no language) so the browser opens the TCP
        connection immediately. It is replaced with the full URL once the nugget
        loads and the language is known.
      -->
      <iframe
        v-if="!loading && !error"
        id="lti-frame"
        height="600px"
        width="100%"
        style="border: none"
        :src="iframeUrl ?? preloadUrl"
        webkitallowfullscreen
        mozallowfullscreen
        allowfullscreen
      />
    </div>

    <div class="row">
      <div id="completion-modal-button" class="col text-center">
        <button class="btn btn-primary" @click="complete">
          {{ config.labels.complete_nugget }}
        </button>
      </div>
    </div>

    <NuggetCompletionModal
      v-if="nugget"
      :visible="showCompletion"
      :nugget="nugget"
      @close="showCompletion = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
// @ts-expect-error iframe-resizer ships no type declarations
import iframeResizeLib from 'iframe-resizer/js/iframeResizer'
const iframeResize = iframeResizeLib as (opts: Record<string, unknown>, selector: string) => void

import NuggetAboutModal from './NuggetAboutModal.vue'
import NuggetCompletionModal from './NuggetCompletionModal.vue'
import NuggetViewSkeleton from './NuggetViewSkeleton.vue'
import { useNaasConfig } from '@/composables/useNaasConfig'
import { useNuggetView } from '@/composables/useNuggetView'
import { useXapi } from '@/composables/useXapi'

const config = useNaasConfig()
const { nugget, loading, error, load } = useNuggetView()
const { postStatement } = useXapi()

const language = ref<string | null>(null)
const showAbout = ref(false)
const showCompletion = ref(false)

// The Moodle ≥ 4.0 secondary-nav has its own About link; hide our button in that case.
const aboutButton = !document.querySelector('.secondary-navigation nav ul li[data-key=about]')

// Available synchronously — lets the browser open the TCP connection before the nugget API resolves.
const preloadUrl = `launch.php?id=${config.cm_id}&triggerview=0`

const iframeUrl = computed(() => {
  if (!language.value) return null
  return `launch.php?id=${config.cm_id}&triggerview=0&language=${language.value}`
})

let experiencedTimer: ReturnType<typeof setTimeout> | null = null
let visibilityObserver: IntersectionObserver | null = null

// React to the nugget loading: set language, init iframe-resizer, schedule xAPI.
watch(nugget, (loaded) => {
  if (!loaded) return
  language.value = loaded.language

  // Wait for the iframe to be in the DOM before attaching iframe-resizer.
  nextTick(() => {
    setTimeout(() => {
      iframeResize(
        { log: false, checkOrigin: false, heightCalculationMethod: 'lowestElement' },
        '#lti-frame'
      )

      // Delay "experienced" by 30 s of confirmed iframe visibility so accidental
      // landings do not pollute xAPI records.
      const iframe = document.getElementById('lti-frame')
      if (!iframe) return

      visibilityObserver = new IntersectionObserver((entries) => {
        const visible = entries[0]?.isIntersecting ?? false
        if (visible && !experiencedTimer) {
          experiencedTimer = setTimeout(() => {
            postStatement({
              id: config.cm_id,
              verb: 'experienced',
              version_id: loaded.version_id,
            })
            visibilityObserver?.disconnect()
          }, 10000)
        } else if (!visible && experiencedTimer) {
          clearTimeout(experiencedTimer)
          experiencedTimer = null
        }
      }, { threshold: 0.5 })

      visibilityObserver.observe(iframe)
    }, 500)
  })
})

function complete() {
  if (!nugget.value) return
  showCompletion.value = true
  postStatement({
    id: config.cm_id,
    verb: 'completed',
    version_id: nugget.value.version_id,
  })
}
</script>

<style scoped>
.container {
  padding: 0;
}

#nugget-info-button {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.language-select {
  margin-left: 5px;
  padding: 0.3rem 1rem 0.5rem 1rem;
  cursor: pointer;
  border: 1px solid var(--primary, #0f6cbf);
  border-radius: 4px;
  background: #fff;
  font-size: 0.875rem;
}

.naas-error-banner {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
  background: #fff3cd;
  border: 1px solid #ffc107;
  border-radius: var(--naas-radius, 6px);
  color: #856404;
}

.gallery {
  margin: 0;
}

#completion-modal-button {
  margin-top: 1.5rem;
}

#completion-modal-button button {
  padding: 10px 30px;
}
</style>
