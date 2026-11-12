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
 * Tests for the v-click-outside directive.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, it, expect, vi } from "vitest";
import { defineComponent, h, withDirectives } from "vue";
import { vClickOutside } from "./clickOutside";
import { mountComponent } from "@/test/mountComponent";

describe("vClickOutside", () => {
  it("calls its value on clicks outside the element only, until unmounted", () => {
    const onOutside = vi.fn();
    const Box = defineComponent({
      setup: () => () =>
        withDirectives(h("div", { class: "box" }, [h("span", "inside")]), [
          [vClickOutside, onOutside],
        ]),
    });
    const { el, unmount } = mountComponent(Box);

    (el.querySelector(".box span") as HTMLElement).click();
    expect(onOutside).not.toHaveBeenCalled();

    document.body.click();
    expect(onOutside).toHaveBeenCalledTimes(1);

    unmount();
    document.body.click();
    expect(onOutside).toHaveBeenCalledTimes(1);
  });

  it("ignores an unmount without a registered handler", () => {
    const el = document.createElement("div");
    expect(() =>
      (vClickOutside.unmounted as (el: HTMLElement) => void)(el)
    ).not.toThrow();
  });
});
