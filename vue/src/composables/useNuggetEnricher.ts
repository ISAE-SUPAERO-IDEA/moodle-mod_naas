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
 * Shared author and domain keys on a page are resolved once.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { useMoodleService } from './useMoodleService'
import { useNaasConfig } from './useNaasConfig'
import type { Nugget } from '@/types/nugget.types'
import type { Person } from '@/types/person.types'
import type { Domain } from '@/types/domain.types'

export function useNuggetEnricher() {
  const service = useMoodleService()
  const config = useNaasConfig()

  async function enrichMany(nuggets: Nugget[]): Promise<Nugget[]> {
    const personKeys = [...new Set(nuggets.flatMap((nugget) => nugget.authors ?? []).filter(Boolean))]
    const domainKeys = [...new Set(nuggets.flatMap((nugget) => nugget.domains ?? []).filter(Boolean))]

    const [people, domains] = await Promise.all([
      Promise.all(
        personKeys.map((key) =>
          service.getPerson(key, config.courseId)
            .then((person) => [key, person] as const)
            .catch(() => [key, null] as const)
        )
      ),
      Promise.all(
        domainKeys.map((key) =>
          service.getDomain(key, config.courseId)
            .then((domain) => [key, domain] as const)
            .catch(() => [key, null] as const)
        )
      ),
    ])

    const peopleByKey = new Map<string, Person>()
    for (const [key, person] of people) {
      if (person) {
        peopleByKey.set(key, person)
      }
    }

    const domainsByKey = new Map<string, Domain>()
    for (const [key, domain] of domains) {
      if (domain) {
        domainsByKey.set(key, domain)
      }
    }

    return nuggets.map((nugget) => ({
      ...nugget,
      authors_data: (nugget.authors ?? [])
        .map((key) => peopleByKey.get(key))
        .filter(Boolean) as Person[],
      domains_data: (nugget.domains ?? [])
        .map((key) => domainsByKey.get(key))
        .filter(Boolean) as Domain[],
    }))
  }

  async function enrich(nugget: Nugget): Promise<Nugget> {
    const [result] = await enrichMany([nugget])
    return result
  }

  return { enrich, enrichMany }
}
