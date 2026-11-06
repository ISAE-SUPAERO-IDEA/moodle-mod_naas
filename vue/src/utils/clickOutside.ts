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
 * `v-click-outside` directive: calls its value when a click lands outside
 * the element. Used by the facet filter panel to close its dropdowns.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type { ObjectDirective } from "vue";

const handlers = new WeakMap<HTMLElement, (e: Event) => void>();

export const vClickOutside: ObjectDirective<HTMLElement, () => void> = {
  mounted(el, binding) {
    const handler = (e: Event) => {
      if (!el.contains(e.target as Node)) binding.value();
    };
    handlers.set(el, handler);
    document.addEventListener("click", handler);
  },
  unmounted(el) {
    const handler = handlers.get(el);
    if (handler) {
      document.removeEventListener("click", handler);
      handlers.delete(el);
    }
  },
};
