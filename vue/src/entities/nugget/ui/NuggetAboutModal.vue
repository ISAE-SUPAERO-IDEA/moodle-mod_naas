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
 * Modal displaying full nugget metadata: what the Nugget teaches on the left
 * (description, outcomes, prerequisites, authors, references), its key facts in
 * a side panel. Teleported to body so it never lays out inside a nugget card,
 * which also puts it outside .naas-widget: every token needs its fallback.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <Teleport to="body">
    <transition name="modal-fade">
      <div
        v-if="visible"
        class="nugget-modal-backdrop"
        @click.self="emit('close')"
      >
        <div
          ref="dialog"
          class="nugget-modal"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
          tabindex="-1"
          @click.stop
        >
          <div class="nugget-modal-header">
            <img
              v-if="thumbnail"
              class="about-thumb"
              :src="thumbnail"
              alt=""
              @error="failedThumbnail = thumbnail"
            />
            <h2 :id="titleId">
              {{ fillLabel(config.labels.about_title, nugget.name) }}
            </h2>
            <button
              type="button"
              class="btn-close"
              :aria-label="config.labels.close"
              @click="emit('close')"
            >
              ✕
            </button>
          </div>

          <div class="nugget-modal-body">
            <div
              class="about-grid"
              :class="{ 'about-grid--single': !mainShown || !inBriefShown }"
            >
              <div v-if="mainShown" class="about-main">
                <section v-if="isShown(nugget.resume)" class="about-section">
                  <h3>{{ config.labels.metadata.description }}</h3>
                  <p class="about-text">{{ stripHtml(nugget.resume) }}</p>
                </section>

                <section
                  v-if="isShown(nugget.learning_outcomes)"
                  class="about-section"
                >
                  <h3>{{ config.labels.metadata.learning_outcomes }}</h3>
                  <ul class="about-list about-list--outcomes">
                    <li v-for="item in nugget.learning_outcomes" :key="item">
                      {{ item }}
                    </li>
                  </ul>
                </section>

                <section
                  v-if="isShown(nugget.prerequisites)"
                  class="about-section"
                >
                  <h3>{{ config.labels.metadata.prerequisites }}</h3>
                  <ul class="about-list">
                    <li v-for="item in nugget.prerequisites" :key="item">
                      {{ item }}
                    </li>
                  </ul>
                </section>

                <section
                  v-if="isShown(nugget.authors_data)"
                  class="about-section"
                >
                  <h3>{{ config.labels.metadata.about_author }}</h3>
                  <ul class="about-authors">
                    <li
                      v-for="author in nugget.authors_data"
                      :key="author.email"
                      class="about-author"
                    >
                      <span class="about-author-initials" aria-hidden="true">{{
                        initials(author)
                      }}</span>
                      <div class="about-author-text">
                        <h4>{{ author.firstname }} {{ author.lastname }}</h4>
                        <p v-if="bioOf(author)" class="about-text">
                          {{ bioOf(author) }}
                        </p>
                      </div>
                    </li>
                  </ul>
                </section>

                <section
                  v-if="isShown(nugget.references)"
                  class="about-section"
                >
                  <h3>{{ config.labels.metadata.references }}</h3>
                  <ul class="about-list">
                    <li v-for="item in nugget.references" :key="item">
                      {{ item }}
                    </li>
                  </ul>
                </section>
              </div>

              <div v-if="inBriefShown" class="about-brief">
                <h3>{{ config.labels.metadata.in_brief }}</h3>
                <dl class="about-facts">
                  <div v-if="isShown(nugget.duration)" class="about-fact">
                    <dt>
                      <i class="icon fa fa-clock-o" aria-hidden="true" />
                      {{ config.labels.metadata.duration }}
                    </dt>
                    <dd>
                      {{
                        fillLabel(
                          config.labels.duration_minutes,
                          String(nugget.duration)
                        )
                      }}
                    </dd>
                  </div>
                  <div v-if="isShown(nugget.language)" class="about-fact">
                    <dt>
                      <i class="icon fa fa-globe" aria-hidden="true" />
                      {{ config.labels.metadata.language }}
                    </dt>
                    <dd>{{ config.labels.metadata[nugget.language] }}</dd>
                  </div>
                  <div v-if="isShown(nugget.level)" class="about-fact">
                    <dt>
                      <i class="icon fa fa-signal" aria-hidden="true" />
                      {{ config.labels.metadata.level }}
                    </dt>
                    <dd>{{ config.labels.metadata[nugget.level!] }}</dd>
                  </div>
                  <div
                    v-if="isShown(nugget.publication_date)"
                    class="about-fact"
                  >
                    <dt>
                      <i class="icon fa fa-calendar" aria-hidden="true" />
                      {{ config.labels.metadata.publication_date }}
                    </dt>
                    <dd>{{ formatDate(nugget.publication_date) }}</dd>
                  </div>
                  <div v-if="licenceText" class="about-fact about-fact--wide">
                    <dt>
                      <i class="icon fa fa-id-card-o" aria-hidden="true" />
                      {{ config.labels.metadata.license }}
                    </dt>
                    <dd>{{ licenceText }}</dd>
                  </div>
                  <div
                    v-if="producerNames.length"
                    class="about-fact about-fact--wide"
                  >
                    <dt>
                      <i class="icon fa fa-university" aria-hidden="true" />
                      {{ config.labels.metadata.producer }}
                    </dt>
                    <dd>{{ producerNames.join(", ") }}</dd>
                  </div>
                  <div v-if="partnerName" class="about-fact about-fact--wide">
                    <dt>
                      <i class="icon fa fa-handshake-o" aria-hidden="true" />
                      {{ config.labels.metadata.partner }}
                    </dt>
                    <dd>
                      <a
                        v-if="partnerUrl"
                        :href="partnerUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        >{{ partnerName }}</a
                      >
                      <template v-else>{{ partnerName }}</template>
                    </dd>
                  </div>
                  <div
                    v-if="isShown(nugget.domains_data)"
                    class="about-fact about-fact--wide"
                  >
                    <dt>
                      <i class="icon fa fa-graduation-cap" aria-hidden="true" />
                      {{ config.labels.metadata.field_of_study }}
                    </dt>
                    <dd class="meta-tags">
                      <span
                        v-for="item in nugget.domains_data"
                        :key="item.id"
                        class="meta-tag"
                        >{{ item.label }}</span
                      >
                    </dd>
                  </div>
                  <div
                    v-if="isShown(nugget.tags)"
                    class="about-fact about-fact--wide"
                  >
                    <dt>
                      <i class="icon fa fa-tags" aria-hidden="true" />
                      {{ config.labels.metadata.tags }}
                    </dt>
                    <dd class="meta-tags">
                      <span
                        v-for="tag in nugget.tags"
                        :key="tag"
                        class="meta-tag"
                        >{{ tag }}</span
                      >
                    </dd>
                  </div>
                </dl>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, ref, useId, watch } from "vue";
import { formatShortDate, fillLabel } from "@/shared/lib";
import { licenceRights, rightsCaption } from "../lib/nuggetLicence";
import { useStructureResolvers } from "@/entities/catalogue/@x/nugget";
import { useModalA11y } from "@/shared/ui";
import { useNaasConfig } from "@/shared/config";
import { createMediaRouter } from "@/shared/api";
import type { Nugget, Person } from "@/shared/api";

const props = defineProps<{ nugget: Nugget; visible: boolean }>();
const emit = defineEmits<{ (e: "close"): void }>();

const config = useNaasConfig();
const media = createMediaRouter(config.moodle_url, config.courseId);

const dialog = ref<HTMLElement | null>(null);
const titleId = useId();
useModalA11y(
  () => props.visible,
  dialog,
  () => emit("close")
);

/** The thumbnail URL that failed to load: the header then shows no image. */
const failedThumbnail = ref("");
/** Same URL as on the card, so the browser already holds the image. */
const thumbnail = computed(() => {
  const url = media.image(props.nugget.nugget_thumbnail_url, 700, 394);
  return url === failedThumbnail.value ? "" : url;
});

function stripHtml(value?: string): string {
  return (
    // An element's textContent is never null (only a document's is).
    new DOMParser().parseFromString(value ?? "", "text/html").body.textContent!
  );
}

function isShown(val: unknown): boolean {
  if (val === undefined || val === null || val === "") return false;
  if (Array.isArray(val)) return val.length > 0;
  if (typeof val === "object") return Object.values(val).some(isShown);
  return true;
}

function formatDate(value?: string): string {
  return formatShortDate(value);
}

/** First letters of the first and last names, for the author's badge. */
function initials(author: Person): string {
  return [author.firstname, author.lastname]
    .map((part) =>
      String(part ?? "")
        .trim()
        .charAt(0)
    )
    .join("")
    .toUpperCase();
}

function bioOf(author: Person): string {
  return stripHtml(author.bio).trim();
}

/** Legacy numeric licence when NaaS still sends one, else the institute's licence rights. */
const licenceText = computed(() => {
  const legacy = props.nugget.license;
  if (isShown(legacy)) {
    return config.labels.metadata[`license_${legacy}`] ?? String(legacy);
  }
  return rightsCaption(
    licenceRights(props.nugget.access_licences, config.structure_id),
    config.labels.metadata
  );
});

const partnerName = computed(() => props.nugget.partner?.name?.trim() || "");
/** Only web links: never javascript: or other schemes coming from NaaS. */
const partnerUrl = computed(() => {
  const url = props.nugget.partner?.url?.trim() || "";
  return /^https?:\/\//i.test(url) ? url : "";
});

/** Producing institutes, named from the catalogue snapshot or Moodle. */
const producerNames = ref<string[]>([]);
const { getStructureAcronym } = useStructureResolvers();
watch(
  () => [props.visible, props.nugget] as const,
  async ([visible, nugget]) => {
    if (!visible) {
      return;
    }
    const keys = [
      ...new Set(nugget.managing_structures ?? nugget.producers ?? []),
    ].filter(Boolean);
    const names = await Promise.all(keys.map(getStructureAcronym));
    // Ignore an answer for a Nugget that was replaced in the meantime.
    if (props.nugget === nugget) {
      producerNames.value = names.filter(Boolean);
    }
  },
  { immediate: true }
);

/** The left column: what the Nugget teaches and who wrote it. */
const mainShown = computed(
  () =>
    isShown(props.nugget.resume) ||
    isShown(props.nugget.learning_outcomes) ||
    isShown(props.nugget.prerequisites) ||
    isShown(props.nugget.authors_data) ||
    isShown(props.nugget.references)
);

const inBriefShown = computed(
  () =>
    isShown(props.nugget.duration) ||
    isShown(props.nugget.language) ||
    isShown(props.nugget.level) ||
    !!licenceText.value ||
    producerNames.value.length > 0 ||
    !!partnerName.value ||
    isShown(props.nugget.domains_data) ||
    isShown(props.nugget.tags) ||
    isShown(props.nugget.publication_date)
);
</script>

<style scoped>
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
  overflow-y: auto;
}

/* The dialog takes focus when it opens; no outline around the whole box. */
.nugget-modal:focus {
  outline: none;
}

.nugget-modal {
  width: 100%;
  max-width: 1000px;
  margin: 0 auto;
  background: var(--naas-surface, #fff);
  color: var(--naas-text, #1f2937);
  box-shadow: var(--naas-shadow-lg, 0 12px 40px rgba(0, 0, 0, 0.18));
  border-radius: var(--naas-radius-xl, 16px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* ── Header: thumbnail, title, close ── */
.nugget-modal-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 1.25rem 1rem 1.5rem;
  border-bottom: 1px solid var(--naas-border-light, #e9ecef);
  flex-shrink: 0;
}

.about-thumb {
  flex: 0 0 auto;
  width: 6.5rem;
  aspect-ratio: 16 / 9;
  object-fit: cover;
  border-radius: var(--naas-radius, 8px);
  background: var(--naas-surface-muted, #f8f9fa);
}

.nugget-modal-header h2 {
  flex: 1 1 auto;
  min-width: 0;
  font-size: 1.25rem;
  font-weight: 700;
  margin: 0;
  padding: 0;
  color: var(--naas-text, #1f2937);
  line-height: 1.3;
  overflow-wrap: anywhere;
}

.btn-close {
  flex-shrink: 0;
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  padding: 0;
  color: var(--naas-text-muted, #6c757d);
  font-size: 1.1rem;
  font-weight: 700;
  border: none;
  background: transparent;
  border-radius: 50%;
  cursor: pointer;
}

.btn-close:hover {
  color: var(--naas-text, #1f2937);
  background: var(--naas-surface-muted, #f8f9fa);
}

/* ── Body: reading column and facts panel ── */
.nugget-modal-body {
  padding: 1.5rem;
  max-height: calc(88vh - 100px);
  overflow-y: auto;
}

.about-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(15rem, 19rem);
  gap: 2rem;
  align-items: start;
}

.about-grid--single {
  grid-template-columns: minmax(0, 1fr);
}

.about-main {
  display: grid;
  gap: 1.5rem;
  min-width: 0;
}

/* Section titles are small labels: the text under them carries the weight. */
.about-section h3,
.about-brief h3 {
  margin: 0 0 0.6rem;
  padding: 0;
  font-size: 0.78rem;
  font-weight: 700;
  line-height: 1.3;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #5c636a; /* muted grey reaching 4.5:1 on white and on #f8f9fa */
}

.about-text {
  margin: 0;
  max-width: 68ch;
  font-size: 0.95rem;
  line-height: 1.65;
  color: var(--naas-text, #1f2937);
  overflow-wrap: anywhere;
}

/* ── Lists: outcomes are ticked, the others get a small dot ── */
.about-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.45rem;
  max-width: 68ch;
  font-size: 0.95rem;
  line-height: 1.5;
}

.about-list li {
  position: relative;
  padding-left: 1.5rem;
  overflow-wrap: anywhere;
}

.about-list li::before {
  content: "";
  position: absolute;
  left: 0.4rem;
  top: 0.6em;
  width: 0.35rem;
  height: 0.35rem;
  border-radius: 50%;
  background: var(--naas-text-subtle, #adb5bd);
}

.about-list--outcomes li::before {
  left: 0.15rem;
  top: 0.35em;
  width: 0.55rem;
  height: 0.3rem;
  border-radius: 0;
  background: none;
  border-left: 2px solid var(--naas-primary, #0f6cbf);
  border-bottom: 2px solid var(--naas-primary, #0f6cbf);
  transform: rotate(-45deg);
}

/* ── Authors ── */
.about-authors {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
  gap: 0.9rem 1.5rem;
}

.about-author {
  display: flex;
  align-items: flex-start;
  gap: 0.7rem;
  min-width: 0;
}

.about-author-initials {
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%;
  background: var(--naas-primary-light, #dce9fa);
  color: var(--naas-primary-dark, #0a4a8f);
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.about-author-text {
  min-width: 0;
}

.about-author h4 {
  margin: 0;
  padding: 0.4rem 0 0;
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.35;
  color: var(--naas-text, #1f2937);
}

.about-author .about-text {
  margin-top: 0.15rem;
  font-size: 0.875rem;
  color: #5c636a;
}

/* ── Facts panel ── */
.about-brief {
  position: sticky;
  top: 0;
  padding: 1.1rem 1.15rem 1.2rem;
  background: var(--naas-surface-muted, #f8f9fa);
  border: 1px solid var(--naas-border-light, #e9ecef);
  border-radius: 12px;
  min-width: 0;
}

.about-facts {
  margin: 0;
  display: grid;
  gap: 0.85rem;
}

.about-fact {
  min-width: 0;
}

.about-fact dt {
  display: flex;
  align-items: center;
  margin: 0 0 0.1rem;
  font-size: 0.78rem;
  font-weight: 400;
  line-height: 1.4;
  color: #5c636a;
}

.about-fact dt .icon {
  flex: 0 0 auto;
  width: 1rem;
  height: auto;
  margin: 0 0.5rem 0 0;
  font-size: 0.85rem;
  text-align: center;
  color: var(--naas-primary, #0f6cbf);
}

.about-fact dd {
  margin: 0;
  padding-left: 1.5rem;
  font-size: 0.9rem;
  font-weight: 600;
  line-height: 1.45;
  color: var(--naas-text, #1f2937);
  overflow-wrap: anywhere;
}

/* Moodle's theme gives dd a clearing pseudo-element: in a flex dd it would
   take a slot and push the first tag by one gap. */
.about-fact dd::before,
.about-fact dd::after {
  content: none;
}

.about-fact dd a {
  color: var(--naas-primary, #0f6cbf);
}

.about-fact dd.meta-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
  margin-top: 0.3rem;
}

.meta-tag {
  display: inline-flex;
  align-items: center;
  padding: 0.12rem 0.55rem;
  border-radius: var(--naas-radius-pill, 999px);
  background: var(--naas-primary-light, #dce9fa);
  color: var(--naas-primary-dark, #0a4a8f);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.5;
}

.modal-fade-enter-active {
  transition:
    opacity 0.22s ease,
    transform 0.22s ease;
}
.modal-fade-leave-active {
  transition:
    opacity 0.18s ease,
    transform 0.18s ease;
}
.modal-fade-enter-from {
  opacity: 0;
  transform: translateY(18px);
}
.modal-fade-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

@media (prefers-reduced-motion: reduce) {
  .modal-fade-enter-active,
  .modal-fade-leave-active {
    transition: none;
  }
}

@media (max-width: 768px) {
  .about-grid {
    grid-template-columns: minmax(0, 1fr);
    gap: 1.25rem;
  }
  /* On a phone the key facts come first, under the title, two per row; the
     long ones (licence, producer, tags) keep the full width. */
  .about-brief {
    position: static;
    order: -1;
  }
  .about-facts {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem 1rem;
  }
  .about-fact--wide {
    grid-column: 1 / -1;
  }
  .nugget-modal-header {
    padding: 0.85rem 0.85rem 0.85rem 1rem;
    gap: 0.75rem;
  }
  .nugget-modal-header h2 {
    font-size: 1.1rem;
  }
  .about-thumb {
    width: 4.5rem;
  }
  .nugget-modal-body {
    max-height: calc(92vh - 80px);
    padding: 1rem;
  }
}
</style>
