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
 * Tests for the facet label resolution.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, it, expect, vi } from "vitest";
import { createApp, ref } from "vue";
import { useFacetLabels } from "./useFacetLabels";
import {
  mapAggregationBuckets,
  type MappedAggregation,
} from "./searchAggregations";
import { NAAS_API_KEY } from "@/plugins/naas-api.plugin";
import type { CatalogueFacetLabels } from "@/types/nugget.types";

const naasConfig = {
  courseId: 7,
  labels: {
    unknown_author: "Unknown author",
    unknown_producer: "Unknown producer",
    metadata: { beginner: "Beginner" },
  },
};

const ISAE = "06d37c13-6ffe-4c4a-a9e3-ac227652f98c";
const hash = (n: number) => n.toString(16).padStart(32, "0");

function setup(
  service: object = {},
  networkLabels: CatalogueFacetLabels | undefined = undefined,
  columns: MappedAggregation[] = []
) {
  const aggregations = ref<MappedAggregation[]>(columns);
  const onSelectedRelabel = vi.fn();
  const app = createApp({});
  app.provide(NAAS_API_KEY, service);
  app.provide("naasConfig", naasConfig);
  const labels = app.runWithContext(() =>
    useFacetLabels({
      aggregations,
      networkLabels: () => networkLabels,
      onSelectedRelabel,
    })
  );
  return { ...labels, aggregations, onSelectedRelabel };
}

function column(
  name: string,
  keys: string[],
  selected: string[] = []
): MappedAggregation {
  return mapAggregationBuckets(
    name,
    keys.map((key) => ({ key, docCount: 1 })),
    selected,
    (key) => key
  );
}

describe("useFacetLabels — local labels", () => {
  it("uses lang strings for plain facets and the raw key otherwise", () => {
    const { localLabel, hasHumanLabel } = setup();
    expect(localLabel("level", "beginner")).toBe("Beginner");
    expect(localLabel("level", "expert")).toBe("expert");
    expect(hasHumanLabel("level", "expert")).toBe(false);
    // Entity facets never fall back to lang strings.
    expect(localLabel("authors", "beginner")).toBe("beginner");
  });

  it("reads catalogue labels, with prefixed keys and the domains alias", () => {
    const { localLabel, hasHumanLabel } = setup(
      {},
      {
        producers: { [ISAE]: "ISAE" },
        authors: { [hash(1)]: "ADA LOVELACE", [hash(2)]: hash(2) },
        domains: { "01.02": "Aerospace" },
      }
    );
    expect(localLabel("producers", `managed_by:structure:${ISAE}`)).toBe(
      "ISAE"
    );
    expect(localLabel("authors", `authored_by:person:${hash(1)}`)).toBe(
      "ADA LOVELACE"
    );
    expect(localLabel("related_domains", "01.02")).toBe("Aerospace");
    expect(hasHumanLabel("related_domains", "01.02")).toBe(true);
    // A catalogue label that is still the raw key does not count.
    expect(localLabel("authors", hash(2))).toBe(hash(2));
    expect(hasHumanLabel("authors", hash(2))).toBe(false);
    expect(localLabel("producers", "other")).toBe("other");
    expect(localLabel("related_domains", "09")).toBe("09");
  });
});

describe("useFacetLabels — resolution", () => {
  it("skips plain facets, resolved facets and facets already resolving", async () => {
    const getPerson = vi.fn().mockResolvedValue({ name: "Ada" });
    const authors = column("authors", [hash(1)]);
    const { resolveFacetLabels, aggregations } = setup(
      { getPerson },
      undefined,
      [authors]
    );
    await resolveFacetLabels(column("level", ["beginner"]));
    await resolveFacetLabels({ ...authors, labelsResolved: true });
    expect(getPerson).not.toHaveBeenCalled();

    await Promise.all([
      resolveFacetLabels(authors),
      resolveFacetLabels(authors),
    ]);
    expect(getPerson).toHaveBeenCalledTimes(1);
    expect(aggregations.value[0].buckets[0].help).toBe("ADA");
    expect(aggregations.value[0].labelsResolved).toBe(true);
  });

  it("names domains through NaaS and only asks for unknown keys", async () => {
    const getDomain = vi.fn().mockResolvedValue({ label: "Propulsion" });
    const domains = column("related_domains", ["01.02", "01.03"]);
    const { resolveFacetLabels, aggregations, resolvedLabels, localLabel } =
      setup({ getDomain }, { domains: { "01.02": "Aerospace" } }, [domains]);
    await resolveFacetLabels(domains);
    expect(getDomain).toHaveBeenCalledTimes(1);
    expect(getDomain).toHaveBeenCalledWith("01.03", 7);
    expect(aggregations.value[0].buckets.map((b) => b.help)).toEqual([
      "Aerospace",
      "Propulsion",
    ]);
    expect(resolvedLabels.value.related_domains).toEqual({
      "01.02": "Aerospace",
      "01.03": "Propulsion",
    });
    expect(localLabel("related_domains", "01.03")).toBe("Propulsion");
  });

  it("keeps a domain unnamed when NaaS has no label for it", async () => {
    const getDomain = vi.fn().mockRejectedValue(new Error("offline"));
    const domains = column("related_domains", ["01.02"]);
    const { resolveFacetLabels, aggregations } = setup(
      { getDomain },
      undefined,
      [domains]
    );
    await resolveFacetLabels(domains);
    expect(aggregations.value[0].buckets[0].help).toBe("01.02");
    expect(aggregations.value[0].labelsResolved).toBe(false);
  });

  it("names producers by acronym, keeps full names as titles", async () => {
    const getStructure = vi.fn(async (key: string) => {
      if (key === ISAE) return { acronym: "ISAE", name: "ISAE-SUPAERO" };
      if (key === hash(1)) return { name: "ONERA" };
      return {};
    });
    const producers = column("producers", [ISAE, hash(1), hash(2)]);
    const { resolveFacetLabels, aggregations } = setup(
      { getStructure },
      undefined,
      [producers]
    );
    await resolveFacetLabels(producers);
    const buckets = aggregations.value[0].buckets;
    expect(buckets.map((b) => [b.help, b.title])).toEqual([
      ["ISAE", "ISAE-SUPAERO"],
      ["ONERA", undefined],
      ["Unknown producer", undefined],
    ]);
  });

  it("gives unnamed authors a fallback and reports relabelled selections", async () => {
    const getPerson = vi.fn(async (key: string) =>
      key === hash(1) ? { firstname: "Ada", lastname: "Lovelace" } : {}
    );
    const authors = column("authors", [hash(1), hash(2)], [hash(2)]);
    const { resolveFacetLabels, aggregations, onSelectedRelabel } = setup(
      { getPerson },
      undefined,
      [authors]
    );
    await resolveFacetLabels(authors);
    expect(aggregations.value[0].buckets.map((b) => b.help)).toEqual([
      "ADA LOVELACE",
      "Unknown author",
    ]);
    expect(onSelectedRelabel).toHaveBeenCalledTimes(1);
  });

  it("resolves against the current column, not a stale copy", async () => {
    const getPerson = vi.fn().mockResolvedValue({ name: "Ada" });
    const stale = column("authors", [hash(1)]);
    const { resolveFacetLabels, aggregations, onSelectedRelabel } = setup(
      { getPerson },
      undefined,
      [column("authors", [hash(1), hash(2)])]
    );
    await resolveFacetLabels(stale);
    expect(getPerson).toHaveBeenCalledTimes(2);
    expect(aggregations.value[0].buckets).toHaveLength(2);
    expect(onSelectedRelabel).not.toHaveBeenCalled();
  });

  it("still resolves a facet that is no longer listed", async () => {
    const getPerson = vi.fn().mockResolvedValue({ name: "Ada" });
    const gone = column("authors", [hash(1)]);
    const { resolveFacetLabels, aggregations, resolvedLabels } = setup({
      getPerson,
    });
    await resolveFacetLabels(gone);
    expect(aggregations.value).toEqual([]);
    expect(resolvedLabels.value.authors).toEqual({ [hash(1)]: "ADA" });
  });
});
