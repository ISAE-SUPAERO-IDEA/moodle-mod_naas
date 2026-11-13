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
 * Tests for the filter panel skeleton: decorative, hidden from assistive tech.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it } from "vitest";
import FilterSkeleton from "./FilterSkeleton.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";

let mounted: Mounted | null = null;

describe("FilterSkeleton", () => {
  afterEach(() => {
    mounted?.unmount();
    mounted = null;
  });

  it("is hidden from screen readers", () => {
    mounted = mountComponent(FilterSkeleton);
    const root = mounted.el.firstElementChild!;
    expect(root.getAttribute("aria-hidden")).toBe("true");
    expect(root.textContent?.trim()).toBe("");
  });
});
