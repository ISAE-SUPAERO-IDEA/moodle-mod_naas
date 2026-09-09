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
 * Composable that enriches raw Nugget objects with authors_data and domains_data
 * by fetching related entities in parallel via the Moodle webservice.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { useMoodleService } from './useMoodleService'
import { useNaasConfig } from './useNaasConfig'
import type { Nugget } from '@/types/nugget.types'

export function useNuggetEnricher() {
  const service = useMoodleService()
  const config = useNaasConfig()

  async function enrich(nugget: Nugget): Promise<Nugget> {
    const [authorsData, domainsData] = await Promise.all([
      Promise.all(
        (nugget.authors ?? []).map((key) =>
          service.getPerson(key, config.courseId).catch(() => null)
        )
      ),
      Promise.all(
        (nugget.domains ?? []).map((key) =>
          service.getDomain(key, config.courseId).catch(() => null)
        )
      ),
    ])

    return {
      ...nugget,
      authors_data: authorsData.filter(Boolean) as Nugget['authors_data'],
      domains_data: domainsData.filter(Boolean) as Nugget['domains_data'],
    }
  }

  async function enrichMany(nuggets: Nugget[]): Promise<Nugget[]> {
    return Promise.all(nuggets.map(enrich))
  }

  return { enrich, enrichMany }
}
