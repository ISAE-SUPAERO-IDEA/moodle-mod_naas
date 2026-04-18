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
 * Composable for posting xAPI statements via the Moodle webservice.
 * Fire-and-forget: failures are logged but never bubble to the UI.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { useMoodleService } from './useMoodleService'
import type { XapiParams } from '@/types/nugget.types'

export function useXapi() {
  const service = useMoodleService()

  function postStatement(params: XapiParams): void {
    service.postXapiStatement(params).catch((err) => {
      console.warn('[NaaS xAPI] failed to post statement', err)
    })
  }

  return { postStatement }
}
