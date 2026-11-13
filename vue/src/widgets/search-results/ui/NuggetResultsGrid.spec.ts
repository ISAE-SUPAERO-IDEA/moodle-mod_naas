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
 * Tests for the Nugget cards of the search widget.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it, vi } from "vitest";
import { reactive } from "vue";
import NuggetResultsGrid from "./NuggetResultsGrid.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import { pressKey } from "@/test/modalKeys";
import {
  makeConfig,
  makeNugget,
  makeService,
  providers,
} from "@/test/widgetFixtures";
import type { Nugget } from "@/shared/api";

const aircraft = makeNugget();
const orbital = makeNugget({
  nugget_id: "n-orbital",
  name: "Orbital mechanics in five minutes",
});

let mounted: Mounted | null = null;

function mountGrid(
  props: Partial<{
    nuggets: Nugget[];
    loading: boolean;
    loadingMore: boolean;
    layout: "card" | "row";
    selectedId: string | null;
    failed: boolean;
  }> = {},
  service = makeService()
) {
  const gridProps = reactive({
    nuggets: [] as Nugget[],
    loading: false,
    loadingMore: false,
    layout: "card",
    selectedId: null,
    ...props,
  });
  mounted = mountComponent(
    NuggetResultsGrid,
    gridProps,
    providers(makeConfig(), service)
  );
  return { ...mounted, props: gridProps };
}

function selectButtons(el: HTMLElement): HTMLButtonElement[] {
  return Array.from(el.querySelectorAll(".nugget-btn-select"));
}

function previewButtons(el: HTMLElement): HTMLButtonElement[] {
  return Array.from(el.querySelectorAll(".nugget-btn-ghost"));
}

function previewDialog(): HTMLElement | null {
  return document.getElementById("nugget-preview-modal");
}

afterEach(() => {
  mounted?.unmount();
  mounted = null;
  document.body.innerHTML = "";
});

describe("NuggetResultsGrid", () => {
  it("paints a page of hidden skeletons on the first load", () => {
    const { el } = mountGrid({ loading: true, nuggets: [aircraft] });
    const grid = el.querySelector("div.nugget-grid")!;
    expect(grid.getAttribute("aria-hidden")).toBe("true");
    expect(grid.classList.contains("nugget-grid--rows")).toBe(false);
    expect(grid.querySelectorAll(".nugget-skeleton")).toHaveLength(9);
    expect(el.querySelector("ul")).toBeNull();
  });

  it("uses row skeletons in the list layout", () => {
    const { el } = mountGrid({ loading: true, layout: "row" });
    expect(
      el
        .querySelector("div.nugget-grid")
        ?.classList.contains("nugget-grid--rows")
    ).toBe(true);
  });

  it("lists named cards and marks the pick as pressed", () => {
    const { el } = mountGrid({
      nuggets: [aircraft, orbital],
      selectedId: "n-orbital",
      layout: "row",
    });
    const list = el.querySelector("ul.nugget-grid")!;
    expect(list.getAttribute("role")).toBe("list");
    expect(list.getAttribute("aria-label")).toBe("Search Nuggets");
    expect(list.classList.contains("nugget-grid--rows")).toBe(true);
    const items = Array.from(list.querySelectorAll(":scope > li"));
    expect(
      items.map((li) =>
        li.querySelector('[role="article"]')?.getAttribute("aria-label")
      )
    ).toEqual(["Aircraft design basics", "Orbital mechanics in five minutes"]);
    expect(
      selectButtons(el).map((b) => b.getAttribute("aria-pressed"))
    ).toEqual(["false", "true"]);
    expect(
      items.map((li) =>
        li.firstElementChild?.classList.contains("nugget-post-selected")
      )
    ).toEqual([false, true]);
  });

  it("emits the Nugget whose Select button is pressed", () => {
    const { el, emitted } = mountGrid({ nuggets: [aircraft, orbital] });
    selectButtons(el)[1].click();
    expect(emitted.select).toEqual([[orbital]]);
  });

  it("previews the Nugget whose Preview button is pressed", async () => {
    const service = makeService();
    const { el, flush } = mountGrid({ nuggets: [aircraft, orbital] }, service);
    expect(previewDialog()).toBeNull();

    previewButtons(el)[1].click();
    await flush();
    expect(document.querySelectorAll('[role="dialog"]')).toHaveLength(1);
    expect(previewDialog()?.textContent).toContain(
      "Orbital mechanics in five minutes"
    );
    expect(service.getNuggetPreview).toHaveBeenCalledWith(
      orbital.version_id,
      7
    );
  });

  it("closes the preview", async () => {
    const { el, flush } = mountGrid({ nuggets: [aircraft] });
    previewButtons(el)[0].click();
    await flush();
    previewDialog()!.querySelector<HTMLButtonElement>(".btn-close")!.click();
    await vi.waitFor(() => expect(previewDialog()).toBeNull());
  });

  it("keeps the preview open while its card stays listed", async () => {
    const { el, props, flush } = mountGrid({ nuggets: [aircraft, orbital] });
    previewButtons(el)[0].click();
    await flush();

    props.nuggets = [aircraft];
    await flush();
    expect(previewDialog()?.textContent).toContain("Aircraft design basics");
  });

  it("does not reopen a preview whose card left the list and came back", async () => {
    const { el, props, flush } = mountGrid({ nuggets: [aircraft, orbital] });
    previewButtons(el)[1].click();
    await flush();
    expect(previewDialog()).not.toBeNull();

    props.nuggets = [aircraft];
    await vi.waitFor(() => expect(previewDialog()).toBeNull());

    props.nuggets = [aircraft, orbital];
    await flush();
    expect(previewDialog()).toBeNull();
  });

  it("moves between cards with the arrow keys", () => {
    const { el } = mountGrid({ nuggets: [aircraft, orbital] });
    const [first, second] = selectButtons(el);
    first.focus();
    expect(pressKey("ArrowRight")).toBe(false);
    expect(document.activeElement).toBe(second);
  });

  it("appends three hidden skeleton items while the next page loads", () => {
    const { el } = mountGrid({ nuggets: [aircraft], loadingMore: true });
    const items = el.querySelectorAll("ul.nugget-grid > li");
    expect(items).toHaveLength(4);
    expect(
      Array.from(items).map((li) => li.getAttribute("aria-hidden"))
    ).toEqual([null, "true", "true", "true"]);
  });

  it("keeps the list while a next page loads after an empty one", () => {
    const { el } = mountGrid({ loadingMore: true });
    expect(el.querySelectorAll("ul.nugget-grid > li")).toHaveLength(3);
    expect(el.querySelector(".no-results")).toBeNull();
  });

  it("does not claim no results when the search failed", () => {
    const { el } = mountGrid({ failed: true });
    expect(el.querySelector(".no-results")).toBeNull();
    expect(el.querySelector(".nugget-grid")).toBeNull();
  });

  it("says so when nothing matches", () => {
    const { el } = mountGrid();
    expect(el.querySelector(".nugget-grid")).toBeNull();
    expect(el.querySelector(".no-results")?.textContent?.trim()).toBe(
      "The search returned no results, please use another keyword."
    );
  });
});
