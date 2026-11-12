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
 * Tests for countsAreExact().
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it } from "vitest";
import { countsAreExact } from "./licenceCounts";

describe("countsAreExact", () => {
  it("trusts NaaS counts without a licence filter", () => {
    expect(countsAreExact(undefined)).toBe(true);
    expect(countsAreExact({ commercial: "all", access: "all" })).toBe(true);
  });

  it("distrusts them as soon as one licence filter is set", () => {
    expect(countsAreExact({ commercial: "commercial", access: "all" })).toBe(
      false
    );
    expect(countsAreExact({ commercial: "all", access: "restricted" })).toBe(
      false
    );
  });
});
