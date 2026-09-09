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
 * vue-i18n setup — seeds messages from window.NAAS.labels so that
 * Moodle remains the single source of truth for all translated strings.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { createI18n } from 'vue-i18n'
import type { NaasConfig } from '../types/naas-config.types'

// Flattens NAAS.labels into a single-level map compatible with vue-i18n.
// metadata sub-keys are promoted to the top level, preserving the Vue 2
// mixin behaviour: this.$t('en') and this.$t('description') both resolved.
function buildMessages(labels: NaasConfig['labels']): Record<string, string> {
  const flat: Record<string, string> = {}

  for (const [k, v] of Object.entries(labels)) {
    if (k === 'metadata' || k === 'rating') continue
    if (typeof v === 'string') flat[k] = v
  }

  if (labels.metadata) {
    for (const [k, v] of Object.entries(labels.metadata)) {
      flat[k] = v as string
    }
  }

  if (labels.rating) {
    for (const [k, v] of Object.entries(labels.rating)) {
      flat[`rating.${k}`] = v as string
    }
  }

  return flat
}

export function createNaasI18n(config: NaasConfig) {
  const locale = (config as unknown as Record<string, string>).lang ?? 'en'
  const messages = buildMessages(config.labels)

  return createI18n({
    legacy: false,
    locale,
    fallbackLocale: 'en',
    messages: { [locale]: messages, en: messages },
    missing: (_locale: string, key: string) => key,
  })
}
