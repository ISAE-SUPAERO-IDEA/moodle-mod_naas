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
 * Helpers on the keys NaaS uses for structures and persons.
 *
 * Search aggregations identify a producer or an author by a hash, a UUID or a
 * prefixed key such as `managed_by:structure:{id}`. These helpers recognise
 * such keys, strip their prefix and look a readable label up by key.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const OPAQUE_KEY =
  /^(?:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|[0-9a-f]{32,64})$/i;

export function isOpaqueEntityKey(value: string): boolean {
  const text = value.trim();
  return OPAQUE_KEY.test(text) || /^[0-9a-f]{16,}$/i.test(text);
}

/** True when a facet caption is still the raw aggregation key, not a name. */
export function isRawEntityLabel(value: string, originalKey = ""): boolean {
  const text = value.trim();
  if (!text) {
    return true;
  }
  if (originalKey && text === originalKey) {
    return true;
  }
  if (/^(?:managed_by|authored_by|designed_by|reviewed_by):/i.test(text)) {
    return true;
  }
  return isOpaqueEntityKey(text);
}

/** Aggregation keys may be `managed_by:structure:{id}` instead of the bare structure id. */
export function normalizeStructureKey(key: string): string {
  const text = key.trim();
  const prefixed = /^(?:managed_by:)?structure:(.+)$/i.exec(text);
  return (prefixed ? prefixed[1] : text).trim();
}

/** Author buckets may be `authored_by:person:{hash}` instead of the email hash. */
export function normalizePersonKey(key: string): string {
  const text = key.trim();
  const prefixed =
    /^(?:authored_by:|designed_by:|reviewed_by:)?person:(.+)$/i.exec(text);
  return (prefixed ? prefixed[1] : text).trim();
}

export function lookupLabel(
  labels: Record<string, string> | undefined,
  key: string,
  normalize: (value: string) => string
): string {
  if (!labels) {
    return "";
  }
  const direct = labels[key];
  if (direct) {
    return direct;
  }
  const normalised = normalize(key);
  if (normalised && labels[normalised]) {
    return labels[normalised];
  }
  const needle = normalised.toLowerCase();
  if (!needle) {
    return "";
  }
  for (const [entry, label] of Object.entries(labels)) {
    if (normalize(entry).toLowerCase() === needle) {
      return label;
    }
  }
  return "";
}
