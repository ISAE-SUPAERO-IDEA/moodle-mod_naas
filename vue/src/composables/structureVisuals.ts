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

const OPAQUE_KEY =
  /^(?:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|[0-9a-f]{32,64})$/i;

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

export function isOpaqueEntityKey(value: string): boolean {
  return OPAQUE_KEY.test(value.trim());
}

/** Aggregation keys may be `managed_by:structure:{id}` instead of the bare structure id. */
export function normalizeStructureKey(key: string): string {
  const text = key.trim();
  const prefixed = /^(?:managed_by:)?structure:(.+)$/i.exec(text);
  return (prefixed ? prefixed[1] : text).trim();
}

function defaultStructureMedia(
  structureId: string,
  baseUrl: string
): { logoUrl: string; imageUrl: string } {
  const id = normalizeStructureKey(structureId);
  const root = baseUrl.trim().replace(/\/+$/, "");
  if (!id || !root || !/^https?:\/\//i.test(root)) {
    return { logoUrl: "", imageUrl: "" };
  }
  const encoded = encodeURIComponent(id);
  return {
    logoUrl: `${root}/thumbnails/structure/${encoded}/thumbnail`,
    imageUrl: `${root}/thumbnails/structure/${encoded}/banner`,
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

function resolveMediaUrl(value: string, baseUrl = ""): string {
  if (!value) {
    return "";
  }
  if (/^(https?:)?\/\//i.test(value) || value.startsWith("data:")) {
    return value;
  }
  const base = baseUrl.trim();
  if (!base || !/^https?:\/\//i.test(base)) {
    return value;
  }
  try {
    const origin = new URL(base).origin;
    if (value.startsWith("/")) {
      return `${origin}${value}`;
    }
    return new URL(value, base.endsWith("/") ? base : `${base}/`).toString();
  } catch {
    return value;
  }
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

  return { ...nuxeo, ...nested, ...translated, ...raw };
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

export function structureVisuals(
  entity: unknown,
  fallbackKey = "",
  mediaBaseUrl = ""
): StructureVisuals {
  const record = unwrapEntity(entity) ?? {};
  const structureId = normalizeStructureKey(
    firstMatching(record, ["structure_id", "structureId"], rawString) ||
      fallbackKey
  );
  const defaults = defaultStructureMedia(structureId, mediaBaseUrl);
  const acronym = firstMatching(record, ACRONYM_KEYS, displayText);
  const name = firstMatching(record, NAME_KEYS, displayText);
  const fallbackLabel =
    fallbackKey && !isOpaqueEntityKey(normalizeStructureKey(fallbackKey))
      ? fallbackKey
      : "";
  const logoUrl =
    resolveMediaUrl(firstMatching(record, LOGO_KEYS, mediaUrl), mediaBaseUrl) ||
    defaults.logoUrl;
  const coverUrl =
    resolveMediaUrl(
      firstMatching(record, IMAGE_KEYS, mediaUrl),
      mediaBaseUrl
    ) || defaults.imageUrl;
  const imageUrl = coverUrl || logoUrl;
  return {
    name: name || acronym || fallbackLabel,
    acronym: acronym || name || fallbackLabel,
    logoUrl: logoUrl || coverUrl,
    imageUrl,
    hasCover: Boolean(coverUrl && coverUrl !== logoUrl),
  };
}
