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
 * Tests for the catalogue landing (All Nuggets + one card per producer).
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it, vi } from "vitest";
import { reactive } from "vue";
import NuggetBrowseLanding from "./NuggetBrowseLanding.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import {
  makeConfig,
  makeService,
  providers,
  type FakeService,
} from "@/test/widgetFixtures";
import type { CatalogueProducer } from "@/types/naas-config.types";

let mounted: Mounted | null = null;

const directory: CatalogueProducer[] = [
  { structure_id: "isae-supaero", acronym: "ISAE", name: "ISAE-SUPAERO" },
];

function producerBuckets(...entries: Array<[string, number]>) {
  return {
    producers: {
      buckets: entries.map(([key, docCount]) => ({ key, docCount })),
    },
  };
}

function mountLanding(
  props: Record<string, unknown>,
  service: FakeService = makeService()
) {
  const reactiveProps = reactive<Record<string, unknown>>({
    aggregations: {},
    ...props,
  });
  mounted = mountComponent(
    NuggetBrowseLanding,
    reactiveProps,
    providers(makeConfig(), service)
  );
  return { props: reactiveProps, service, ...mounted };
}

function cardTitles(el: HTMLElement): string[] {
  return Array.from(
    el.querySelectorAll(".browse-card--producer .browse-card-title")
  ).map((t) => t.textContent?.trim() ?? "");
}

describe("NuggetBrowseLanding", () => {
  afterEach(() => {
    mounted?.unmount();
    mounted = null;
  });

  it("opens the whole catalogue from the All Nuggets card", () => {
    const { el, emitted } = mountLanding({ resultsCount: 12 });
    const all = el.querySelector<HTMLButtonElement>(".browse-card--all")!;
    expect(all.tagName).toBe("BUTTON");
    expect(all.textContent).toContain("All Nuggets");
    expect(all.textContent).toContain("12 Nuggets");
    all.click();
    expect(emitted.select).toEqual([
      [{ facet: "all", value: "", caption: "All Nuggets" }],
    ]);
  });

  it("names producers from the directory without asking Moodle", async () => {
    const { el, service, emitted, flush } = mountLanding({
      aggregations: producerBuckets(["isae-supaero", 4]),
      directory,
    });
    await flush();
    expect(cardTitles(el)).toEqual(["ISAE"]);
    const card = el.querySelector<HTMLButtonElement>(".browse-card--producer")!;
    expect(card.getAttribute("title")).toBe("ISAE-SUPAERO");
    expect(card.querySelector(".browse-card-name")?.textContent).toBe(
      "ISAE-SUPAERO"
    );
    expect(card.querySelector(".browse-card-meta")?.textContent).toBe(
      "4 Nuggets"
    );
    expect(service.getStructure).not.toHaveBeenCalled();
    expect(emitted.named).toEqual([
      [[{ id: "isae-supaero", acronym: "ISAE", name: "ISAE-SUPAERO" }]],
    ]);

    card.click();
    expect(emitted.select).toEqual([
      [{ facet: "producers", value: "isae-supaero", caption: "ISAE" }],
    ]);
  });

  it("sorts producers by Nugget count and resolves unknown ones", async () => {
    const service = makeService({
      getStructure: vi.fn().mockResolvedValue({
        acronym: "ENAC",
        name: "École nationale de l'aviation civile",
      }),
    });
    const { el, emitted, flush } = mountLanding(
      {
        aggregations: producerBuckets(["isae-supaero", 2], ["enac", 9]),
        directory,
      },
      service
    );
    await flush();
    expect(service.getStructure).toHaveBeenCalledTimes(1);
    expect(service.getStructure).toHaveBeenCalledWith("enac", 7);
    expect(cardTitles(el)).toEqual(["ENAC", "ISAE"]);
    expect(emitted.named?.[emitted.named.length - 1]?.[0]).toContainEqual({
      id: "enac",
      acronym: "ENAC",
      name: "École nationale de l'aviation civile",
    });
  });

  it("titles a producer by its name when it has no acronym", async () => {
    const service = makeService({
      getStructure: vi.fn().mockResolvedValue({ name: "Polytechnique" }),
    });
    const { el, flush } = mountLanding(
      { aggregations: producerBuckets(["x", 1]) },
      service
    );
    await flush();
    expect(cardTitles(el)).toEqual(["Polytechnique"]);
  });

  it("shows hidden skeleton cards while the producers load", async () => {
    const { el, props, flush } = mountLanding({
      loading: true,
      aggregations: {},
    });
    await flush();
    const grid = el.querySelector(".browse-grid")!;
    expect(grid.getAttribute("aria-busy")).toBe("true");
    const skeletons = el.querySelectorAll(".browse-card--skel");
    expect(skeletons).toHaveLength(6);
    expect(skeletons[0].getAttribute("aria-hidden")).toBe("true");

    props.loading = false;
    await flush();
    expect(grid.getAttribute("aria-busy")).toBe("false");
    expect(el.querySelector(".browse-card--skel")).toBeNull();
    expect(el.querySelector(".browse-empty")?.textContent?.trim()).toBe(
      "No producers yet."
    );
  });

  it("repaints when the aggregations change", async () => {
    const { el, props, flush } = mountLanding({
      aggregations: producerBuckets(["isae-supaero", 2]),
      directory,
    });
    await flush();
    props.aggregations = {};
    await flush();
    expect(cardTitles(el)).toEqual([]);
    expect(
      el.querySelector(".browse-card--all .browse-card-meta")?.textContent
    ).toBe("");
  });
});
