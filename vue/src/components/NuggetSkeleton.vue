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
  0%   { background-position: -600px 0; }
  100% { background-position: 600px 0; }
}

.nugget-skeleton {
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: var(--naas-radius, 6px);
  box-shadow: var(--naas-shadow-sm, 0 2px 8px rgba(0, 0, 0, 0.10));
  overflow: hidden;
  height: 100%;
}

.skel-thumb {
  width: 100%;
  aspect-ratio: 16 / 9;
  background: linear-gradient(90deg, #e8e8e8 25%, #f5f5f5 50%, #e8e8e8 75%);
  background-size: 1200px 100%;
  animation: shimmer 1.4s infinite linear;
}

.skel-body {
  flex: 1;
  padding: 0.875rem 1rem 0.5rem;
}

.skel-footer {
  display: flex;
  gap: 0.4rem;
  padding: 0.625rem 1rem;
  border-top: 1px solid #f0f2f5;
  background: #fafafa;
}

.skel-line,
.skel-btn {
  background: linear-gradient(90deg, #e8e8e8 25%, #f5f5f5 50%, #e8e8e8 75%);
  background-size: 1200px 100%;
  animation: shimmer 1.4s infinite linear;
  border-radius: 4px;
  margin-bottom: 8px;
}

.skel-title  { height: 16px; width: 75%; margin-bottom: 10px; }
.skel-author { height: 12px; width: 50%; margin-bottom: 10px; }
.skel-desc   { height: 12px; width: 95%; }
.skel-desc--short { width: 65%; margin-bottom: 0; }

.skel-btn {
  flex: 1;
  height: 28px;
  border-radius: var(--naas-radius, 6px);
  margin-bottom: 0;
}
</style>
