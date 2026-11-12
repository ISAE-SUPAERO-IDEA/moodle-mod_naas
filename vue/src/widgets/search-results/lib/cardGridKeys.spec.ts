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
 * Tests for the arrow-key moves between result cards.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, beforeEach, describe, expect, it } from "vitest";
import { onCardGridKeydown } from "./cardGridKeys";
import { pressKey } from "@/test/modalKeys";

let list: HTMLUListElement;

function selectButtons(): HTMLButtonElement[] {
  return Array.from(list.querySelectorAll(".nugget-btn-select"));
}

beforeEach(() => {
  document.body.innerHTML = `
    <ul>
      <li class="nugget-grid-item">
        <button class="nugget-btn-select">Select 1</button>
        <button class="about">About 1</button>
      </li>
      <li class="nugget-grid-item">
        <button class="nugget-btn-select">Select 2</button>
      </li>
      <li class="other"><button class="stray">Stray</button></li>
    </ul>`;
  list = document.querySelector("ul")!;
  list.addEventListener("keydown", onCardGridKeydown);
});

afterEach(() => {
  document.body.innerHTML = "";
});

describe("onCardGridKeydown", () => {
  it("moves forward and back, stopping at both ends", () => {
    const [first, second] = selectButtons();
    first.focus();
    expect(pressKey("ArrowDown")).toBe(false);
    expect(document.activeElement).toBe(second);
    pressKey("ArrowRight");
    expect(document.activeElement).toBe(second);
    pressKey("ArrowUp");
    expect(document.activeElement).toBe(first);
    pressKey("ArrowLeft");
    expect(document.activeElement).toBe(first);
  });

  it("starts from any button of a card", () => {
    list.querySelector<HTMLButtonElement>(".about")!.focus();
    pressKey("ArrowRight");
    expect(document.activeElement).toBe(selectButtons()[1]);
  });

  it("leaves other keys alone", () => {
    selectButtons()[0].focus();
    expect(pressKey("Enter")).toBe(true);
    expect(document.activeElement).toBe(selectButtons()[0]);
  });

  it("ignores keys pressed outside a card", () => {
    const stray = list.querySelector<HTMLButtonElement>(".stray")!;
    stray.focus();
    expect(pressKey("ArrowDown")).toBe(true);
    expect(document.activeElement).toBe(stray);
  });

  it("ignores events with no element target or list", () => {
    const fromWindow = new KeyboardEvent("keydown", {
      key: "ArrowDown",
      cancelable: true,
    });
    // Dispatched on window: neither target nor currentTarget is an element.
    window.addEventListener("keydown", onCardGridKeydown, { once: true });
    window.dispatchEvent(fromWindow);
    expect(fromWindow.defaultPrevented).toBe(false);

    const onText = new KeyboardEvent("keydown", {
      key: "ArrowDown",
      bubbles: true,
      cancelable: true,
    });
    // A text node target is not an HTMLElement.
    const text = document.createTextNode("x");
    list.querySelector("li")!.appendChild(text);
    text.dispatchEvent(onText);
    expect(onText.defaultPrevented).toBe(false);
  });
});
