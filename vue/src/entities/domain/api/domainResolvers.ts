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
 * Resolve a domain key to its label, through Moodle.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { unwrapNaasPayload, useMoodleService } from "@/shared/api";
import { useNaasConfig } from "@/shared/config";
import { stringField } from "@/shared/lib";

export function useDomainResolvers() {
  const service = useMoodleService();
  const config = useNaasConfig();

  /** Label of a domain; its key when NaaS has no label or cannot be reached. */
  async function getDomainLabel(key: string): Promise<string> {
    try {
      const domain = unwrapNaasPayload<Record<string, unknown> | null>(
        await service.getDomain(key, config.courseId)
      );
      const label = stringField(domain, ["label", "name", "title"]);
      return label || key;
    } catch {
      return key;
    }
  }

  return { getDomainLabel };
}
