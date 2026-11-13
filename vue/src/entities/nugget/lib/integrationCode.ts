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
 * Integration code of a Nugget, derived from its exact title.
 *
 * Same algorithm as \mod_naas\integration_code and the NaaS author app: first
 * 4 bytes of SHA-256(title) as an unsigned big-endian integer, modulo 1000000,
 * zero-padded to 6 digits. Moodle checks the code again when the form is saved.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type { Nugget } from "@/shared/api";

export const INTEGRATION_CODE_LENGTH = 6;

export async function integrationCodeFromTitle(title: string): Promise<string> {
  const digest = await crypto.subtle.digest(
    "SHA-256",
    new TextEncoder().encode(title)
  );
  return String(new DataView(digest).getUint32(0, false) % 1000000).padStart(
    INTEGRATION_CODE_LENGTH,
    "0"
  );
}

/**
 * Whether a typed code is the code of a title.
 *
 * @returns null when the browser cannot hash (a page served without HTTPS):
 *   the code is then accepted here and checked by Moodle on save.
 */
export async function integrationCodeMatches(
  title: string,
  code: string
): Promise<boolean | null> {
  if (!globalThis.crypto?.subtle) {
    return null;
  }
  return (await integrationCodeFromTitle(title)) === code;
}

/** Public Nuggets (open educational resources) need no code. */
export function isPublicNugget(nugget: Pick<Nugget, "is_public">): boolean {
  const value = nugget.is_public;
  return value === true || value === 1 || value === "1" || value === "true";
}

/** Whether selecting this Nugget asks for its code, given the site setting. */
export function needsIntegrationCode(
  nugget: Pick<Nugget, "is_public">,
  required: boolean | undefined
): boolean {
  return !!required && !isPublicNugget(nugget);
}
