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
 * Public API of entities/nugget: the Nugget card, its skeleton, its About
 * sheet, and the loader of the authors and domains of a Nugget.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export { useNuggetEnricher } from "./api/useNuggetEnricher";
export {
  INTEGRATION_CODE_LENGTH,
  integrationCodeMatches,
  needsIntegrationCode,
} from "./lib/integrationCode";
export { default as NuggetAboutModal } from "./ui/NuggetAboutModal.vue";
export { default as NuggetPost } from "./ui/NuggetPost.vue";
export { default as NuggetSkeleton } from "./ui/NuggetSkeleton.vue";
