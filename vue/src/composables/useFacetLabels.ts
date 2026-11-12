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
 * Label resolution for the facet filter panel.
 *
 * Domain, producer and author buckets are keyed by entity ids. Their labels
 * come from the catalogue labels when known, otherwise they are fetched
 * through Moodle when the facet is opened (or prefetched) and kept for the
 * rest of the page.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { ref, type Ref } from "vue";
import type { CatalogueFacetLabels } from "@/types/nugget.types";
import { useNaasConfig } from "./useNaasConfig";
import { useEntityResolvers } from "./useEntityResolvers";
import {
  applyResolvedLabels,
  facetNeedsNetworkLabels,
  withFallbackLabels,
  type MappedAggregation,
} from "./searchAggregations";
import {
  isRawEntityLabel,
  lookupLabel,
  normalizePersonKey,
  normalizeStructureKey,
} from "./structureVisuals";
import { findFacet, hasSelectedBuckets } from "./facetColumns";

export interface FacetLabelOptions {
  /** The facet columns; resolved labels are written back into them. */
  aggregations: Ref<MappedAggregation[]>;
  /** Labels shipped with the catalogue (facet name → key → label). */
  networkLabels: () => CatalogueFacetLabels | undefined;
  /** Called when a facet with selected values got new labels. */
  onSelectedRelabel: () => void;
}

function normalizerFor(aggName: string): (value: string) => string {
  if (aggName === "producers") return normalizeStructureKey;
  if (aggName === "authors") return normalizePersonKey;
  return (value: string) => value;
}

export function useFacetLabels(options: FacetLabelOptions) {
  const { aggregations, networkLabels, onSelectedRelabel } = options;
  const config = useNaasConfig();
  const { getDomainLabel, getStructureVisuals, getPersonName } =
    useEntityResolvers();

  /** Labels fetched on this page: facet name → key → label. */
  const resolvedLabels = ref<Record<string, Record<string, string>>>({});
  const resolvingNames = new Set<string>();

  function localLabel(aggName: string, key: string): string {
    const normalize = normalizerFor(aggName);
    const known = networkLabels();
    const cached =
      lookupLabel(resolvedLabels.value[aggName], key, normalize) ||
      lookupLabel(known?.[aggName], key, normalize) ||
      (aggName === "related_domains"
        ? lookupLabel(known?.domains, key, normalize)
        : "");
    if (cached && !isRawEntityLabel(cached, key)) return cached;
    if (facetNeedsNetworkLabels(aggName)) return key;
    return config.labels.metadata[key] ?? key;
  }

  function hasHumanLabel(aggName: string, key: string): boolean {
    const label = localLabel(aggName, key);
    return !!label && !isRawEntityLabel(label, key);
  }

  /** Every label already known for a facet, resolved ones winning. */
  function knownLabels(aggName: string): Record<string, string> {
    const known = networkLabels();
    return {
      ...(known?.[aggName] ?? {}),
      ...(aggName === "related_domains" ? (known?.domains ?? {}) : {}),
      ...(resolvedLabels.value[aggName] ?? {}),
    };
  }

  /**
   * Fetch the labels of `keys` into `labels`.
   *
   * @returns Producer full names, used as option tooltips.
   */
  async function fetchLabels(
    aggName: string,
    keys: string[],
    labels: Record<string, string>
  ): Promise<Record<string, string>> {
    const titles: Record<string, string> = {};
    if (aggName === "producers") {
      const visuals = await Promise.all(
        keys.map(async (key) => [key, await getStructureVisuals(key)] as const)
      );
      for (const [key, visual] of visuals) {
        const label = visual.acronym || visual.name;
        if (label && !isRawEntityLabel(label, key)) {
          labels[key] = label;
        }
        if (visual.name && visual.name !== labels[key]) {
          titles[key] = visual.name;
        }
      }
      return titles;
    }
    const resolveKey =
      aggName === "related_domains" ? getDomainLabel : getPersonName;
    const entries = await Promise.all(
      keys.map(async (key) => [key, await resolveKey(key)] as const)
    );
    for (const [key, label] of entries) {
      if (label && !isRawEntityLabel(label, key)) {
        labels[key] = label;
      }
    }
    return titles;
  }

  /** Readable label for authors / producers NaaS cannot name. */
  function fallbackFor(aggName: string): string {
    if (aggName === "authors") return config.labels.unknown_author;
    if (aggName === "producers") return config.labels.unknown_producer;
    return "";
  }

  /** Fetch the missing labels of one entity facet and apply them. */
  async function resolveFacetLabels(agg: MappedAggregation): Promise<void> {
    if (
      !facetNeedsNetworkLabels(agg.name) ||
      agg.labelsResolved ||
      resolvingNames.has(agg.name)
    )
      return;
    resolvingNames.add(agg.name);
    try {
      const current = findFacet(aggregations.value, agg.name) ?? agg;
      const labels = knownLabels(agg.name);
      const keys = current.buckets
        .map((bucket) => bucket.key)
        .filter((key) => {
          const known = labels[key];
          return !known || isRawEntityLabel(known, key);
        });
      const titles = await fetchLabels(agg.name, keys, labels);
      // Unnamed keys get the fallback, kept for the rest of the page so they
      // are not requested again.
      const fallback = fallbackFor(agg.name);
      const named = fallback
        ? withFallbackLabels(labels, keys, fallback)
        : labels;
      resolvedLabels.value = { ...resolvedLabels.value, [agg.name]: named };
      aggregations.value = aggregations.value.map((item) =>
        item.name === agg.name ? applyResolvedLabels(item, named, titles) : item
      );
      const updated = findFacet(aggregations.value, agg.name);
      if (updated && hasSelectedBuckets(updated)) {
        onSelectedRelabel();
      }
    } finally {
      resolvingNames.delete(agg.name);
    }
  }

  return { resolvedLabels, localLabel, hasHumanLabel, resolveFacetLabels };
}
