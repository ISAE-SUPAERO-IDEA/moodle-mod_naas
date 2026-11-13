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
 * Tests for the integration code helpers.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it, vi } from "vitest";
import {
  integrationCodeFromTitle,
  integrationCodeMatches,
  isPublicNugget,
  needsIntegrationCode,
} from "./integrationCode";

// Codes the NaaS author app shows for these titles; the PHP side checks the same list.
const VECTORS: [string, string][] = [
  ["Introduction to flight mechanics", "878569"],
  ["introduction to flight mechanics", "637587"],
  ["Introduction to flight mechanics ", "005099"],
  ["Hello World", "786068"],
  ["École d'été", "956553"],
  ["Aérodynamique : les bases", "678476"],
  ["Mécanique du vol — partie 1", "316475"],
  ["Mécanique du vol - partie 1", "256180"],
  ["日本語のタイトル", "292531"],
  ["a", "926610"],
];

describe("integration code", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it.each(VECTORS)("gives %j the code %s", async (title, code) => {
    await expect(integrationCodeFromTitle(title)).resolves.toBe(code);
    await expect(integrationCodeMatches(title, code)).resolves.toBe(true);
  });

  it("refuses another code", async () => {
    await expect(integrationCodeMatches("Hello World", "786069")).resolves.toBe(
      false
    );
  });

  it("leaves the check to Moodle when the browser cannot hash", async () => {
    vi.stubGlobal("crypto", {});
    await expect(integrationCodeMatches("Hello World", "000000")).resolves.toBe(
      null
    );
  });

  it("reads every form of a public Nugget", () => {
    for (const value of [true, 1, "1", "true"]) {
      expect(isPublicNugget({ is_public: value })).toBe(true);
    }
    for (const value of [false, 0, "0", "false", undefined]) {
      expect(isPublicNugget({ is_public: value })).toBe(false);
    }
  });

  it("asks for a code only when the site requires it and the Nugget is not public", () => {
    expect(needsIntegrationCode({ is_public: false }, true)).toBe(true);
    expect(needsIntegrationCode({ is_public: true }, true)).toBe(false);
    expect(needsIntegrationCode({ is_public: false }, false)).toBe(false);
    expect(needsIntegrationCode({}, undefined)).toBe(false);
  });
});
