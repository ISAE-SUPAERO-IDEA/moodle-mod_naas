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
 * TypeScript interface for the window.NAAS configuration object injected by Moodle.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {
  CatalogueVocabulary,
  CatalogueStamp,
  CatalogueFacetLabels,
  SearchResult,
} from "./nugget.types";

export type { CatalogueVocabulary };

export interface NaasLabelsMetadata {
  preview: string;
  description: string;
  in_brief: string;
  about_author: string;
  learning_outcomes: string;
  prerequisites: string;
  references: string;
  field_of_study: string;
  language: string;
  duration: string;
  level: string;
  advanced: string;
  intermediate: string;
  beginner: string;
  tags: string;
  producers: string;
  authors: string;
  related_domains: string;
  type: string;
  lesson: string;
  demo: string;
  tutorial: string;
  en: string;
  fr: string;
  de: string;
  es: string;
  it: string;
  pl: string;
  sv: string;
  publication_date: string;
  [key: string]: string;
}

export interface NaasLabels {
  nugget_search_here: string;
  nugget_search_no_result: string;
  nugget_search_collecting?: string;
  search: string;
  click_to_replace: string;
  clear_filters: string;
  show_more_authors: string;
  hide_authors: string;
  no_nugget: string;
  about: string;
  back_to_course: string;
  show_more_nugget_button: string;
  select_button: string;
  preview_button: string;
  loading: string;
  complete_nugget: string;
  next_unit: string;
  learning_outcomes_desc: string;
  error_generic_user_message: string;
  retry?: string;
  load_more?: string;
  active_filters?: string;
  open_access?: string;
  all_nuggets?: string;
  by_producers?: string;
  view_as_cards?: string;
  view_as_list?: string;
  browse_help?: string;
  back_to_catalogue?: string;
  checking_updates?: string;
  browse_nugget_count?: string;
  browse_no_producers?: string;
  rating: {
    title: string;
    send: string;
    sent: string;
    description: string;
  };
  metadata: NaasLabelsMetadata;
}

export interface CatalogueProducer {
  structure_id?: string;
  uuid?: string;
  uid?: string;
  id?: string;
  name?: string;
  acronym?: string;
  structure_thumbnail_url?: string;
  structure_banner_url?: string;
  /** Nugget count from the last landing aggregation, when known. */
  count?: number;
  /** Seen in the aggregations but not yet resolved to a real name/logo. */
  needs_resolve?: boolean;
  seen_at?: number;
}

export interface CatalogueSnapshot {
  warmed_at?: number;
  max_modification_date?: string;
  search?: SearchResult;
  producers?: CatalogueProducer[];
  producers_digest?: string;
  stamp?: CatalogueStamp | null;
  labels?: CatalogueFacetLabels;
}

export interface LicenseFilterConfig {
  commercial: "all" | "commercial" | "noncommercial";
  access: "all" | "unrestricted" | "restricted";
}

export interface NaasConfig {
  mount_point: string;
  component: "NuggetView" | "NuggetSearchWidget";
  moodle_url: string;
  naas_endpoint?: string;
  cm_id: number;
  courseId: number;
  nugget_id?: string;
  catalogue_snapshot?: CatalogueSnapshot | null;
  license_filter?: LicenseFilterConfig;
  labels: NaasLabels;
}

declare global {
  interface Window {
    NAAS: NaasConfig;
  }
}
