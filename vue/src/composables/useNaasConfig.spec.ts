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
 * Tests for useNaasConfig().
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, it, expect } from "vitest";
import { createApp } from "vue";
import { useNaasConfig } from "./useNaasConfig";

describe("useNaasConfig", () => {
  it("should throw an error if config is not provided", () => {
    const app = createApp({});
    expect(() => app.runWithContext(() => useNaasConfig())).toThrowError(
      /naasConfig not provided/
    );
  });

  it("should return the config if it is provided", () => {
    const app = createApp({});
    const mockConfig = { mount_point: "#test" };
    app.provide("naasConfig", mockConfig);
    const result = app.runWithContext(() => useNaasConfig());
    expect(result).toBe(mockConfig);
  });
});
