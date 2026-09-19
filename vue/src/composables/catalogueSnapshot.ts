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
 * Helpers for the catalogue snapshot injected by Moodle after Test connection.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { normalizeStructureKey } from "./structureVisuals";
import type {
  CatalogueProducer,
  CatalogueSnapshot,
  CatalogueVocabulary,
} from "@/types/naas-config.types";
import type {
  CatalogueFacetLabels,
  CatalogueStamp,
  Nugget,
  SearchResult,
} from "@/types/nugget.types";

const ID_FIELDS = ["structure_id", "uuid", "uid", "id"] as const;

export function matchCachedProducer(
  producers: CatalogueProducer[] | undefined,
  key: string
): CatalogueProducer | null {
  const needle = normalizeStructureKey(key).toLowerCase();
  if (!needle || !producers?.length) {
    return null;
  }
  for (const row of producers) {
    for (const field of ID_FIELDS) {
      const value = row[field];
      if (
        typeof value === "string" &&
        normalizeStructureKey(value).toLowerCase() === needle
      ) {
        return row;
      }
    }
  }
  return null;
}

/**
 * Join the denormalised vocabulary table onto the items' raw key arrays.
 *
 * A key with no entry in the table is dropped, exactly as `useNuggetEnricher`
 * drops an author it could not fetch. `isEnriched` then reports the item as
 * incomplete, so the widget falls back to resolving it over the wire.
 */
export function applyVocabulary(
  items: Nugget[],
  vocabulary: CatalogueVocabulary | undefined
): Nugget[] {
  const persons = vocabulary?.persons ?? {};
  const domains = vocabulary?.domains ?? {};
  if (!Object.keys(persons).length && !Object.keys(domains).length) {
    return items;
  }
  return items.map((item) => ({
    ...item,
    authors_data: (item.authors ?? [])
      .map((key) => persons[key])
      .filter(Boolean),
    domains_data: (item.domains ?? [])
      .map((key) => domains[key])
      .filter(Boolean),
  }));
}

/**
 * True when every author and domain key already has its resolved record, so
 * the card needs no further webservice call to paint completely.
 */
export function isEnriched(nugget: Nugget): boolean {
  const authors = nugget.authors ?? [];
  const domains = nugget.domains ?? [];
  return (
    Array.isArray(nugget.authors_data) &&
    nugget.authors_data.length === authors.length &&
    Array.isArray(nugget.domains_data) &&
    nugget.domains_data.length === domains.length
  );
}

export function snapshotSearch(
  snapshot: CatalogueSnapshot | null | undefined
): SearchResult | null {
  const search = snapshot?.search;
  if (!search || typeof search !== "object") {
    return null;
  }
  const items = Array.isArray(search.items) ? search.items : [];
  const aggregations =
    search.aggregations && typeof search.aggregations === "object"
      ? search.aggregations
      : {};
  return {
    items: applyVocabulary(items, search.vocabulary),
    aggregations,
    results_count: Number(search.results_count ?? 0),
  };
}

export function sameCatalogueStamp(
  a?: CatalogueStamp | null,
  b?: CatalogueStamp | null
): boolean {
  if (!a || !b) {
    return false;
  }
  if (!a.newest_modification_date || !b.newest_modification_date) {
    return false;
  }
  return (
    Number(a.results_count) === Number(b.results_count) &&
    a.newest_modification_date === b.newest_modification_date
  );
}

export function mergeFacetLabels(
  base: CatalogueFacetLabels | undefined,
  extra: CatalogueFacetLabels | undefined
): CatalogueFacetLabels {
  const next: CatalogueFacetLabels = { ...(base ?? {}) };
  if (!extra) {
    return next;
  }
  for (const [facet, map] of Object.entries(extra)) {
    if (!map) {
      continue;
    }
    next[facet] = { ...(next[facet] ?? {}), ...map };
  }
  return next;
}

/**
 * Display labels already sitting on the injected snapshot, so the filter
 * panel does not have to resolve every bucket over the wire.
 */
export function facetLabelsFromSnapshot(
  snapshot: CatalogueSnapshot | null | undefined
): CatalogueFacetLabels {
  if (snapshot?.labels) {
    return mergeFacetLabels(undefined, snapshot.labels);
  }
  const labels: CatalogueFacetLabels = {
    producers: {},
    related_domains: {},
    authors: {},
  };
  for (const producer of snapshot?.producers ?? []) {
    const text = producer.acronym || producer.name;
    if (!text) {
      continue;
    }
    for (const field of ID_FIELDS) {
      const value = producer[field];
      if (typeof value === "string" && value) {
        labels.producers![value] = text;
      }
    }
  }
  const vocabulary = snapshot?.search?.vocabulary;
  for (const [key, domain] of Object.entries(vocabulary?.domains ?? {})) {
    if (domain?.label) {
      labels.related_domains![key] = domain.label;
    }
  }
  for (const [key, person] of Object.entries(vocabulary?.persons ?? {})) {
    const name = `${person.firstname ?? ""} ${person.lastname ?? ""}`.trim();
    if (name) {
      labels.authors![key] = name.toUpperCase();
    }
  }
  return labels;
}
