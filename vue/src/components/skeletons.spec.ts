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
 * Tests for the loading skeletons: decorative, hidden from assistive tech.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it } from "vitest";
import FilterSkeleton from "./FilterSkeleton.vue";
import NuggetSkeleton from "./NuggetSkeleton.vue";
import NuggetViewSkeleton from "./NuggetViewSkeleton.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";

let mounted: Mounted | null = null;

describe("skeletons", () => {
  afterEach(() => {
    mounted?.unmount();
    mounted = null;
  });

  it.each([
    ["NuggetSkeleton", NuggetSkeleton],
    ["FilterSkeleton", FilterSkeleton],
    ["NuggetViewSkeleton", NuggetViewSkeleton],
  ])("%s is hidden from screen readers", (_name, component) => {
    mounted = mountComponent(component);
    const root = mounted.el.firstElementChild!;
    expect(root.getAttribute("aria-hidden")).toBe("true");
    expect(root.textContent?.trim()).toBe("");
  });

  it("NuggetSkeleton follows the layout and staggers its sheen", () => {
    mounted = mountComponent(NuggetSkeleton, { variant: "row", delay: 2 });
    const root = mounted.el.querySelector<HTMLElement>(".nugget-skeleton")!;
    expect(root.classList.contains("nugget-skeleton--row")).toBe(true);
    expect(root.style.getPropertyValue("--skel-delay")).toBe("2");

    mounted.unmount();
    mounted = mountComponent(NuggetSkeleton);
    const card = mounted.el.querySelector<HTMLElement>(".nugget-skeleton")!;
    expect(card.classList.contains("nugget-skeleton--row")).toBe(false);
    expect(card.style.getPropertyValue("--skel-delay")).toBe("0");
  });
});
