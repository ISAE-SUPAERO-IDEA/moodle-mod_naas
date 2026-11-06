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
 * Tests for useNuggetEnricher (author and domain lookups).
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, it, expect, vi } from "vitest";
import { createApp } from "vue";
import { useNuggetEnricher } from "./useNuggetEnricher";
import { NAAS_API_KEY } from "@/plugins/naas-api.plugin";
import type { Nugget } from "@/types/nugget.types";

function withProviders<T>(service: object, run: () => T): T {
  const app = createApp({});
  app.provide(NAAS_API_KEY, service);
  app.provide("naasConfig", { courseId: 7 });
  return app.runWithContext(run);
}

describe("useNuggetEnricher", () => {
  it("fetches each author and domain key once across a page of nuggets", async () => {
    const getPerson = vi.fn().mockImplementation(async (key: string) => ({
      firstname: key,
      lastname: "Author",
      email: `${key}@example.com`,
    }));
    const getDomain = vi.fn().mockImplementation(async (key: string) => ({
      id: key,
      label: key,
    }));

    const { enrichMany } = withProviders({ getPerson, getDomain }, () =>
      useNuggetEnricher()
    );
    const enriched = await enrichMany([
      { nugget_id: "1", authors: ["a1", "a2"], domains: ["d1"] },
      { nugget_id: "2", authors: ["a1"], domains: ["d1", "d2"] },
    ] as Nugget[]);

    expect(getPerson).toHaveBeenCalledTimes(2);
    expect(getDomain).toHaveBeenCalledTimes(2);
    expect(getPerson).toHaveBeenCalledWith("a1", 7);
    expect(getPerson).toHaveBeenCalledWith("a2", 7);
    expect(enriched[0].authors_data).toHaveLength(2);
    expect(enriched[1].authors_data).toHaveLength(1);
    expect(enriched[1].domains_data).toHaveLength(2);
  });

  it("drops authors and domains NaaS could not return", async () => {
    const getPerson = vi.fn(async (key: string) => {
      if (key === "gone") throw new Error("404");
      return key === "empty" ? null : { firstname: key, lastname: "A" };
    });
    const getDomain = vi.fn().mockRejectedValue(new Error("offline"));
    const { enrich } = withProviders({ getPerson, getDomain }, () =>
      useNuggetEnricher()
    );
    const enriched = await enrich({
      nugget_id: "1",
      authors: ["a1", "gone", "empty", ""],
      domains: ["d1"],
    } as Nugget);

    expect(getPerson).toHaveBeenCalledTimes(3);
    expect(enriched.authors_data).toEqual([{ firstname: "a1", lastname: "A" }]);
    expect(enriched.domains_data).toEqual([]);
  });

  it("enriches a nugget without author or domain keys without calling NaaS", async () => {
    const getPerson = vi.fn();
    const getDomain = vi.fn();
    const { enrich } = withProviders({ getPerson, getDomain }, () =>
      useNuggetEnricher()
    );
    const enriched = await enrich({ nugget_id: "1" } as Nugget);
    expect(enriched).toMatchObject({
      nugget_id: "1",
      authors_data: [],
      domains_data: [],
    });
    expect(getPerson).not.toHaveBeenCalled();
    expect(getDomain).not.toHaveBeenCalled();
  });
});
