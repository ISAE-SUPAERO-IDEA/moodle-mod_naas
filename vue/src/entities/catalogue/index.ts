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
 * Public API of entities/catalogue: the producers (structures) of the NaaS
 * catalogue and the snapshot of it that Moodle injects in the page.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export { useStructureResolvers } from "./api/structureResolvers";
export {
  applyVocabulary,
  isEnriched,
  matchCachedProducer,
  sameCatalogueStamp,
  snapshotSearch,
} from "./lib/catalogueSnapshot";
export { namedPayload, namedPayloadChanged } from "./lib/producerDirectory";
export type { NamedProducer } from "./lib/producerDirectory";
export { structureVisuals } from "./lib/structureVisuals";
export { useProducerDirectory } from "./model/useProducerDirectory";
export type { ProducerDirectory } from "./model/useProducerDirectory";
