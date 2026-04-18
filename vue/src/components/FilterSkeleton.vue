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
 * Skeleton placeholder for the filter panel while aggregations are loading.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div class="filter-skeleton" aria-hidden="true">
    <div v-for="n in 4" :key="n" class="skel-group">
      <div class="skel-line skel-heading" />
      <div class="skel-line skel-badge" />
      <div class="skel-line skel-badge skel-badge--short" />
      <div class="skel-line skel-badge" />
    </div>
  </div>
</template>

<style scoped>
@keyframes shimmer {
  0%   { background-position: -400px 0; }
  100% { background-position: 400px 0; }
}

.skel-line {
  background: linear-gradient(90deg, #e8e8e8 25%, #f5f5f5 50%, #e8e8e8 75%);
  background-size: 800px 100%;
  animation: shimmer 1.4s infinite linear;
  border-radius: 4px;
  margin-bottom: 6px;
}

.filter-skeleton {
  padding: 10px;
  width: 200px;
}

.skel-group {
  margin-bottom: 16px;
}

.skel-heading       { height: 14px; width: 80%; margin-bottom: 10px; }
.skel-badge         { height: 20px; width: 90%; border-radius: 50px; }
.skel-badge--short  { width: 60%; }
</style>
