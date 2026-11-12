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
      <!-- A missing Nugget will not come back on retry: say so plainly. -->
      <span v-if="nuggetNotFound">{{ error }}</span>
      <template v-else>
        <span
          >{{ config.labels.error_generic_user_message
          }}<template v-if="error.trim()"> — {{ error }}</template></span
        >
        <button class="btn btn-sm btn-outline-danger" @click="load">
          {{ config.labels.retry }}
        </button>
      </template>
    </div>

    <div id="nugget-info-button">
      <div>
        <button
          v-if="aboutButton"
          type="button"
          class="btn btn-primary"
          @click="showAbout = true"
        >
          {{ config.labels.about }}
        </button>
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
            {{ config.labels.metadata[item.language] }}
          </option>
        </select>
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
        :title="nugget?.name"
        height="600px"
        width="100%"
        style="border: none"
        :src="iframeUrl ?? preloadUrl"
        webkitallowfullscreen
        mozallowfullscreen
        allowfullscreen
      />
    </div>

    <!-- No Nugget to finish while it failed to load. -->
    <div v-if="nugget && !error" class="row">
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
import {
  ref,
  computed,
  watch,
  nextTick,
  onBeforeUnmount,
  onMounted,
} from "vue";
// @ts-expect-error iframe-resizer ships no type declarations
import iframeResizeLib from "iframe-resizer/js/iframeResizer";
const iframeResize = iframeResizeLib as (
  opts: Record<string, unknown>,
  selector: string
) => void;

import NuggetAboutModal from "./NuggetAboutModal.vue";
import NuggetCompletionModal from "./NuggetCompletionModal.vue";
import NuggetViewSkeleton from "./NuggetViewSkeleton.vue";
import { useNaasConfig } from "@/shared/config";
import { useNuggetView } from "@/composables/useNuggetView";
import { useXapi } from "@/shared/api";
import {
  isFrameSeen,
  VISIBILITY_THRESHOLDS,
} from "@/composables/frameVisibility";

const config = useNaasConfig();
const { nugget, loading, error, load } = useNuggetView();
// Moodle may append debug info to the message when debugging is on.
const nuggetNotFound = computed(
  () =>
    !!error.value &&
    !!config.labels.error_nugget_not_found &&
    error.value.startsWith(config.labels.error_nugget_not_found)
);
const { postStatement } = useXapi();

const language = ref<string | null>(null);
const showAbout = ref(false);
const showCompletion = ref(false);

// Moodle ≥ 4.0 shows an About entry in the activity's secondary navigation
// (lib.php, naas_extend_settings_navigation): it opens the dialog instead of
// our own button, which only shows on pages without that navigation.
const navAbout = document.querySelector<HTMLElement>(
  ".secondary-navigation nav ul li[data-key=about] a"
);
const aboutButton = !navAbout;

function openAboutFromNav(event: Event) {
  event.preventDefault();
  showAbout.value = true;
}
onMounted(() => navAbout?.addEventListener("click", openAboutFromNav));
onBeforeUnmount(() => navAbout?.removeEventListener("click", openAboutFromNav));

// Available synchronously — lets the browser open the TCP connection before the nugget API resolves.
const preloadUrl = `launch.php?id=${config.cm_id}&triggerview=0`;

const iframeUrl = computed(() => {
  if (!language.value) return null;
  return `launch.php?id=${config.cm_id}&triggerview=0&language=${language.value}`;
});

let setupTimer: ReturnType<typeof setTimeout> | null = null;
let experiencedTimer: ReturnType<typeof setTimeout> | null = null;
let visibilityObserver: IntersectionObserver | null = null;

/** Stop the pending setup, the visibility observer and the xAPI timer. */
function stopVisibilityTracking(): void {
  if (setupTimer) {
    clearTimeout(setupTimer);
    setupTimer = null;
  }
  if (experiencedTimer) {
    clearTimeout(experiencedTimer);
    experiencedTimer = null;
  }
  visibilityObserver?.disconnect();
  visibilityObserver = null;
}

onBeforeUnmount(stopVisibilityTracking);

// React to the nugget loading: set language, init iframe-resizer, schedule xAPI.
watch(nugget, (loaded) => {
  // One observer per loaded nugget: drop the previous one first.
  stopVisibilityTracking();
  /* v8 ignore next -- @preserve */
  if (!loaded) return;
  language.value = loaded.language;

  // Wait for the iframe to be in the DOM before attaching iframe-resizer.
  nextTick(() => {
    setupTimer = setTimeout(() => {
      setupTimer = null;
      iframeResize(
        {
          log: false,
          checkOrigin: false,
          heightCalculationMethod: "lowestElement",
        },
        "#lti-frame"
      );

      // Delay "experienced" by 10 s of confirmed iframe visibility so accidental
      // landings do not pollute xAPI records.
      const iframe = document.getElementById("lti-frame");
      // Gone only if the view was unmounted during the delay.
      /* v8 ignore next -- @preserve */
      if (!iframe) return;

      visibilityObserver = new IntersectionObserver(
        (entries) => {
          // The latest entry is the current state when several are batched.
          const entry = entries[entries.length - 1];
          const visible = entry ? isFrameSeen(entry) : false;
          if (visible && !experiencedTimer) {
            experiencedTimer = setTimeout(() => {
              postStatement({
                id: config.cm_id,
                verb: "experienced",
                version_id: loaded.version_id,
              });
              experiencedTimer = null;
              visibilityObserver?.disconnect();
              visibilityObserver = null;
            }, 10000);
          } else if (!visible && experiencedTimer) {
            clearTimeout(experiencedTimer);
            experiencedTimer = null;
          }
        },
        { threshold: VISIBILITY_THRESHOLDS }
      );

      visibilityObserver.observe(iframe);
    }, 500);
  });
});

function complete() {
  // The button only renders once the Nugget is loaded; kept for direct callers.
  /* v8 ignore next -- @preserve */
  if (!nugget.value) return;
  showCompletion.value = true;
  postStatement({
    id: config.cm_id,
    verb: "completed",
    version_id: nugget.value.version_id,
  });
}
</script>

<style scoped>
.container {
  padding: 0;
}

/* ── Toolbar ── */
#nugget-info-button {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.language-select {
  padding: 0.38rem 2rem 0.38rem 0.875rem;
  cursor: pointer;
  border: 1.5px solid var(--naas-border, #dee2e6);
  border-radius: var(--naas-radius-pill, 999px);
  background: var(--naas-surface, #fff)
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%236c757d' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E")
    no-repeat right 0.75rem center;
  -webkit-appearance: none;
  appearance: none;
  font-size: 0.875rem;
  color: var(--naas-text, #1f2937);
  transition:
    border-color var(--naas-transition, 0.18s ease),
    box-shadow var(--naas-transition, 0.18s ease);
  outline: none;
}

.language-select:focus {
  border-color: var(--naas-primary, #0f6cbf);
  box-shadow: 0 0 0 3px rgba(15, 108, 191, 0.15);
}

/* ── Error banner ── */
.naas-error-banner {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
  background: #fffbeb;
  border: 1.5px solid #fcd34d;
  border-radius: var(--naas-radius, 8px);
  color: #92400e;
  font-size: 0.875rem;
}

.gallery {
  margin: 0;
}

/* ── Complete button row ── */
#completion-modal-button {
  margin-top: 1.75rem;
  text-align: center;
}

#completion-modal-button button {
  padding: 0.6rem 2.5rem;
  font-size: 1rem;
  font-weight: 700;
  border-radius: var(--naas-radius-pill, 999px);
  letter-spacing: 0.02em;
  box-shadow: var(--naas-shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.1));
  transition:
    background var(--naas-transition, 0.18s ease),
    box-shadow var(--naas-transition, 0.18s ease),
    transform var(--naas-transition, 0.18s ease);
}

#completion-modal-button button:hover {
  transform: translateY(-1px);
  box-shadow: var(--naas-shadow-md, 0 6px 20px rgba(0, 0, 0, 0.14));
}
</style>
