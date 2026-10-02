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
 * Composable that drives search queries for the NuggetSearchWidget and
 * NuggetSearchFilter components.
 *
 * Nothing the teacher looks at waits on NaaS. A page already seen in this
 * session paints from memory; otherwise Moodle serves its cached copy, which
 * costs no NaaS round-trip. Either way a live call follows in the background
 * and the grid is only rebuilt when the freshness digest actually moved, so an
 * unchanged catalogue costs no re-render and no vocabulary lookups.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { ref } from "vue";
import { useMoodleService } from "./useMoodleService";
import { useNaasConfig } from "./useNaasConfig";
import { useNuggetEnricher } from "./useNuggetEnricher";
import {
  applyVocabulary,
  isEnriched,
  sameCatalogueStamp,
  snapshotSearch,
} from "./catalogueSnapshot";
import type {
  CatalogueStamp,
  Nugget,
  SearchFreshness,
  SearchOptions,
  SearchResult,
} from "@/types/nugget.types";

/** Pages retained per page session; paging deep should not grow without end. */
const MEMORY_LIMIT = 30;

function mergeEnriched(current: Nugget[], enriched: Nugget[]): Nugget[] {
  const byId = new Map(enriched.map((nugget) => [nugget.nugget_id, nugget]));
  return current.map((nugget) => byId.get(nugget.nugget_id) ?? nugget);
}

/**
 * Identity of a query, matching the canonical form PHP hashes: blanks dropped,
 * keys and array values sorted, page always present.
 */
export function queryKey(options: SearchOptions): string {
  const normalised: Record<string, string | string[]> = {};
  for (const [name, value] of Object.entries(options)) {
    if (value === null || value === undefined || value === "") {
      continue;
    }
    if (Array.isArray(value)) {
      const copy = value.map(String).filter((entry) => entry !== "");
      if (!copy.length) {
        continue;
      }
      normalised[name] = [...copy].sort();
    } else if (typeof value === "boolean") {
      normalised[name] = value ? "true" : "false";
    } else {
      normalised[name] = String(value);
    }
  }
  normalised.page = String(options.page ?? 0);
  return JSON.stringify(
    Object.fromEntries(
      Object.entries(normalised).sort(([a], [b]) => (a < b ? -1 : 1))
    )
  );
}

export function sameFreshness(
  a: SearchFreshness | undefined,
  b: SearchFreshness | undefined
): boolean {
  if (!a || !b) {
    return false;
  }
  return (
    a.results_count === b.results_count &&
    a.id_digest === b.id_digest &&
    a.max_modification_date === b.max_modification_date
  );
}

export function useNuggetSearch(opts: { initialLoading?: boolean } = {}) {
  const service = useMoodleService();
  const config = useNaasConfig();
  const { enrichMany } = useNuggetEnricher();

  const nuggets = ref<Nugget[]>([]);
  const searchResult = ref<SearchResult | null>(null);
  const loading = ref(opts.initialLoading ?? false);
  const loadingMore = ref(false);
  const revalidating = ref(false);
  const error = ref<Error | null>(null);

  // Pages already seen in this page session, so browse → section → back is free.
  const memory = new Map<string, SearchResult>();
  let searchGeneration = 0;
  // Several revalidations can overlap; the affordance shows while any is open.
  let openRevalidations = 0;
  // Bumped by every "load more". A revalidation of page 0 must not collapse
  // pages appended since it was issued.
  let appendEpoch = 0;

  type StampState = "unknown" | "holding" | "unchanged" | "moved";
  let stampState: StampState = "unknown";
  const revalidatedKeys = new Set<string>();
  const pendingRevalidates: Array<{ key: string; run: () => void }> = [];

  /**
   * Queue revalidation until the landing probe returns. Without this, tests
   * and any caller that never checks the catalogue keep the old behaviour:
   * a cache hit always revalidates.
   */
  function holdRevalidation(): void {
    stampState = "holding";
  }

  /**
   * Compare the live probe against the stamp the page was rendered with.
   * Unchanged → drop queued revalidates. Moved → run each queued key once.
   */
  function applyCatalogueStamp(
    live?: CatalogueStamp | null,
    previous?: CatalogueStamp | null
  ): boolean {
    if (!live?.newest_modification_date) {
      stampState = "unknown";
      flushPending();
      return true;
    }
    const moved = !sameCatalogueStamp(live, previous);
    stampState = moved ? "moved" : "unchanged";
    if (moved) {
      flushPending();
    } else {
      pendingRevalidates.length = 0;
    }
    return moved;
  }

  function flushPending(): void {
    const queued = pendingRevalidates.splice(0);
    for (const item of queued) {
      maybeRevalidate(item.key, item.run);
    }
  }

  function maybeRevalidate(key: string, run: () => void): void {
    if (stampState === "unchanged") {
      return;
    }
    if (stampState === "moved" && revalidatedKeys.has(key)) {
      return;
    }
    if (stampState === "holding") {
      pendingRevalidates.push({ key, run });
      return;
    }
    revalidatedKeys.add(key);
    run();
  }

  function remember(key: string, result: SearchResult): void {
    memory.delete(key);
    memory.set(key, result);
    if (memory.size > MEMORY_LIMIT) {
      memory.delete(memory.keys().next().value as string);
    }
  }

  /**
   * Adopt the snapshot Moodle injected into the page.
   *
   * Passing the landing options files the snapshot under that query, so if the
   * teacher ever lands back on it the grid paints from memory instead of
   * flashing a skeleton. Any *other* query stays a genuine miss and still gets
   * its skeleton — the snapshot must never stand in for a producer's cards.
   */
  function hydrateFromSnapshot(landingOptions?: SearchOptions): boolean {
    const cached = snapshotSearch(config.catalogue_snapshot);
    if (!cached) {
      return false;
    }
    nuggets.value = cached.items;
    searchResult.value = cached;
    loading.value = false;
    if (landingOptions) {
      remember(queryKey(landingOptions), cached);
    }
    return true;
  }

  /** Resolve author and domain names for hits the server could not complete. */
  function enrichPending(items: Nugget[], generation: number): void {
    const pending = items.filter((item) => !isEnriched(item));
    if (!pending.length) {
      return;
    }
    void enrichMany(pending)
      .then((enriched) => {
        if (generation !== searchGeneration) {
          return;
        }
        nuggets.value = mergeEnriched(nuggets.value, enriched);
        if (searchResult.value) {
          searchResult.value = {
            ...searchResult.value,
            items: mergeEnriched(searchResult.value.items, enriched),
          };
        }
      })
      .catch(() => {
        // Cards are already visible; vocabulary labels stay as raw keys.
      });
  }

  function normaliseResult(result: SearchResult): SearchResult {
    const items = Array.isArray(result?.items) ? result.items : [];
    return { ...result, items: applyVocabulary(items, result?.vocabulary) };
  }

  function paint(result: SearchResult, append: boolean, generation: number) {
    if (append) {
      appendEpoch++;
      nuggets.value.push(...result.items);
    } else {
      nuggets.value = result.items;
    }
    searchResult.value = append ? { ...result, items: nuggets.value } : result;
    enrichPending(result.items, generation);
  }

  /**
   * Confirm a served-from-cache page against NaaS, repainting only on a real
   * change. Failures are swallowed: the teacher is already looking at cards.
   */
  async function revalidate(
    options: SearchOptions,
    key: string,
    generation: number,
    previous: SearchResult
  ): Promise<void> {
    openRevalidations++;
    revalidating.value = true;
    const epoch = appendEpoch;
    try {
      const raw = await service.searchNuggets(
        options,
        config.courseId,
        "revalidate"
      );
      if (generation !== searchGeneration) {
        return;
      }
      const fresh = normaliseResult(raw);
      remember(key, fresh);
      if (sameFreshness(previous._cache?.digest, fresh._cache?.digest)) {
        return;
      }
      if (epoch !== appendEpoch) {
        // Further pages are on screen; the fresh copy is cached for next time.
        return;
      }
      paint(fresh, false, generation);
    } catch {
      // Keep the cached page; the next navigation will try again.
    } finally {
      openRevalidations--;
      revalidating.value = openRevalidations > 0;
    }
  }

  async function search(
    options: SearchOptions,
    append = false
  ): Promise<SearchResult | null> {
    const generation = append ? searchGeneration : ++searchGeneration;
    const key = queryKey(options);

    // A page seen earlier in this session needs no round-trip to paint.
    const remembered = append ? undefined : memory.get(key);
    if (remembered) {
      paint(remembered, false, generation);
      loading.value = false;
      error.value = null;
      maybeRevalidate(key, () => {
        void revalidate(options, key, generation, remembered);
      });
      return searchResult.value;
    }

    try {
      if (append) {
        loadingMore.value = true;
      } else {
        loading.value = true;
      }
      error.value = null;

      const raw = await service.searchNuggets(
        options,
        config.courseId,
        "cache_first"
      );
      if (generation !== searchGeneration) {
        return null;
      }
      const result = normaliseResult(raw);
      if (!append) {
        remember(key, result);
      }
      paint(result, append, generation);
      loading.value = false;
      loadingMore.value = false;

      // Moodle answered from its own cache, so confirm it against NaaS. A miss
      // was already a live call and needs no second trip.
      if (!append && result._cache?.hit) {
        maybeRevalidate(key, () => {
          void revalidate(options, key, generation, result);
        });
      }
      return searchResult.value;
    } catch (e) {
      if (generation !== searchGeneration) {
        return null;
      }
      error.value = e as Error;
      return null;
    } finally {
      if (generation === searchGeneration) {
        loading.value = false;
        loadingMore.value = false;
      }
    }
  }

  /** Join the vocabulary table, then resolve whatever it could not cover. */
  async function completeNugget(raw: Nugget): Promise<Nugget> {
    const [joined] = applyVocabulary([raw], raw.vocabulary);
    if (isEnriched(joined)) {
      return joined;
    }
    const [enriched] = await enrichMany([joined]);
    return enriched;
  }

  /**
   * Resolve the saved selection.
   *
   * `onRevalidated` fires only when Moodle served a stored document and NaaS
   * then reported a newer one, so the card is never rebuilt for nothing.
   */
  async function getNuggetById(
    nuggetId: string,
    onRevalidated?: (nugget: Nugget) => void
  ): Promise<Nugget | null> {
    const seeded = seedFromCache(nuggetId);
    try {
      const raw = await service.getNugget(
        nuggetId,
        config.courseId,
        "cache_first"
      );
      const current = await completeNugget(raw);

      if (raw._cache?.hit && onRevalidated) {
        maybeRevalidate(`nugget:${nuggetId}`, () => {
          void service
            .getNugget(nuggetId, config.courseId, "revalidate")
            .then(async (fresh) => {
              const before = raw._cache?.digest;
              const after = fresh._cache?.digest;
              if (
                before &&
                after &&
                before.modification_date === after.modification_date &&
                before.version_id === after.version_id
              ) {
                return;
              }
              onRevalidated(await completeNugget(fresh));
            })
            .catch(() => {
              // The stored document is already on screen.
            });
        });
      }
      return current;
    } catch (e) {
      if (seeded) {
        return seeded;
      }
      error.value = e as Error;
      return null;
    }
  }

  /** A hit already cached for another query is enough to paint the selection. */
  function seedFromCache(nuggetId: string): Nugget | null {
    const pools: SearchResult[] = [...memory.values()];
    const snapshot = snapshotSearch(config.catalogue_snapshot);
    if (snapshot) {
      pools.push(snapshot);
    }
    for (const pool of pools) {
      const found = pool.items.find((item) => item.nugget_id === nuggetId);
      if (found) {
        return found;
      }
    }
    return null;
  }

  return {
    nuggets,
    searchResult,
    loading,
    loadingMore,
    revalidating,
    error,
    search,
    getNuggetById,
    seedFromCache,
    hydrateFromSnapshot,
    holdRevalidation,
    applyCatalogueStamp,
  };
}
