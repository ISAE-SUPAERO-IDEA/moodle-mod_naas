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
 * Tests for usePersonResolvers (author names).
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, it, expect, vi } from "vitest";
import { usePersonResolvers } from "./personResolvers";
import { withProviders } from "@/test/withProviders";

describe("usePersonResolvers", () => {
  it("builds a person name from snake_case fields", async () => {
    const getPerson = vi
      .fn()
      .mockResolvedValue({ first_name: "Ada", last_name: "Lovelace" });
    const { getPersonName } = withProviders({ getPerson }, () =>
      usePersonResolvers()
    );
    await expect(getPersonName("hash-1")).resolves.toBe("ADA LOVELACE");
  });

  it("reads an author name from Nuxeo person properties", async () => {
    const getPerson = vi.fn().mockResolvedValue({
      properties: {
        "person:firstname": "Ada",
        "person:lastname": "Lovelace",
      },
    });
    const { getPersonName } = withProviders({ getPerson }, () =>
      usePersonResolvers()
    );
    await expect(
      getPersonName("abcdef0123456789abcdef0123456789")
    ).resolves.toBe("ADA LOVELACE");
  });

  it("unwraps a person payload and ignores relationship prefixes", async () => {
    const getPerson = vi.fn().mockResolvedValue({
      payload: { firstname: "Ada", lastname: "Lovelace" },
    });
    const { getPersonName } = withProviders({ getPerson }, () =>
      usePersonResolvers()
    );
    await expect(
      getPersonName("authored_by:person:abcdef0123456789")
    ).resolves.toBe("ADA LOVELACE");
    expect(getPerson).toHaveBeenCalledWith("abcdef0123456789", 7);
  });

  it("reads a full name field, and returns nothing for an unnamed or failed person", async () => {
    const getPerson = vi
      .fn()
      .mockResolvedValueOnce({ fullname: "Grace Hopper" })
      .mockResolvedValueOnce({ properties: { "dc:title": "Alan Turing" } })
      .mockResolvedValueOnce({ properties: ["not", "a", "record"] })
      .mockResolvedValueOnce(null)
      .mockRejectedValueOnce(new Error("offline"));
    const { getPersonName } = withProviders({ getPerson }, () =>
      usePersonResolvers()
    );
    await expect(getPersonName("p1")).resolves.toBe("GRACE HOPPER");
    await expect(getPersonName("p2")).resolves.toBe("ALAN TURING");
    await expect(getPersonName("p3")).resolves.toBe("");
    await expect(getPersonName("p4")).resolves.toBe("");
    await expect(getPersonName("p5")).resolves.toBe("");
  });
});
