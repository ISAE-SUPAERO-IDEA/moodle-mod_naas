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
 * Tests for the active filter chips.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it } from "vitest";
import FilterChips from "./FilterChips.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import { providers } from "@/test/widgetFixtures";

let mounted: Mounted | null = null;

function mountChips(
  filters: Record<string, string[]>,
  bucketCaptions?: Record<string, string>
): Mounted {
  mounted = mountComponent(
    FilterChips,
    { filters, labels: {}, bucketCaptions },
    providers()
  );
  return mounted;
}

describe("FilterChips", () => {
  afterEach(() => {
    mounted?.unmount();
    mounted = null;
  });

  it("renders nothing without active filters", () => {
    const { el } = mountChips({});
    expect(el.querySelector(".filter-chips")).toBeNull();
  });

  it("shows one chip per value, captioned when a caption is known", () => {
    const { el } = mountChips(
      { producers: ["isae"], language: ["en", "fr"] },
      { isae: "ISAE-SUPAERO" }
    );
    const group = el.querySelector(".filter-chips")!;
    expect(group.getAttribute("role")).toBe("group");
    expect(group.getAttribute("aria-label")).toBe("Active filters");
    expect(
      Array.from(el.querySelectorAll(".naas-chip__label")).map(
        (c) => c.textContent
      )
    ).toEqual(["ISAE-SUPAERO", "en", "fr"]);
  });

  it("names each remove button after its chip and emits the facet and value", () => {
    const { el, emitted } = mountChips(
      { producers: ["isae"] },
      { isae: "ISAE-SUPAERO" }
    );
    const remove = el.querySelector<HTMLButtonElement>(".naas-chip__close")!;
    expect(remove.getAttribute("aria-label")).toBe(
      "Remove filter ISAE-SUPAERO"
    );
    remove.click();
    expect(emitted.remove).toEqual([["producers", "isae"]]);
  });

  it("offers Clear filters only from two chips on", () => {
    const { el } = mountChips({ language: ["en"] });
    expect(el.querySelector(".filter-chip-clear")).toBeNull();

    mounted?.unmount();
    const { el: two, emitted } = mountChips({ language: ["en", "fr"] });
    const clear = two.querySelector<HTMLButtonElement>(".filter-chip-clear")!;
    expect(clear.textContent?.trim()).toBe("Clear filters");
    clear.click();
    expect(emitted.clear).toHaveLength(1);
  });
});
