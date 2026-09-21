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
 * Pure helpers for search facet aggregations.
 * Labels for authors / producers / domains are resolved lazily by the filter UI.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {
  AggregationBucket,
  AggregationResult,
} from "@/types/nugget.types";
import type { LicenseFilterConfig } from "@/types/naas-config.types";

/** Facets whose bucket keys are entity ids and need a vocabulary lookup. */
export const NETWORK_LABEL_FACETS = new Set([
  "related_domains",
  "producers",
  "authors",
]);

export const AUTHOR_PREVIEW_LIMIT = 6;

export function facetNeedsNetworkLabels(name: string): boolean {
  return NETWORK_LABEL_FACETS.has(name);
}

export function captionForBucket(label: string, docCount: number): string {
  return `${label} (${docCount})`;
}

export interface MappedAggregation {
  name: string;
  buckets: AggregationBucket[];
  visible: boolean;
  showAll: boolean;
  labelsResolved: boolean;
}

/**
 * Map one aggregation's raw buckets into UI rows. `labelForKey` is synchronous:
 * local lang strings, a previously resolved caption, or the raw key.
 */
export function mapAggregationBuckets(
  name: string,
  buckets: Array<{ key: string; docCount?: number; doc_count?: number }>,
  selectedKeys: string[],
  labelForKey: (key: string) => string,
  preserved?: Pick<MappedAggregation, "visible" | "showAll" | "labelsResolved">
): MappedAggregation {
  const mapped = buckets.map((b) => {
    const docCount =
      typeof b.docCount === "number" ? b.docCount : Number(b.doc_count ?? 0);
    const label = labelForKey(b.key);
    return {
      key: b.key,
      docCount,
      selected: selectedKeys.includes(b.key),
      caption: captionForBucket(label, docCount),
      help: label,
      query_value: b.key,
      children: {} as Record<string, AggregationBucket>,
    };
  });

  mapped.sort((a, b) => (a.caption ?? "").localeCompare(b.caption ?? ""));

  return {
    name,
    buckets: mapped,
    visible: preserved?.visible ?? false,
    showAll: preserved?.showAll ?? false,
    labelsResolved: preserved?.labelsResolved ?? !facetNeedsNetworkLabels(name),
  };
}

export function visibleBuckets(
  aggregation: MappedAggregation
): AggregationBucket[] {
  if (aggregation.name !== "authors" || aggregation.showAll) {
    return aggregation.buckets;
  }
  return aggregation.buckets.slice(0, AUTHOR_PREVIEW_LIMIT);
}

export function hasMoreAuthors(aggregation: MappedAggregation): boolean {
  return (
    aggregation.name === "authors" &&
    aggregation.buckets.length > AUTHOR_PREVIEW_LIMIT
  );
}

export function applyResolvedLabels(
  aggregation: MappedAggregation,
  labels: Record<string, string>,
  titles: Record<string, string> = {}
): MappedAggregation {
  const buckets = aggregation.buckets.map((bucket) => {
    const label = labels[bucket.key] ?? bucket.help ?? bucket.key;
    return {
      ...bucket,
      help: label,
      title: titles[bucket.key] || bucket.title,
      caption: captionForBucket(label, bucket.docCount),
    };
  });
  buckets.sort((a, b) => (a.caption ?? "").localeCompare(b.caption ?? ""));
  return { ...aggregation, buckets, labelsResolved: true };
}

export type NetworkAggregations = Record<string, AggregationResult | undefined>;

export function selectedKeysFor(
  activeFilters: Record<string, string[]>,
  aggName: string
): string[] {
  return activeFilters[aggName] ?? [];
}

/**
 * Site-level commercial / distribution filters replace the CC licence facet.
 */
export function siteRightsFilterActive(
  filter?: LicenseFilterConfig | null
): boolean {
  if (!filter) {
    return false;
  }
  return (
    (filter.commercial ?? "all") !== "all" || (filter.access ?? "all") !== "all"
  );
}

