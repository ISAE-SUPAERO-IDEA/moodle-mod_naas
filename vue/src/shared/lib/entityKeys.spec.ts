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
 * Tests for the structure and person key helpers.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it } from "vitest";
import {
  isOpaqueEntityKey,
  isRawEntityLabel,
  lookupLabel,
  normalizePersonKey,
  normalizeStructureKey,
} from "./entityKeys";

describe("entity keys", () => {
  it("recognises a hash as an opaque key", () => {
    const key =
      "a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2";
    expect(isOpaqueEntityKey(key)).toBe(true);
  });

  it("strips a managed_by:structure prefix from aggregation keys", () => {
    const id = "06d37c13-6ffe-4c4a-a9e3-ac227652f98c";
    expect(normalizeStructureKey(`managed_by:structure:${id}`)).toBe(id);
  });

  it("strips relationship prefixes and looks up labels by either form", () => {
    expect(normalizePersonKey("authored_by:person:abcdef0123456789")).toBe(
      "abcdef0123456789"
    );
    expect(normalizeStructureKey("managed_by:structure:isae-uuid")).toBe(
      "isae-uuid"
    );
    expect(
      lookupLabel(
        { "isae-uuid": "ISAE" },
        "managed_by:structure:isae-uuid",
        normalizeStructureKey
      )
    ).toBe("ISAE");
    expect(isRawEntityLabel("authored_by:person:abcdef0123456789")).toBe(true);
    expect(isRawEntityLabel("ISAE", "managed_by:structure:x")).toBe(false);
  });
});

describe("lookupLabel", () => {
  const labels = { "ISAE-UUID": "ISAE", plain: "Plain" };

  it("returns nothing without a label map", () => {
    expect(lookupLabel(undefined, "plain", normalizeStructureKey)).toBe("");
  });

  it("prefers the exact key", () => {
    expect(lookupLabel(labels, "plain", normalizeStructureKey)).toBe("Plain");
  });

  it("matches a differently prefixed or cased key", () => {
    expect(
      lookupLabel(
        labels,
        "managed_by:structure:isae-uuid",
        normalizeStructureKey
      )
    ).toBe("ISAE");
  });

  it("returns nothing for a blank key or an unknown one", () => {
    expect(lookupLabel(labels, "   ", normalizeStructureKey)).toBe("");
    expect(lookupLabel(labels, "unknown", normalizeStructureKey)).toBe("");
  });
});

describe("normalizePersonKey", () => {
  it("keeps a bare email hash as it is", () => {
    expect(normalizePersonKey(" abcdef0123456789 ")).toBe("abcdef0123456789");
  });
});

describe("isRawEntityLabel", () => {
  it("treats a blank caption, the key itself or a hash as raw", () => {
    expect(isRawEntityLabel("  ")).toBe(true);
    expect(isRawEntityLabel("isae", "isae")).toBe(true);
    expect(isRawEntityLabel("0123456789abcdef0123")).toBe(true);
  });
});
