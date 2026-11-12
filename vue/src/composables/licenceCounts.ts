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
 * Whether the Nugget counts NaaS returns can be shown as they are.
 *
 * NaaS cannot search on "this institute's licence has this right": the plugin
 * asks for the right on any licence, then drops the Nuggets whose licence for
 * this institute lacks it. The counts (total, per producer, per filter option)
 * come from NaaS and still include the dropped Nuggets, so they are hidden
 * while a licence filter is set in the plugin settings.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type { LicenseFilterConfig } from "@/types/naas-config.types";

/**
 * True when no licence filter is set, so NaaS counts match the Nuggets shown.
 *
 * @param filter window.NAAS.license_filter (absent on older configurations)
 * @returns Whether counts may be displayed
 */
export function countsAreExact(filter?: LicenseFilterConfig): boolean {
  if (!filter) {
    return true;
  }
  return filter.commercial === "all" && filter.access === "all";
}
