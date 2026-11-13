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
 * Public API of features/pick-nugget: choosing a Nugget for the activity and
 * keeping the Moodle activity form in step with the choice.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export {
  concealCguAgreement,
  placeCguAgreement,
  restoreCguAgreement,
  setActivityDetailsVisible,
} from "./lib/moodleActivityForm";
export {
  readStoredNuggetId,
  useNuggetSelection,
} from "./model/useNuggetSelection";
export { default as IntegrationCodeModal } from "./ui/IntegrationCodeModal.vue";
