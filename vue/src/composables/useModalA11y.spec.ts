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
 * Tests for useModalA11y.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it, vi } from "vitest";
import { createApp, defineComponent, h, nextTick, ref } from "vue";
import { focusableIn, useModalA11y } from "./useModalA11y";
import { pressKey } from "@/test/modalKeys";

type Child = () => ReturnType<typeof h>;

function mountModal(open: boolean, children?: Child) {
  const visible = ref(open);
  const close = vi.fn(() => {
    visible.value = false;
  });
  const Modal = defineComponent({
    setup() {
      const dialog = ref<HTMLElement | null>(null);
      useModalA11y(() => visible.value, dialog, close);
      return () =>
        visible.value
          ? h(
              "div",
              { ref: dialog, tabindex: -1, id: "dialog" },
              children ? children() : "content"
            )
          : null;
    },
  });
  const host = document.createElement("div");
  document.body.appendChild(host);
  const app = createApp(Modal);
  app.mount(host);
  return { visible, close, app };
}

function pressEscape(): void {
  document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));
}

const threeControls: Child = () =>
  h("div", [
    h("button", { id: "first" }, "Close"),
    h("button", { id: "disabled", disabled: true }, "Off"),
    h("a", { id: "middle", href: "#x" }, "Link"),
    h("span", { id: "skipped", tabindex: -1 }, "Not tabbable"),
    h("button", { id: "last" }, "Send"),
  ]);

async function openWith(children: Child) {
  const mounted = mountModal(false, children);
  mounted.visible.value = true;
  await nextTick();
  await nextTick();
  return mounted;
}

describe("useModalA11y", () => {
  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("closes on Escape only while open", async () => {
    const { visible, close, app } = mountModal(false);
    pressEscape();
    expect(close).not.toHaveBeenCalled();

    visible.value = true;
    await nextTick();
    pressEscape();
    expect(close).toHaveBeenCalledTimes(1);

    await nextTick();
    pressEscape();
    expect(close).toHaveBeenCalledTimes(1);
    app.unmount();
  });

  it("focuses the dialog, then gives focus back to the opener", async () => {
    const opener = document.createElement("button");
    document.body.appendChild(opener);
    opener.focus();

    const { visible, app } = mountModal(false);
    visible.value = true;
    await nextTick();
    await nextTick();
    expect(document.activeElement?.id).toBe("dialog");

    visible.value = false;
    await nextTick();
    expect(document.activeElement).toBe(opener);
    app.unmount();
  });

  it("lists only the controls Tab can reach, in order", async () => {
    const { app } = await openWith(threeControls);
    const root = document.getElementById("dialog")!;
    expect(focusableIn(root).map((el) => el.id)).toEqual([
      "first",
      "middle",
      "last",
    ]);
    app.unmount();
  });

  it("wraps Tab from the last control to the first", async () => {
    const { app } = await openWith(threeControls);
    document.getElementById("last")!.focus();

    expect(pressKey("Tab")).toBe(false);
    expect(document.activeElement?.id).toBe("first");
    app.unmount();
  });

  it("wraps Shift+Tab from the first control, or the dialog, to the last", async () => {
    const { app } = await openWith(threeControls);
    // On open the dialog itself holds focus.
    expect(document.activeElement?.id).toBe("dialog");
    expect(pressKey("Tab", true)).toBe(false);
    expect(document.activeElement?.id).toBe("last");

    document.getElementById("first")!.focus();
    expect(pressKey("Tab", true)).toBe(false);
    expect(document.activeElement?.id).toBe("last");
    app.unmount();
  });

  it("lets Tab move normally between inner controls", async () => {
    const { app } = await openWith(threeControls);
    document.getElementById("first")!.focus();
    // Not the last control: the browser's own Tab order applies.
    expect(pressKey("Tab")).toBe(true);
    document.getElementById("middle")!.focus();
    expect(pressKey("Tab", true)).toBe(true);
    app.unmount();
  });

  it("keeps focus on the dialog when it has no control", async () => {
    const { app } = await openWith(() => h("p", "Only text"));
    expect(pressKey("Tab")).toBe(false);
    expect(document.activeElement?.id).toBe("dialog");
    app.unmount();
  });

  it("pulls focus back when it lands outside the dialog", async () => {
    const outside = document.createElement("button");
    document.body.appendChild(outside);
    const { app } = await openWith(threeControls);

    outside.focus();
    expect(document.activeElement?.id).toBe("first");
    app.unmount();
  });

  it("stops trapping once closed", async () => {
    const outside = document.createElement("button");
    document.body.appendChild(outside);
    const { visible, app } = await openWith(threeControls);
    visible.value = false;
    await nextTick();

    outside.focus();
    expect(document.activeElement).toBe(outside);
    expect(pressKey("Tab")).toBe(true);
    app.unmount();
  });

  it("stops listening when unmounted", async () => {
    const { close, app } = mountModal(true);
    await nextTick();
    app.unmount();
    pressEscape();
    expect(close).not.toHaveBeenCalled();
  });
});
