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
 * While the modal is open, Escape closes it and focus moves into the dialog.
 * When it closes, focus goes back to the element that opened it.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { nextTick, onBeforeUnmount, watch } from "vue";
import type { Ref } from "vue";

export function useModalA11y(
  visible: () => boolean,
  dialog: Ref<HTMLElement | null>,
  close: () => void
): void {
  let opener: HTMLElement | null = null;

  function onKeydown(event: KeyboardEvent): void {
    if (event.key === "Escape") {
      event.preventDefault();
      close();
    }
  }

  function release(): void {
    document.removeEventListener("keydown", onKeydown);
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
      await nextTick();
      dialog.value?.focus();
    },
    { immediate: true }
  );

  onBeforeUnmount(release);
}
