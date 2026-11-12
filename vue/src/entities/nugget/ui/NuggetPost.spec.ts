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
 * Tests for the NuggetPost card.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it, vi } from "vitest";
import { reactive } from "vue";
import NuggetPost from "./NuggetPost.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import { makeNugget, providers } from "@/test/widgetFixtures";

let mounted: Mounted | null = null;

function mountPost(props: Record<string, unknown>): Mounted {
  mounted = mountComponent(NuggetPost, props, providers());
  return mounted;
}

function button(el: HTMLElement, text: string): HTMLButtonElement {
  const found = Array.from(el.querySelectorAll("button")).find(
    (b) => b.textContent?.trim() === text
  );
  if (!found) throw new Error(`No "${text}" button`);
  return found;
}

describe("NuggetPost", () => {
  afterEach(() => {
    mounted?.unmount();
    mounted = null;
    document.body.innerHTML = "";
  });

  it("is an article named after the full Nugget name", () => {
    const name = "A".repeat(80);
    const { el } = mountPost({ nugget: makeNugget({ name }) });
    const card = el.querySelector(".nugget-post")!;
    expect(card.tagName).toBe("DIV");
    expect(card.getAttribute("role")).toBe("article");
    expect(card.getAttribute("aria-label")).toBe(name);
  });

  it("ties every action to the card title", () => {
    const { el } = mountPost({ nugget: makeNugget(), selection: true });
    const title = el.querySelector("h4.nugget-title")!;
    expect(title.id).not.toBe("");
    for (const text of ["Select", "About", "Preview"]) {
      expect(button(el, text).getAttribute("aria-describedby")).toBe(title.id);
    }
  });

  it("exposes the selected state on the Select button", async () => {
    const props = reactive({
      nugget: makeNugget(),
      selection: true,
      selected: false,
    });
    const { el, flush } = mountPost(props);
    expect(button(el, "Select").getAttribute("aria-pressed")).toBe("false");

    props.selected = true;
    await flush();
    expect(button(el, "Select").getAttribute("aria-pressed")).toBe("true");
  });

  it("emits SelectButton with its Nugget", () => {
    const nugget = makeNugget();
    const { el, emitted } = mountPost({ nugget, selection: true });
    button(el, "Select").click();
    expect(emitted.SelectButton).toEqual([[nugget]]);
  });

  it("offers no Select button outside the search results", () => {
    const { el } = mountPost({ nugget: makeNugget() });
    expect(el.querySelector(".nugget-btn-select")).toBeNull();
    expect(el.querySelector(".nugget-clear-btn")).toBeNull();
  });

  it("emits ReplaceButton from the clear button of the selected card", () => {
    const nugget = makeNugget();
    const { el, emitted } = mountPost({ nugget, replaceable: true });
    const clear = el.querySelector<HTMLButtonElement>(".nugget-clear-btn")!;
    expect(clear.getAttribute("aria-label")).toBe(
      "Replace the selected nugget"
    );
    clear.click();
    expect(emitted.ReplaceButton).toEqual([[nugget]]);
  });

  it("truncates long titles but keeps the full name in the tooltip", () => {
    const name = "Orbital mechanics ".repeat(6).trim();
    const { el } = mountPost({ nugget: makeNugget({ name }) });
    const title = el.querySelector("h4")!;
    expect(title.getAttribute("title")).toBe(name);
    expect(title.textContent?.trim()).toBe(name.substring(0, 60) + "…");
  });

  it("shows authors, duration, license and a Moodle-served thumbnail", () => {
    const { el } = mountPost({ nugget: makeNugget() });
    expect(el.querySelector(".nugget-authors")?.textContent?.trim()).toBe(
      "Ada Lovelace"
    );
    expect(el.querySelector(".nugget-badge-duration")?.textContent).toContain(
      "5"
    );
    expect(el.querySelector(".nugget-badge-license")?.textContent?.trim()).toBe(
      "CC BY"
    );
    const src = el.querySelector("img.nugget-thumb")!.getAttribute("src")!;
    expect(src).toContain("https://moodle.test/mod/naas/media.php?course=7");
    expect(src).toContain("w=700");
  });

  it("falls back to the raw license and hides empty badges", () => {
    const { el } = mountPost({
      nugget: makeNugget({ license: 9, duration: undefined, authors_data: [] }),
    });
    expect(el.querySelector(".nugget-badge-license")?.textContent?.trim()).toBe(
      "9"
    );
    expect(el.querySelector(".nugget-badge-duration")).toBeNull();
    expect(
      el
        .querySelector(".nugget-authors")
        ?.classList.contains("nugget-authors--empty")
    ).toBe(true);

    mounted?.unmount();
    const { el: noLicense } = mountPost({
      nugget: makeNugget({ license: "" }),
    });
    expect(noLicense.querySelector(".nugget-badge-license")).toBeNull();
  });

  it("shows the plain-text description in the row layout only", () => {
    const { el } = mountPost({ nugget: makeNugget(), variant: "row" });
    expect(el.querySelector(".nugget-post--row")).not.toBeNull();
    expect(el.querySelector(".nugget-description")?.textContent?.trim()).toBe(
      "Wings, lift and drag."
    );

    mounted?.unmount();
    const { el: card } = mountPost({ nugget: makeNugget() });
    expect(card.querySelector(".nugget-description")).toBeNull();
  });

  it("has no description or authors line for a Nugget without them", () => {
    const { el } = mountPost({
      nugget: makeNugget({ resume: "  ", authors_data: undefined }),
      variant: "row",
    });
    expect(el.querySelector(".nugget-description")).toBeNull();
    const authors = el.querySelector(".nugget-authors")!;
    expect(authors.textContent?.trim()).toBe("");
    expect(authors.classList.contains("nugget-authors--empty")).toBe(true);

    mounted?.unmount();
    const { el: noResume } = mountPost({
      nugget: makeNugget({ resume: undefined }),
      variant: "row",
    });
    expect(noResume.querySelector(".nugget-description")).toBeNull();
  });

  it("opens the About dialog and closes it again", async () => {
    const { el, flush } = mountPost({ nugget: makeNugget() });
    button(el, "About").click();
    await flush();
    const dialog = document.querySelector('[role="dialog"]')!;
    expect(dialog.textContent).toContain("About: Aircraft design basics");

    dialog.querySelector<HTMLButtonElement>(".btn-close")!.click();
    // The leave transition removes the dialog a frame later.
    await vi.waitFor(() =>
      expect(document.querySelector('[role="dialog"]')).toBeNull()
    );
  });

  it("emits PreviewButton with its Nugget and opens no dialog itself", async () => {
    const nugget = makeNugget();
    const { el, emitted, flush } = mountPost({ nugget });
    button(el, "Preview").click();
    await flush();
    expect(emitted.PreviewButton).toEqual([[nugget]]);
    expect(document.querySelector('[role="dialog"]')).toBeNull();
  });
});
