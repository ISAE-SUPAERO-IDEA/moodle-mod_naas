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
 * Test helpers: keyboard events as a browser would dispatch them to a modal.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Press a key on the focused element. Returns false if a handler cancelled it. */
export function pressKey(key: string, shiftKey = false): boolean {
  const target = document.activeElement ?? document.body;
  return target.dispatchEvent(
    new KeyboardEvent("keydown", {
      key,
      shiftKey,
      bubbles: true,
      cancelable: true,
    })
  );
}

/** The open dialog (modals are teleported to body). */
export function openDialog(): HTMLElement {
  const dialog = document.querySelector<HTMLElement>('[role="dialog"]');
  if (!dialog) throw new Error("No dialog is open");
  return dialog;
}

/**
 * Walk the focus trap: from the last control, Tab must come back to the first;
 * from the first, Shift+Tab must go to the last. Returns both ends.
 */
export function expectTabCycles(
  dialog: HTMLElement,
  first: HTMLElement,
  last: HTMLElement
): { forward: boolean; backward: boolean } {
  last.focus();
  pressKey("Tab");
  const forward = document.activeElement === first && dialog.contains(first);
  first.focus();
  pressKey("Tab", true);
  const backward = document.activeElement === last && dialog.contains(last);
  return { forward, backward };
}
