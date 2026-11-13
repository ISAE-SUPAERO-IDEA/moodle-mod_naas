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
 * Public API of shared/api: the Moodle adapter for NaaS and the NaaS response types.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export { errorMessage } from "./errorMessage";
export { createMediaRouter } from "./media";
export type { MediaRouter } from "./media";
export { moodleNaasApiService } from "./moodle-naas-api.service";
export type { INaasApiService } from "./naas-api.interface";
export { NAAS_API_KEY } from "./naasApiKey";
export { unwrapNaasPayload } from "./unwrapNaasPayload";
export { useMoodleService } from "./useMoodleService";
export { useXapi } from "./xapi";
export type { Domain } from "./types/domain.types";
export type {
  AccessLicence,
  AggregationBucket,
  AggregationResult,
  CatalogueCheck,
  CatalogueFacetLabels,
  CatalogueStamp,
  CatalogueVocabulary,
  Nugget,
  SearchFreshness,
  SearchOptions,
  SearchResult,
} from "./types/nugget.types";
export type { Person } from "./types/person.types";
