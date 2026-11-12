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
 * Tests for unwrapNaasPayload.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it } from "vitest";
import { unwrapNaasPayload } from "./unwrapNaasPayload";

describe("unwrapNaasPayload", () => {
  it("reads payload from a wrapped JSON string", () => {
    expect(unwrapNaasPayload('{"payload":{"firstname":"Ada"}}')).toEqual({
      firstname: "Ada",
    });
  });

  it("reads a bare entity JSON string", () => {
    expect(
      unwrapNaasPayload('{"firstname":"Ada","lastname":"Lovelace"}')
    ).toEqual({
      firstname: "Ada",
      lastname: "Lovelace",
    });
  });

  it("reads payload from an already-parsed object", () => {
    expect(unwrapNaasPayload({ payload: { acronym: "ISAE" } })).toEqual({
      acronym: "ISAE",
    });
  });

  it("unwraps a JSON string nested inside payload", () => {
    expect(unwrapNaasPayload({ payload: '{"acronym":"ISAE"}' })).toEqual({
      acronym: "ISAE",
    });
  });

  it("unwraps nested payload wrappers", () => {
    expect(
      unwrapNaasPayload({ payload: { payload: { acronym: "ISAE" } } })
    ).toEqual({ acronym: "ISAE" });
  });

  it("returns plain text and blank strings untouched", () => {
    expect(unwrapNaasPayload("https://naas.test/preview")).toBe(
      "https://naas.test/preview"
    );
    expect(unwrapNaasPayload("   ")).toBe("   ");
  });

  it("returns a string that only looks like JSON untouched", () => {
    expect(unwrapNaasPayload("{not json")).toBe("{not json");
  });

  it("decodes a JSON-encoded string and a JSON list", () => {
    expect(unwrapNaasPayload('"https://naas.test/x"')).toBe(
      "https://naas.test/x"
    );
    expect(unwrapNaasPayload("[1,2]")).toEqual([1, 2]);
  });

  it("keeps a wrapper whose payload is empty", () => {
    expect(unwrapNaasPayload({ payload: null, status: 204 })).toEqual({
      payload: null,
      status: 204,
    });
  });

  it("stops after four levels of wrapping", () => {
    const four = { payload: { payload: { payload: { payload: { id: 1 } } } } };
    expect(unwrapNaasPayload(four)).toEqual({ id: 1 });
    expect(unwrapNaasPayload({ payload: four })).toEqual({
      payload: { id: 1 },
    });
  });
});
