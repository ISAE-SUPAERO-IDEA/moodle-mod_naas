import { describe, expect, it } from "vitest";
import {
  isOpaqueEntityKey,
  isRawEntityLabel,
  lookupLabel,
  normalizePersonKey,
  normalizeStructureKey,
  structureVisuals,
} from "./structureVisuals";
import { createMediaRouter } from "./mediaUrls";

const MEDIA = "https://moodle.test/mod/naas/media.php";
const media = createMediaRouter("https://moodle.test/", 7);

describe("structureVisuals", () => {
  it("prefers acronym and separate logo/cover URLs", () => {
    expect(
      structureVisuals({
        acronym: "ISAE",
        name: "ISAE-SUPAERO",
        logo_url: "https://example.test/logo.png",
        cover_url: "https://example.test/cover.jpg",
      })
    ).toEqual({
      name: "ISAE-SUPAERO",
      acronym: "ISAE",
      logoUrl: "https://example.test/logo.png",
      imageUrl: "https://example.test/cover.jpg",
      hasCover: true,
    });
  });

  it("reuses the only available image for both slots without treating it as a cover", () => {
    expect(
      structureVisuals({ logo: "https://example.test/mark.svg" }, "fallback")
    ).toEqual({
      name: "fallback",
      acronym: "",
      logoUrl: "https://example.test/mark.svg",
      imageUrl: "https://example.test/mark.svg",
      hasCover: false,
    });
  });

  it("reads nested payload, localized names, and file objects", () => {
    expect(
      structureVisuals(
        {
          payload: {
            translations: {
              fr: { acronym: "ISAE", name: "ISAE-SUPAERO" },
            },
            logo: { url: "https://cdn.example.test/logo.svg" },
            image: { href: "/files/cover.jpg" },
          },
        },
        "06d37c13-6ffe-4c4a-a9e3-ac227652f98c"
      )
    ).toEqual({
      name: "ISAE-SUPAERO",
      acronym: "ISAE",
      logoUrl: "https://cdn.example.test/logo.svg",
      imageUrl: "/files/cover.jpg",
      hasCover: true,
    });
  });

  it("sends relative media paths to media.php, which resolves them on NaaS", () => {
    expect(
      structureVisuals({ logo: { path: "/files/logo.svg" } }, "ISAE", media)
        .logoUrl
    ).toBe(`${MEDIA}?course=7&src=%2Ffiles%2Flogo.svg`);
  });

  it("resolves a file object id to a files URL served by media.php", () => {
    expect(
      structureVisuals(
        { logo: { id: "11111111-2222-4333-8444-555555555555" } },
        "ISAE",
        media
      ).logoUrl
    ).toBe(
      `${MEDIA}?course=7&src=%2Ffiles%2F11111111-2222-4333-8444-555555555555`
    );
  });

  it("routes absolute NaaS image URLs through media.php", () => {
    expect(
      structureVisuals(
        {
          structure_thumbnail_url:
            "https://api.example/api/thumbnails/structure/abc/thumbnail",
        },
        "abc",
        media
      ).logoUrl
    ).toBe(
      `${MEDIA}?course=7&src=https%3A%2F%2Fapi.example%2Fapi%2Fthumbnails%2Fstructure%2Fabc%2Fthumbnail`
    );
  });

  it("reads NaaS structure_thumbnail_url / structure_banner_url", () => {
    expect(
      structureVisuals({
        acronym: "ISAE",
        name: "ISAE-SUPAERO",
        structure_thumbnail_url:
          "https://api.example/api/thumbnails/structure/abc/thumbnail",
        structure_banner_url:
          "https://api.example/api/thumbnails/structure/abc/banner",
      })
    ).toEqual({
      name: "ISAE-SUPAERO",
      acronym: "ISAE",
      logoUrl: "https://api.example/api/thumbnails/structure/abc/thumbnail",
      imageUrl: "https://api.example/api/thumbnails/structure/abc/banner",
      hasCover: true,
    });
  });

  it("builds thumbnail URLs from the structure id when the payload has no media", () => {
    const key = "06d37c13-6ffe-4c4a-a9e3-ac227652f98c";
    expect(
      structureVisuals({ acronym: "ISAE", name: "ISAE-SUPAERO" }, key, media)
    ).toMatchObject({
      acronym: "ISAE",
      logoUrl: `${MEDIA}?course=7&structure=${key}&variant=thumbnail`,
      imageUrl: `${MEDIA}?course=7&structure=${key}&variant=banner`,
      hasCover: true,
    });
  });

  it("strips a managed_by:structure prefix from aggregation keys", () => {
    const id = "06d37c13-6ffe-4c4a-a9e3-ac227652f98c";
    expect(normalizeStructureKey(`managed_by:structure:${id}`)).toBe(id);
  });

  it("does not treat a hash or UUID as the producer title", () => {
    const key =
      "a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2";
    expect(isOpaqueEntityKey(key)).toBe(true);
    expect(structureVisuals({ id: key }, key)).toEqual({
      name: "",
      acronym: "",
      logoUrl: "",
      imageUrl: "",
      hasCover: false,
    });
  });

  it("reads Nuxeo dc:title and structure:acronym from properties", () => {
    expect(
      structureVisuals(
        {
          properties: {
            "dc:title": "ISAE-SUPAERO",
            "structure:acronym": "ISAE",
          },
        },
        "06d37c13-6ffe-4c4a-a9e3-ac227652f98c"
      )
    ).toMatchObject({
      name: "ISAE-SUPAERO",
      acronym: "ISAE",
    });
  });

  it("keeps a nested acronym when the wrapper sends null", () => {
    expect(
      structureVisuals({
        acronym: null,
        name: "ISAE-SUPAERO",
        translations: {
          en: { acronym: "ISAE", name: "ISAE-SUPAERO" },
        },
      })
    ).toMatchObject({
      name: "ISAE-SUPAERO",
      acronym: "ISAE",
    });
  });

  it("strips relationship prefixes and looks up labels by either form", () => {
    expect(normalizePersonKey("authored_by:person:abcdef0123456789")).toBe(
      "abcdef0123456789"
    );
    expect(normalizeStructureKey("managed_by:structure:isae-uuid")).toBe(
      "isae-uuid"
    );
    expect(
      lookupLabel(
        { "isae-uuid": "ISAE" },
        "managed_by:structure:isae-uuid",
        normalizeStructureKey
      )
    ).toBe("ISAE");
    expect(isRawEntityLabel("authored_by:person:abcdef0123456789")).toBe(true);
    expect(isRawEntityLabel("ISAE", "managed_by:structure:x")).toBe(false);
  });
});
