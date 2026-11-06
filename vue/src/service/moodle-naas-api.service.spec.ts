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
 * Tests for the Moodle web service client (requirejs wait, shared and cached calls).
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  moodleNaasApiService,
  resetWebserviceCache,
  waitForRequirejs,
} from "./moodle-naas-api.service";

type Win = { require?: unknown };

describe("waitForRequirejs", () => {
  beforeEach(() => {
    vi.useFakeTimers();
    delete (window as unknown as Win).require;
  });

  afterEach(() => {
    vi.useRealTimers();
    delete (window as unknown as Win).require;
  });

  it("resolves at once when RequireJS is already there", async () => {
    const loader = vi.fn();
    (window as unknown as Win).require = loader;
    await expect(waitForRequirejs()).resolves.toBe(loader);
  });

  it("resolves when RequireJS appears later", async () => {
    const promise = waitForRequirejs(50, 100);
    const loader = vi.fn();
    vi.advanceTimersByTime(300);
    (window as unknown as Win).require = loader;
    vi.advanceTimersByTime(100);
    await expect(promise).resolves.toBe(loader);
  });

  it("rejects after the last attempt instead of waiting forever", async () => {
    const promise = waitForRequirejs(3, 100);
    const check = expect(promise).rejects.toThrow(/RequireJS/);
    vi.advanceTimersByTime(300);
    await check;
  });
});

describe("web service calls", () => {
  type Deferred = {
    resolve: (v: unknown) => void;
    reject: (e: unknown) => void;
  };
  let deferreds: Deferred[];
  let ajaxCall: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    resetWebserviceCache();
    deferreds = [];
    ajaxCall = vi.fn(() => [
      new Promise((resolve, reject) => deferreds.push({ resolve, reject })),
    ]);
    (window as unknown as Win).require = (
      _deps: string[],
      callback: (ajax: unknown) => void
    ) => callback({ call: ajaxCall });
  });

  afterEach(() => {
    delete (window as unknown as Win).require;
  });

  async function settle(): Promise<void> {
    for (let i = 0; i < 5; i++) await Promise.resolve();
  }

  it("sends one request for identical lookups made at the same time", async () => {
    const calls = Array.from({ length: 9 }, () =>
      moodleNaasApiService.getPerson("ann@example.test", 7)
    );
    await settle();
    expect(ajaxCall).toHaveBeenCalledTimes(1);

    deferreds[0].resolve('{"payload":{"firstname":"Ann"}}');
    const people = await Promise.all(calls);
    expect(
      people.every((p) => (p as { firstname: string }).firstname === "Ann")
    ).toBe(true);

    await moodleNaasApiService.getPerson("ann@example.test", 7);
    expect(ajaxCall).toHaveBeenCalledTimes(1);
  });

  it("shares a failure with the waiting callers, then lets a retry through", async () => {
    const first = moodleNaasApiService.getStructure("isae", 7);
    const second = moodleNaasApiService.getStructure("isae", 7);
    await settle();
    deferreds[0].reject(new Error("offline"));
    await expect(first).rejects.toThrow("offline");
    await expect(second).rejects.toThrow("offline");
    expect(ajaxCall).toHaveBeenCalledTimes(1);

    const retry = moodleNaasApiService.getStructure("isae", 7);
    await settle();
    expect(ajaxCall).toHaveBeenCalledTimes(2);
    deferreds[1].resolve('{"payload":{"acronym":"ISAE"}}');
    await expect(retry).resolves.toMatchObject({ acronym: "ISAE" });
  });

  it("never shares calls that are not cached", async () => {
    void moodleNaasApiService.getNugget("n1", 7);
    void moodleNaasApiService.getNugget("n1", 7);
    await settle();
    expect(ajaxCall).toHaveBeenCalledTimes(2);
  });
});
