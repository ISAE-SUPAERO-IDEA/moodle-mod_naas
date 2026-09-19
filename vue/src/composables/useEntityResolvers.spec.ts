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
    await expect(getStructureVisuals("hash-2")).resolves.toMatchObject({
      acronym: "ISAE",
      name: "ISAE-SUPAERO",
      logoUrl: "https://cdn.example.test/logo.svg",
      imageUrl: "https://cdn.example.test/logo.svg",
      hasCover: false,
    });
  });

  it("resolves producer visuals from the catalogue snapshot without HTTP", async () => {
    const getStructure = vi.fn();
    const { getStructureVisuals, getStructureAcronym } = withProviders(
      { getStructure },
      () => useEntityResolvers(),
      {
        courseId: 7,
        naas_endpoint: "https://api.example.test/api",
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
    });
    expect(getStructure).not.toHaveBeenCalled();
  });
});
