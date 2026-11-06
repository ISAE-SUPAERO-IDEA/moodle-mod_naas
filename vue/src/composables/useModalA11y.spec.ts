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
import { useModalA11y } from "./useModalA11y";

function mountModal(open: boolean) {
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
          ? h("div", { ref: dialog, tabindex: -1, id: "dialog" }, "content")
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

  it("stops listening when unmounted", async () => {
    const { close, app } = mountModal(true);
    await nextTick();
    app.unmount();
    pressEscape();
    expect(close).not.toHaveBeenCalled();
  });
});
