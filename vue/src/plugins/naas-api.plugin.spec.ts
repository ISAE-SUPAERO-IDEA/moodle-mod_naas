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
 * Tests for the plugin that provides the Moodle NaaS API adapter.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it } from "vitest";
import { createApp, inject } from "vue";
import { NAAS_API_KEY, moodleNaasApiService } from "@/shared/api";
import { naasApiPlugin } from "./naas-api.plugin";

describe("naasApiPlugin", () => {
  it("provides the Moodle web service adapter to every component", () => {
    const app = createApp({});
    app.use(naasApiPlugin);
    expect(app.runWithContext(() => inject(NAAS_API_KEY))).toBe(
      moodleNaasApiService
    );
  });
});
