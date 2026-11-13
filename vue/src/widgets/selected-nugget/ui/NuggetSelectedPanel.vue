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
 * The Nugget chosen in the search widget, with its Replace button. Moodle's
 * CGU checkbox is parked under the card while it is shown.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div ref="wrap" class="selected-nugget-wrap">
    <div class="selected-nugget-inner">
      <div v-if="loading" class="selected-nugget-loading">
        <NuggetLoadingBanner />
        <NuggetSkeleton />
      </div>
      <div v-if="!loading && nugget">
        <NuggetPost
          :nugget="nugget"
          replaceable
          @replace-button="emit('replace')"
          @preview-button="showPreview = true"
        />
        <NuggetViewModal
          :visible="showPreview"
          :nugget="nugget"
          @close="showPreview = false"
        />
        <div ref="cguAnchor" class="naas-cgu-anchor"></div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from "vue";
import { NuggetPost, NuggetSkeleton } from "@/entities/nugget";
import { NuggetViewModal } from "@/features/preview-nugget";
import { NuggetLoadingBanner } from "@/shared/ui";
import { placeCguAgreement, restoreCguAgreement } from "@/features/pick-nugget";
import type { Nugget } from "@/shared/api";

const props = defineProps<{
  /** The chosen Nugget; null while it is still being fetched. */
  nugget: Nugget | null;
  /** The saved Nugget is being reloaded: a skeleton stands in for the card. */
  loading: boolean;
}>();

const emit = defineEmits<{
  (e: "replace"): void;
}>();

const cguAnchor = ref<HTMLElement | null>(null);
const wrap = ref<HTMLElement | null>(null);
const showPreview = ref(false);

/**
 * Move the keyboard focus onto the chosen card (its first control, Replace),
 * since the Select button the teacher pressed has left the page.
 */
function focusCard(): void {
  wrap.value?.querySelector<HTMLElement>("button")?.focus();
}

defineExpose({ focusCard });

// Put the CGU row back before the card (and its anchor) leaves the page.
// The card takes its preview with it.
watch(
  () => [props.nugget, props.loading] as const,
  ([nugget, loading]) => {
    if (!nugget || loading) {
      restoreCguAgreement();
      showPreview.value = false;
    }
  },
  { flush: "pre" }
);

watch(
  () => [props.nugget, props.loading] as const,
  async ([nugget, loading]) => {
    if (!nugget || loading) {
      return;
    }
    await nextTick();
    placeCguAgreement(cguAnchor.value);
  },
  { immediate: true }
);

onBeforeUnmount(() => {
  restoreCguAgreement();
});
</script>

<style scoped>
.selected-nugget-wrap {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 75px;
}

.selected-nugget-inner {
  width: 75%;
}

.naas-cgu-anchor {
  margin-top: 0.85rem;
}
</style>
