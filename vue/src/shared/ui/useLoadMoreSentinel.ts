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
 * Infinite scroll: call back when a sentinel under the list nears the viewport.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { ref, watch } from "vue";

/** Distance below the viewport, in pixels, at which the next page loads. */
export const LOAD_MORE_MARGIN_PX = 320;

export function useLoadMoreSentinel(onNear: () => void) {
  /** Bind with ref="sentinel" on an element placed under the list. */
  const sentinel = ref<HTMLElement | null>(null);

  /** Whether the sentinel is within the load margin right now. */
  function sentinelIsNear(): boolean {
    const el = sentinel.value;
    if (!el) {
      return false;
    }
    return (
      el.getBoundingClientRect().top < window.innerHeight + LOAD_MORE_MARGIN_PX
    );
  }

  watch(
    sentinel,
    (el, _prev, onCleanup) => {
      if (!el) {
        return;
      }
      const observer = new IntersectionObserver(
        (entries) => {
          if (entries.some((entry) => entry.isIntersecting)) {
            onNear();
          }
        },
        {
          root: null,
          rootMargin: `${LOAD_MORE_MARGIN_PX}px 0px`,
          threshold: 0,
        }
      );
      observer.observe(el);
      onCleanup(() => observer.disconnect());
    },
    { flush: "post" }
  );

  return { sentinel, sentinelIsNear };
}
