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
 * Producer directory and facet labels of the search widget.
 *
 * Starts from the catalogue snapshot injected by Moodle, then absorbs the
 * producer rows of the catalogue check and the names the landing resolves.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { ref } from "vue";
import {
  facetLabelsFromSnapshot,
  mergeFacetLabels,
  mergeProducerDirectory,
} from "./catalogueSnapshot";
import { mergeProducerNames, producerLabelUpdates } from "./producerDirectory";
import type { NamedProducer } from "./producerDirectory";
import type { CatalogueProducer, CatalogueSnapshot } from "@/shared/config";
import type { CatalogueFacetLabels } from "@/shared/api";

export function useProducerDirectory(
  snapshot: CatalogueSnapshot | null | undefined
) {
  const producerDirectory = ref<CatalogueProducer[]>(snapshot?.producers ?? []);
  const facetLabels = ref<CatalogueFacetLabels>(
    facetLabelsFromSnapshot(snapshot)
  );

  /** Add labels (e.g. from the catalogue check) to the facet labels. */
  function mergeLabels(labels: CatalogueFacetLabels): void {
    facetLabels.value = mergeFacetLabels(facetLabels.value, labels);
  }

  /** Merge producer rows from NaaS into the directory and the producer labels. */
  function absorbProducers(rows: CatalogueProducer[]): void {
    if (rows.length === 0) {
      return;
    }
    producerDirectory.value = mergeProducerDirectory(
      producerDirectory.value,
      rows
    );
    mergeLabels(facetLabelsFromSnapshot({ producers: rows }));
  }

  /**
   * Fold names the landing resolved into the facet labels and the directory.
   *
   * Assigning `producerDirectory` hands NuggetBrowseLanding a new `directory`
   * reference and re-runs its watcher, which emits `named` again. Only assign
   * when something actually changed, or the round trip never settles.
   */
  function onProducersNamed(rows: NamedProducer[]): void {
    const labels = producerLabelUpdates(facetLabels.value.producers, rows);
    if (Object.keys(labels).length) {
      mergeLabels({ producers: labels });
    }
    const merged = mergeProducerNames(producerDirectory.value, rows);
    if (merged !== producerDirectory.value) {
      producerDirectory.value = merged;
    }
  }

  return {
    producerDirectory,
    facetLabels,
    mergeLabels,
    absorbProducers,
    onProducersNamed,
  };
}

export type ProducerDirectory = ReturnType<typeof useProducerDirectory>;
