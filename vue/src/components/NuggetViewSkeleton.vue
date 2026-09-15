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
 * Skeleton placeholder for the NuggetView iframe area while the nugget loads.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div class="nugget-view-skeleton" aria-hidden="true">
    <div class="skel-toolbar">
      <div class="skel-btn" />
      <div class="skel-select" />
    </div>
    <div class="skel-iframe" />
  </div>
</template>

<style scoped>
@keyframes shimmer {
  0%   { background-position: -800px 0; }
  100% { background-position: 800px 0; }
}

.skel-toolbar,
.skel-btn,
.skel-select,
.skel-iframe {
  background: linear-gradient(90deg, #e8e8e8 25%, #f5f5f5 50%, #e8e8e8 75%);
  background-size: 1600px 100%;
  animation: shimmer 1.4s infinite linear;
  border-radius: var(--naas-radius, 6px);
}

.nugget-view-skeleton {
  width: 100%;
}

.skel-toolbar {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  background: none;
  animation: none;
}

.skel-btn    { width: 100px; height: 36px; }
.skel-select { width: 140px; height: 36px; }

.skel-iframe {
  width: 100%;
  height: 600px;
}
</style>
