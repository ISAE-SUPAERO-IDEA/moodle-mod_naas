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
 * Tests for waitForRequirejs.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { waitForRequirejs } from "./moodle-naas-api.service";

type Win = { require?: unknown };

describe("waitForRequirejs", () => {
  beforeEach(() => {
    vi.useFakeTimers();
    delete (window as unknown as Win).require;
  });

  afterEach(() => {
    vi.useRealTimers();
    delete (window as unknown as Win).require;
  });

  it("resolves at once when RequireJS is already there", async () => {
    const loader = vi.fn();
    (window as unknown as Win).require = loader;
    await expect(waitForRequirejs()).resolves.toBe(loader);
  });

  it("resolves when RequireJS appears later", async () => {
    const promise = waitForRequirejs(50, 100);
    const loader = vi.fn();
    vi.advanceTimersByTime(300);
    (window as unknown as Win).require = loader;
    vi.advanceTimersByTime(100);
    await expect(promise).resolves.toBe(loader);
  });

  it("rejects after the last attempt instead of waiting forever", async () => {
    const promise = waitForRequirejs(3, 100);
    const check = expect(promise).rejects.toThrow(/RequireJS/);
    vi.advanceTimersByTime(300);
    await check;
  });
});
