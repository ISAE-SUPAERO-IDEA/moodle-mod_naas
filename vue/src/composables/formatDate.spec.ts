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
 * Tests for formatShortDate.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it } from "vitest";
import { formatShortDate } from "./formatDate";

describe("formatShortDate", () => {
  afterEach(() => {
    document.documentElement.lang = "";
  });

  it("formats day, month and year in the requested locale", () => {
    expect(formatShortDate("2026-03-07T10:00:00Z", "fr")).toBe("07/03/2026");
    expect(formatShortDate("2026-03-07T10:00:00Z", "en-US")).toBe("03/07/2026");
  });

  it("uses the Moodle page language by default", () => {
    document.documentElement.lang = "fr";
    expect(formatShortDate("2026-12-25")).toBe("25/12/2026");
  });

  it("returns an empty string for missing or invalid dates", () => {
    expect(formatShortDate(undefined)).toBe("");
    expect(formatShortDate("")).toBe("");
    expect(formatShortDate("not a date")).toBe("");
  });

  it("falls back to the browser locale for an invalid language tag", () => {
    expect(formatShortDate("2026-03-07", "not_a_locale!")).toMatch(/2026/);
  });
});
