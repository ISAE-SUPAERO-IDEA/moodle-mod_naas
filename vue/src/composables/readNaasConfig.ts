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
 * Read the widget configuration that Moodle prints in the page.
 *
 * Moodle writes it in a non-executed <script type="application/json"> block
 * (mod_naas/widget template), so pages with a strict CSP still work. The dev
 * server sets window.NAAS directly from dev_config.js instead.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type { NaasConfig } from "@/types/naas-config.types";

/** Id of the JSON block printed by templates/widget.mustache. */
export const NAAS_CONFIG_ELEMENT_ID = "naas_config";

/**
 * Return the configuration from the JSON block, or window.NAAS when there is none.
 *
 * @throws Error when neither source is available or the block is not valid JSON.
 */
export function readNaasConfig(doc: Document = document): NaasConfig {
  const block = doc.getElementById(NAAS_CONFIG_ELEMENT_ID);
  if (block) {
    return JSON.parse(block.textContent ?? "") as NaasConfig;
  }
  if (window.NAAS) {
    return window.NAAS;
  }
  throw new Error(
    `mod_naas: no widget configuration (#${NAAS_CONFIG_ELEMENT_ID} or window.NAAS)`
  );
}
