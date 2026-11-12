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
 * Tests for useNuggetSelection: the pick and the Moodle form fields.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it, vi } from "vitest";
import { nextTick } from "vue";
import { readStoredNuggetId, useNuggetSelection } from "./useNuggetSelection";
import { makeNugget } from "@/test/widgetFixtures";
import type { Nugget } from "@/shared/api";

const aircraft = makeNugget();
const orbital = makeNugget({ nugget_id: "n-orbital", name: "Orbital" });

function addForm(nuggetId = "", nameError = ""): HTMLFormElement {
  document.body.insertAdjacentHTML(
    "beforeend",
    `<form class="mform">
       <input id="id_name" name="name" value="">
       ${nameError ? `<div id="id_error_name">${nameError}</div>` : ""}
       <input type="hidden" name="nugget_id" value="${nuggetId}">
     </form>`
  );
  return document.querySelector("form")!;
}

function field(name: string): string | undefined {
  return document.querySelector<HTMLInputElement>(`[name="${name}"]`)?.value;
}

afterEach(() => {
  document.body.innerHTML = "";
});

describe("readStoredNuggetId", () => {
  it("reads the saved id", () => {
    addForm("n-orbital");
    expect(readStoredNuggetId()).toBe("n-orbital");
  });

  it("is empty for a new activity, a template leftover or no form", () => {
    expect(readStoredNuggetId()).toBe("");
    addForm("nugget_id");
    expect(readStoredNuggetId()).toBe("");
    document.body.innerHTML = "";
    addForm("");
    expect(readStoredNuggetId()).toBe("");
  });
});

describe("useNuggetSelection", () => {
  it("picks a Nugget, fills the form, and drops it on a second Select", () => {
    addForm();
    const s = useNuggetSelection();
    s.toggleNugget(aircraft);
    expect(s.selectedId.value).toBe("n-aircraft");
    expect(s.selectedNugget.value).toEqual(aircraft);
    expect(field("name")).toBe("Aircraft design basics");
    expect(field("nugget_id")).toBe("n-aircraft");

    s.toggleNugget(orbital);
    expect(s.selectedId.value).toBe("n-orbital");

    s.toggleNugget(orbital);
    expect(s.selectedId.value).toBeNull();
    expect(s.selectedNugget.value).toBeNull();
    expect(field("nugget_id")).toBe("");
  });

  it("clears the choice and the form fields", () => {
    addForm();
    const s = useNuggetSelection();
    s.toggleNugget(aircraft);
    s.clearSelection();
    expect(s.selectedNugget.value).toBeNull();
    expect(s.selectedId.value).toBeNull();
    expect(field("name")).toBe("");
  });

  it("keeps Moodle's error until a Nugget is chosen", async () => {
    addForm("", "Select a Nugget before saving the activity.");
    const s = useNuggetSelection();
    expect(s.formError.value).toBe(
      "Select a Nugget before saving the activity."
    );
    s.toggleNugget(aircraft);
    await nextTick();
    expect(s.formError.value).toBe("");
    // Dropping the pick again does not bring the stale error back.
    s.clearSelection();
    await nextTick();
    expect(s.formError.value).toBe("");
  });

  describe("restoreSavedNugget", () => {
    it("paints a cached hit at once, then the reloaded and newer documents", async () => {
      const form = addForm("n-aircraft");
      const s = useNuggetSelection();
      const newer = makeNugget({ name: "Aircraft design, 2nd edition" });
      let resolve: (n: Nugget | null) => void = () => {};
      let revalidated: (n: Nugget) => void = () => {};
      const loader = {
        seedFromCache: vi.fn(() => aircraft),
        getNuggetById: vi.fn(
          (_id: string, onRevalidated?: (n: Nugget) => void) => {
            revalidated = onRevalidated!;
            return new Promise<Nugget | null>((r) => (resolve = r));
          }
        ),
      };
      const done = s.restoreSavedNugget("n-aircraft", loader);
      expect(form.getAttribute("data-naas-details")).toBe("visible");
      expect(s.selectedId.value).toBe("n-aircraft");
      expect(s.selectedNugget.value).toEqual(aircraft);
      expect(s.selectedNuggetLoading.value).toBe(false);
      expect(loader.getNuggetById).toHaveBeenCalledWith(
        "n-aircraft",
        expect.any(Function)
      );

      revalidated(newer);
      expect(s.selectedNugget.value).toEqual(newer);
      resolve(orbital);
      await done;
      expect(s.selectedNugget.value).toEqual(orbital);
      expect(s.selectedNuggetLoading.value).toBe(false);
    });

    it("shows a skeleton until Moodle answers, and keeps the id if it never does", async () => {
      const s = useNuggetSelection();
      let resolve: (n: Nugget | null) => void = () => {};
      const done = s.restoreSavedNugget("n-gone", {
        seedFromCache: () => null,
        getNuggetById: () => new Promise((r) => (resolve = r)),
      });
      expect(s.selectedNuggetLoading.value).toBe(true);
      expect(s.selectedNugget.value).toBeNull();
      resolve(null);
      await done;
      expect(s.selectedNuggetLoading.value).toBe(false);
      expect(s.selectedNugget.value).toBeNull();
      // The Select button of that Nugget still shows as pressed.
      expect(s.selectedId.value).toBe("n-gone");
    });
  });
});
