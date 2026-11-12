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
 * Tests for useXapi().
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, it, expect, vi } from "vitest";
import { createApp } from "vue";
import { useXapi } from "./useXapi";
import { NAAS_API_KEY } from "@/plugins/naas-api.plugin";
import type { XapiParams } from "@/types/nugget.types";

describe("useXapi", () => {
  it("should post a statement and log warnings on error", async () => {
    const app = createApp({});
    const mockPost = vi.fn().mockRejectedValue(new Error("Network error"));
    const consoleSpy = vi.spyOn(console, "warn").mockImplementation(() => {});

    app.provide(NAAS_API_KEY, { postXapiStatement: mockPost });

    app.runWithContext(() => {
      const { postStatement } = useXapi();
      postStatement({ verb: "completed" } as unknown as XapiParams);
    });

    expect(mockPost).toHaveBeenCalledWith({ verb: "completed" });

    // Wait a tick for the promise to reject
    await new Promise((resolve) => setTimeout(resolve, 0));
    expect(consoleSpy).toHaveBeenCalledWith(
      "[NaaS xAPI] failed to post statement",
      expect.any(Error)
    );

    consoleSpy.mockRestore();
  });

  it("should post a statement successfully without logging", async () => {
    const app = createApp({});
    const mockPost = vi.fn().mockResolvedValue(true);
    const consoleSpy = vi.spyOn(console, "warn").mockImplementation(() => {});

    app.provide(NAAS_API_KEY, { postXapiStatement: mockPost });

    app.runWithContext(() => {
      const { postStatement } = useXapi();
      postStatement({ verb: "viewed" } as unknown as XapiParams);
    });

    await new Promise((resolve) => setTimeout(resolve, 0));
    expect(consoleSpy).not.toHaveBeenCalled();

    consoleSpy.mockRestore();
  });
});
