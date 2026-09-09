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
 * TypeScript interfaces for NaaS nugget entities and search payloads.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type { Person } from './person.types'
import type { Domain } from './domain.types'

export interface NuggetLanguage {
  language: string
}

export interface Nugget {
  nugget_id: string
  name: string
  resume: string
  authors: string[]
  authors_data?: Person[]
  domains: string[]
  domains_data?: Domain[]
  domainsData?: Domain[]
  language: string
  multilanguages: NuggetLanguage[]
  version_id: string
  duration?: number
  level?: string
  tags?: string[]
  references?: string[]
  learning_outcomes?: string[]
  prerequisites?: string[]
  publication_date?: string
  nugget_thumbnail_url: string
  displayinfo?: string
}

export interface AggregationBucket {
  key: string
  docCount: number
  selected?: boolean
  caption?: string
  help?: string
  query_value?: string
  children?: Record<string, AggregationBucket>
}

export interface AggregationResult {
  buckets: AggregationBucket[]
}

export interface SearchResult {
  items: Nugget[]
  results_count: number
  aggregations: Record<string, AggregationResult>
}

export interface SearchOptions {
  page?: number
  page_size: number
  fulltext?: string
  related_domains?: string[]
  level?: string[]
  language?: string[]
  tags?: string[]
  producers?: string[]
  authors?: string[]
  references?: string[]
  type?: string[]
  [key: string]: unknown
}

export interface XapiParams {
  id: number
  verb: 'experienced' | 'completed' | 'rated'
  version_id: string
  body?: string
}
