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
 * Tests for useCatalogueCheck.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it, vi } from "vitest";
import { useCatalogueCheck } from "./useCatalogueCheck";
import type { CatalogueSnapshot } from "@/types/naas-config.types";
import type { CatalogueCheck } from "@/types/nugget.types";

const STAMP = { results_count: 40, newest_modification_date: "2026-11-01" };

function snapshot(): CatalogueSnapshot {
  return {
    producers_digest: "digest-1",
    stamp: STAMP,
    search: {
      items: [],
      results_count: 40,
      aggregations: {
        producers: { buckets: [{ key: "isae", docCount: 40 }] },
      },
    },
  } as unknown as CatalogueSnapshot;
}

function check(overrides: Partial<CatalogueCheck> = {}): CatalogueCheck {
  return {
    stamp: STAMP,
    producers_digest: "digest-1",
    producers: [],
    aggregations: {
      producers: { buckets: [{ key: "onera", docCount: 3 }] },
    },
    results_count: 43,
    ...overrides,
  } as CatalogueCheck;
}

function setup(result: Promise<CatalogueCheck>, snap = snapshot()) {
  const directory = { mergeLabels: vi.fn(), absorbProducers: vi.fn() };
  const applyCatalogueStamp = vi.fn();
  const service = { checkCatalogue: vi.fn(() => result) };
  const state = useCatalogueCheck({
    service,
    courseId: 7,
    snapshot: snap,
    directory,
    applyCatalogueStamp,
  });
  return { state, directory, applyCatalogueStamp, service };
}

describe("useCatalogueCheck", () => {
  it("paints the landing from the snapshot before any check", () => {
    const { state } = setup(new Promise(() => {}));
    expect(state.catalogueResultsCount.value).toBe(40);
    expect(state.facetAggregations.value.producers.buckets).toHaveLength(1);
    expect(state.catalogueLoading.value).toBe(false);
    expect(setup(new Promise(() => {}), {}).state.catalogueLoading.value).toBe(
      true
    );
  });

  it("keeps the landing when the producers digest did not move", async () => {
    const { state, applyCatalogueStamp, service } = setup(
      Promise.resolve(check())
    );
    await state.runCatalogueCheck();

    expect(service.checkCatalogue).toHaveBeenCalledWith(7);
    expect(applyCatalogueStamp).toHaveBeenCalledWith(STAMP, STAMP);
    expect(state.catalogueResultsCount.value).toBe(40);
    expect(state.facetAggregations.value.producers.buckets[0].key).toBe("isae");
  });

  it("repaints aggregations and count when the digest moved", async () => {
    const { state } = setup(
      Promise.resolve(check({ producers_digest: "digest-2" }))
    );
    await state.runCatalogueCheck();

    expect(state.catalogueResultsCount.value).toBe(43);
    expect(state.facetAggregations.value.producers.buckets[0].key).toBe(
      "onera"
    );
  });

  it("always passes labels and producer rows to the directory", async () => {
    const rows = [{ uuid: "u1", name: "ISAE" }];
    const labels = { producers: { u1: "ISAE" } };
    const { state, directory } = setup(
      Promise.resolve(check({ producers: rows, labels }))
    );
    await state.runCatalogueCheck();

    expect(directory.mergeLabels).toHaveBeenCalledWith(labels);
    expect(directory.absorbProducers).toHaveBeenCalledWith(rows);
  });

  it("keeps the landing data a sparse check does not carry", async () => {
    const { state, directory } = setup(
      Promise.resolve({
        stamp: STAMP,
        aggregations: {},
      } as unknown as CatalogueCheck)
    );
    await state.runCatalogueCheck();

    expect(directory.mergeLabels).not.toHaveBeenCalled();
    expect(directory.absorbProducers).not.toHaveBeenCalled();
    expect(state.catalogueResultsCount.value).toBe(40);
    expect(state.facetAggregations.value.producers.buckets[0].key).toBe("isae");
    expect(state.catalogueLoading.value).toBe(false);
  });

  it("distrusts cached pages and stops loading when the check fails", async () => {
    const { state, applyCatalogueStamp } = setup(
      Promise.reject(new Error("offline")),
      {}
    );
    expect(state.catalogueLoading.value).toBe(true);

    await state.runCatalogueCheck();

    expect(applyCatalogueStamp).toHaveBeenCalledWith(null, undefined);
    expect(state.catalogueLoading.value).toBe(false);
  });
});
