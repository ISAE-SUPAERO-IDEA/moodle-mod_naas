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
  <div class="nugget-skeleton" aria-hidden="true">
    <div class="skel-thumb" />
    <div class="skel-body">
      <div class="skel-line skel-title" />
      <div class="skel-line skel-author" />
      <div class="skel-line skel-desc" />
      <div class="skel-line skel-desc skel-desc--short" />
    </div>
    <div class="skel-footer">
      <div class="skel-btn" />
      <div class="skel-btn" />
      <div class="skel-btn" />
    </div>
  </div>
</template>

<style scoped>
@keyframes shimmer {
  0%   { background-position: -700px 0; }
  100% { background-position: 700px 0; }
}

.nugget-skeleton {
  display: flex;
  flex-direction: column;
  background: var(--naas-surface, #fff);
  border-radius: var(--naas-radius, 8px);
  box-shadow: var(--naas-shadow-sm, 0 2px 8px rgba(0,0,0,.10));
  overflow: hidden;
  height: 100%;
}

.skel-thumb {
  width: 100%;
  aspect-ratio: 16 / 9;
  background: linear-gradient(90deg, #ececec 25%, #f7f7f7 50%, #ececec 75%);
  background-size: 1400px 100%;
  animation: shimmer 1.5s infinite linear;
  flex-shrink: 0;
}

.skel-body {
  flex: 1;
  padding: 0.875rem 1rem 0.5rem;
}

.skel-footer {
  display: flex;
  gap: 0.35rem;
  padding: 0.6rem 0.875rem;
  border-top: 1px solid var(--naas-border-light, #e9ecef);
  background: var(--naas-surface-muted, #f8f9fa);
  flex-shrink: 0;
}

.skel-line,
.skel-btn {
  background: linear-gradient(90deg, #ececec 25%, #f7f7f7 50%, #ececec 75%);
  background-size: 1400px 100%;
  animation: shimmer 1.5s infinite linear;
  border-radius: 4px;
  margin-bottom: 8px;
}

/* Stagger lines so they pulse offset from each other */
.skel-title        { height: 15px; width: 72%; margin-bottom: 10px; animation-delay: 0s; }
.skel-author       { height: 11px; width: 48%; margin-bottom: 10px; animation-delay: 0.1s; }
.skel-desc         { height: 11px; width: 95%; animation-delay: 0.2s; }
.skel-desc--short  { width: 62%; margin-bottom: 0; animation-delay: 0.3s; }

.skel-btn {
  flex: 1;
  height: 27px;
  border-radius: var(--naas-radius, 8px);
  margin-bottom: 0;
  animation-delay: 0.15s;
}

.skel-btn:nth-child(2) { animation-delay: 0.25s; }
.skel-btn:nth-child(3) { animation-delay: 0.35s; }
</style>
