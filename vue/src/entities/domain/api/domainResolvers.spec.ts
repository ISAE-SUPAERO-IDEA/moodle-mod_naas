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
 * Tests for useDomainResolvers (domain labels).
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, it, expect, vi } from "vitest";
import { useDomainResolvers } from "./domainResolvers";
import { withProviders } from "@/test/withProviders";

describe("useDomainResolvers", () => {
  it("reads a domain label from a wrapped payload", async () => {
    const getDomain = vi.fn().mockResolvedValue({
      payload: { label: "Aerospace engineering" },
    });
    const { getDomainLabel } = withProviders({ getDomain }, () =>
      useDomainResolvers()
    );
    await expect(getDomainLabel("01.02")).resolves.toBe(
      "Aerospace engineering"
    );
  });

  it("falls back to the raw key when a domain has no label or fails", async () => {
    const getDomain = vi
      .fn()
      .mockResolvedValueOnce({ payload: { label: "  " } })
      .mockRejectedValueOnce(new Error("offline"));
    const { getDomainLabel } = withProviders({ getDomain }, () =>
      useDomainResolvers()
    );
    await expect(getDomainLabel("01.02")).resolves.toBe("01.02");
    await expect(getDomainLabel("01.03")).resolves.toBe("01.03");
  });
});
