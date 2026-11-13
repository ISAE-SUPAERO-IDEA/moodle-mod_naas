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
 * Licence of a Nugget for the configured institute, as shown in the About sheet.
 *
 * NaaS no longer fills `license`: each Nugget carries `access_licences`, one per
 * institute, with rights such as by, nc, nd, co, uu, ru.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type { AccessLicence } from "@/shared/api";

/**
 * Rights of the licence granted to the institute, else of the open-access licence,
 * else of the first licence.
 *
 * @param licences The Nugget's access_licences
 * @param structureId Institute configured in the plugin (window.NAAS.structure_id)
 * @returns Right codes, in NaaS order
 */
export function licenceRights(
  licences: AccessLicence[] | undefined,
  structureId: string | undefined
): string[] {
  const list = licences ?? [];
  const chosen =
    (structureId && list.find((l) => l.structure_id === structureId)) ||
    list.find((l) => l.is_open_access) ||
    list[0];
  return (chosen?.rights ?? []).filter(
    (right): right is string => typeof right === "string" && right !== ""
  );
}

/**
 * Readable list of rights, from the license_right_* labels.
 *
 * @param rights Right codes
 * @param labels config.labels.metadata
 * @returns For example "Attribution · Non-commercial use"; unknown codes in capitals
 */
export function rightsCaption(
  rights: string[],
  labels: Record<string, string | undefined>
): string {
  return rights
    .map((right) => labels[`license_right_${right}`] || right.toUpperCase())
    .join(" · ");
}
