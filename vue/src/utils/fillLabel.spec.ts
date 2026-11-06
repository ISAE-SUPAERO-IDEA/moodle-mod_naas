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
 * Tests for fillLabel.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it } from "vitest";
import { fillLabel } from "./fillLabel";

describe("fillLabel", () => {
  it("replaces the {$a} placeholder", () => {
    expect(fillLabel("Remove filter {$a}", "French")).toBe(
      "Remove filter French"
    );
    expect(fillLabel("{$a} minutes", 12)).toBe("12 minutes");
  });

  it("replaces every occurrence and leaves strings without placeholder alone", () => {
    expect(fillLabel("{$a} / {$a}", 3)).toBe("3 / 3");
    expect(fillLabel("Filters", 1)).toBe("Filters");
  });

  it("does not interpret $ patterns in the value", () => {
    expect(fillLabel("Remove filter {$a}", "$& $1")).toBe(
      "Remove filter $& $1"
    );
  });
});
