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
 * Tests for errorMessage().
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it } from "vitest";
import { errorMessage } from "./errorMessage";

describe("errorMessage", () => {
  it("reads an Error's message", () => {
    expect(errorMessage(new Error("NaaS down"))).toBe("NaaS down");
  });

  it("reads the message of a Moodle exception object", () => {
    expect(
      errorMessage({ message: "Could not resolve host", errorcode: "x" })
    ).toBe("Could not resolve host");
  });

  it("falls back to the value as text", () => {
    expect(errorMessage("offline")).toBe("offline");
    expect(errorMessage({ errorcode: "x" })).toBe("[object Object]");
    expect(errorMessage(null)).toBe("null");
  });
});
