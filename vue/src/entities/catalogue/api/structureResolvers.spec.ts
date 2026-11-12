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
 * Tests for useStructureResolvers (producer acronyms and images).
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, it, expect, vi } from "vitest";
import { useStructureResolvers } from "./structureResolvers";
import { withProviders } from "@/test/withProviders";

describe("useStructureResolvers", () => {
  it("uses structure acronym then name", async () => {
    const getStructure = vi.fn().mockResolvedValue({ name: "ISAE-SUPAERO" });
    const { getStructureAcronym } = withProviders({ getStructure }, () =>
      useStructureResolvers()
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
      useStructureResolvers()
    );
    // The logo goes through media.php; the cover falls back to the NaaS banner.
    await expect(getStructureVisuals("hash-2")).resolves.toMatchObject({
      acronym: "ISAE",
      name: "ISAE-SUPAERO",
      logoUrl:
        "/mod/naas/media.php?course=7&src=https%3A%2F%2Fcdn.example.test%2Flogo.svg&w=480",
      imageUrl:
        "/mod/naas/media.php?course=7&structure=hash-2&variant=banner&w=640",
      hasCover: true,
    });
  });

  it("resolves producer visuals from the catalogue snapshot without HTTP", async () => {
    const getStructure = vi.fn();
    const { getStructureVisuals, getStructureAcronym } = withProviders(
      { getStructure },
      () => useStructureResolvers(),
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
        "https://moodle.test/mod/naas/media.php?course=7&src=https%3A%2F%2Fapi.example.test%2Fapi%2Fthumbnails%2Fstructure%2Fisae-supaero%2Fthumbnail&w=480",
    });
    expect(getStructure).not.toHaveBeenCalled();
  });

  it("uses the producer key when the structure is unknown or unreachable", async () => {
    const getStructure = vi
      .fn()
      .mockResolvedValueOnce({})
      .mockRejectedValueOnce(new Error("offline"))
      .mockRejectedValueOnce(new Error("offline"));
    const { getStructureAcronym, getStructureVisuals } = withProviders(
      { getStructure },
      () => useStructureResolvers(),
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
        "https://moodle.test/mod/naas/media.php?course=7&structure=cnes&variant=thumbnail&w=480",
      imageUrl:
        "https://moodle.test/mod/naas/media.php?course=7&structure=cnes&variant=banner&w=640",
      hasCover: true,
    });
  });

  it("asks NaaS when the snapshot knows the producer but not its name", async () => {
    const uuid = "06d37c13-6ffe-4c4a-a9e3-ac227652f98c";
    const getStructure = vi.fn().mockResolvedValue({ acronym: "ISAE" });
    const { getStructureAcronym, getStructureVisuals } = withProviders(
      { getStructure },
      () => useStructureResolvers(),
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
});
