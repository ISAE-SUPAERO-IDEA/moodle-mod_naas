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
 * Tests for the results section of the search widget.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createApp, h, reactive, ref } from "vue";
import NuggetSearchResults from "./NuggetSearchResults.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import { makeNugget, providers } from "@/test/widgetFixtures";

type Callback = (entries: Array<{ isIntersecting: boolean }>) => void;
const observers: Array<{ callback: Callback }> = [];

const aircraft = makeNugget();

let mounted: Mounted | null = null;

function baseProps() {
  return {
    nuggets: [aircraft],
    loading: false,
    loadingMore: false,
    revalidating: false,
    hasMore: false,
    selectedId: null as string | null,
    filters: {} as Record<string, string[]>,
    bucketCaptions: {} as Record<string, string>,
    layout: "card" as "card" | "row",
  };
}

/** Props are reactive: the test changes them as the widget would. */
function mountResults(overrides: Partial<ReturnType<typeof baseProps>> = {}) {
  const props = reactive({ ...baseProps(), ...overrides });
  mounted = mountComponent(NuggetSearchResults, props, providers());
  return { ...mounted, props };
}

function buttonByText(root: ParentNode, text: string): HTMLButtonElement {
  const found = Array.from(root.querySelectorAll("button")).find(
    (b) => b.textContent?.trim() === text
  );
  if (!found) throw new Error(`No "${text}" button`);
  return found;
}

beforeEach(() => {
  observers.length = 0;
  vi.stubGlobal(
    "IntersectionObserver",
    vi.fn(function (callback: Callback) {
      const observer = { callback, observe: vi.fn(), disconnect: vi.fn() };
      observers.push(observer);
      return observer;
    })
  );
});

afterEach(() => {
  mounted?.unmount();
  mounted = null;
  document.body.innerHTML = "";
  vi.unstubAllGlobals();
});

describe("NuggetSearchResults", () => {
  it("goes back to the catalogue", () => {
    const { el, emitted } = mountResults();
    const back = el.querySelector<HTMLButtonElement>(".browse-back-btn")!;
    expect(back.textContent?.trim()).toBe("← Back to the catalogue");
    back.click();
    expect(emitted.back).toHaveLength(1);
  });

  it("switches between cards and rows", async () => {
    const { el, emitted, props, flush } = mountResults();
    const group = el.querySelector(".layout-toggle")!;
    expect(group.getAttribute("role")).toBe("group");
    const cards = buttonByText(el, "Cards");
    const rows = buttonByText(el, "List");
    expect(cards.getAttribute("aria-pressed")).toBe("true");
    expect(cards.classList.contains("layout-toggle-btn--active")).toBe(true);
    expect(rows.getAttribute("aria-pressed")).toBe("false");

    rows.click();
    expect(emitted["update:layout"]).toEqual([["row"]]);
    props.layout = "row";
    await flush();
    expect(rows.getAttribute("aria-pressed")).toBe("true");
    expect(
      el
        .querySelector("ul.nugget-grid")
        ?.classList.contains("nugget-grid--rows")
    ).toBe(true);

    cards.click();
    expect(emitted["update:layout"]).toEqual([["row"], ["card"]]);
  });

  it("forwards chip removals and clearing", () => {
    const { el, emitted } = mountResults({
      filters: { level: ["beginner"], language: ["fr"] },
      bucketCaptions: { beginner: "Beginner" },
    });
    expect(el.querySelector(".naas-chip__label")?.textContent).toBe("Beginner");
    el.querySelector<HTMLButtonElement>(".naas-chip__close")!.click();
    expect(emitted.removeFilter).toEqual([["level", "beginner"]]);
    buttonByText(el, "Clear filters").click();
    expect(emitted.clearFilters).toHaveLength(1);
  });

  it("forwards the picked Nugget", () => {
    const { el, emitted } = mountResults();
    buttonByText(el, "Select").click();
    expect(emitted.select).toEqual([[aircraft]]);
  });

  it("says when it loads, and more quietly when it checks for updates", async () => {
    const { el, props, flush } = mountResults({ loading: true });
    const status = () => el.querySelectorAll('[role="status"]');
    expect(status()).toHaveLength(1);
    expect(el.querySelector(".nugget-loading-banner")).not.toBeNull();

    props.loading = false;
    props.loadingMore = true;
    props.revalidating = true;
    await flush();
    expect(el.querySelector(".nugget-loading-banner")).not.toBeNull();
    expect(el.querySelector(".nugget-refreshing-msg")).toBeNull();

    props.loadingMore = false;
    await flush();
    const quiet = el.querySelector(".nugget-refreshing-msg")!;
    expect(quiet.getAttribute("aria-live")).toBe("polite");
    expect(quiet.textContent?.trim()).toBe("Checking for updates");

    props.revalidating = false;
    await flush();
    expect(status()).toHaveLength(0);
  });

  it("asks for the next page when the sentinel nears the viewport", async () => {
    const { el, emitted, props, flush } = mountResults({
      hasMore: true,
      loading: true,
    });
    expect(el.querySelector(".load-more-sentinel")).toBeNull();
    props.loading = false;
    await flush();
    const sentinel = el.querySelector(".load-more-sentinel")!;
    expect(sentinel.getAttribute("aria-hidden")).toBe("true");
    observers[observers.length - 1].callback([{ isIntersecting: true }]);
    expect(emitted.more).toHaveLength(1);
  });

  it("tells the widget whether the sentinel is still in reach", async () => {
    const el = document.createElement("div");
    document.body.appendChild(el);
    const results = ref<{ sentinelIsNear: () => boolean } | null>(null);
    const props = reactive({ ...baseProps(), hasMore: true });
    const app = createApp({
      render: () => h(NuggetSearchResults, { ...props, ref: results }),
    });
    const provided = providers();
    for (const key of Reflect.ownKeys(provided)) {
      app.provide(key as string, provided[key as string]);
    }
    app.mount(el);
    await new Promise((r) => setTimeout(r, 0));
    // jsdom lays nothing out: the sentinel sits at the top, so it is near.
    expect(results.value?.sentinelIsNear()).toBe(true);
    props.hasMore = false;
    await new Promise((r) => setTimeout(r, 0));
    expect(results.value?.sentinelIsNear()).toBe(false);
    app.unmount();
  });
});
