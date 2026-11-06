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
 * Tests for the saved Nugget's freshness rule: the nightly task revalidates
 * it, so a recent stored copy needs no NaaS call when the form opens.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it, vi } from "vitest";
import { createApp } from "vue";
import {
  isRecent,
  NUGGET_FRESH_SECONDS,
  useNuggetSearch,
} from "./useNuggetSearch";
import { NAAS_API_KEY } from "@/plugins/naas-api.plugin";

const digest = { modification_date: "2026-11-06T10:00:00Z", version_id: "v1" };

function stored(cachedAt: number) {
  return {
    nugget_id: "n1",
    name: "Alpha",
    authors: [],
    domains: [],
    _cache: { hit: true, cached_at: cachedAt, digest },
  };
}

function nuggetSearch(service: object) {
  const app = createApp({});
  app.provide(NAAS_API_KEY, {
    getPerson: vi.fn().mockResolvedValue(null),
    getDomain: vi.fn().mockResolvedValue(null),
    ...service,
  });
  app.provide("naasConfig", { courseId: 7 });
  return app.runWithContext(() => useNuggetSearch());
}

describe("isRecent", () => {
  it("trusts a copy written less than NUGGET_FRESH_SECONDS ago", () => {
    const now = 2_000_000_000_000;
    expect(isRecent(now / 1000 - 60, now)).toBe(true);
    expect(isRecent(now / 1000 - NUGGET_FRESH_SECONDS + 1, now)).toBe(true);
    expect(isRecent(now / 1000 - NUGGET_FRESH_SECONDS, now)).toBe(false);
    expect(isRecent(0, now)).toBe(false);
  });
});

describe("getNuggetById and the nightly revalidation", () => {
  it("does not ask NaaS again for a copy the nightly task refreshed", async () => {
    const getNugget = vi
      .fn()
      .mockResolvedValue(stored(Math.floor(Date.now() / 1000) - 3600));
    const onRevalidated = vi.fn();
    const { getNuggetById } = nuggetSearch({ getNugget });

    const nugget = await getNuggetById("n1", onRevalidated);
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(nugget?.name).toBe("Alpha");
    expect(getNugget).toHaveBeenCalledTimes(1);
    expect(getNugget).toHaveBeenCalledWith("n1", 7, "cache_first");
    expect(onRevalidated).not.toHaveBeenCalled();
  });

  it("still revalidates an older copy in the background", async () => {
    const getNugget = vi
      .fn()
      .mockResolvedValueOnce(stored(1000))
      .mockResolvedValueOnce({
        ...stored(Math.floor(Date.now() / 1000)),
        name: "Alpha 2",
        _cache: {
          hit: false,
          cached_at: 0,
          digest: { ...digest, version_id: "v2" },
        },
      });
    const onRevalidated = vi.fn();
    const { getNuggetById } = nuggetSearch({ getNugget });

    await getNuggetById("n1", onRevalidated);

    await vi.waitFor(() => expect(onRevalidated).toHaveBeenCalledTimes(1));
    expect(getNugget).toHaveBeenLastCalledWith("n1", 7, "revalidate");
    expect(onRevalidated.mock.calls[0][0].name).toBe("Alpha 2");
  });
});
