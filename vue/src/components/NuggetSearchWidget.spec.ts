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
 * Tests for the search widget of the activity form.
 *
 * The Moodle web services are replaced by spies (see widgetFixtures) and the
 * filter panel by a stub, so these specs cover the widget's own flow.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { defineComponent, h } from "vue";
import NuggetSearchWidget from "./NuggetSearchWidget.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import { pressKey } from "@/test/modalKeys";
import {
  makeConfig,
  makeNugget,
  makeSearchResult,
  makeService,
  providers,
  type FakeService,
} from "@/test/widgetFixtures";
import type { NaasConfig } from "@/types/naas-config.types";

/** The filter panel has its own specs: a stub that can emit a filter. */
vi.mock("./NuggetSearchFilter.vue", () => ({
  default: defineComponent({
    name: "NuggetSearchFilterStub",
    emits: ["filters", "captions"],
    setup(_props, { emit }) {
      return () =>
        h("button", {
          type: "button",
          class: "stub-level-filter",
          onClick: () =>
            emit("filters", { level: ["beginner"] }, { beginner: "Beginner" }),
        });
    },
  }),
}));

type Callback = (entries: Array<{ isIntersecting: boolean }>) => void;
const observers: Array<{ callback: Callback }> = [];

const aircraft = makeNugget();
const orbital = makeNugget({
  nugget_id: "n-orbital",
  name: "Orbital mechanics in five minutes",
  version_id: "v-orbital",
});
const composites = makeNugget({
  nugget_id: "n-composites",
  name: "Composite materials",
  version_id: "v-composites",
});

let mounted: Mounted | null = null;

/** The parts of the Moodle activity form the widget reads and fills. */
function addMoodleForm(storedNuggetId = "", nameError = ""): void {
  document.body.insertAdjacentHTML(
    "beforeend",
    `<form class="mform">
       <input id="id_name" name="name" value="">
       ${nameError ? `<div id="id_error_name">${nameError}</div>` : ""}
       <input type="hidden" name="nugget_id" value="${storedNuggetId}">
       <div id="fitem_id_cgu_agreement" class="fitem">
         <input type="checkbox" id="id_cgu_agreement">
       </div>
     </form>`
  );
}

async function mountWidget(
  service: FakeService = makeService(),
  config: NaasConfig = makeConfig()
) {
  mounted = mountComponent(NuggetSearchWidget, {}, providers(config, service));
  await mounted.flush();
  return { service, ...mounted };
}

function searchReturning(...pages: ReturnType<typeof makeSearchResult>[]) {
  const fn = vi.fn();
  for (const page of pages) fn.mockResolvedValueOnce(page);
  return fn.mockResolvedValue(pages[pages.length - 1]);
}

function buttonByText(root: ParentNode, text: string): HTMLButtonElement {
  const found = Array.from(root.querySelectorAll("button")).find(
    (b) => b.textContent?.trim() === text
  );
  if (!found) throw new Error(`No "${text}" button`);
  return found;
}

function card(el: HTMLElement, name: string): HTMLElement {
  const found = el.querySelector<HTMLElement>(
    `.nugget-post[aria-label="${name}"]`
  );
  if (!found) throw new Error(`No card for "${name}"`);
  return found;
}

/** Open the full catalogue from the landing, as a teacher would. */
async function openAllNuggets(m: Mounted): Promise<void> {
  m.el.querySelector<HTMLButtonElement>(".browse-card--all")!.click();
  await m.flush();
}

describe("NuggetSearchWidget", () => {
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
    vi.useRealTimers();
  });

  it("starts on the catalogue landing and checks the catalogue", async () => {
    addMoodleForm();
    const { el, service } = await mountWidget();
    expect(el.querySelector(".browse-landing")).not.toBeNull();
    expect(el.querySelector(".nugget-grid")).toBeNull();
    expect(service.checkCatalogue).toHaveBeenCalledWith(7);
    expect(service.searchNuggets).not.toHaveBeenCalled();
    // The activity details stay hidden until a Nugget is chosen.
    expect(
      document.querySelector("form.mform")?.getAttribute("data-naas-details")
    ).toBe("hidden");
  });

  describe("results list (V10)", () => {
    async function showResults() {
      const service = makeService({
        searchNuggets: searchReturning(
          makeSearchResult([aircraft, orbital, composites])
        ),
      });
      const m = await mountWidget(service);
      await openAllNuggets(m);
      return { ...m, service };
    }

    it("is a plain list of named cards, not a listbox", async () => {
      const { el, service } = await showResults();
      expect(service.searchNuggets).toHaveBeenCalledWith(
        { page_size: 9, fulltext: "" },
        7,
        "cache_first"
      );
      const list = el.querySelector("ul.nugget-grid")!;
      expect(list.getAttribute("role")).toBe("list");
      expect(list.getAttribute("aria-label")).toBe("Search Nuggets");
      const items = Array.from(list.children);
      expect(items.map((li) => li.tagName)).toEqual(["LI", "LI", "LI"]);
      expect(
        items.map((li) =>
          li.querySelector('[role="article"]')?.getAttribute("aria-label")
        )
      ).toEqual([
        "Aircraft design basics",
        "Orbital mechanics in five minutes",
        "Composite materials",
      ]);
      expect(el.querySelector('[role="listbox"], [role="option"]')).toBeNull();
      // List items are not focus stops of their own: the buttons are.
      expect(items.every((li) => !li.hasAttribute("tabindex"))).toBe(true);
    });

    it("exposes the Select buttons as not pressed", async () => {
      const { el } = await showResults();
      const selects = Array.from(
        el.querySelectorAll<HTMLButtonElement>(".nugget-btn-select")
      );
      expect(selects).toHaveLength(3);
      expect(selects.map((b) => b.getAttribute("aria-pressed"))).toEqual([
        "false",
        "false",
        "false",
      ]);
    });

    it("moves between cards with the arrow keys", async () => {
      const { el } = await showResults();
      const selects = Array.from(
        el.querySelectorAll<HTMLButtonElement>(".nugget-btn-select")
      );
      selects[0].focus();
      expect(pressKey("ArrowRight")).toBe(false);
      expect(document.activeElement).toBe(selects[1]);
      pressKey("ArrowDown");
      expect(document.activeElement).toBe(selects[2]);
      pressKey("ArrowDown");
      expect(document.activeElement).toBe(selects[2]);
      pressKey("ArrowUp");
      pressKey("ArrowLeft");
      expect(document.activeElement).toBe(selects[0]);

      // From any button of a card, not only Select.
      buttonByText(card(el, "Composite materials"), "About").focus();
      pressKey("ArrowLeft");
      expect(document.activeElement).toBe(selects[1]);
      // Other keys keep their default behaviour.
      expect(pressKey("Enter")).toBe(true);
    });

    it("switches between the card and row layouts", async () => {
      const { el, flush } = await showResults();
      const cards = buttonByText(el, "Cards");
      const rows = buttonByText(el, "List");
      expect(cards.getAttribute("aria-pressed")).toBe("true");
      rows.click();
      await flush();
      expect(rows.getAttribute("aria-pressed")).toBe("true");
      expect(cards.getAttribute("aria-pressed")).toBe("false");
      expect(
        el
          .querySelector("ul.nugget-grid")
          ?.classList.contains("nugget-grid--rows")
      ).toBe(true);
    });

    it("keeps the row layout after a Nugget is picked and replaced", async () => {
      const { el, flush } = await showResults();
      buttonByText(el, "List").click();
      await flush();
      buttonByText(card(el, "Composite materials"), "Select").click();
      await flush();
      expect(el.querySelector("ul.nugget-grid")).toBeNull();
      el.querySelector<HTMLButtonElement>(".nugget-clear-btn")!.click();
      await flush();
      expect(buttonByText(el, "List").getAttribute("aria-pressed")).toBe(
        "true"
      );
      expect(
        el
          .querySelector("ul.nugget-grid")
          ?.classList.contains("nugget-grid--rows")
      ).toBe(true);
    });

    it("says so when nothing matches", async () => {
      const m = await mountWidget();
      await openAllNuggets(m);
      expect(m.el.querySelector("ul.nugget-grid")).toBeNull();
      expect(m.el.querySelector(".no-results")?.textContent?.trim()).toBe(
        "The search returned no results, please use another keyword."
      );
    });
  });

  it("shows hidden skeleton cards while a search is in flight", async () => {
    let resolve: (value: unknown) => void = () => {};
    const service = makeService({
      searchNuggets: vi.fn(() => new Promise((r) => (resolve = r))),
    });
    const m = await mountWidget(service);
    await openAllNuggets(m);
    const skeletons = m.el.querySelector(".nugget-grid")!;
    expect(skeletons.getAttribute("aria-hidden")).toBe("true");
    expect(skeletons.querySelectorAll(".nugget-skeleton")).toHaveLength(9);
    expect(m.el.querySelector('[role="status"]')?.textContent).toContain(
      "We are collecting nugget data"
    );

    resolve(makeSearchResult([aircraft]));
    await m.flush();
    expect(m.el.querySelector(".nugget-skeleton")).toBeNull();
    expect(m.el.querySelectorAll("ul.nugget-grid > li")).toHaveLength(1);
  });

  it("selects a Nugget, fills the Moodle form, then lets it be replaced", async () => {
    addMoodleForm();
    const service = makeService({
      searchNuggets: searchReturning(makeSearchResult([aircraft, orbital])),
    });
    const m = await mountWidget(service);
    await openAllNuggets(m);

    buttonByText(card(m.el, "Aircraft design basics"), "Select").click();
    await m.flush();

    expect(m.el.querySelector("ul.nugget-grid")).toBeNull();
    expect(document.querySelector<HTMLInputElement>("#id_name")?.value).toBe(
      "Aircraft design basics"
    );
    expect(
      document.querySelector<HTMLInputElement>('[name="nugget_id"]')?.value
    ).toBe("n-aircraft");
    // The CGU checkbox is moved under the selected card.
    expect(
      m.el.querySelector(".naas-cgu-anchor #id_cgu_agreement")
    ).not.toBeNull();

    m.el.querySelector<HTMLButtonElement>(".nugget-clear-btn")!.click();
    await m.flush();
    expect(
      document.querySelector<HTMLInputElement>('[name="nugget_id"]')?.value
    ).toBe("");
    expect(m.el.querySelectorAll("ul.nugget-grid > li")).toHaveLength(2);
    expect(m.el.querySelector(".naas-cgu-anchor")).toBeNull();
  });

  it("reloads the saved selection when the form is edited", async () => {
    addMoodleForm("n-orbital");
    const service = makeService({
      getNugget: vi.fn().mockResolvedValue(orbital),
    });
    const { el, flush } = await mountWidget(service);
    await flush();
    expect(service.getNugget).toHaveBeenCalledWith(
      "n-orbital",
      7,
      "cache_first"
    );
    expect(el.querySelector(".browse-landing")).toBeNull();
    expect(el.querySelector(".nugget-post")?.getAttribute("aria-label")).toBe(
      "Orbital mechanics in five minutes"
    );
    expect(el.querySelector(".nugget-clear-btn")).not.toBeNull();
  });

  it("marks the saved Nugget as pressed when it could not be reloaded", async () => {
    addMoodleForm("n-orbital");
    const service = makeService({
      getNugget: vi.fn().mockRejectedValue(new Error("gone")),
      searchNuggets: searchReturning(makeSearchResult([aircraft, orbital])),
    });
    const m = await mountWidget(service);
    await m.flush();
    await openAllNuggets(m);
    const pressed = Array.from(
      m.el.querySelectorAll<HTMLButtonElement>(".nugget-btn-select")
    ).map((b) => b.getAttribute("aria-pressed"));
    expect(pressed).toEqual(["false", "true"]);

    // Select on the pressed card toggles the choice off.
    buttonByText(
      card(m.el, "Orbital mechanics in five minutes"),
      "Select"
    ).click();
    await m.flush();
    expect(
      Array.from(m.el.querySelectorAll(".nugget-btn-select")).map((b) =>
        b.getAttribute("aria-pressed")
      )
    ).toEqual(["false", "false"]);
  });

  it("repeats Moodle's 'no Nugget chosen' error until one is picked", async () => {
    addMoodleForm("", "Select a Nugget before saving the activity.");
    const { el } = await mountWidget();
    expect(el.querySelector(".alert-danger")?.textContent?.trim()).toBe(
      "Select a Nugget before saving the activity."
    );
  });

  it("shows search errors with a working Retry", async () => {
    const service = makeService({
      searchNuggets: vi
        .fn()
        .mockRejectedValueOnce(new Error("NaaS timeout"))
        .mockResolvedValue(makeSearchResult([aircraft])),
    });
    const m = await mountWidget(service);
    await openAllNuggets(m);
    const banner = m.el.querySelector(".naas-error-banner")!;
    expect(banner.getAttribute("role")).toBe("alert");
    expect(banner.textContent).toContain("NaaS timeout");

    buttonByText(banner, "Retry").click();
    await m.flush();
    expect(m.el.querySelector(".naas-error-banner")).toBeNull();
    expect(m.el.querySelectorAll("ul.nugget-grid > li")).toHaveLength(1);
  });

  it("says why the landing is empty when NaaS cannot be reached, and retries", async () => {
    const service = makeService({
      checkCatalogue: vi
        .fn()
        .mockRejectedValueOnce(new Error("Could not resolve host"))
        .mockResolvedValue({
          producers_digest: "d1",
          producers: [],
          aggregations: {
            producers: { buckets: [{ key: "isae", docCount: 4 }] },
          },
          results_count: 4,
        }),
    });
    const m = await mountWidget(service);

    const banner = m.el.querySelector(".naas-error-banner")!;
    expect(banner.getAttribute("role")).toBe("alert");
    expect(banner.textContent).toContain(
      "Something went wrong — Could not resolve host"
    );
    expect(m.el.querySelector(".browse-landing")).toBeNull();

    buttonByText(banner, "Retry").click();
    await m.flush();
    expect(service.checkCatalogue).toHaveBeenCalledTimes(2);
    expect(m.el.querySelector(".naas-error-banner")).toBeNull();
    expect(m.el.querySelector(".browse-landing")).not.toBeNull();
  });

  it("does not add 'no results' under a failed search", async () => {
    const service = makeService({
      searchNuggets: vi.fn().mockRejectedValue(new Error("NaaS timeout")),
    });
    const m = await mountWidget(service);
    await openAllNuggets(m);

    expect(m.el.querySelector(".naas-error-banner")).not.toBeNull();
    expect(m.el.querySelector(".no-results")).toBeNull();
  });

  it("searches what the teacher types, once typing pauses", async () => {
    vi.useFakeTimers();
    const service = makeService({
      searchNuggets: searchReturning(makeSearchResult([aircraft])),
    });
    mounted = mountComponent(
      NuggetSearchWidget,
      {},
      providers(makeConfig(), service)
    );
    await vi.advanceTimersByTimeAsync(0);
    const input = mounted.el.querySelector<HTMLInputElement>(
      ".search-field-input"
    )!;
    input.value = "air";
    input.dispatchEvent(new Event("input"));
    input.value = "aircraft";
    input.dispatchEvent(new Event("input"));
    await vi.advanceTimersByTimeAsync(400);
    expect(service.searchNuggets).not.toHaveBeenCalled();

    await vi.advanceTimersByTimeAsync(200);
    expect(service.searchNuggets).toHaveBeenCalledTimes(1);
    expect(service.searchNuggets.mock.calls[0][0]).toEqual({
      page_size: 9,
      fulltext: "aircraft",
    });
    expect(mounted.el.querySelectorAll("ul.nugget-grid > li")).toHaveLength(1);

    // Clearing the field searches again with no text.
    mounted.el.querySelector<HTMLButtonElement>(".search-field-clear")!.click();
    await vi.advanceTimersByTimeAsync(0);
    expect(service.searchNuggets.mock.lastCall?.[0]).toEqual({
      page_size: 9,
      fulltext: "",
    });
  });

  it("opens and closes the filter panel", async () => {
    const { el, flush } = await mountWidget();
    const toggle = el.querySelector<HTMLButtonElement>(".filters-toggle-btn")!;
    expect(toggle.getAttribute("aria-expanded")).toBe("false");
    toggle.click();
    await flush();
    expect(toggle.getAttribute("aria-expanded")).toBe("true");
    toggle.click();
    await flush();
    expect(toggle.getAttribute("aria-expanded")).toBe("false");
  });

  it("filters from the panel, shows chips and clears them", async () => {
    const service = makeService({
      searchNuggets: searchReturning(makeSearchResult([aircraft])),
    });
    const m = await mountWidget(service);
    m.el.querySelector<HTMLButtonElement>(".stub-level-filter")!.click();
    await m.flush();
    expect(service.searchNuggets.mock.calls[0][0]).toEqual({
      page_size: 9,
      fulltext: "",
      level: ["beginner"],
    });
    expect(m.el.querySelector(".filters-toggle-badge")?.textContent).toBe("1");
    const chip = m.el.querySelector<HTMLButtonElement>(".naas-chip__close")!;
    expect(chip.getAttribute("aria-label")).toBe("Remove filter Beginner");

    chip.click();
    await m.flush();
    expect(service.searchNuggets.mock.lastCall?.[0]).toEqual({
      page_size: 9,
      fulltext: "",
    });
    expect(m.el.querySelector(".naas-chip__close")).toBeNull();
  });

  it("opens a producer from the landing and goes back to it", async () => {
    const service = makeService({
      checkCatalogue: vi.fn().mockResolvedValue({
        producers_digest: "d1",
        producers: [
          {
            structure_id: "isae-supaero",
            acronym: "ISAE",
            name: "ISAE-SUPAERO",
          },
        ],
        aggregations: {
          producers: { buckets: [{ key: "isae-supaero", docCount: 3 }] },
        },
        results_count: 3,
      }),
      searchNuggets: searchReturning(makeSearchResult([aircraft])),
    });
    const m = await mountWidget(service);
    await m.flush();
    m.el.querySelector<HTMLButtonElement>(".browse-card--producer")!.click();
    await m.flush();
    expect(service.searchNuggets.mock.calls[0][0]).toEqual({
      page_size: 9,
      fulltext: "",
      producers: ["isae-supaero"],
    });
    expect(m.el.querySelector(".naas-chip__label")?.textContent).toBe("ISAE");

    m.el.querySelector<HTMLButtonElement>(".browse-back-btn")!.click();
    await m.flush();
    expect(m.el.querySelector(".browse-landing")).not.toBeNull();
  });

  it("paints the landing from the injected snapshot", async () => {
    const config = makeConfig({
      catalogue_snapshot: {
        search: makeSearchResult([aircraft], {
          results_count: 25,
          aggregations: {
            producers: { buckets: [{ key: "isae-supaero", docCount: 25 }] },
          },
        }),
        producers: [
          {
            structure_id: "isae-supaero",
            acronym: "ISAE",
            name: "ISAE-SUPAERO",
          },
        ],
      },
    });
    let release: (value: unknown) => void = () => {};
    const service = makeService({
      checkCatalogue: vi.fn(() => new Promise((r) => (release = r))),
    });
    const { el } = await mountWidget(service, config);
    // Before the catalogue check answers.
    expect(el.querySelector(".browse-card--all")?.textContent).toContain(
      "25 Nuggets"
    );
    expect(
      el.querySelector(".browse-card--producer .browse-card-title")?.textContent
    ).toBe("ISAE");
    release({ producers_digest: "", producers: [], aggregations: {} });
  });

  it("keeps the producer skeletons when the snapshot lists no producer", async () => {
    const config = makeConfig({
      catalogue_snapshot: {
        search: makeSearchResult([aircraft], { results_count: 4 }),
        producers: [],
      },
    });
    const service = makeService({
      checkCatalogue: vi.fn(() => new Promise(() => {})),
    });
    const { el } = await mountWidget(service, config);
    expect(el.querySelector(".browse-card--all")?.textContent).toContain(
      "4 Nuggets"
    );
    expect(el.querySelector(".browse-card--skel")).not.toBeNull();
  });

  it("stops paging when a Nugget is picked while a page loads", async () => {
    let release: (value: unknown) => void = () => {};
    const service = makeService({
      searchNuggets: vi
        .fn()
        .mockResolvedValueOnce(
          makeSearchResult([aircraft], { results_count: 3 })
        )
        .mockImplementationOnce(() => new Promise((r) => (release = r))),
    });
    const m = await mountWidget(service);
    await openAllNuggets(m);
    observers[observers.length - 1].callback([{ isIntersecting: true }]);
    await m.flush();
    buttonByText(card(m.el, "Aircraft design basics"), "Select").click();
    await m.flush();

    release(makeSearchResult([orbital], { results_count: 3 }));
    await m.flush();
    await m.flush();
    // The list is gone: no sentinel to ask, so no third page.
    expect(service.searchNuggets).toHaveBeenCalledTimes(2);
    expect(m.el.querySelector(".nugget-clear-btn")).not.toBeNull();
  });

  it("loads the next page when the end of the list comes into view", async () => {
    const service = makeService({
      searchNuggets: searchReturning(
        makeSearchResult([aircraft], { results_count: 2 }),
        makeSearchResult([orbital], { results_count: 2 })
      ),
    });
    const m = await mountWidget(service);
    await openAllNuggets(m);
    await m.flush();
    expect(m.el.querySelector(".load-more-sentinel")).not.toBeNull();

    observers[observers.length - 1].callback([{ isIntersecting: true }]);
    await m.flush();
    await m.flush();
    expect(service.searchNuggets.mock.calls[1][0]).toEqual({
      page_size: 9,
      fulltext: "",
      page: 1,
    });
    expect(m.el.querySelectorAll("ul.nugget-grid > li")).toHaveLength(2);
    expect(m.el.querySelector(".load-more-sentinel")).toBeNull();
  });
});
