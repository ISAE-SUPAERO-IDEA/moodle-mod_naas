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
});
