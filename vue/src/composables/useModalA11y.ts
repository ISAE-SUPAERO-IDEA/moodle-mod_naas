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
 * Keyboard and focus behaviour shared by the widget modals.
 *
 * While the modal is open, Escape closes it, focus moves into the dialog and
 * stays there: Tab and Shift+Tab cycle through the dialog's controls. When it
 * closes, focus goes back to the element that opened it.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { nextTick, onBeforeUnmount, watch } from "vue";
import type { Ref } from "vue";

const FOCUSABLE = [
  "a[href]",
  "area[href]",
  "button:not([disabled])",
  "input:not([disabled]):not([type='hidden'])",
  "select:not([disabled])",
  "textarea:not([disabled])",
  "iframe",
  "[contenteditable]:not([contenteditable='false'])",
  "[tabindex]:not([tabindex='-1'])",
].join(",");

/** Controls Tab can reach inside `root`, in document order. */
export function focusableIn(root: HTMLElement): HTMLElement[] {
  return Array.from(root.querySelectorAll<HTMLElement>(FOCUSABLE)).filter(
    (el) => el.tabIndex >= 0 && !el.closest("[hidden], [inert]")
  );
}

export function useModalA11y(
  visible: () => boolean,
  dialog: Ref<HTMLElement | null>,
  close: () => void
): void {
  let opener: HTMLElement | null = null;

  /** Keep Tab and Shift+Tab inside the dialog: wrap at either end. */
  function trapTab(event: KeyboardEvent, root: HTMLElement): void {
    const items = focusableIn(root);
    if (!items.length) {
      event.preventDefault();
      root.focus();
      return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    const active = document.activeElement;
    const inside = active instanceof Node && root.contains(active);
    if (event.shiftKey && (!inside || active === first || active === root)) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && (!inside || active === last)) {
      event.preventDefault();
      first.focus();
    }
  }

  function onKeydown(event: KeyboardEvent): void {
    if (event.key === "Escape") {
      event.preventDefault();
      close();
      return;
    }
    if (event.key === "Tab" && dialog.value) {
      trapTab(event, dialog.value);
    }
  }

  /**
   * Focus can still leave without a keydown here, e.g. tabbing out of an
   * iframe, whose keys never reach this document. Pull it back in.
   */
  function onFocusin(event: FocusEvent): void {
    const root = dialog.value;
    const target = event.target;
    if (!root || !(target instanceof Node) || root.contains(target)) {
      return;
    }
    (focusableIn(root)[0] ?? root).focus();
  }

  function release(): void {
    document.removeEventListener("keydown", onKeydown);
    document.removeEventListener("focusin", onFocusin);
    if (opener && document.contains(opener)) {
      opener.focus();
    }
    opener = null;
  }

  watch(
    visible,
    async (open) => {
      if (!open) {
        release();
        return;
      }
      opener =
        document.activeElement instanceof HTMLElement
          ? document.activeElement
          : null;
      document.addEventListener("keydown", onKeydown);
      document.addEventListener("focusin", onFocusin);
      await nextTick();
      dialog.value?.focus();
    },
    { immediate: true }
  );

  onBeforeUnmount(release);
}
