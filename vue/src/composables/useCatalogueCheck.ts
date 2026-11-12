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
 * Catalogue landing state and its check against NaaS.
 *
 * The landing paints at once from the injected snapshot. The check then
 * confirms the producer strip and the catalogue stamp, and only repaints the
 * aggregations when the producers digest moved.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { ref } from "vue";
import { snapshotSearch } from "./catalogueSnapshot";
import { errorMessage } from "@/utils/errorMessage";
import type { ProducerDirectory } from "./useProducerDirectory";
import type { INaasApiService } from "@/service/naas-api.interface";
import type {
  CatalogueProducer,
  CatalogueSnapshot,
} from "@/types/naas-config.types";
import type { AggregationResult, CatalogueStamp } from "@/types/nugget.types";

export interface CatalogueCheckOptions {
  service: Pick<INaasApiService, "checkCatalogue">;
  courseId: number;
  snapshot: CatalogueSnapshot | null | undefined;
  directory: Pick<ProducerDirectory, "mergeLabels" | "absorbProducers">;
  /** useNuggetSearch().applyCatalogueStamp: trust or distrust cached pages. */
  applyCatalogueStamp: (
    live?: CatalogueStamp | null,
    previous?: CatalogueStamp | null
  ) => unknown;
}

export function useCatalogueCheck(options: CatalogueCheckOptions) {
  const { service, courseId, snapshot, directory, applyCatalogueStamp } =
    options;
  const landingSnapshot = snapshotSearch(snapshot);

  const facetAggregations = ref<Record<string, AggregationResult>>(
    landingSnapshot?.aggregations ?? {}
  );
  const catalogueResultsCount = ref(
    Number(landingSnapshot?.results_count ?? 0)
  );
  const catalogueLoading = ref(
    !landingSnapshot?.aggregations?.producers?.buckets?.length
  );
  const producersDigest = ref(snapshot?.producers_digest ?? "");
  /** Set when NaaS could not be reached and the landing has nothing cached to show. */
  const catalogueError = ref<Error | null>(null);

  /**
   * Confirm the producer strip and the catalogue stamp against NaaS.
   *
   * Aggregations span the whole match set, so one hit carries every producer,
   * every count, and the newest modification date.
   */
  async function runCatalogueCheck(): Promise<void> {
    try {
      catalogueError.value = null;
      const check = await service.checkCatalogue(courseId);
      applyCatalogueStamp(check.stamp, snapshot?.stamp);
      if (check.labels) {
        directory.mergeLabels(check.labels);
      }
      // The digest ignores display names: always absorb producer rows.
      if (Array.isArray(check.producers)) {
        directory.absorbProducers(check.producers as CatalogueProducer[]);
      }
      if (
        check.producers_digest &&
        check.producers_digest === producersDigest.value
      ) {
        return;
      }
      producersDigest.value = check.producers_digest ?? "";
      if (check.aggregations && Object.keys(check.aggregations).length) {
        facetAggregations.value = check.aggregations;
      }
      if (typeof check.results_count === "number") {
        catalogueResultsCount.value = check.results_count;
      }
    } catch (e) {
      applyCatalogueStamp(null, snapshot?.stamp);
      // A cached landing stays usable offline; an empty one must say why it is empty
      // instead of "No producers in this catalogue yet".
      const hasLanding = Object.keys(facetAggregations.value).length > 0;
      if (!hasLanding) {
        catalogueError.value =
          e instanceof Error ? e : new Error(errorMessage(e));
      }
    } finally {
      catalogueLoading.value = false;
    }
  }

  return {
    landingSnapshot,
    facetAggregations,
    catalogueResultsCount,
    catalogueLoading,
    catalogueError,
    runCatalogueCheck,
  };
}
