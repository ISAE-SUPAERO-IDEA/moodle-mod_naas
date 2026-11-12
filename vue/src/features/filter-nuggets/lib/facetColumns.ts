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
 * Pure helpers for the facet filter panel: column building, selection state
 * and option captions. Used by NuggetSearchFilter and FilterFacetColumn.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type { AggregationBucket } from "@/shared/api";
import {
  aggregationFor,
  facetNeedsNetworkLabels,
  mapAggregationBuckets,
  selectedKeysFor,
  type MappedAggregation,
  type NetworkAggregations,
} from "./searchAggregations";
import { isRawEntityLabel } from "@/shared/lib";

/** Display order of the facet columns; facets NaaS does not return are skipped. */
export const FACET_ORDER = [
  "related_domains",
  "level",
  "language",
  "producers",
  "authors",
  "references",
  "type",
] as const;

/** Synchronous label lookups used while building the columns. */
export interface FacetLabelLookup {
  /** Best label known locally for a bucket key (may still be the raw key). */
  localLabel: (aggName: string, key: string) => string;
  /** True when the local label is a human-readable name. */
  hasHumanLabel: (aggName: string, key: string) => boolean;
}

/**
 * Build the facet columns from the search aggregations, in FACET_ORDER.
 *
 * The open / "show all" state of a column present in `previous` is kept.
 */
export function buildFacetColumns(
  networkAggregations: NetworkAggregations,
  activeFilters: Record<string, string[]>,
  previous: MappedAggregation[],
  lookup: FacetLabelLookup
): MappedAggregation[] {
  const prior = new Map(previous.map((agg) => [agg.name, agg]));
  const next: MappedAggregation[] = [];

  for (const name of FACET_ORDER) {
    const raw = aggregationFor(networkAggregations, name);
    if (!raw?.buckets?.length) continue;
    const kept = prior.get(name);
    next.push(
      mapAggregationBuckets(
        name,
        raw.buckets,
        selectedKeysFor(activeFilters, name),
        (key) => lookup.localLabel(name, key),
        {
          visible: kept?.visible ?? false,
          showAll: kept?.showAll ?? false,
          labelsResolved:
            !facetNeedsNetworkLabels(name) ||
            raw.buckets.every((b) => lookup.hasHumanLabel(name, b.key)),
        }
      )
    );
  }

  return next;
}

export function findFacet(
  aggregations: MappedAggregation[],
  name: string
): MappedAggregation | undefined {
  return aggregations.find((agg) => agg.name === name);
}

/** Checkbox caption; an entity whose name is not known yet shows "…". */
export function optionCaption(
  bucket: Pick<AggregationBucket, "key" | "help" | "caption" | "docCount">,
  showCount = true
): string {
  const label = bucket.help ?? "";
  if (!isRawEntityLabel(label, bucket.key)) {
    return showCount ? bucket.caption || label : label;
  }
  return showCount && bucket.docCount ? `… (${bucket.docCount})` : "…";
}

export function hasSelectedBuckets(agg: MappedAggregation): boolean {
  return agg.buckets.some((b) => b.selected);
}

export function selectedBucketCount(agg: MappedAggregation): number {
  return agg.buckets.filter((b) => b.selected).length;
}

export function hasAnySelection(aggregations: MappedAggregation[]): boolean {
  return aggregations.some(hasSelectedBuckets);
}

/** Selected keys per facet, plus the caption of each selected key for the chips. */
export function collectSelection(aggregations: MappedAggregation[]): {
  filters: Record<string, string[]>;
  captions: Record<string, string>;
} {
  const filters: Record<string, string[]> = {};
  const captions: Record<string, string> = {};
  for (const agg of aggregations) {
    for (const bucket of agg.buckets) {
      if (bucket.selected) {
        filters[agg.name] = [...(filters[agg.name] ?? []), bucket.key];
        captions[bucket.key] = bucket.help ?? bucket.caption ?? bucket.key;
      }
    }
  }
  return { filters, captions };
}

/** Tick exactly the buckets listed in `activeFilters` (mutates the columns). */
export function syncSelection(
  aggregations: MappedAggregation[],
  activeFilters: Record<string, string[]>
): void {
  for (const agg of aggregations) {
    const selected = selectedKeysFor(activeFilters, agg.name);
    for (const bucket of agg.buckets) {
      bucket.selected = selected.includes(bucket.key);
    }
  }
}

/** Untick every bucket (mutates the columns). */
export function clearSelection(aggregations: MappedAggregation[]): void {
  for (const agg of aggregations) {
    for (const bucket of agg.buckets) {
      bucket.selected = false;
    }
  }
}

/** Close every facet dropdown (mutates the columns). */
export function closeFacets(aggregations: MappedAggregation[]): void {
  for (const agg of aggregations) {
    agg.visible = false;
  }
}
