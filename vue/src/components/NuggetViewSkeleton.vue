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
      <VSkeletonLoader class="skel-btn" type="button" />
      <VSkeletonLoader class="skel-select" type="button" />
    </div>
    <VSkeletonLoader class="skel-iframe" type="image" />
  </div>
</template>

<style scoped>
.nugget-view-skeleton {
  width: 100%;
}

.skel-toolbar {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.skel-btn {
  width: 100px;
  height: 36px;
}

.skel-select {
  width: 140px;
  height: 36px;
}

.skel-iframe {
  width: 100%;
  height: 600px;
}

.skel-iframe :deep(.v-skeleton-loader__image),
.skel-iframe :deep(.v-skeleton-loader__bone) {
  height: 600px;
}
</style>
