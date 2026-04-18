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
    <div class="skel-line skel-title" />
    <div class="skel-line skel-author" />
    <div class="skel-line skel-desc" />
    <div class="skel-line skel-desc skel-desc--short" />
  </div>
</template>

<style scoped>
.nugget-skeleton {
  border-radius: var(--naas-radius, 6px);
  box-shadow: var(--naas-shadow-sm, 0 2px 6px rgba(0, 0, 0, 0.12));
  padding: 10px;
  margin: 10px 10px 25px 10px;
  overflow: hidden;
}

@keyframes shimmer {
  0%   { background-position: -400px 0; }
  100% { background-position: 400px 0; }
}

.skel-thumb,
.skel-line {
  background: linear-gradient(90deg, #e8e8e8 25%, #f5f5f5 50%, #e8e8e8 75%);
  background-size: 800px 100%;
  animation: shimmer 1.4s infinite linear;
  border-radius: 4px;
}

.skel-thumb {
  width: 100%;
  aspect-ratio: 16 / 9;
  margin-bottom: 10px;
}

.skel-line {
  height: 14px;
  margin-bottom: 8px;
}

.skel-title  { width: 70%; height: 18px; }
.skel-author { width: 45%; }
.skel-desc   { width: 90%; }
.skel-desc--short { width: 60%; }
</style>
