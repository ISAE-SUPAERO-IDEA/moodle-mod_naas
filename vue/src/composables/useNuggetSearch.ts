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
 * Composable that drives search queries for the NuggetSearchWidget and
 * NuggetSearchFilter components.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { ref } from 'vue'
import { useMoodleService } from './useMoodleService'
import { useNaasConfig } from './useNaasConfig'
import { useNuggetEnricher } from './useNuggetEnricher'
import type { Nugget, SearchOptions, SearchResult } from '@/types/nugget.types'

export function useNuggetSearch() {
  const service = useMoodleService()
  const config = useNaasConfig()
  const { enrichMany } = useNuggetEnricher()

  const nuggets = ref<Nugget[]>([])
  const searchResult = ref<SearchResult | null>(null)
  const loading = ref(false)
  const error = ref<Error | null>(null)

  async function search(options: SearchOptions): Promise<SearchResult | null> {
    try {
      loading.value = true
      error.value = null
      const result = await service.searchNuggets(options, config.courseId)
      const enriched = await enrichMany(result.items)
      nuggets.value = enriched
      searchResult.value = { ...result, items: enriched }
      return searchResult.value
    } catch (e) {
      error.value = e as Error
      return null
    } finally {
      loading.value = false
    }
  }

  async function getNuggetById(nuggetId: string): Promise<Nugget | null> {
    try {
      const raw = await service.getNugget(nuggetId, config.courseId)
      const [enriched] = await enrichMany([raw])
      return enriched
    } catch (e) {
      error.value = e as Error
      return null
    }
  }

  return { nuggets, searchResult, loading, error, search, getNuggetById }
}
