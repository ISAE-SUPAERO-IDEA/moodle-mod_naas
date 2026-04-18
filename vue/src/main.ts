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
 * Entry point for the NaaS Vue 3 widget.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { createApp } from 'vue'
import Main from '@/Main.vue'
import { naasApiPlugin } from '@/plugins/naas-api.plugin'
import { createNaasI18n } from '@/plugins/i18n'

const config = window.NAAS

const app = createApp(Main)

app.use(naasApiPlugin)
app.use(createNaasI18n(config))

// Provide config globally so all components can inject it without prop-drilling.
app.provide('naasConfig', config)

app.mount(config.mount_point)
