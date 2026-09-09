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
 * Helpers that resolve entity keys (domain, structure, person) to display strings.
 * Used by NuggetSearchFilter to build human-readable aggregation bucket labels.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { useMoodleService } from './useMoodleService'
import { useNaasConfig } from './useNaasConfig'

export function useEntityResolvers() {
  const service = useMoodleService()
  const config = useNaasConfig()

  async function getDomainLabel(key: string): Promise<string> {
    try {
      const domain = await service.getDomain(key, config.courseId)
      return domain?.label ?? key
    } catch {
      return key
    }
  }

  async function getStructureAcronym(key: string): Promise<string> {
    try {
      const structure = await service.getStructure(key, config.courseId)
      return structure?.acronym ?? key
    } catch {
      return key
    }
  }

  async function getPersonName(email: string): Promise<string> {
    try {
      const person = await service.getPerson(email, config.courseId)
      if (!person || (!person.firstname && !person.lastname)) return ''
      return `${person.firstname} ${person.lastname}`.toUpperCase()
    } catch {
      return email
    }
  }

  return { getDomainLabel, getStructureAcronym, getPersonName }
}
