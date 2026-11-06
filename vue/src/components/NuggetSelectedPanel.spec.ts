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
 * Tests for the chosen-Nugget panel and the CGU checkbox it hosts.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it } from "vitest";
import { reactive } from "vue";
import NuggetSelectedPanel from "./NuggetSelectedPanel.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import { makeNugget, providers } from "@/test/widgetFixtures";
import type { Nugget } from "@/types/nugget.types";

const aircraft = makeNugget();

let mounted: Mounted | null = null;

/** Moodle's CGU row, rendered in the form before the widget. */
function addCguRow(): HTMLElement {
  document.body.insertAdjacentHTML(
    "beforeend",
    `<form class="mform">
       <div id="fitem_id_cgu_agreement" class="fitem">
         <input type="checkbox" id="id_cgu_agreement">
       </div>
       <div id="after-cgu"></div>
     </form>`
  );
  return document.getElementById("fitem_id_cgu_agreement")!;
}

function mountPanel(nugget: Nugget | null, loading: boolean) {
  const props = reactive({ nugget, loading });
  mounted = mountComponent(NuggetSelectedPanel, props, providers());
  return { ...mounted, props };
}

function cguIsHome(row: HTMLElement): boolean {
  return row.nextElementSibling?.id === "after-cgu";
}

afterEach(() => {
  mounted?.unmount();
  mounted = null;
  document.body.innerHTML = "";
});

describe("NuggetSelectedPanel", () => {
  it("shows the chosen card with the CGU checkbox under it", async () => {
    const row = addCguRow();
    const { el, flush } = mountPanel(aircraft, false);
    await flush();
    expect(el.querySelector(".nugget-post")?.getAttribute("aria-label")).toBe(
      "Aircraft design basics"
    );
    expect(el.querySelector(".nugget-skeleton")).toBeNull();
    expect(el.querySelector(".naas-cgu-anchor")?.contains(row)).toBe(true);
    expect(row.classList.contains("naas-cgu-field")).toBe(true);
  });

  it("asks for a replacement", async () => {
    const { el, emitted, flush } = mountPanel(aircraft, false);
    await flush();
    el.querySelector<HTMLButtonElement>(".nugget-clear-btn")!.click();
    expect(emitted.replace).toHaveLength(1);
  });

  it("shows a skeleton while the saved Nugget loads, then the card", async () => {
    const row = addCguRow();
    const { el, props, flush } = mountPanel(null, true);
    await flush();
    expect(el.querySelector(".nugget-loading-banner")).not.toBeNull();
    expect(el.querySelector(".nugget-skeleton")).not.toBeNull();
    expect(el.querySelector(".naas-cgu-anchor")).toBeNull();
    expect(cguIsHome(row)).toBe(true);

    props.nugget = aircraft;
    props.loading = false;
    await flush();
    expect(el.querySelector(".nugget-loading-banner")).toBeNull();
    expect(el.querySelector(".naas-cgu-anchor")?.contains(row)).toBe(true);
  });

  it("puts the CGU row back when the card goes away", async () => {
    const row = addCguRow();
    const { props, flush } = mountPanel(aircraft, false);
    await flush();
    expect(cguIsHome(row)).toBe(false);

    props.loading = true;
    await flush();
    expect(cguIsHome(row)).toBe(true);

    props.loading = false;
    await flush();
    expect(cguIsHome(row)).toBe(false);
    mounted!.unmount();
    mounted = null;
    expect(cguIsHome(row)).toBe(true);
  });

  it("works on forms with no CGU row", async () => {
    const { el, flush } = mountPanel(aircraft, false);
    await flush();
    expect(el.querySelector(".naas-cgu-anchor")?.children).toHaveLength(0);
  });
});
