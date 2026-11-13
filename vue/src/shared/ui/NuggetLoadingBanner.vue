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
 * "We are collecting nugget data" status line with its spinner, shown while
 * the search widget waits for NaaS.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <p class="nugget-loading-banner" role="status" aria-live="polite">
    <span class="nugget-loading-spinner" aria-hidden="true" />
    {{
      config.labels.nugget_search_collecting ||
      "We are collecting nugget data, please wait."
    }}
  </p>
</template>

<script setup lang="ts">
import { useNaasConfig } from "../config";

const config = useNaasConfig();
</script>

<style scoped>
.nugget-loading-banner {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  margin: 0 0 1rem;
  padding: 0.7rem 0.95rem;
  border: 1px solid rgba(15, 108, 191, 0.18);
  border-radius: var(--naas-radius, 8px);
  background: var(--naas-primary-light, #dce9fa);
  color: var(--naas-primary-dark, #0a4a8f);
  font-size: 0.9rem;
  font-weight: 600;
  line-height: 1.35;
}

.nugget-loading-spinner {
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
  border: 2px solid rgba(15, 108, 191, 0.25);
  border-top-color: var(--naas-primary, #0f6cbf);
  border-radius: 50%;
  animation: nugget-loading-spin 0.7s linear infinite;
}

@keyframes nugget-loading-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .nugget-loading-spinner {
    animation: none;
    border-top-color: rgba(15, 108, 191, 0.25);
    background: var(--naas-primary, #0f6cbf);
  }
}
</style>
