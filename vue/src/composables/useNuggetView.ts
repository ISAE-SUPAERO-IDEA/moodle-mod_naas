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
 * Composable for the NuggetView mode: calls mod_naas_view_nugget (which records
 * the Moodle activity view event) then enriches the nugget with related entities.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { ref, onMounted } from 'vue'
import { useMoodleService } from './useMoodleService'
import { useNaasConfig } from './useNaasConfig'
import { useNuggetEnricher } from './useNuggetEnricher'
import type { Nugget } from '@/types/nugget.types'

export function useNuggetView() {
  const service = useMoodleService()
  const config = useNaasConfig()
  const { enrich } = useNuggetEnricher()

  const nugget = ref<Nugget | null>(null)
  const loading = ref(true)
  const error = ref<string | null>(null)

  async function load() {
    console.log('[NaaS] useNuggetView.load() called, cm_id=', config.cm_id)
    try {
      loading.value = true
      error.value = null
      const raw = await service.viewNugget(config.cm_id)
      console.log('[NaaS] viewNugget response:', raw)
      nugget.value = await enrich(raw)
    } catch (e) {
      const msg = e instanceof Error ? e.message : String(e)
      console.error('[NaaS] useNuggetView.load() error:', e)
      error.value = msg
    } finally {
      loading.value = false
    }
  }

  onMounted(() => {
    console.log('[NaaS] NuggetView onMounted — starting load')
    load()
  })

  return { nugget, loading, error, load }
}
