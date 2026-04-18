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
 * Service interface for NaaS API calls — implemented by the Moodle adapter.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type { Nugget, SearchOptions, SearchResult, XapiParams } from '../types/nugget.types'
import type { Person } from '../types/person.types'
import type { Domain } from '../types/domain.types'
import type { Structure } from '../types/structure.types'

export interface INaasApiService {
  getNugget(nuggetId: string, courseId: number): Promise<Nugget>
  viewNugget(cmId: number): Promise<Nugget>
  getPerson(personKey: string, courseId: number): Promise<Person>
  getDomain(domainKey: string, courseId: number): Promise<Domain>
  getStructure(structureKey: string, courseId: number): Promise<Structure>
  searchNuggets(searchOptions: SearchOptions, courseId: number): Promise<SearchResult>
  getNuggetPreview(versionId: string, courseId: number): Promise<string>
  postXapiStatement(params: XapiParams): Promise<void>
}
