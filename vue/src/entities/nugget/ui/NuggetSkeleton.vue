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
 * Skeleton placeholder card — mirrors the NuggetPost layout to prevent layout shift.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div
    class="nugget-skeleton"
    :class="{ 'nugget-skeleton--row': variant === 'row' }"
    :style="{ '--skel-delay': String(delay ?? 0) }"
    aria-hidden="true"
  >
    <div class="skel-thumb">
      <span class="skel-pill" />
    </div>
    <div class="skel-body">
      <span class="skel-line skel-line--title" />
      <span class="skel-line skel-line--title-short" />
      <span class="skel-line skel-line--meta" />
      <span class="skel-line skel-line--text" />
      <span class="skel-line skel-line--text-short" />
    </div>
    <div class="skel-footer">
      <span class="skel-btn skel-btn--primary" />
      <span class="skel-btn" />
      <span class="skel-btn" />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  variant?: "card" | "row";
  /** Staggers the sheen so a grid does not pulse in unison. */
  delay?: number;
}>();
</script>

<style scoped>
.nugget-skeleton {
  position: relative;
  display: flex;
  flex-direction: column;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  box-sizing: border-box;
  background: var(--naas-surface, #fff);
  border: 1.5px solid var(--naas-border, #dee2e6);
  border-radius: var(--naas-radius, 8px);
  box-shadow: var(--naas-shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.08));
  overflow: hidden;
  height: 100%;
}

.nugget-skeleton::after {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(
    105deg,
    transparent 0%,
    transparent 38%,
    rgba(255, 255, 255, 0.72) 50%,
    transparent 62%,
    transparent 100%
  );
  transform: translateX(-120%);
  animation: nugget-skeleton-sheen 1.6s ease-in-out infinite;
  animation-delay: calc(var(--skel-delay) * 0.12s);
  pointer-events: none;
  z-index: 1;
}

.skel-thumb {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
  flex-shrink: 0;
  background: linear-gradient(160deg, #e7eef6 0%, #d5e3f2 100%);
}

.skel-play {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 2.4rem;
  height: 2.4rem;
  margin: -1.2rem 0 0 -1.2rem;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.72);
  box-shadow: 0 2px 8px rgba(15, 108, 191, 0.12);
}

.skel-play::after {
  content: "";
  position: absolute;
  top: 50%;
  left: 54%;
  transform: translate(-50%, -50%);
  border-style: solid;
  border-width: 0.38rem 0 0.38rem 0.62rem;
  border-color: transparent transparent transparent #8eb4d8;
}

.skel-pill {
  position: absolute;
  left: 0.55rem;
  bottom: 0.55rem;
  width: 3.4rem;
  height: 1.05rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.55);
}

.skel-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  padding: 0.875rem 1rem 0.65rem;
}

.skel-line {
  display: block;
  height: 0.72rem;
  border-radius: 999px;
  background: #e6ebf1;
}

.skel-line--title {
  height: 0.85rem;
  width: 92%;
  background: #d5dee8;
}

.skel-line--title-short {
  height: 0.85rem;
  width: 64%;
  background: #d5dee8;
}

.skel-line--meta {
  width: 48%;
  margin-top: 0.15rem;
  background: #eef2f6;
}

.skel-line--text {
  width: 100%;
}

.skel-line--text-short {
  width: 78%;
}

.skel-footer {
  display: flex;
  gap: 0.35rem;
  padding: 0.6rem 0.875rem;
  border-top: 1px solid var(--naas-border-light, #e9ecef);
  background: var(--naas-surface-muted, #f8f9fa);
  flex-shrink: 0;
}

.skel-btn {
  flex: 1;
  height: 1.7rem;
  border-radius: var(--naas-radius, 8px);
  background: #e4e9ef;
}

.skel-btn--primary {
  background: #c5daf0;
}

.nugget-skeleton--row {
  flex-direction: row;
  min-height: 7.25rem;
  height: auto;
}

.nugget-skeleton--row .skel-thumb {
  flex: 0 1 13rem;
  width: 13rem;
  max-width: 38%;
  min-width: 0;
  height: auto;
}

.nugget-skeleton--row .skel-body {
  flex: 1 1 auto;
  min-width: 0;
}

.nugget-skeleton--row .skel-line--text,
.nugget-skeleton--row .skel-line--text-short {
  display: none;
}

.nugget-skeleton--row .skel-footer {
  flex-direction: column;
  width: 8.75rem;
  max-width: 30%;
  flex: 0 0 auto;
  border-top: none;
  border-left: 1px solid var(--naas-border-light, #e9ecef);
}

@keyframes nugget-skeleton-sheen {
  0% {
    transform: translateX(-120%);
  }
  100% {
    transform: translateX(120%);
  }
}

@media (prefers-reduced-motion: reduce) {
  .nugget-skeleton::after {
    animation: none;
  }
}
</style>
