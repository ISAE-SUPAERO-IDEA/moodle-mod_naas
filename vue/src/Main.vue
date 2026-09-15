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
 * Root component — renders NuggetView or NuggetSearchWidget based on NAAS.component.
 * Static imports are intentional: the IIFE bundle inlines everything anyway, so
 * defineAsyncComponent would only add an async tick with no visual benefit.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div>
    <NuggetView v-if="config.component === 'NuggetView'" />
    <NuggetSearchWidget v-else />
  </div>
</template>

<script setup lang="ts">
import NuggetView from '@/components/NuggetView.vue'
import NuggetSearchWidget from '@/components/NuggetSearchWidget.vue'
import { useNaasConfig } from '@/composables/useNaasConfig'

const config = useNaasConfig()
</script>
