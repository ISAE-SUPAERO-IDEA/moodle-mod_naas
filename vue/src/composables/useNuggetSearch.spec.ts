import { describe, it, expect, vi } from "vitest";
import { createApp } from "vue";
import { queryKey, sameFreshness, useNuggetSearch } from "./useNuggetSearch";
import { NAAS_API_KEY } from "@/plugins/naas-api.plugin";
import type { Nugget, SearchFreshness } from "@/types/nugget.types";

const digest = (over: Partial<SearchFreshness> = {}): SearchFreshness => ({
  results_count: 1,
  id_digest: "abc",
  max_modification_date: "2026-01-01T00:00:00Z",
  ...over,
});

const nugget = {
  nugget_id: "n1",
  name: "Alpha",
  resume: "Intro",
  authors: ["a1"],
  domains: ["d1"],
} as Nugget;

function withProviders<T>(
  service: object,
  run: () => T,
  naasConfig: object = { courseId: 7 }
): T {
  const app = createApp({});
  app.provide(NAAS_API_KEY, service);
  app.provide("naasConfig", naasConfig);
  return app.runWithContext(run);
}

describe("useNuggetSearch", () => {
  it("paints search hits before author and domain enrichment resolves", async () => {
    let resolvePerson!: (value: unknown) => void;
    const personPromise = new Promise((resolve) => {
      resolvePerson = resolve;
    });
    const searchNuggets = vi.fn().mockResolvedValue({
      items: [nugget],
      results_count: 1,
      aggregations: { level: { buckets: [] } },
    });
    const getPerson = vi.fn().mockReturnValue(personPromise);
    const getDomain = vi.fn().mockResolvedValue({ id: "d1", label: "Math" });

    const { nuggets, loading, search } = withProviders(
      { searchNuggets, getPerson, getDomain },
      () => useNuggetSearch()
    );

    const result = await search({ page_size: 9 });

    expect(loading.value).toBe(false);
    expect(nuggets.value).toHaveLength(1);
    expect(nuggets.value[0].name).toBe("Alpha");
    expect(nuggets.value[0].authors_data).toBeUndefined();
    expect(result?.aggregations.level).toEqual({ buckets: [] });

    resolvePerson({
      firstname: "Ada",
      lastname: "Lovelace",
      email: "ada@example.com",
    });
    await vi.waitFor(() => {
      expect(nuggets.value[0].authors_data?.[0]).toMatchObject({
        firstname: "Ada",
      });
    });
    expect(nuggets.value[0].domains_data?.[0]).toMatchObject({ label: "Math" });
  });

  it("does not apply enrichment from a superseded search", async () => {
    let resolveFirstPerson!: (value: unknown) => void;
    const firstPerson = new Promise((resolve) => {
      resolveFirstPerson = resolve;
    });
    const searchNuggets = vi
      .fn()
      .mockResolvedValueOnce({
        items: [{ ...nugget, nugget_id: "old", name: "Old" }],
        results_count: 1,
        aggregations: {},
      })
      .mockResolvedValueOnce({
        items: [{ ...nugget, nugget_id: "new", name: "New", authors: [] }],
        results_count: 1,
        aggregations: {},
      });
    const getPerson = vi
      .fn()
      .mockReturnValueOnce(firstPerson)
      .mockResolvedValue({
        firstname: "Ignored",
        lastname: "Person",
        email: "x@example.com",
      });
    const getDomain = vi.fn().mockResolvedValue({ id: "d1", label: "Math" });

    const { nuggets, search } = withProviders(
      { searchNuggets, getPerson, getDomain },
      () => useNuggetSearch()
    );

    await search({ page_size: 9, fulltext: "old" });
    expect(nuggets.value[0].nugget_id).toBe("old");

    await search({ page_size: 9, fulltext: "new" });
    expect(nuggets.value[0].nugget_id).toBe("new");

    resolveFirstPerson({
      firstname: "Stale",
      lastname: "Author",
      email: "stale@example.com",
    });
    await new Promise((resolve) => setTimeout(resolve, 20));
    expect(nuggets.value[0].nugget_id).toBe("new");
    expect(
      nuggets.value[0].authors_data?.some(
        (author) => author.firstname === "Stale"
      )
    ).toBeFalsy();
  });

  it("paints the catalogue snapshot immediately and refreshes without a loading flash", async () => {
    let resolveSearch!: (value: unknown) => void;
    const searchNuggets = vi.fn().mockReturnValue(
      new Promise((resolve) => {
        resolveSearch = resolve;
      })
    );
    const { nuggets, loading, search, hydrateFromSnapshot, searchResult } =
      withProviders(
        { searchNuggets, getPerson: vi.fn(), getDomain: vi.fn() },
        () => useNuggetSearch({ initialLoading: true }),
        {
          courseId: 7,
          catalogue_snapshot: {
            search: {
              items: [{ ...nugget, name: "Cached" }],
              aggregations: { producers: { buckets: [] } },
              results_count: 1,
            },
          },
        }
      );

    expect(hydrateFromSnapshot({ page_size: 9 })).toBe(true);
    expect(loading.value).toBe(false);
    expect(nuggets.value[0].name).toBe("Cached");
    expect(searchResult.value?.results_count).toBe(1);

    // The landing query is filed under the snapshot, so no skeleton appears.
    const pending = search({ page_size: 9 });
    expect(loading.value).toBe(false);
    expect(nuggets.value[0].name).toBe("Cached");
    await pending;

    resolveSearch({
      items: [{ ...nugget, name: "Live" }],
      results_count: 1,
      aggregations: { producers: { buckets: [] } },
    });
    await vi.waitFor(() => expect(nuggets.value[0].name).toBe("Live"));
  });

  it("still shows a skeleton for a section the snapshot cannot answer", async () => {
    let resolveSearch!: (value: unknown) => void;
    const searchNuggets = vi.fn().mockReturnValue(
      new Promise((resolve) => {
        resolveSearch = resolve;
      })
    );
    const { loading, search, hydrateFromSnapshot } = withProviders(
      { searchNuggets, getPerson: vi.fn(), getDomain: vi.fn() },
      () => useNuggetSearch(),
      {
        courseId: 7,
        catalogue_snapshot: {
          search: {
            items: [{ ...nugget, name: "Cached" }],
            aggregations: {},
            results_count: 1,
          },
        },
      }
    );

    hydrateFromSnapshot({ page_size: 9 });
    // Entering a producer is a different query: the landing cards must not
    // stand in for it, so the grid reports loading.
    const pending = search({ page_size: 9, producers: ["p1"] });
    expect(loading.value).toBe(true);

    resolveSearch({ items: [nugget], results_count: 1, aggregations: {} });
    await pending;
    expect(loading.value).toBe(false);
  });

  it("keeps the snapshot on screen when the background refresh fails", async () => {
    const searchNuggets = vi.fn().mockRejectedValue(new Error("network"));
    const { nuggets, error, search, hydrateFromSnapshot } = withProviders(
      { searchNuggets, getPerson: vi.fn(), getDomain: vi.fn() },
      () => useNuggetSearch(),
      {
        courseId: 7,
        catalogue_snapshot: {
          search: {
            items: [nugget],
            aggregations: {},
            results_count: 1,
          },
        },
      }
    );

    hydrateFromSnapshot({ page_size: 9 });
    const result = await search({ page_size: 9 });
    await new Promise((resolve) => setTimeout(resolve, 20));

    // Revalidation threw, but the teacher never sees an error over good cards.
    expect(result?.items[0].nugget_id).toBe("n1");
    expect(nuggets.value[0].name).toBe("Alpha");
    expect(error.value).toBeNull();
  });

  it("surfaces the error when there is nothing cached to fall back on", async () => {
    const searchNuggets = vi.fn().mockRejectedValue(new Error("network"));
    const { error, search } = withProviders(
      { searchNuggets, getPerson: vi.fn(), getDomain: vi.fn() },
      () => useNuggetSearch()
    );

    expect(await search({ page_size: 9, producers: ["p1"] })).toBeNull();
    expect(error.value?.message).toBe("network");
  });

  it("does not rebuild the grid when revalidation returns the same digest", async () => {
    const cached = {
      items: [nugget],
      results_count: 1,
      aggregations: {},
      _cache: { hit: true, cached_at: 10, digest: digest() },
    };
    const searchNuggets = vi
      .fn()
      .mockResolvedValueOnce(cached)
      .mockResolvedValueOnce({
        ...cached,
        items: [{ ...nugget }],
        _cache: { hit: false, cached_at: 20, digest: digest() },
      });

    const { nuggets, search } = withProviders(
      {
        searchNuggets,
        getPerson: vi.fn().mockResolvedValue(null),
        getDomain: vi.fn().mockResolvedValue(null),
      },
      () => useNuggetSearch()
    );

    await search({ page_size: 9, producers: ["p1"] });
    const painted = nuggets.value;

    await vi.waitFor(() => expect(searchNuggets).toHaveBeenCalledTimes(2));
    expect(searchNuggets.mock.calls[0][2]).toBe("cache_first");
    expect(searchNuggets.mock.calls[1][2]).toBe("revalidate");
    // Same freshness triple: the array is never reassigned, so Vue does not repaint.
    expect(nuggets.value).toBe(painted);
  });

  it("swaps the grid when revalidation reports a changed catalogue", async () => {
    let resolveLive!: (value: unknown) => void;
    const searchNuggets = vi
      .fn()
      .mockResolvedValueOnce({
        items: [{ ...nugget, name: "Stale" }],
        results_count: 1,
        aggregations: {},
        _cache: { hit: true, cached_at: 10, digest: digest() },
      })
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveLive = resolve;
        })
      );

    const { nuggets, search } = withProviders(
      {
        searchNuggets,
        getPerson: vi.fn().mockResolvedValue(null),
        getDomain: vi.fn().mockResolvedValue(null),
      },
      () => useNuggetSearch()
    );

    await search({ page_size: 9, producers: ["p1"] });
    // The cached page is on screen while NaaS is still being asked.
    expect(nuggets.value[0].name).toBe("Stale");

    resolveLive({
      items: [{ ...nugget, name: "Fresh" }],
      results_count: 2,
      aggregations: {},
      _cache: {
        hit: false,
        cached_at: 20,
        digest: digest({ results_count: 2, id_digest: "zzz" }),
      },
    });
    await vi.waitFor(() => expect(nuggets.value[0].name).toBe("Fresh"));
  });

  it("skips revalidation when the server had to go live anyway", async () => {
    const searchNuggets = vi.fn().mockResolvedValue({
      items: [nugget],
      results_count: 1,
      aggregations: {},
      _cache: { hit: false, cached_at: 0, digest: digest() },
    });

    const { search } = withProviders(
      {
        searchNuggets,
        getPerson: vi.fn().mockResolvedValue(null),
        getDomain: vi.fn().mockResolvedValue(null),
      },
      () => useNuggetSearch()
    );

    await search({ page_size: 9, producers: ["p1"] });
    await new Promise((resolve) => setTimeout(resolve, 20));
    expect(searchNuggets).toHaveBeenCalledTimes(1);
  });

  it("repaints a query seen earlier in the session without waiting on Moodle", async () => {
    const searchNuggets = vi.fn().mockResolvedValue({
      items: [{ ...nugget, name: "Section" }],
      results_count: 1,
      aggregations: {},
      _cache: { hit: false, cached_at: 0, digest: digest() },
    });

    const { nuggets, loading, search } = withProviders(
      {
        searchNuggets,
        getPerson: vi.fn().mockResolvedValue(null),
        getDomain: vi.fn().mockResolvedValue(null),
      },
      () => useNuggetSearch()
    );

    await search({ page_size: 9, producers: ["p1"] });
    expect(searchNuggets).toHaveBeenCalledTimes(1);

    // Leaving and coming back paints synchronously; the refetch is background.
    await search({ page_size: 9, producers: ["p2"] });
    const pending = search({ page_size: 9, producers: ["p1"] });
    expect(loading.value).toBe(false);
    expect(nuggets.value[0].name).toBe("Section");
    await pending;
  });

  it("clears the refreshing affordance even when the search is superseded", async () => {
    let resolveLive!: (value: unknown) => void;
    const searchNuggets = vi
      .fn()
      .mockResolvedValueOnce({
        items: [nugget],
        results_count: 1,
        aggregations: {},
        _cache: { hit: true, cached_at: 10, digest: digest() },
      })
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveLive = resolve;
        })
      )
      .mockResolvedValue({
        items: [nugget],
        results_count: 1,
        aggregations: {},
        _cache: { hit: false, cached_at: 0, digest: digest() },
      });

    const { revalidating, search } = withProviders(
      {
        searchNuggets,
        getPerson: vi.fn().mockResolvedValue(null),
        getDomain: vi.fn().mockResolvedValue(null),
      },
      () => useNuggetSearch()
    );

    await search({ page_size: 9, producers: ["p1"] });
    expect(revalidating.value).toBe(true);

    // A new section supersedes the pending revalidation.
    await search({ page_size: 9, producers: ["p2"] });
    resolveLive({ items: [], results_count: 0, aggregations: {} });

    await vi.waitFor(() => expect(revalidating.value).toBe(false));
  });

  it("does not collapse appended pages when a revalidation lands late", async () => {
    let resolveLive!: (value: unknown) => void;
    const searchNuggets = vi
      .fn()
      .mockResolvedValueOnce({
        items: [{ ...nugget, nugget_id: "p1a" }],
        results_count: 2,
        aggregations: {},
        _cache: { hit: true, cached_at: 10, digest: digest() },
      })
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveLive = resolve;
        })
      )
      .mockResolvedValueOnce({
        items: [{ ...nugget, nugget_id: "p1b" }],
        results_count: 2,
        aggregations: {},
        _cache: { hit: false, cached_at: 0, digest: digest() },
      });

    const { nuggets, search } = withProviders(
      {
        searchNuggets,
        getPerson: vi.fn().mockResolvedValue(null),
        getDomain: vi.fn().mockResolvedValue(null),
      },
      () => useNuggetSearch()
    );

    await search({ page_size: 1, producers: ["p1"] });
    await search({ page_size: 1, producers: ["p1"], page: 1 }, true);
    expect(nuggets.value).toHaveLength(2);

    // The page-0 revalidation reports a change, but page 1 is already on screen.
    resolveLive({
      items: [{ ...nugget, nugget_id: "changed" }],
      results_count: 5,
      aggregations: {},
      _cache: {
        hit: false,
        cached_at: 20,
        digest: digest({ id_digest: "zz" }),
      },
    });
    await new Promise((resolve) => setTimeout(resolve, 20));
    expect(nuggets.value).toHaveLength(2);
  });

  it("does not look up authors already denormalised by the server", async () => {
    const getPerson = vi.fn();
    const getDomain = vi.fn();
    const searchNuggets = vi.fn().mockResolvedValue({
      items: [nugget],
      results_count: 1,
      aggregations: {},
      vocabulary: {
        persons: {
          a1: { email: "ada@example.com", firstname: "Ada", lastname: "L" },
        },
        domains: { d1: { id: "d1", label: "Math" } },
      },
      _cache: { hit: false, cached_at: 0, digest: digest() },
    });

    const { nuggets, search } = withProviders(
      { searchNuggets, getPerson, getDomain },
      () => useNuggetSearch()
    );

    await search({ page_size: 9 });
    expect(nuggets.value[0].authors_data?.[0].firstname).toBe("Ada");
    expect(nuggets.value[0].domains_data?.[0].label).toBe("Math");
    expect(getPerson).not.toHaveBeenCalled();
    expect(getDomain).not.toHaveBeenCalled();
  });

  it("skips revalidation when the catalogue stamp is unchanged", async () => {
    const searchNuggets = vi.fn().mockResolvedValue({
      items: [nugget],
      results_count: 1,
      aggregations: {},
      _cache: { hit: true, cached_at: 10, digest: digest() },
    });
    const stamp = {
      results_count: 10,
      newest_modification_date: "2026-09-17T08:00:00Z",
    };

    const { search, holdRevalidation, applyCatalogueStamp } = withProviders(
      {
        searchNuggets,
        getPerson: vi.fn().mockResolvedValue(null),
        getDomain: vi.fn().mockResolvedValue(null),
      },
      () => useNuggetSearch()
    );

    holdRevalidation();
    applyCatalogueStamp(stamp, stamp);
    await search({ page_size: 9, producers: ["p1"] });
    await new Promise((resolve) => setTimeout(resolve, 20));
    expect(searchNuggets).toHaveBeenCalledTimes(1);
    expect(searchNuggets.mock.calls[0][2]).toBe("cache_first");
  });

  it("revalidates a cached page once when the catalogue stamp moved", async () => {
    const searchNuggets = vi
      .fn()
      .mockResolvedValueOnce({
        items: [nugget],
        results_count: 1,
        aggregations: {},
        _cache: { hit: true, cached_at: 10, digest: digest() },
      })
      .mockResolvedValueOnce({
        items: [nugget],
        results_count: 1,
        aggregations: {},
        _cache: { hit: false, cached_at: 20, digest: digest() },
      });

    const { search, holdRevalidation, applyCatalogueStamp } = withProviders(
      {
        searchNuggets,
        getPerson: vi.fn().mockResolvedValue(null),
        getDomain: vi.fn().mockResolvedValue(null),
      },
      () => useNuggetSearch()
    );

    holdRevalidation();
    const pending = search({ page_size: 9, producers: ["p1"] });
    expect(searchNuggets).toHaveBeenCalledTimes(1);
    applyCatalogueStamp(
      { results_count: 11, newest_modification_date: "2026-09-17T12:00:00Z" },
      { results_count: 10, newest_modification_date: "2026-09-17T08:00:00Z" }
    );
    await pending;
    await vi.waitFor(() => expect(searchNuggets).toHaveBeenCalledTimes(2));
    expect(searchNuggets.mock.calls[1][2]).toBe("revalidate");
  });
});

describe("queryKey", () => {
  it("is stable across key order and array order", () => {
    expect(queryKey({ page_size: 9, producers: ["b", "a"] })).toBe(
      queryKey({ producers: ["a", "b"], page_size: 9 })
    );
  });

  it("treats an absent page as page 0 but distinguishes real pages", () => {
    expect(queryKey({ page_size: 9 })).toBe(
      queryKey({ page_size: 9, page: 0 })
    );
    expect(queryKey({ page_size: 9, page: 1 })).not.toBe(
      queryKey({ page_size: 9, page: 0 })
    );
  });

  it("ignores blank and empty values", () => {
    expect(queryKey({ page_size: 9, fulltext: "", tags: [] })).toBe(
      queryKey({ page_size: 9 })
    );
  });
});

describe("sameFreshness", () => {
  it("treats a missing digest as changed", () => {
    expect(sameFreshness(undefined, digest())).toBe(false);
    expect(sameFreshness(digest(), undefined)).toBe(false);
  });

  it("detects an edit that only moves the modification date", () => {
    expect(
      sameFreshness(
        digest(),
        digest({ max_modification_date: "2026-02-02T00:00:00Z" })
      )
    ).toBe(false);
  });

  it("detects a deletion that leaves the count untouched", () => {
    expect(sameFreshness(digest(), digest({ id_digest: "other" }))).toBe(false);
  });
});
