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
 * Tests for structureVisuals and the structure/person key helpers.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
    ).toBe(`${MEDIA}?course=7&src=%2Ffiles%2Flogo.svg&w=480`);
  });

  it("resolves a file object id to a files URL served by media.php", () => {
    expect(
      structureVisuals(
        { logo: { id: "11111111-2222-4333-8444-555555555555" } },
        "ISAE",
        media
      ).logoUrl
    ).toBe(
      `${MEDIA}?course=7&src=%2Ffiles%2F11111111-2222-4333-8444-555555555555&w=480`
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
      `${MEDIA}?course=7&src=https%3A%2F%2Fapi.example%2Fapi%2Fthumbnails%2Fstructure%2Fabc%2Fthumbnail&w=480`
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
      logoUrl: `${MEDIA}?course=7&structure=${key}&variant=thumbnail&w=480`,
      imageUrl: `${MEDIA}?course=7&structure=${key}&variant=banner&w=640`,
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

describe("lookupLabel", () => {
  const labels = { "ISAE-UUID": "ISAE", plain: "Plain" };

  it("returns nothing without a label map", () => {
    expect(lookupLabel(undefined, "plain", normalizeStructureKey)).toBe("");
  });

  it("prefers the exact key", () => {
    expect(lookupLabel(labels, "plain", normalizeStructureKey)).toBe("Plain");
  });

  it("matches a differently prefixed or cased key", () => {
    expect(
      lookupLabel(
        labels,
        "managed_by:structure:isae-uuid",
        normalizeStructureKey
      )
    ).toBe("ISAE");
  });

  it("returns nothing for a blank key or an unknown one", () => {
    expect(lookupLabel(labels, "   ", normalizeStructureKey)).toBe("");
    expect(lookupLabel(labels, "unknown", normalizeStructureKey)).toBe("");
  });
});

describe("normalizePersonKey", () => {
  it("keeps a bare email hash as it is", () => {
    expect(normalizePersonKey(" abcdef0123456789 ")).toBe("abcdef0123456789");
  });
});

describe("isRawEntityLabel", () => {
  it("treats a blank caption, the key itself or a hash as raw", () => {
    expect(isRawEntityLabel("  ")).toBe(true);
    expect(isRawEntityLabel("isae", "isae")).toBe(true);
    expect(isRawEntityLabel("0123456789abcdef0123")).toBe(true);
  });
});

describe("structureVisuals with loose NaaS payloads", () => {
  const empty = {
    name: "",
    acronym: "",
    logoUrl: "",
    imageUrl: "",
    hasCover: false,
  };

  it("reads an entity sent as a JSON string, a payload string or a list", () => {
    expect(structureVisuals('{"acronym":"ISAE"}').acronym).toBe("ISAE");
    expect(structureVisuals({ payload: '[{"acronym":"ISAE"}]' }).acronym).toBe(
      "ISAE"
    );
    expect(structureVisuals([null, { acronym: "ISAE" }]).acronym).toBe("ISAE");
  });

  it("returns empty visuals for payloads that hold no entity", () => {
    for (const raw of [
      null,
      "",
      "   ",
      "ISAE",
      "{not json",
      [],
      [null, "plain"],
      42,
      { payload: "{not json" },
    ]) {
      expect(structureVisuals(raw)).toEqual(empty);
    }
  });

  it("stops unwrapping beyond five nesting levels", () => {
    const deep: Record<string, unknown> = {};
    let leaf = deep;
    for (let i = 0; i < 6; i++) {
      leaf.data = {};
      leaf = leaf.data as Record<string, unknown>;
    }
    leaf.acronym = "TOO-DEEP";
    expect(structureVisuals(deep).acronym).toBe("");
    expect(structureVisuals({ data: { acronym: "ISAE" } }).acronym).toBe(
      "ISAE"
    );
  });

  it("merges fields from the structure / data wrappers", () => {
    expect(
      structureVisuals({
        structure: { acronym: "ISAE" },
        data: { name: "ISAE-SUPAERO" },
      })
    ).toMatchObject({ acronym: "ISAE", name: "ISAE-SUPAERO" });
  });

  it("picks the default translation, then the first one", () => {
    expect(
      structureVisuals({ translations: { default: { name: "Default" } } }).name
    ).toBe("Default");
    expect(
      structureVisuals({ translations: { de: { name: "Deutsch" } } }).name
    ).toBe("Deutsch");
    expect(structureVisuals({ translations: { en: "flat" } }).name).toBe("");
  });

  it("reads Nuxeo structure:name and skips blank Nuxeo fields", () => {
    expect(
      structureVisuals({
        properties: { "structure:name": " ISAE-SUPAERO ", "dc:title": "x" },
      }).name
    ).toBe("ISAE-SUPAERO");
    expect(
      structureVisuals({ properties: { "structure:acronym": "  " } })
    ).toEqual(empty);
  });

  it("reads display text from numbers, lists and localized objects", () => {
    expect(structureVisuals({ code: 42 }).acronym).toBe("42");
    expect(structureVisuals({ name: ["", { fr: "Institut" }] }).name).toBe(
      "Institut"
    );
    expect(structureVisuals({ name: { und: { value: "Nested" } } }).name).toBe(
      "Nested"
    );
  });

  it("ignores text that is not displayable", () => {
    expect(
      structureVisuals({
        name: [true, Number.NaN, {}, []],
        acronym: { en: { en: { en: { en: { en: "too deep" } } } } },
        label: "0123456789abcdef0123456789abcdef",
      })
    ).toEqual(empty);
  });

  it("accepts data URIs and bare image file names as media", () => {
    const pixel = "data:image/png;base64,AAAA";
    expect(structureVisuals({ logo: pixel }, "", media).logoUrl).toBe(pixel);
    expect(structureVisuals({ cover: "cover.webp?v=2" }).imageUrl).toBe(
      "cover.webp?v=2"
    );
  });

  it("finds media in lists and skips values that are not image URLs", () => {
    expect(
      structureVisuals({
        logo: ["", "0123456789abcdef0123456789abcdef", { src: "/l.svg" }],
      }).logoUrl
    ).toBe("/l.svg");
    expect(
      structureVisuals({
        logo: [12, "plain text", { id: "not-a-uuid" }, [[[[["/deep.png"]]]]]],
        image: [],
      })
    ).toEqual(empty);
  });

  it("builds no default image for an id media.php would refuse", () => {
    expect(
      structureVisuals({ structure_id: "bad id/../x" }, "", media)
    ).toMatchObject({ logoUrl: "", imageUrl: "" });
    expect(structureVisuals({ structure_id: "isae" }, "", media).logoUrl).toBe(
      `${MEDIA}?course=7&structure=isae&variant=thumbnail&w=480`
    );
  });
});
