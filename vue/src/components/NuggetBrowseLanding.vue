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
 * Catalogue landing: All Nuggets plus producer cards.
 * Clicking All Nuggets opens the unfiltered nugget grid; a producer card
 * opens that producer's nuggets.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div class="browse-landing">
    <div class="browse-grid" :aria-busy="producersLoading ? 'true' : 'false'">
      <button
        type="button"
        class="browse-card browse-card--all"
        @click="
          emit('select', {
            facet: 'all',
            value: '',
            caption: labels.allNuggets,
          })
        "
      >
        <span class="browse-card-media browse-card-media--all">
          <i class="icon fa fa-th-large" aria-hidden="true" />
        </span>
        <span class="browse-card-body">
          <span class="browse-card-title">{{ labels.allNuggets }}</span>
          <span class="browse-card-meta">{{ countLabel(resultsCount) }}</span>
        </span>
      </button>
      <template v-if="producersLoading && producers.length === 0">
        <div
          v-for="n in skeletonCount"
          :key="`producer-skel-${n}`"
          class="browse-card browse-card--skel"
          aria-hidden="true"
        >
          <span class="browse-card-media browse-card-media--skel" />
          <span class="browse-card-body">
            <span class="browse-skel-line browse-skel-line--title" />
            <span class="browse-skel-line browse-skel-line--meta" />
          </span>
        </div>
      </template>
      <button
        v-for="producer in producers"
        :key="producer.id"
        type="button"
        class="browse-card browse-card--producer"
        :title="cardHoverName(producer) || undefined"
        @click="
          emit('select', {
            facet: 'producers',
            value: producer.id,
            caption: cardTitle(producer),
          })
        "
      >
          <span
            class="browse-card-media"
            :class="{ 'browse-card-media--has-logo': !!producer.logoUrl }"
          >
            <img
              v-if="producer.hasCover && producer.imageUrl"
              class="browse-card-image"
              :src="producer.imageUrl"
              alt=""
            />
            <span
              v-else-if="!producer.logoUrl"
              class="browse-card-image browse-card-image--empty"
              aria-hidden="true"
            >
              {{ initials(cardTitle(producer)) }}
            </span>
            <span
              v-if="producer.logoUrl"
              class="browse-card-scrim"
              aria-hidden="true"
            />
            <img
              v-if="producer.logoUrl"
              class="browse-card-logo"
              :src="producer.logoUrl"
              :alt="cardTitle(producer)"
            />
          </span>
          <span class="browse-card-body">
            <span v-if="cardAcronym(producer)" class="browse-card-title">{{
              cardAcronym(producer)
            }}</span>
            <span v-if="cardFullName(producer)" class="browse-card-name">{{
              cardFullName(producer)
            }}</span>
            <span class="browse-card-meta">{{
              countLabel(producer.count)
            }}</span>
          </span>
        </button>
        <p
          v-if="!producersLoading && producers.length === 0"
          class="browse-empty"
        >
          {{ labels.noProducers }}
        </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useNaasConfig } from "@/composables/useNaasConfig";
import { useEntityResolvers } from "@/composables/useEntityResolvers";
import { matchCachedProducer } from "@/composables/catalogueSnapshot";
import {
  isOpaqueEntityKey,
  isRawEntityLabel,
  structureVisuals,
} from "@/composables/structureVisuals";
import type { CatalogueProducer } from "@/types/naas-config.types";
import type { AggregationResult } from "@/types/nugget.types";

const props = defineProps<{
  aggregations: Record<string, AggregationResult>;
  resultsCount?: number;
  loading?: boolean;
  directory?: CatalogueProducer[];
}>();

const emit = defineEmits<{
  (
    e: "select",
    payload: { facet: string; value: string; caption: string }
  ): void;
}>();

const config = useNaasConfig();
const { getStructureVisuals } = useEntityResolvers();

const labels = computed(() => ({
  allNuggets: config.labels.all_nuggets || "All Nuggets",
  nuggets: config.labels.browse_nugget_count || "Nuggets",
  noProducers:
    config.labels.browse_no_producers ||
    "No producers in this catalogue yet.",
}));

interface ProducerCard {
  id: string;
  name: string;
  acronym: string;
  logoUrl: string;
  imageUrl: string;
  hasCover: boolean;
  count: number;
}

const producers = ref<ProducerCard[]>([]);
const resolvingProducers = ref(true);
let producerLoad = 0;

const producersLoading = computed(
  () =>
    resolvingProducers.value ||
    (!!props.loading && producers.value.length === 0)
);

function bucketDocCount(bucket: {
  docCount?: number;
  doc_count?: number;
}): number {
  if (typeof bucket.docCount === "number") {
    return bucket.docCount;
  }
  return Number(bucket.doc_count ?? 0);
}

const producerCount = computed(
  () => props.aggregations.producers?.buckets?.length ?? 0
);
const resultsCount = computed(() => Number(props.resultsCount ?? 0));
const skeletonCount = computed(() =>
  producerCount.value > 0 ? producerCount.value : 6
);

function countLabel(count: number): string {
  if (!count) {
    return "";
  }
  return `${count} ${labels.value.nuggets}`;
}

function initials(value: string): string {
  return value.trim().slice(0, 3).toUpperCase() || "?";
}

function visibleLabel(value: string): string {
  const text = value.trim();
  if (!text || isOpaqueEntityKey(text)) {
    return "";
  }
  return text;
}

function cardAcronym(producer: ProducerCard): string {
  const acronym = visibleLabel(producer.acronym);
  const name = visibleLabel(producer.name);
  if (acronym && (!name || acronym !== name)) {
    return acronym;
  }
  return acronym || name;
}

function cardFullName(producer: ProducerCard): string {
  const name = visibleLabel(producer.name);
  const acronym = cardAcronym(producer);
  if (name && name !== acronym) {
    return name;
  }
  return "";
}

function cardTitle(producer: ProducerCard): string {
  return cardAcronym(producer);
}

function cardHoverName(producer: ProducerCard): string {
  const name = visibleLabel(producer.name);
  if (name && name !== cardAcronym(producer)) {
    return name;
  }
  return "";
}

function directoryVisuals(key: string) {
  const row =
    matchCachedProducer(props.directory, key) ??
    matchCachedProducer(config.catalogue_snapshot?.producers, key);
  if (!row) {
    return null;
  }
  const visuals = structureVisuals(row, key, config.naas_endpoint ?? "");
  const hasName =
    (visuals.acronym && !isRawEntityLabel(visuals.acronym, key)) ||
    (visuals.name && !isRawEntityLabel(visuals.name, key));
  return hasName ? visuals : null;
}

watch(
  [() => props.aggregations.producers?.buckets, () => props.directory],
  async ([buckets]) => {
    const load = ++producerLoad;
    const list = [...(buckets ?? [])].sort(
      (a, b) => bucketDocCount(b) - bucketDocCount(a)
    );
    if (!list.length) {
      producers.value = [];
      resolvingProducers.value = false;
      return;
    }
    const painted = list.map((bucket) => {
      const key = String(bucket.key);
      const visuals =
        directoryVisuals(key) ??
        structureVisuals(null, key, config.naas_endpoint ?? "");
      return {
        id: key,
        count: bucketDocCount(bucket),
        ...visuals,
      };
    });
    producers.value = painted;
    resolvingProducers.value = false;
    const missing = painted.filter((card) => !directoryVisuals(card.id));
    if (!missing.length) {
      return;
    }
    const resolved = await Promise.all(
      missing.map(async (card) => {
        const visuals = await getStructureVisuals(card.id);
        return { ...card, ...visuals };
      })
    );
    if (load !== producerLoad) {
      return;
    }
    const byId = new Map(resolved.map((card) => [card.id, card]));
    producers.value = producers.value.map((card) => byId.get(card.id) ?? card);
  },
  { immediate: true }
);
</script>

<style scoped>
.browse-landing {
  padding-bottom: 2.5rem;
}

.browse-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  align-items: start;
  gap: 1rem;
}

.browse-card {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  height: auto;
  text-align: left;
  padding: 0;
  border: 1.5px solid var(--naas-border, #dee2e6);
  border-radius: var(--naas-radius, 8px);
  background: var(--naas-surface, #fff);
  box-shadow: var(--naas-shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.08));
  overflow: hidden;
  cursor: pointer;
  color: inherit;
  transition: border-color 0.18s ease, box-shadow 0.18s ease,
    transform 0.18s ease;
}


.browse-card:hover,
.browse-card:focus-visible {
  border-color: var(--naas-primary, #0f6cbf);
  box-shadow: 0 8px 20px rgba(15, 108, 191, 0.16);
  transform: translateY(-2px);
  outline: none;
}

.browse-card--skel:hover,
.browse-card--skel:focus-visible {
  border-color: var(--naas-border, #dee2e6);
  box-shadow: var(--naas-shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.08));
  transform: none;
}

.browse-card-media {
  position: relative;
  display: block;
  width: 100%;
  aspect-ratio: 16 / 9;
  flex: 0 0 auto;
  overflow: hidden;
  background: var(--naas-surface-muted, #f8f9fa);
}

.browse-card-media--all {
  display: flex;
  align-items: center;
  justify-content: center;
  background: #e8f1fb;
  color: var(--naas-primary, #0f6cbf);
}

.browse-card-media--all .icon {
  margin: 0;
  font-size: 2.2rem;
}

.browse-card-image {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

.browse-card-media--has-logo {
  background: #14171b;
}

.browse-card-media--has-logo .browse-card-image {
  filter: brightness(0.62);
}

.browse-card-image--empty {
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  color: var(--naas-primary, #0f6cbf);
  letter-spacing: 0.04em;
}

.browse-card-scrim {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.46);
  pointer-events: none;
}

.browse-card-logo {
  position: absolute;
  left: 50%;
  top: 50%;
  width: 76%;
  height: 64%;
  object-fit: contain;
  transform: translate(-50%, -50%);
  pointer-events: none;
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.65))
    drop-shadow(0 6px 14px rgba(0, 0, 0, 0.35));
}

.browse-card-body {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  box-sizing: border-box;
  height: 5.6rem;
  flex: 0 0 5.6rem;
  padding: 0.8rem 0.9rem 0.85rem;
  overflow: hidden;
}

.browse-card-title {
  display: block;
  font-weight: 800;
  font-size: 0.95rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--naas-text, #1f2937);
  line-height: 1.3;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.browse-card-name {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--naas-text, #1f2937);
  line-height: 1.35;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.browse-card-subtitle,
.browse-card-meta {
  font-size: 0.8rem;
  color: var(--naas-text-muted, #6c757d);
  line-height: 1.35;
}

.browse-card--skel {
  cursor: default;
  pointer-events: none;
}

.browse-card-media--skel,
.browse-skel-line {
  background: linear-gradient(90deg, #ececec 25%, #f7f7f7 50%, #ececec 75%);
  background-size: 1400px 100%;
  animation: browse-shimmer 1.5s infinite linear;
}

.browse-skel-line {
  display: block;
  height: 0.85rem;
  border-radius: 4px;
}

.browse-skel-line--title {
  width: 72%;
}

.browse-skel-line--meta {
  width: 46%;
  height: 0.7rem;
}

.browse-empty {
  grid-column: 1 / -1;
  margin: 0;
  color: var(--naas-text-muted, #6c757d);
  font-size: 0.9rem;
}

@keyframes browse-shimmer {
  0% {
    background-position: -700px 0;
  }
  100% {
    background-position: 700px 0;
  }
}
</style>
