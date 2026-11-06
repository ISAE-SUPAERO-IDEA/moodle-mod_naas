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
 * Tests for the preview modal.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it, vi } from "vitest";
import { reactive } from "vue";
import NuggetViewModal from "./NuggetViewModal.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import { expectTabCycles, openDialog, pressKey } from "@/test/modalKeys";
import {
  makeConfig,
  makeNugget,
  makeService,
  providers,
  type FakeService,
} from "@/test/widgetFixtures";

let mounted: Mounted | null = null;

async function openPreview(service: FakeService = makeService()) {
  const props = reactive({ nugget: makeNugget(), visible: false });
  mounted = mountComponent(
    NuggetViewModal,
    props,
    providers(makeConfig(), service)
  );
  props.visible = true;
  await mounted.flush();
  return { props, service, ...mounted };
}

describe("NuggetViewModal", () => {
  afterEach(() => {
    mounted?.unmount();
    mounted = null;
    document.body.innerHTML = "";
    vi.restoreAllMocks();
  });

  it("renders nothing while hidden", () => {
    mounted = mountComponent(
      NuggetViewModal,
      { nugget: makeNugget(), visible: false },
      providers()
    );
    expect(document.getElementById("nugget-preview-modal")).toBeNull();
  });

  it("is a labelled modal dialog with the preview iframe", async () => {
    const { service } = await openPreview();
    const dialog = document.getElementById("nugget-preview-modal")!;
    expect(dialog.getAttribute("role")).toBe("dialog");
    expect(dialog.getAttribute("aria-modal")).toBe("true");
    const title = document.getElementById(
      dialog.getAttribute("aria-labelledby")!
    );
    expect(title?.textContent?.trim()).toBe("Preview: Aircraft design basics");
    expect(service.getNuggetPreview).toHaveBeenCalledWith("v-aircraft", 7);
    expect(
      document.getElementById("naas-preview-frame")?.getAttribute("src")
    ).toBe("https://naas.test/preview");
  });

  it("never loads a non-web URL into the iframe", async () => {
    const service = makeService({
      getNuggetPreview: vi.fn().mockResolvedValue("javascript:alert(1)"),
    });
    await openPreview(service);
    expect(document.getElementById("naas-preview-frame")).toBeNull();
  });

  it("keeps the dialog open without a frame when the preview fails", async () => {
    const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
    const service = makeService({
      getNuggetPreview: vi.fn().mockRejectedValue(new Error("down")),
    });
    await openPreview(service);
    expect(document.getElementById("nugget-preview-modal")).not.toBeNull();
    expect(document.getElementById("naas-preview-frame")).toBeNull();
    expect(warn).toHaveBeenCalled();
  });

  it("takes focus on open and closes on Escape", async () => {
    const { emitted } = await openPreview();
    expect(document.activeElement?.id).toBe("nugget-preview-modal");
    pressKey("Escape");
    expect(emitted.close).toHaveLength(1);
  });

  it("closes from the close button and the backdrop, not from inside", async () => {
    const { emitted } = await openPreview();
    const dialog = openDialog();
    dialog.click();
    expect(emitted.close).toBeUndefined();

    document.querySelector<HTMLElement>(".nugget-modal-backdrop")!.click();
    expect(emitted.close).toHaveLength(1);
    dialog.querySelector<HTMLButtonElement>(".btn-close")!.click();
    expect(emitted.close).toHaveLength(2);
    expect(dialog.querySelector(".btn-close")!.getAttribute("aria-label")).toBe(
      "Close"
    );
  });

  it("traps Tab between the close button and the iframe", async () => {
    await openPreview();
    const dialog = openDialog();
    const close = dialog.querySelector<HTMLElement>(".btn-close")!;
    const frame = document.getElementById("naas-preview-frame")!;
    expect(expectTabCycles(dialog, close, frame)).toEqual({
      forward: true,
      backward: true,
    });
  });

  it("drops the preview URL when hidden, and asks again on reopen", async () => {
    const { props, service, flush } = await openPreview();
    props.visible = false;
    await flush();
    props.visible = true;
    await flush();
    expect(service.getNuggetPreview).toHaveBeenCalledTimes(2);
  });
});
