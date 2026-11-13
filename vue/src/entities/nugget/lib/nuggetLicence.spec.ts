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
 * Tests for licenceRights() and rightsCaption().
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it } from "vitest";
import { licenceRights, rightsCaption } from "./nuggetLicence";

const licences = [
  { structure_id: "other", is_open_access: false, rights: ["by", "co"] },
  { structure_id: "oer", is_open_access: true, rights: ["by", "nc", "nd"] },
  { structure_id: "isae", is_open_access: false, rights: ["by", "nc", "uu"] },
];

describe("licenceRights", () => {
  it("uses the licence of the configured institute", () => {
    expect(licenceRights(licences, "isae")).toEqual(["by", "nc", "uu"]);
  });

  it("falls back to the open-access licence, then to the first one", () => {
    expect(licenceRights(licences, "unknown")).toEqual(["by", "nc", "nd"]);
    expect(licenceRights([licences[0]], undefined)).toEqual(["by", "co"]);
  });

  it("is empty without licences and skips malformed rights", () => {
    expect(licenceRights(undefined, "isae")).toEqual([]);
    expect(licenceRights([{ structure_id: "isae" }], "isae")).toEqual([]);
    expect(
      licenceRights(
        [{ structure_id: "isae", rights: ["by", "", 3 as never] }],
        "isae"
      )
    ).toEqual(["by"]);
  });
});

describe("rightsCaption", () => {
  it("names each right and spells unknown ones in capitals", () => {
    expect(
      rightsCaption(["by", "nc", "xx"], {
        license_right_by: "Attribution",
        license_right_nc: "Non-commercial use",
      })
    ).toBe("Attribution · Non-commercial use · XX");
  });
});
