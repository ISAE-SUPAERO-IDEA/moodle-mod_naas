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
 * Tests for createMediaRouter.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it } from "vitest";
import { createMediaRouter } from "./media";

const media = createMediaRouter("https://moodle.test/", 12);

describe("createMediaRouter", () => {
  it("serves NaaS images through media.php with the course and the size", () => {
    expect(
      media.image("https://api.naas.example/api/thumbnails/n1", 700, 394)
    ).toBe(
      "https://moodle.test/mod/naas/media.php?course=12&src=https%3A%2F%2Fapi.naas.example%2Fapi%2Fthumbnails%2Fn1&w=700&h=394"
    );
  });

  it("never returns a NaaS URL the browser would load directly", () => {
    const url = media.image("https://api.naas.example/x.png");
    expect(url.startsWith("https://moodle.test/mod/naas/media.php?")).toBe(
      true
    );
  });

  it("builds structure thumbnails and banners from the id", () => {
    expect(media.structure("isae-supaero", "banner")).toBe(
      "https://moodle.test/mod/naas/media.php?course=12&structure=isae-supaero&variant=banner"
    );
  });

  it("returns nothing for a missing image and keeps inline images", () => {
    expect(media.image("")).toBe("");
    expect(media.structure("", "thumbnail")).toBe("");
    expect(media.image("data:image/png;base64,AAAA")).toBe(
      "data:image/png;base64,AAAA"
    );
  });

  it("treats a missing URL or id from a loose payload as no image", () => {
    const missing = undefined as unknown as string;
    expect(media.image(missing)).toBe("");
    expect(media.structure(missing, "banner")).toBe("");
  });

  it("serves from the site root when the Moodle URL is missing", () => {
    const relative = createMediaRouter(undefined as unknown as string, 3);
    expect(relative.structure("isae", "thumbnail")).toBe(
      "/mod/naas/media.php?course=3&structure=isae&variant=thumbnail"
    );
  });
});
