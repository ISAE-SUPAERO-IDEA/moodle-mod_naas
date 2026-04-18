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
 * Vue plugin that registers the Moodle NaaS API adapter via provide/inject.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type { App } from 'vue'
import type { INaasApiService } from '../service/naas-api.interface'
import { moodleNaasApiService } from '../service/moodle-naas-api.service'

export const NAAS_API_KEY = Symbol('naasApi')

export const naasApiPlugin = {
  install(app: App) {
    app.provide(NAAS_API_KEY, moodleNaasApiService)
  },
}

export type { INaasApiService }
