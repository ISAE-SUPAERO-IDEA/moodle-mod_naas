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

import type { Person } from "./person.types";
import type { Domain } from "./domain.types";

export interface NuggetLanguage {
  language: string;
}

export interface Nugget {
  nugget_id: string;
  name: string;
  resume: string;
  authors: string[];
  authors_data?: Person[];
  domains: string[];
  domains_data?: Domain[];
  domainsData?: Domain[];
  language: string;
  multilanguages: NuggetLanguage[];
  version_id: string;
  duration?: number;
  license?: string | number;
  is_public?: boolean | string | number;
  producers?: string[];
  level?: string;
  tags?: string[];
  references?: string[];
  learning_outcomes?: string[];
  prerequisites?: string[];
  publication_date?: string;
  modification_date?: string;
  nugget_thumbnail_url: string;
  displayinfo?: string;
  vocabulary?: CatalogueVocabulary;
  _cache?: NuggetCacheMeta;
}

/** Freshness of a cached Nugget document, per NUGGET_SELECTION_CACHE.md. */
export interface NuggetCacheMeta {
  hit: boolean;
  cached_at: number;
  digest: {
    modification_date: string;
    version_id: string;
  };
}

export interface AggregationBucket {
  key: string;
  docCount: number;
  selected?: boolean;
  caption?: string;
  help?: string;
  title?: string;
  query_value?: string;
  children?: Record<string, AggregationBucket>;
}

export interface AggregationResult {
  buckets: AggregationBucket[];
}

/**
 * Author and domain records denormalised server-side from the vocabulary MUC
 * and deduplicated across the hits, so a card paints without N+1 lookups.
 */
export interface CatalogueVocabulary {
  persons?: Record<string, Person>;
  domains?: Record<string, Domain>;
}

/**
 * Identity of a result page. The same query compared against itself at the
 * same page size moves this whenever a Nugget is added, removed or edited.
 */
export interface SearchFreshness {
  results_count: number;
  id_digest: string;
  max_modification_date: string;
}

export interface SearchCacheMeta {
  hit: boolean;
  cached_at: number;
  digest: SearchFreshness;
}

export interface SearchResult {
  items: Nugget[];
  results_count: number;
  aggregations: Record<string, AggregationResult>;
  vocabulary?: CatalogueVocabulary;
  _cache?: SearchCacheMeta;
}

/** cache_first serves a stored page; revalidate forces a live NaaS call. */
export type SearchMode = "cache_first" | "revalidate";

/**
 * Landing probe result. Costs one search hit and carries the whole producer
 * strip, because aggregations span the match set rather than the page.
 */
export interface CatalogueCheck {
  stamp?: CatalogueStamp | null;
  producers_digest: string;
  producers: unknown[];
  aggregations: Record<string, AggregationResult>;
  results_count: number;
  labels?: CatalogueFacetLabels;
  from_cache?: boolean;
}

/** Catalogue-wide freshness from the sorted page_size=1 probe. */
export interface CatalogueStamp {
  results_count: number;
  newest_modification_date: string;
}

export type CatalogueFacetLabels = {
  producers?: Record<string, string>;
  related_domains?: Record<string, string>;
  authors?: Record<string, string>;
  [facet: string]: Record<string, string> | undefined;
};

export interface SearchOptions {
  page?: number;
  page_size: number;
  fulltext?: string;
  related_domains?: string[];
  level?: string[];
  language?: string[];
  tags?: string[];
  producers?: string[];
  authors?: string[];
  references?: string[];
  type?: string[];
  [key: string]: unknown;
}

export interface XapiParams {
  id: number;
  verb: "experienced" | "completed" | "rated";
  version_id: string;
  body?: string;
}
