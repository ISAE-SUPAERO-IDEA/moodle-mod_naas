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
 * Test helper: mount a component in jsdom without @vue/test-utils.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { createApp, h, nextTick, type Component } from "vue";

export interface Mounted {
  /** The mounted root element's container. */
  el: HTMLElement;
  /** Events emitted by the component: name → list of argument arrays. */
  emitted: Record<string, unknown[][]>;
  /** Wait for Vue to flush pending updates (and resolved promises). */
  flush: () => Promise<void>;
  unmount: () => void;
}

/**
 * Mount `component` with `props` into a fresh element attached to document.body.
 *
 * Every emitted event is recorded in `emitted`; `provide` entries are provided at app level.
 */
export function mountComponent(
  component: Component,
  props: Record<string, unknown> = {},
  provide: Record<string | symbol, unknown> = {}
): Mounted {
  const el = document.createElement("div");
  document.body.appendChild(el);
  const emitted: Record<string, unknown[][]> = {};
  const listeners: Record<string, (...args: unknown[]) => void> = {};
  const emits = (component as { emits?: string[] | Record<string, unknown> })
    .emits;
  const names = Array.isArray(emits) ? emits : Object.keys(emits ?? {});
  for (const name of names) {
    const key = "on" + name.charAt(0).toUpperCase() + name.slice(1);
    listeners[key] = (...args: unknown[]) => {
      (emitted[name] ??= []).push(args);
    };
  }
  const app = createApp({
    render: () => h(component, { ...props, ...listeners }),
  });
  for (const key of Reflect.ownKeys(provide)) {
    app.provide(key as string, provide[key as string]);
  }
  app.mount(el);
  return {
    el,
    emitted,
    flush: async () => {
      await new Promise((resolve) => setTimeout(resolve, 0));
      await nextTick();
    },
    unmount: () => {
      app.unmount();
      el.remove();
    },
  };
}
