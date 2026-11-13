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
 * Dialog asking for the 6-digit integration code of a Nugget before it is
 * selected, with the authors to ask for it. Teleported to body, like the other
 * widget dialogs: every token needs its fallback.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <Teleport to="body">
    <transition name="modal-fade">
      <div
        v-if="visible && nugget"
        class="nugget-modal-backdrop"
        @click.self="emit('cancel')"
      >
        <div
          ref="dialog"
          class="nugget-modal code-modal"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
          :aria-describedby="helpId"
          tabindex="-1"
        >
          <div class="nugget-modal-header">
            <h2 :id="titleId">
              <i class="icon fa fa-lock" aria-hidden="true" />
              {{ labels.title }}
            </h2>
            <button
              type="button"
              class="btn-close"
              :aria-label="labels.cancel"
              @click="emit('cancel')"
            >
              ✕
            </button>
          </div>

          <div class="nugget-modal-body">
            <p :id="helpId" class="code-help">{{ labels.help }}</p>
            <p class="code-nugget-name">{{ nugget.name }}</p>

            <form class="code-form" @submit.prevent="submit">
              <div
                class="code-digits"
                :class="{ 'code-digits--error': error }"
                role="group"
                :aria-label="labels.title"
              >
                <input
                  v-for="(digit, index) in digits"
                  :key="index"
                  ref="inputs"
                  class="code-digit"
                  type="text"
                  inputmode="numeric"
                  autocomplete="one-time-code"
                  maxlength="1"
                  :value="digit"
                  :aria-label="fillLabel(labels.digit, String(index + 1))"
                  :aria-invalid="error ? 'true' : 'false'"
                  @input="onInput(index, $event)"
                  @keydown="onKeydown(index, $event)"
                  @paste.prevent="onPaste"
                  @focus="selectDigit"
                />
              </div>
              <p v-if="error" class="code-error" role="alert">
                {{ labels.error }}
              </p>
              <div class="code-actions">
                <button
                  type="button"
                  class="btn btn-secondary"
                  @click="emit('cancel')"
                >
                  {{ labels.cancel }}
                </button>
                <button
                  type="submit"
                  class="btn btn-primary"
                  :disabled="code.length !== INTEGRATION_CODE_LENGTH"
                >
                  {{ labels.confirm }}
                </button>
              </div>
            </form>

            <section class="code-authors">
              <h3>{{ labels.authors }}</h3>
              <p>{{ labels.authors_help }}</p>
              <ul v-if="authors.length">
                <li v-for="(author, index) in authors" :key="index">
                  <strong>{{ author.firstname }} {{ author.lastname }}</strong>
                  <a
                    v-if="isEmail(author.email)"
                    :href="`mailto:${author.email}`"
                    >{{ author.email }}</a
                  >
                </li>
              </ul>
              <p v-else class="code-authors-empty">
                {{ labels.authors_empty }}
              </p>
            </section>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, useId, watch } from "vue";
import { useNaasConfig } from "@/shared/config";
import { fillLabel } from "@/shared/lib";
import { useModalA11y } from "@/shared/ui";
import {
  INTEGRATION_CODE_LENGTH,
  integrationCodeMatches,
} from "@/entities/nugget";
import type { Nugget, Person } from "@/shared/api";

const props = defineProps<{ nugget: Nugget | null; visible: boolean }>();
const emit = defineEmits<{
  (e: "confirm", code: string): void;
  (e: "cancel"): void;
}>();

const config = useNaasConfig();
const labels = computed(() => config.labels.integration_code);
const titleId = useId();
const helpId = useId();
const dialog = ref<HTMLElement | null>(null);
const inputs = ref<HTMLInputElement[]>([]);
const empty = () => Array<string>(INTEGRATION_CODE_LENGTH).fill("");
const digits = ref<string[]>(empty());
const error = ref(false);
const code = computed(() => digits.value.join(""));
const authors = computed<Person[]>(() =>
  (props.nugget?.authors_data ?? []).filter(Boolean)
);

useModalA11y(
  () => props.visible,
  dialog,
  () => emit("cancel")
);

/** NaaS may send a hashed email: only real addresses get a link. */
function isEmail(value: unknown): boolean {
  return typeof value === "string" && value.includes("@");
}

function focusDigit(index: number): void {
  inputs.value[index]?.focus();
}

function selectDigit(event: FocusEvent): void {
  (event.target as HTMLInputElement).select();
}

function reset(): void {
  digits.value = empty();
  error.value = false;
}

watch(
  () => props.visible,
  async (visible) => {
    reset();
    if (visible) {
      // After useModalA11y has put the focus on the dialog itself.
      await nextTick();
      await nextTick();
      focusDigit(0);
    }
  }
);

/** Write typed or pasted digits from one box on, then focus the box after them. */
function fill(from: number, value: string): void {
  const typed = value.replace(/\D/g, "").split("");
  const next = [...digits.value];
  typed.slice(0, INTEGRATION_CODE_LENGTH - from).forEach((digit, offset) => {
    next[from + offset] = digit;
  });
  digits.value = next;
  focusDigit(Math.min(from + typed.length, INTEGRATION_CODE_LENGTH - 1));
}

function onInput(index: number, event: Event): void {
  const target = event.target as HTMLInputElement;
  error.value = false;
  const value = target.value.replace(/\D/g, "");
  if (value === "") {
    digits.value[index] = "";
    target.value = "";
    return;
  }
  fill(index, value);
}

function onKeydown(index: number, event: KeyboardEvent): void {
  if (event.key === "Backspace" && !digits.value[index] && index > 0) {
    event.preventDefault();
    digits.value[index - 1] = "";
    focusDigit(index - 1);
  } else if (event.key === "ArrowLeft" && index > 0) {
    event.preventDefault();
    focusDigit(index - 1);
  } else if (
    event.key === "ArrowRight" &&
    index < INTEGRATION_CODE_LENGTH - 1
  ) {
    event.preventDefault();
    focusDigit(index + 1);
  }
}

function onPaste(event: ClipboardEvent): void {
  reset();
  fill(0, event.clipboardData?.getData("text") ?? "");
}

async function submit(): Promise<void> {
  // The confirm button stays disabled until every box holds a digit.
  /* v8 ignore next 3 -- @preserve */
  if (code.value.length !== INTEGRATION_CODE_LENGTH || !props.nugget) {
    return;
  }
  const typed = code.value;
  if ((await integrationCodeMatches(props.nugget.name, typed)) === false) {
    reset();
    error.value = true;
    await nextTick();
    focusDigit(0);
    return;
  }
  emit("confirm", typed);
}
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
  padding: 8vh 1rem 2rem;
  overflow-y: auto;
}

/* The dialog takes focus when it opens; no outline around the whole box. */
.nugget-modal:focus {
  outline: none;
}

.nugget-modal {
  width: min(32rem, 100%);
  margin: 0 auto;
  background: var(--naas-surface, #fff);
  color: var(--naas-text, #1f2937);
  box-shadow: var(--naas-shadow-lg, 0 12px 40px rgba(0, 0, 0, 0.18));
  border-radius: var(--naas-radius-xl, 16px);
  overflow: hidden;
}

.nugget-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.25rem 0.9rem 1.5rem;
  border-bottom: 1px solid var(--naas-border-light, #e9ecef);
}

.nugget-modal-header h2 {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  margin: 0;
  padding: 0;
  font-size: 1.15rem;
  font-weight: 700;
  line-height: 1.3;
  color: var(--naas-text, #1f2937);
}

.nugget-modal-header h2 .icon {
  margin: 0;
  color: var(--naas-primary, #0f6cbf);
}

.btn-close {
  flex-shrink: 0;
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

.nugget-modal-body {
  display: grid;
  gap: 1rem;
  padding: 1.25rem 1.5rem 1.5rem;
}

.code-help,
.code-nugget-name {
  margin: 0;
}

.code-help {
  font-size: 0.95rem;
  line-height: 1.5;
}

.code-nugget-name {
  padding: 0.6rem 0.8rem;
  border-radius: var(--naas-radius, 8px);
  background: var(--naas-surface-muted, #f8f9fa);
  font-weight: 700;
  overflow-wrap: anywhere;
}

.code-form {
  display: grid;
  gap: 0.75rem;
}

.code-digits {
  display: flex;
  justify-content: center;
  gap: 0.5rem;
}

.code-digit {
  width: 2.75rem;
  height: 3.25rem;
  padding: 0;
  border: 1.5px solid var(--naas-border, #dee2e6);
  border-radius: var(--naas-radius, 8px);
  background: var(--naas-surface, #fff);
  color: var(--naas-text, #1f2937);
  font-size: 1.4rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  text-align: center;
}

.code-digit:focus {
  outline: none;
  border-color: var(--naas-primary, #0f6cbf);
  box-shadow: 0 0 0 3px rgba(15, 108, 191, 0.25);
}

.code-digits--error .code-digit {
  border-color: #b42318;
}

.code-error {
  margin: 0;
  color: #b42318;
  font-size: 0.9rem;
  font-weight: 600;
  text-align: center;
}

.code-actions {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.code-authors {
  padding-top: 1rem;
  border-top: 1px solid var(--naas-border-light, #e9ecef);
  font-size: 0.875rem;
}

.code-authors h3 {
  margin: 0 0 0.35rem;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #5c636a; /* muted grey reaching 4.5:1 on white */
}

.code-authors p {
  margin: 0 0 0.5rem;
}

.code-authors ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.35rem;
}

.code-authors li {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem 0.6rem;
  overflow-wrap: anywhere;
}

.code-authors a {
  color: var(--naas-primary, #0f6cbf);
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.18s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .modal-fade-enter-active,
  .modal-fade-leave-active {
    transition: none;
  }
}

@media (max-width: 420px) {
  .code-digits {
    gap: 0.35rem;
  }
  .code-digit {
    width: 2.4rem;
    height: 2.9rem;
  }
}
</style>
