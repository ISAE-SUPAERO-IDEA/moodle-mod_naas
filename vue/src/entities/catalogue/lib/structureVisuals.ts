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
 * Pick display name and image URLs from a NaaS structure payload.
 *
 * Aggregation keys are hashes/UUIDs. The structure body often nests the
 * real name under `payload` / translations, and logo/cover as file objects.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type { MediaRouter } from "@/shared/api";
import { isOpaqueEntityKey, normalizeStructureKey } from "@/shared/lib";

const LOGO_KEYS = [
  "structure_thumbnail_url",
  "structureThumbnailUrl",
  "logo_url",
  "logoUrl",
  "logo",
  "thumbnail_url",
  "thumbnailUrl",
  "thumbnail",
  "mark",
];
const IMAGE_KEYS = [
  "structure_banner_url",
  "structureBannerUrl",
  "image_url",
  "imageUrl",
  "cover_url",
  "coverUrl",
  "cover",
  "picture",
  "picture_url",
  "banner_url",
  "bannerUrl",
  "banner",
  "image",
  "photo",
  "illustration",
];
const NAME_KEYS = [
  "name",
  "label",
  "title",
  "long_name",
  "longName",
  "official_name",
];
const ACRONYM_KEYS = ["acronym", "short_name", "shortName", "code", "sigle"];
const NESTED_ENTITY_KEYS = [
  "structure",
  "data",
  "attributes",
  "entity",
  "result",
  "item",
];
const LOCALIZED_KEYS = [
  "en",
  "fr",
  "default",
  "und",
  "label",
  "value",
  "text",
  "name",
  "title",
  "acronym",
];
const MEDIA_URL_KEYS = [
  "url",
  "src",
  "href",
  "path",
  "uri",
  "content_url",
  "contentUrl",
  "file_url",
  "fileUrl",
  "download_url",
  "downloadUrl",
  "public_url",
  "publicUrl",
  "logo_url",
  "image_url",
];

export interface StructureVisuals {
  name: string;
  acronym: string;
  logoUrl: string;
  imageUrl: string;
  hasCover: boolean;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return !!value && typeof value === "object" && !Array.isArray(value);
}

/**
 * Widths asked from NaaS for producer cards (about twice their largest display
 * size, for high-density screens). Original banners can weigh more than 10 MB.
 */
export const STRUCTURE_BANNER_WIDTH = 640;
export const STRUCTURE_LOGO_WIDTH = 480;

function defaultStructureMedia(
  structureId: string,
  media?: MediaRouter
): { logoUrl: string; imageUrl: string } {
  const id = normalizeStructureKey(structureId);
  // Same rule as media.php: anything else would only produce a broken image.
  if (!media || !/^[A-Za-z0-9_-]{1,128}$/.test(id)) {
    return { logoUrl: "", imageUrl: "" };
  }
  return {
    logoUrl: media.structure(id, "thumbnail", STRUCTURE_LOGO_WIDTH),
    imageUrl: media.structure(id, "banner", STRUCTURE_BANNER_WIDTH),
  };
}

function looksLikeMediaUrl(value: string): boolean {
  const text = value.trim();
  if (!text || isOpaqueEntityKey(text)) {
    return false;
  }
  return (
    /^(https?:)?\/\//i.test(text) ||
    text.startsWith("/") ||
    text.startsWith("data:") ||
    /\.(png|jpe?g|gif|svg|webp|avif)(\?|#|$)/i.test(text)
  );
}

function resolveMediaUrl(
  value: string,
  media: MediaRouter | undefined,
  width: number
): string {
  if (!value) {
    return "";
  }
  if (value.startsWith("data:")) {
    return value;
  }
  // media.php resolves relative URLs against the NaaS API URL and checks the host.
  return media ? media.image(value, width) : value;
}

function displayText(value: unknown, depth = 0): string {
  if (depth > 4 || value === null || value === undefined) {
    return "";
  }
  if (typeof value === "string") {
    const text = value.trim();
    return text && !isOpaqueEntityKey(text) ? text : "";
  }
  if (typeof value === "number" && Number.isFinite(value)) {
    return String(value);
  }
  if (Array.isArray(value)) {
    for (const item of value) {
      const text = displayText(item, depth + 1);
      if (text) {
        return text;
      }
    }
    return "";
  }
  if (!isRecord(value)) {
    return "";
  }
  for (const key of LOCALIZED_KEYS) {
    const text = displayText(value[key], depth + 1);
    if (text) {
      return text;
    }
  }
  return "";
}

function mediaUrl(value: unknown, depth = 0): string {
  if (depth > 4 || value === null || value === undefined) {
    return "";
  }
  if (typeof value === "string") {
    return looksLikeMediaUrl(value) ? value.trim() : "";
  }
  if (Array.isArray(value)) {
    for (const item of value) {
      const url = mediaUrl(item, depth + 1);
      if (url) {
        return url;
      }
    }
    return "";
  }
  if (!isRecord(value)) {
    return "";
  }
  for (const key of MEDIA_URL_KEYS) {
    const url = mediaUrl(value[key], depth + 1);
    if (url) {
      return url;
    }
  }
  if (
    typeof value.id === "string" &&
    /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(
      value.id
    )
  ) {
    return `/files/${value.id}`;
  }
  return "";
}

function firstMatching(
  entity: Record<string, unknown>,
  keys: string[],
  reader: (value: unknown) => string
): string {
  for (const key of keys) {
    const value = reader(entity[key]);
    if (value) {
      return value;
    }
  }
  return "";
}

function unwrapEntity(raw: unknown, depth = 0): Record<string, unknown> | null {
  if (depth > 5 || raw === null || raw === undefined) {
    return null;
  }
  if (typeof raw === "string") {
    const trimmed = raw.trim();
    if (!trimmed) {
      return null;
    }
    if (trimmed.startsWith("{") || trimmed.startsWith("[")) {
      try {
        return unwrapEntity(JSON.parse(trimmed), depth + 1);
      } catch {
        return null;
      }
    }
    return null;
  }
  if (Array.isArray(raw)) {
    for (const item of raw) {
      const entity = unwrapEntity(item, depth + 1);
      if (entity) {
        return entity;
      }
    }
    return null;
  }
  if (!isRecord(raw)) {
    return null;
  }

  if ("payload" in raw && raw.payload !== undefined && raw.payload !== null) {
    const nested = unwrapEntity(raw.payload, depth + 1);
    if (nested) {
      return nested;
    }
  }

  const nuxeo = nuxeoFields(raw.properties);

  const translations = raw.translations;
  let translated: Record<string, unknown> = {};
  if (isRecord(translations)) {
    const lang =
      translations.en ||
      translations.fr ||
      translations.default ||
      Object.values(translations)[0];
    if (isRecord(lang)) {
      translated = lang;
    }
  }

  let nested: Record<string, unknown> = {};
  for (const key of NESTED_ENTITY_KEYS) {
    const candidate = unwrapEntity(raw[key], depth + 1);
    if (candidate) {
      nested = { ...nested, ...candidate };
    }
  }

  return assignDefined({}, nuxeo, nested, translated, raw);
}

function assignDefined(
  target: Record<string, unknown>,
  ...sources: Record<string, unknown>[]
): Record<string, unknown> {
  for (const source of sources) {
    for (const [key, value] of Object.entries(source)) {
      if (value === null || value === undefined || value === "") {
        continue;
      }
      target[key] = value;
    }
  }
  return target;
}

function nuxeoFields(properties: unknown): Record<string, unknown> {
  if (!isRecord(properties)) {
    return {};
  }
  const acronym = rawString(properties["structure:acronym"]);
  const name =
    rawString(properties["structure:name"]) ||
    rawString(properties["dc:title"]);
  const fields: Record<string, unknown> = {};
  if (acronym) {
    fields.acronym = acronym;
  }
  if (name) {
    fields.name = name;
    fields.title = name;
  }
  return fields;
}

function rawString(value: unknown): string {
  return typeof value === "string" && value.trim() ? value.trim() : "";
}

/**
 * Display name and images of a structure.
 *
 * @param media Routes image URLs through Moodle (media.php). Without it, absolute
 *   URLs are kept as they are and no default image is built (unit tests).
 */
export function structureVisuals(
  entity: unknown,
  fallbackKey = "",
  media?: MediaRouter
): StructureVisuals {
  const record = unwrapEntity(entity) ?? {};
  const structureId = normalizeStructureKey(
    firstMatching(record, ["structure_id", "structureId"], rawString) ||
      fallbackKey
  );
  const defaults = defaultStructureMedia(structureId, media);
  const acronym = firstMatching(record, ACRONYM_KEYS, displayText);
  const name = firstMatching(record, NAME_KEYS, displayText);
  const fallbackLabel =
    fallbackKey && !isOpaqueEntityKey(normalizeStructureKey(fallbackKey))
      ? fallbackKey
      : "";
  const logoUrl =
    resolveMediaUrl(
      firstMatching(record, LOGO_KEYS, mediaUrl),
      media,
      STRUCTURE_LOGO_WIDTH
    ) || defaults.logoUrl;
  const coverUrl =
    resolveMediaUrl(
      firstMatching(record, IMAGE_KEYS, mediaUrl),
      media,
      STRUCTURE_BANNER_WIDTH
    ) || defaults.imageUrl;
  const imageUrl = coverUrl || logoUrl;
  return {
    name: name || acronym || fallbackLabel,
    acronym,
    logoUrl: logoUrl || coverUrl,
    imageUrl,
    hasCover: Boolean(coverUrl && coverUrl !== logoUrl),
  };
}
