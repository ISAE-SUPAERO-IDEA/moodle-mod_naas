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
 * Image URLs served by Moodle (mod/naas/media.php) instead of NaaS.
 *
 * The browser never loads anything from NaaS: every Nugget thumbnail, producer
 * logo and banner goes through media.php, which checks access and the host.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export type StructureImageVariant = "thumbnail" | "banner";

export interface MediaRouter {
  /** Moodle URL serving a NaaS image URL (absolute or relative to NaaS); "" if none. */
  image(naasUrl: string, width?: number, height?: number): string;
  /** Moodle URL serving a structure's default thumbnail or banner; "" if no id. */
  structure(structureId: string, variant: StructureImageVariant): string;
}

/**
 * Build the media URLs for one widget instance.
 *
 * @param moodleUrl Site root (window.NAAS.moodle_url).
 * @param courseId Course the widget is shown in: media.php checks access there.
 */
export function createMediaRouter(
  moodleUrl: string,
  courseId: number
): MediaRouter {
  const endpoint = `${(moodleUrl ?? "").replace(
    /\/+$/,
    ""
  )}/mod/naas/media.php`;

  function build(params: Record<string, string | number>): string {
    const query = new URLSearchParams({ course: String(courseId) });
    for (const [key, value] of Object.entries(params)) {
      query.set(key, String(value));
    }
    return `${endpoint}?${query.toString()}`;
  }

  return {
    image(naasUrl, width = 0, height = 0) {
      const src = (naasUrl ?? "").trim();
      if (!src) {
        return "";
      }
      // Inline images need no request; keep them as they are.
      if (src.startsWith("data:image/")) {
        return src;
      }
      const params: Record<string, string | number> = { src };
      if (width > 0) params.w = width;
      if (height > 0) params.h = height;
      return build(params);
    },
    structure(structureId, variant) {
      const id = (structureId ?? "").trim();
      return id ? build({ structure: id, variant }) : "";
    },
  };
}
