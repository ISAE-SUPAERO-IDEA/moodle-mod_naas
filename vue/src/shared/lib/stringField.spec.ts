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
 * Tests for stringField().
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it } from "vitest";
import { stringField } from "./stringField";

describe("stringField", () => {
  it("returns the first key that holds text, trimmed", () => {
    const person = { firstname: "  ", first_name: " Ada ", firstName: "X" };
    expect(stringField(person, ["firstname", "first_name", "firstName"])).toBe(
      "Ada"
    );
  });

  it("skips values that are not text", () => {
    expect(
      stringField({ label: 12, name: ["x"], title: "Domain" }, [
        "label",
        "name",
        "title",
      ])
    ).toBe("Domain");
  });

  it("returns nothing for a missing record or when no key matches", () => {
    expect(stringField(null, ["label"])).toBe("");
    expect(stringField(undefined, ["label"])).toBe("");
    expect(stringField({ label: "" }, ["label", "name"])).toBe("");
  });
});
