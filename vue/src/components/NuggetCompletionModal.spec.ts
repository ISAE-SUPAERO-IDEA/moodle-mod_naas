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
 * Tests for the completion (rating) modal.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it, vi } from "vitest";
import { reactive } from "vue";
import NuggetCompletionModal from "./NuggetCompletionModal.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import { expectTabCycles, openDialog, pressKey } from "@/test/modalKeys";
import {
  makeConfig,
  makeNugget,
  makeService,
  providers,
} from "@/test/widgetFixtures";
import type { Nugget } from "@/types/nugget.types";

let mounted: Mounted | null = null;

/** Moodle course page links the modal reads on mount. */
function addCourseLinks(next = "https://moodle.test/mod/page/view.php?id=55") {
  document.body.insertAdjacentHTML(
    "beforeend",
    `<div class="course-button"><a href="https://moodle.test/course/view.php?id=7">Course</a></div>
     <div class="next-activity"><a href="${next}">Next</a></div>`
  );
}

async function openCompletion(nugget: Nugget = makeNugget()) {
  const service = makeService();
  const props = reactive({ nugget, visible: false });
  mounted = mountComponent(
    NuggetCompletionModal,
    props,
    providers(makeConfig(), service)
  );
  props.visible = true;
  await mounted.flush();
  return { props, service, dialog: openDialog(), ...mounted };
}

function stars(dialog: HTMLElement): HTMLElement[] {
  return Array.from(dialog.querySelectorAll<HTMLElement>('[role="radio"]'));
}

describe("NuggetCompletionModal", () => {
  afterEach(() => {
    mounted?.unmount();
    mounted = null;
    document.body.innerHTML = "";
    vi.useRealTimers();
  });

  it("is a modal dialog labelled by the rating title", async () => {
    const { dialog } = await openCompletion();
    expect(dialog.getAttribute("aria-modal")).toBe("true");
    expect(
      document.getElementById(dialog.getAttribute("aria-labelledby")!)
        ?.textContent
    ).toContain("Rate this nugget");
  });

  it("names each star and checks the one picked, by click or key", async () => {
    const { dialog, flush } = await openCompletion();
    const all = stars(dialog);
    expect(all.map((s) => s.getAttribute("aria-label"))).toEqual([
      "5 out of 5",
      "4 out of 5",
      "3 out of 5",
      "2 out of 5",
      "1 out of 5",
    ]);
    expect(all.every((s) => s.getAttribute("aria-checked") === "false")).toBe(
      true
    );

    all[1].click();
    await flush();
    expect(all[1].getAttribute("aria-checked")).toBe("true");

    all[3].focus();
    pressKey(" ");
    await flush();
    expect(all[3].getAttribute("aria-checked")).toBe("true");
    expect(all[1].getAttribute("aria-checked")).toBe("false");
  });

  it("sends the rating once as an xAPI statement", async () => {
    const { dialog, service, flush } = await openCompletion();
    const send = dialog.querySelector<HTMLButtonElement>("#send-rating")!;
    expect(send.disabled).toBe(true);

    stars(dialog)[0].click();
    await flush();
    expect(send.disabled).toBe(false);
    send.click();
    await flush();

    expect(service.postXapiStatement).toHaveBeenCalledWith({
      id: 42,
      verb: "rated",
      version_id: "v-aircraft",
      body: JSON.stringify({ raw: 5, min: 1, max: 5 }),
    });
    expect(send.disabled).toBe(true);
    expect(send.textContent?.trim()).toBe("Sent");
  });

  it("lists the learning outcomes when the Nugget has some", async () => {
    const { dialog } = await openCompletion(
      makeNugget({ learning_outcomes: ["Size a wing", "Pick an airfoil"] })
    );
    expect(
      Array.from(dialog.querySelectorAll(".learning-outcomes-list li")).map(
        (li) => li.textContent?.trim()
      )
    ).toEqual(["Size a wing", "Pick an airfoil"]);

    mounted?.unmount();
    document.body.innerHTML = "";
    const { dialog: bare } = await openCompletion();
    expect(bare.querySelector(".learning-outcomes")).toBeNull();
  });

  it("links back to the course and on to the next activity", async () => {
    addCourseLinks();
    const { dialog } = await openCompletion();
    const back = dialog.querySelector<HTMLAnchorElement>(".nav-btn-back")!;
    const next = dialog.querySelector<HTMLAnchorElement>(".nav-btn-next")!;
    expect(back.href).toBe("https://moodle.test/course/view.php?id=7");
    expect(next.href).toBe("https://moodle.test/mod/page/view.php?id=55");
  });

  it("has no next link when the course page shows none", async () => {
    const { dialog } = await openCompletion();
    expect(dialog.querySelector(".nav-btn-next")).toBeNull();
    expect(dialog.querySelector(".nav-btn-back")?.getAttribute("href")).toBe(
      "#"
    );
  });

  it("closes and scrolls to the next activity on the course page", async () => {
    addCourseLinks();
    const target = document.createElement("li");
    target.id = "module-55";
    target.scrollIntoView = vi.fn();
    document.body.appendChild(target);
    vi.useFakeTimers();
    const props = reactive({ nugget: makeNugget(), visible: true });
    mounted = mountComponent(NuggetCompletionModal, props, providers());
    await vi.runAllTimersAsync();

    openDialog().querySelector<HTMLAnchorElement>(".nav-btn-next")!.click();
    expect(mounted.emitted.close).toHaveLength(1);
    vi.advanceTimersByTime(100);
    expect(target.scrollIntoView).toHaveBeenCalledWith({
      behavior: "smooth",
      block: "start",
    });
  });

  it("closes on Escape and traps Tab from the close button to the links", async () => {
    addCourseLinks();
    const { dialog, emitted } = await openCompletion();
    const close = dialog.querySelector<HTMLElement>(".btn-close")!;
    const next = dialog.querySelector<HTMLElement>(".nav-btn-next")!;
    expect(expectTabCycles(dialog, close, next)).toEqual({
      forward: true,
      backward: true,
    });

    pressKey("Escape");
    expect(emitted.close).toHaveLength(1);
  });
});
