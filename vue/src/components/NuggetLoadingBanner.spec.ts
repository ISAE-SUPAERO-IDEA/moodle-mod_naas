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
 * Tests for the search widget's loading status line.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it } from "vitest";
import NuggetLoadingBanner from "./NuggetLoadingBanner.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import { makeConfig, makeLabels, providers } from "@/test/widgetFixtures";

let mounted: Mounted | null = null;

afterEach(() => {
  mounted?.unmount();
  mounted = null;
});

describe("NuggetLoadingBanner", () => {
  it("is a polite status with a hidden spinner", () => {
    const labels = makeLabels();
    labels.nugget_search_collecting = "Fetching Nuggets…";
    mounted = mountComponent(
      NuggetLoadingBanner,
      {},
      providers(makeConfig({ labels }))
    );
    const status = mounted.el.querySelector(".nugget-loading-banner")!;
    expect(status.getAttribute("role")).toBe("status");
    expect(status.getAttribute("aria-live")).toBe("polite");
    expect(status.textContent?.trim()).toBe("Fetching Nuggets…");
    expect(
      status
        .querySelector(".nugget-loading-spinner")
        ?.getAttribute("aria-hidden")
    ).toBe("true");
  });

  it("falls back to English when the string is missing", () => {
    const labels = makeLabels();
    labels.nugget_search_collecting = "";
    mounted = mountComponent(
      NuggetLoadingBanner,
      {},
      providers(makeConfig({ labels }))
    );
    expect(mounted.el.textContent?.trim()).toBe(
      "We are collecting nugget data, please wait."
    );
  });
});
