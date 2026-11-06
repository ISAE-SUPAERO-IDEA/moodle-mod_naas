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
 * Tests for useEntityResolvers (domain, structure and person names).
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, it, expect, vi } from "vitest";
import { createApp } from "vue";
import { useEntityResolvers } from "./useEntityResolvers";
import { NAAS_API_KEY } from "@/plugins/naas-api.plugin";

function withProviders<T>(
  service: object,
  run: () => T,
  naasConfig: object = { courseId: 7 }
): T {
  const app = createApp({});
  app.provide(NAAS_API_KEY, service);
  app.provide("naasConfig", naasConfig);
  return app.runWithContext(run);
}

describe("useEntityResolvers", () => {
  it("builds a person name from snake_case fields", async () => {
    const getPerson = vi
      .fn()
      .mockResolvedValue({ first_name: "Ada", last_name: "Lovelace" });
    const { getPersonName } = withProviders({ getPerson }, () =>
      useEntityResolvers()
    );
    await expect(getPersonName("hash-1")).resolves.toBe("ADA LOVELACE");
  });

  it("uses structure acronym then name", async () => {
    const getStructure = vi.fn().mockResolvedValue({ name: "ISAE-SUPAERO" });
    const { getStructureAcronym } = withProviders({ getStructure }, () =>
      useEntityResolvers()
    );
    await expect(getStructureAcronym("hash-2")).resolves.toBe("ISAE-SUPAERO");
  });

  it("reads producer visuals from nested file objects", async () => {
    const getStructure = vi.fn().mockResolvedValue({
      payload: {
        acronym: "ISAE",
        name: "ISAE-SUPAERO",
        logo: { url: "https://cdn.example.test/logo.svg" },
      },
    });
    const { getStructureVisuals } = withProviders({ getStructure }, () =>
      useEntityResolvers()
    );
    // The logo goes through media.php; the cover falls back to the NaaS banner.
    await expect(getStructureVisuals("hash-2")).resolves.toMatchObject({
      acronym: "ISAE",
      name: "ISAE-SUPAERO",
      logoUrl:
        "/mod/naas/media.php?course=7&src=https%3A%2F%2Fcdn.example.test%2Flogo.svg",
      imageUrl: "/mod/naas/media.php?course=7&structure=hash-2&variant=banner",
      hasCover: true,
    });
  });

  it("resolves producer visuals from the catalogue snapshot without HTTP", async () => {
    const getStructure = vi.fn();
    const { getStructureVisuals, getStructureAcronym } = withProviders(
      { getStructure },
      () => useEntityResolvers(),
      {
        courseId: 7,
        moodle_url: "https://moodle.test",
        catalogue_snapshot: {
          producers: [
            {
              structure_id: "isae-supaero",
              uuid: "06d37c13-6ffe-4c4a-a9e3-ac227652f98c",
              acronym: "ISAE",
              name: "ISAE-SUPAERO",
              structure_thumbnail_url:
                "https://api.example.test/api/thumbnails/structure/isae-supaero/thumbnail",
              structure_banner_url:
                "https://api.example.test/api/thumbnails/structure/isae-supaero/banner",
            },
          ],
        },
      }
    );
    await expect(
      getStructureAcronym("06d37c13-6ffe-4c4a-a9e3-ac227652f98c")
    ).resolves.toBe("ISAE");
    await expect(
      getStructureVisuals(
        "managed_by:structure:06d37c13-6ffe-4c4a-a9e3-ac227652f98c"
      )
    ).resolves.toMatchObject({
      acronym: "ISAE",
      name: "ISAE-SUPAERO",
      // Images go through Moodle, never straight to NaaS.
      logoUrl:
        "https://moodle.test/mod/naas/media.php?course=7&src=https%3A%2F%2Fapi.example.test%2Fapi%2Fthumbnails%2Fstructure%2Fisae-supaero%2Fthumbnail",
    });
    expect(getStructure).not.toHaveBeenCalled();
  });

  it("reads a domain label from a wrapped payload", async () => {
    const getDomain = vi.fn().mockResolvedValue({
      payload: { label: "Aerospace engineering" },
    });
    const { getDomainLabel } = withProviders({ getDomain }, () =>
      useEntityResolvers()
    );
    await expect(getDomainLabel("01.02")).resolves.toBe(
      "Aerospace engineering"
    );
  });

  it("reads an author name from Nuxeo person properties", async () => {
    const getPerson = vi.fn().mockResolvedValue({
      properties: {
        "person:firstname": "Ada",
        "person:lastname": "Lovelace",
      },
    });
    const { getPersonName } = withProviders({ getPerson }, () =>
      useEntityResolvers()
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
      useEntityResolvers()
    );
    await expect(
      getPersonName("authored_by:person:abcdef0123456789")
    ).resolves.toBe("ADA LOVELACE");
    expect(getPerson).toHaveBeenCalledWith("abcdef0123456789", 7);
  });

  it("falls back to the raw key when a domain has no label or fails", async () => {
    const getDomain = vi
      .fn()
      .mockResolvedValueOnce({ payload: { label: "  " } })
      .mockRejectedValueOnce(new Error("offline"));
    const { getDomainLabel } = withProviders({ getDomain }, () =>
      useEntityResolvers()
    );
    await expect(getDomainLabel("01.02")).resolves.toBe("01.02");
    await expect(getDomainLabel("01.03")).resolves.toBe("01.03");
  });

  it("uses the producer key when the structure is unknown or unreachable", async () => {
    const getStructure = vi
      .fn()
      .mockResolvedValueOnce({})
      .mockRejectedValueOnce(new Error("offline"))
      .mockRejectedValueOnce(new Error("offline"));
    const { getStructureAcronym, getStructureVisuals } = withProviders(
      { getStructure },
      () => useEntityResolvers(),
      { courseId: 7, moodle_url: "https://moodle.test" }
    );
    // An opaque id is no name, but it is still better than an empty chip.
    const hash = "0123456789abcdef0123456789abcdef";
    await expect(getStructureAcronym(hash)).resolves.toBe(hash);
    await expect(getStructureAcronym("onera")).resolves.toBe("onera");
    await expect(getStructureVisuals("cnes")).resolves.toEqual({
      name: "cnes",
      acronym: "",
      logoUrl:
        "https://moodle.test/mod/naas/media.php?course=7&structure=cnes&variant=thumbnail",
      imageUrl:
        "https://moodle.test/mod/naas/media.php?course=7&structure=cnes&variant=banner",
      hasCover: true,
    });
  });

  it("asks NaaS when the snapshot knows the producer but not its name", async () => {
    const uuid = "06d37c13-6ffe-4c4a-a9e3-ac227652f98c";
    const getStructure = vi.fn().mockResolvedValue({ acronym: "ISAE" });
    const { getStructureAcronym, getStructureVisuals } = withProviders(
      { getStructure },
      () => useEntityResolvers(),
      {
        courseId: 7,
        moodle_url: "https://moodle.test",
        catalogue_snapshot: { producers: [{ uuid }] },
      }
    );
    await expect(getStructureAcronym(uuid)).resolves.toBe("ISAE");
    await expect(getStructureVisuals(uuid)).resolves.toMatchObject({
      acronym: "ISAE",
    });
    expect(getStructure).toHaveBeenCalledTimes(2);
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
      useEntityResolvers()
    );
    await expect(getPersonName("p1")).resolves.toBe("GRACE HOPPER");
    await expect(getPersonName("p2")).resolves.toBe("ALAN TURING");
    await expect(getPersonName("p3")).resolves.toBe("");
    await expect(getPersonName("p4")).resolves.toBe("");
    await expect(getPersonName("p5")).resolves.toBe("");
  });
});
