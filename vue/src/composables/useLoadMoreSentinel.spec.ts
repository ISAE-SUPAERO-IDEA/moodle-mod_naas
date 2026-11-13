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
 * Tests for useLoadMoreSentinel.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { nextTick } from "vue";
import {
  LOAD_MORE_MARGIN_PX,
  useLoadMoreSentinel,
} from "./useLoadMoreSentinel";

type Callback = (entries: Array<{ isIntersecting: boolean }>) => void;

const observers: Array<{
  callback: Callback;
  observe: ReturnType<typeof vi.fn>;
  disconnect: ReturnType<typeof vi.fn>;
}> = [];

beforeEach(() => {
  observers.length = 0;
  vi.stubGlobal(
    "IntersectionObserver",
    // A regular function: the code under test calls it with `new`.
    vi.fn(function (callback: Callback) {
      const observer = { callback, observe: vi.fn(), disconnect: vi.fn() };
      observers.push(observer);
      return observer;
    })
  );
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("useLoadMoreSentinel", () => {
  it("calls back when the sentinel comes into view", async () => {
    const onNear = vi.fn();
    const { sentinel } = useLoadMoreSentinel(onNear);
    sentinel.value = document.createElement("div");
    await nextTick();

    expect(observers).toHaveLength(1);
    expect(observers[0].observe).toHaveBeenCalledWith(sentinel.value);
    observers[0].callback([{ isIntersecting: false }]);
    expect(onNear).not.toHaveBeenCalled();
    observers[0].callback([{ isIntersecting: true }]);
    expect(onNear).toHaveBeenCalledTimes(1);
  });

  it("stops observing when the sentinel goes away", async () => {
    const { sentinel } = useLoadMoreSentinel(vi.fn());
    sentinel.value = document.createElement("div");
    await nextTick();
    sentinel.value = null;
    await nextTick();

    expect(observers[0].disconnect).toHaveBeenCalled();
  });

  it("reports whether the sentinel is within the load margin", () => {
    const { sentinel, sentinelIsNear } = useLoadMoreSentinel(vi.fn());
    expect(sentinelIsNear()).toBe(false);

    const el = document.createElement("div");
    sentinel.value = el;
    const top = (value: number) =>
      vi
        .spyOn(el, "getBoundingClientRect")
        .mockReturnValue({ top: value } as DOMRect);

    top(window.innerHeight + LOAD_MORE_MARGIN_PX - 1);
    expect(sentinelIsNear()).toBe(true);
    top(window.innerHeight + LOAD_MORE_MARGIN_PX + 1);
    expect(sentinelIsNear()).toBe(false);
  });
});
