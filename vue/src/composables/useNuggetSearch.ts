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
 * Cards are painted as soon as search_nuggets returns. Author/domain names
 * are filled in afterwards so the grid is not blocked on N+1 vocabulary calls.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { ref } from 'vue'
import { useMoodleService } from './useMoodleService'
import { useNaasConfig } from './useNaasConfig'
import { useNuggetEnricher } from './useNuggetEnricher'
import type { Nugget, SearchOptions, SearchResult } from '@/types/nugget.types'

function mergeEnriched(current: Nugget[], enriched: Nugget[]): Nugget[] {
  const byId = new Map(enriched.map((nugget) => [nugget.nugget_id, nugget]))
  return current.map((nugget) => byId.get(nugget.nugget_id) ?? nugget)
}

export function useNuggetSearch(opts: { initialLoading?: boolean } = {}) {
  const service = useMoodleService()
  const config = useNaasConfig()
  const { enrichMany } = useNuggetEnricher()

  const nuggets = ref<Nugget[]>([])
  const searchResult = ref<SearchResult | null>(null)
  const loading = ref(opts.initialLoading ?? false)
  const loadingMore = ref(false)
  const error = ref<Error | null>(null)
  let searchGeneration = 0

  async function search(options: SearchOptions, append = false): Promise<SearchResult | null> {
    const generation = append ? searchGeneration : ++searchGeneration
    try {
      if (append) {
        loadingMore.value = true
      } else {
        loading.value = true
      }
      error.value = null
      const result = await service.searchNuggets(options, config.courseId)
      if (generation !== searchGeneration) {
        return null
      }
      const items = Array.isArray(result?.items) ? result.items : []
      if (append) {
        nuggets.value.push(...items)
      } else {
        nuggets.value = items
      }
      searchResult.value = { ...result, items }
      loading.value = false
      loadingMore.value = false

      void enrichMany(items)
        .then((enriched) => {
          if (generation !== searchGeneration) {
            return
          }
          nuggets.value = mergeEnriched(nuggets.value, enriched)
          if (searchResult.value) {
            searchResult.value = {
              ...searchResult.value,
              items: mergeEnriched(searchResult.value.items, enriched),
            }
          }
        })
        .catch(() => {
          // Cards are already visible; vocabulary labels stay as raw keys.
        })

      return searchResult.value
    } catch (e) {
      if (generation !== searchGeneration) {
        return null
      }
      error.value = e as Error
      return null
    } finally {
      if (generation === searchGeneration) {
        loading.value = false
        loadingMore.value = false
      }
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

  return { nuggets, searchResult, loading, loadingMore, error, search, getNuggetById }
}
