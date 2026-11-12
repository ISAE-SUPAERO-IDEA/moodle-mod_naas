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
 * Tests for the search bar (text field + filter panel toggle).
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it, vi } from "vitest";
import { defineComponent, h, reactive } from "vue";
import NuggetSearchBar from "./NuggetSearchBar.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import { providers } from "@/test/widgetFixtures";

/** The filter panel has its own specs: stand in for it here. */
const filterStub = vi.hoisted(() => ({
  props: [] as Record<string, unknown>[],
}));
vi.mock("@/features/filter-nuggets/ui/NuggetSearchFilter.vue", () => ({
  default: defineComponent({
    name: "NuggetSearchFilterStub",
    props: [
      "networkAggregations",
      "networkLabels",
      "activeFilters",
      "loading",
      "prefetch",
    ],
    emits: ["filters", "captions"],
    setup(props, { emit }) {
      filterStub.props.push(props);
      return () =>
        h("div", { class: "filter-stub" }, [
          h("button", {
            class: "stub-filters",
            onClick: () =>
              emit(
                "filters",
                { level: ["beginner"] },
                { beginner: "Beginner" }
              ),
          }),
          h("button", {
            class: "stub-captions",
            onClick: () => emit("captions", { isae: "ISAE" }),
          }),
        ]);
    },
  }),
}));

let mounted: Mounted | null = null;

function mountBar(overrides: Record<string, unknown> = {}) {
  const props = reactive({
    query: "",
    open: false,
    activeFilterCount: 0,
    aggregations: {},
    labels: {},
    activeFilters: {},
    loading: false,
    ...overrides,
  });
  mounted = mountComponent(NuggetSearchBar, props, providers());
  return { props, ...mounted };
}

describe("NuggetSearchBar", () => {
  afterEach(() => {
    mounted?.unmount();
    mounted = null;
    filterStub.props.length = 0;
  });

  it("reports typing through v-model and the typing event", () => {
    const { el, emitted } = mountBar();
    const input = el.querySelector<HTMLInputElement>(".search-field-input")!;
    expect(input.placeholder).toBe("Search here");
    input.value = "aircraft";
    input.dispatchEvent(new Event("input"));
    expect(emitted["update:query"]).toEqual([["aircraft"]]);
    expect(emitted.typing).toHaveLength(1);
  });

  it("does not submit the Moodle form on Enter", () => {
    const { el } = mountBar();
    const input = el.querySelector<HTMLInputElement>(".search-field-input")!;
    const event = new KeyboardEvent("keydown", {
      key: "Enter",
      cancelable: true,
    });
    input.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(true);
  });

  it("offers a named clear button only once something is typed", async () => {
    const { el, props, emitted, flush } = mountBar();
    expect(el.querySelector(".search-field-clear")).toBeNull();

    props.query = "orbital";
    await flush();
    const clear = el.querySelector<HTMLButtonElement>(".search-field-clear")!;
    expect(clear.getAttribute("aria-label")).toBe("Clear search");
    clear.click();
    expect(emitted.clear).toHaveLength(1);
  });

  it("toggles the filter panel and exposes its state", async () => {
    const { el, props, emitted, flush } = mountBar();
    const toggle = el.querySelector<HTMLButtonElement>(".filters-toggle-btn")!;
    const panel = el.querySelector("#naas-filters-collapse")!;
    expect(toggle.getAttribute("aria-controls")).toBe("naas-filters-collapse");
    expect(toggle.getAttribute("aria-expanded")).toBe("false");
    // Closed: out of the tab order and hidden from assistive technology.
    expect(panel.hasAttribute("inert")).toBe(true);

    toggle.click();
    expect(emitted["update:open"]).toEqual([[true]]);

    props.open = true;
    await flush();
    expect(toggle.getAttribute("aria-expanded")).toBe("true");
    expect(panel.hasAttribute("inert")).toBe(false);
    expect(panel.classList.contains("filters-collapse--open")).toBe(true);
    expect(filterStub.props[filterStub.props.length - 1]?.prefetch).toBe(true);
  });

  it("badges the toggle with the active filter count", async () => {
    const { el, props, flush } = mountBar();
    expect(el.querySelector(".filters-toggle-badge")).toBeNull();
    props.activeFilterCount = 3;
    await flush();
    expect(el.querySelector(".filters-toggle-badge")?.textContent).toBe("3");
  });

  it("passes the facets down and relays the panel's events", () => {
    const aggregations = { level: { buckets: [] } };
    const activeFilters = { level: ["beginner"] };
    const { el, emitted } = mountBar({ aggregations, activeFilters });
    expect(filterStub.props[filterStub.props.length - 1]).toMatchObject({
      networkAggregations: aggregations,
      activeFilters,
      loading: false,
    });

    el.querySelector<HTMLButtonElement>(".stub-filters")!.click();
    el.querySelector<HTMLButtonElement>(".stub-captions")!.click();
    expect(emitted.filters).toEqual([
      [{ level: ["beginner"] }, { beginner: "Beginner" }],
    ]);
    expect(emitted.captions).toEqual([[{ isae: "ISAE" }]]);
  });
});
