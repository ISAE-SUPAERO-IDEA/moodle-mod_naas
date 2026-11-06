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
 * Tests for the root component: Moodle's config picks the view.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it, vi } from "vitest";
import { defineComponent, h } from "vue";
import Main from "./Main.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import { makeConfig, providers } from "@/test/widgetFixtures";

vi.mock("@/components/NuggetView.vue", () => ({
  default: defineComponent({ render: () => h("p", { id: "view-stub" }) }),
}));
vi.mock("@/components/NuggetSearchWidget.vue", () => ({
  default: defineComponent({ render: () => h("p", { id: "widget-stub" }) }),
}));

let mounted: Mounted | null = null;

describe("Main", () => {
  afterEach(() => {
    mounted?.unmount();
    mounted = null;
  });

  it("shows the learner view on the activity page", () => {
    mounted = mountComponent(
      Main,
      {},
      providers(makeConfig({ component: "NuggetView" }))
    );
    expect(mounted.el.querySelector(".naas-widget #view-stub")).not.toBeNull();
    expect(mounted.el.querySelector("#widget-stub")).toBeNull();
  });

  it("shows the search widget in the activity form", () => {
    mounted = mountComponent(Main, {}, providers(makeConfig()));
    expect(mounted.el.querySelector("#widget-stub")).not.toBeNull();
    expect(mounted.el.querySelector("#view-stub")).toBeNull();
  });
});
