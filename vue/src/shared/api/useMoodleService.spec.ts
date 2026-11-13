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
 * Tests for useMoodleService().
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, it, expect } from "vitest";
import { createApp } from "vue";
import { useMoodleService } from "./useMoodleService";
import { NAAS_API_KEY } from "./naasApiKey";

describe("useMoodleService", () => {
  it("should throw an error if service is not provided", () => {
    const app = createApp({});
    expect(() => app.runWithContext(() => useMoodleService())).toThrowError(
      /naasApi not provided/
    );
  });

  it("should return the service if it is provided", () => {
    const app = createApp({});
    const mockService = { getVersion: () => Promise.resolve() };
    app.provide(NAAS_API_KEY, mockService);
    const result = app.runWithContext(() => useMoodleService());
    expect(result).toBe(mockService);
  });
});
