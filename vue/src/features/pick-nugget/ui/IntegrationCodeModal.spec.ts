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
 * Tests for the integration code dialog.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it, vi } from "vitest";
import { reactive } from "vue";
import IntegrationCodeModal from "./IntegrationCodeModal.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import { openDialog, pressKey } from "@/test/modalKeys";
import { makeNugget, providers } from "@/test/widgetFixtures";
import type { Nugget } from "@/shared/api";

// Code of the title "Hello World" (the PHP and author-app tests use the same vector).
const hello = makeNugget({
  name: "Hello World",
  authors_data: [
    { firstname: "Ada", lastname: "Lovelace", email: "ada@example.com" },
    { firstname: "Alan", lastname: "Turing", email: "c2f1e0a9b8" },
  ],
});
const RIGHT = "786068";

let mounted: Mounted | null = null;

async function openModal(nugget: Nugget | null = hello) {
  const props = reactive({ nugget, visible: false });
  mounted = mountComponent(IntegrationCodeModal, props, providers());
  props.visible = true;
  await mounted.flush();
  await mounted.flush();
  return { props, ...mounted };
}

function boxes(): HTMLInputElement[] {
  return Array.from(document.querySelectorAll<HTMLInputElement>(".code-digit"));
}

function type(index: number, value: string): void {
  const box = boxes()[index];
  box.value = value;
  box.dispatchEvent(new Event("input", { bubbles: true }));
}

function paste(index: number, text: string): void {
  const event = new Event("paste", { bubbles: true, cancelable: true });
  Object.defineProperty(event, "clipboardData", {
    value: { getData: () => text },
  });
  boxes()[index].dispatchEvent(event);
}

function confirmButton(): HTMLButtonElement {
  return document.querySelector<HTMLButtonElement>(
    '.code-actions button[type="submit"]'
  )!;
}

afterEach(() => {
  mounted?.unmount();
  mounted = null;
  document.body.innerHTML = "";
  vi.unstubAllGlobals();
});

describe("IntegrationCodeModal", () => {
  it("renders nothing while hidden or without a Nugget", async () => {
    mounted = mountComponent(
      IntegrationCodeModal,
      { nugget: hello, visible: false },
      providers()
    );
    expect(document.querySelector('[role="dialog"]')).toBeNull();
    mounted.unmount();
    await openModal(null);
    expect(document.querySelector('[role="dialog"]')).toBeNull();
  });

  it("is a labelled dialog that names the Nugget and focuses the first digit", async () => {
    await openModal();
    const dialog = openDialog();
    expect(dialog.getAttribute("aria-modal")).toBe("true");
    const title = document.getElementById(
      dialog.getAttribute("aria-labelledby")!
    );
    expect(title?.textContent?.trim()).toBe("Enter the integration code");
    expect(
      document.getElementById(dialog.getAttribute("aria-describedby")!)
        ?.textContent
    ).toContain("6-digit integration code");
    expect(dialog.querySelector(".code-nugget-name")?.textContent).toBe(
      "Hello World"
    );
    expect(boxes().map((b) => b.getAttribute("aria-label"))).toEqual([
      "Digit 1",
      "Digit 2",
      "Digit 3",
      "Digit 4",
      "Digit 5",
      "Digit 6",
    ]);
    expect(document.activeElement).toBe(boxes()[0]);
    expect(confirmButton().disabled).toBe(true);
  });

  it("links only the authors whose email is a real address", async () => {
    await openModal();
    const items = Array.from(document.querySelectorAll(".code-authors li"));
    expect(items.map((li) => li.querySelector("strong")?.textContent)).toEqual([
      "Ada Lovelace",
      "Alan Turing",
    ]);
    expect(items[0].querySelector("a")?.getAttribute("href")).toBe(
      "mailto:ada@example.com"
    );
    expect(items[1].querySelector("a")).toBeNull();
  });

  it("says when no author can be contacted", async () => {
    await openModal(
      makeNugget({ name: "Hello World", authors_data: undefined })
    );
    expect(
      document.querySelector(".code-authors-empty")?.textContent?.trim()
    ).toBe("No author contact is available for this Nugget.");
  });

  it("moves along the boxes as digits are typed, and keeps digits only", async () => {
    await openModal();
    type(0, "7");
    expect(document.activeElement).toBe(boxes()[1]);
    type(1, "x");
    expect(boxes()[1].value).toBe("");
    expect(document.activeElement).toBe(boxes()[1]);
    type(1, "86");
    expect(
      boxes()
        .map((b) => b.value)
        .join("")
    ).toBe("786");
    expect(document.activeElement).toBe(boxes()[3]);
  });

  it("walks back with Backspace and the arrow keys", async () => {
    const { flush } = await openModal();
    type(0, "7");
    type(1, "8");
    boxes()[2].focus();
    pressKey("Backspace");
    await flush();
    expect(boxes()[1].value).toBe("");
    expect(document.activeElement).toBe(boxes()[1]);
    pressKey("ArrowLeft");
    expect(document.activeElement).toBe(boxes()[0]);
    pressKey("ArrowLeft");
    expect(document.activeElement).toBe(boxes()[0]);
    pressKey("ArrowRight");
    expect(document.activeElement).toBe(boxes()[1]);
    boxes()[5].focus();
    pressKey("ArrowRight");
    expect(document.activeElement).toBe(boxes()[5]);
    boxes()[0].focus();
    expect(pressKey("Backspace")).toBe(true);
    pressKey("Enter");
    expect(boxes()[0].value).toBe("7");
  });

  it("accepts a pasted code and emits it once it matches the title", async () => {
    const { emitted, flush } = await openModal();
    paste(2, "78 60-68");
    await flush();
    expect(
      boxes()
        .map((b) => b.value)
        .join("")
    ).toBe(RIGHT);
    expect(confirmButton().disabled).toBe(false);
    confirmButton().click();
    // The browser hashes the title asynchronously.
    await vi.waitFor(() => expect(emitted.confirm).toEqual([[RIGHT]]));
  });

  it("empties the boxes on a paste that carries no text", async () => {
    const { flush } = await openModal();
    type(0, "7");
    const event = new Event("paste", { bubbles: true, cancelable: true });
    boxes()[1].dispatchEvent(event);
    expect(event.defaultPrevented).toBe(true);
    await flush();
    expect(boxes()[0].value).toBe("");
  });

  it("refuses a wrong code, empties the boxes and says why", async () => {
    const { emitted, flush } = await openModal();
    paste(0, "000000");
    await flush();
    confirmButton().click();
    await vi.waitFor(() =>
      expect(document.querySelector(".code-error")).not.toBeNull()
    );
    await flush();
    expect(emitted.confirm).toBeUndefined();
    expect(document.querySelector(".code-error")?.textContent?.trim()).toBe(
      "This code does not match this Nugget."
    );
    expect(
      boxes().every(
        (b) => b.value === "" && b.getAttribute("aria-invalid") === "true"
      )
    ).toBe(true);
    expect(document.activeElement).toBe(boxes()[0]);
    type(0, "7");
    await flush();
    expect(document.querySelector(".code-error")).toBeNull();
  });

  it("lets Moodle check the code when the browser cannot hash", async () => {
    vi.stubGlobal("crypto", {});
    const { emitted, flush } = await openModal();
    paste(0, "000000");
    await flush();
    confirmButton().click();
    await flush();
    expect(emitted.confirm).toEqual([["000000"]]);
  });

  it("cancels from its buttons, the backdrop and Escape, and starts empty again", async () => {
    const { props, emitted, flush } = await openModal();
    document.querySelector<HTMLButtonElement>(".btn-close")!.click();
    document
      .querySelector<HTMLButtonElement>(".code-actions .btn-secondary")!
      .click();
    document.querySelector<HTMLElement>(".nugget-modal-backdrop")!.click();
    openDialog().click();
    pressKey("Escape");
    expect(emitted.cancel).toHaveLength(4);

    paste(0, "123");
    props.visible = false;
    await flush();
    props.visible = true;
    await flush();
    await flush();
    expect(boxes().every((b) => b.value === "")).toBe(true);
  });

  it("selects the digit of a box that gets the focus", async () => {
    await openModal();
    type(0, "7");
    const box = boxes()[0];
    const select = vi.spyOn(box, "select");
    box.dispatchEvent(new FocusEvent("focus"));
    expect(select).toHaveBeenCalled();
  });
});
