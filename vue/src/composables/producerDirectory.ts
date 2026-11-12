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
 * Pure helpers behind the producer "named" round trip.
 *
 * NuggetBrowseLanding resolves display names for its producer cards and emits
 * them as `named`; NuggetSearchWidget folds them back into the `directory`
 * prop the landing paints from. That prop change re-runs the landing's
 * watcher, which emits `named` again. Both sides must therefore converge: the
 * parent only replaces its directory when a name actually changes, and the
 * child only emits a payload that differs from the last one it sent.
 * Otherwise every `.map()` hands the child a new reference and the two
 * components ping-pong forever.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { matchCachedProducer } from "./catalogueSnapshot";
import type { CatalogueProducer } from "@/shared/config";

/** One producer card's resolved labels, as emitted by the landing. */
export interface NamedProducer {
  id: string;
  acronym: string;
  name: string;
}

export type ProducerMatcher = (
  producers: CatalogueProducer[] | undefined,
  key: string
) => CatalogueProducer | null;

/**
 * Project cards onto the `named` payload: labels pass through `visible`
 * (trim, drop raw keys) and cards left with neither label are skipped.
 */
export function namedPayload(
  cards: readonly NamedProducer[],
  visible: (value: string) => string = (value) => value.trim()
): NamedProducer[] {
  return cards
    .map((card) => ({
      id: card.id,
      acronym: visible(card.acronym),
      name: visible(card.name),
    }))
    .filter((card) => card.acronym || card.name);
}

function namedKey(row: NamedProducer): string {
  return JSON.stringify([row.id, row.acronym, row.name]);
}

/**
 * True when `next` carries a different set of {id, acronym, name} than
 * `previous`. Order is ignored: a re-sorted strip is not news.
 */
export function namedPayloadChanged(
  previous: readonly NamedProducer[],
  next: readonly NamedProducer[]
): boolean {
  if (previous.length !== next.length) {
    return true;
  }
  const before = previous.map(namedKey).sort();
  const after = next.map(namedKey).sort();
  return before.some((key, index) => key !== after[index]);
}

/**
 * Fold resolved names into the directory.
 *
 * Returns `directory` itself when no acronym or name changes, otherwise a new
 * array in which only the touched rows are replaced, so untouched rows keep
 * their identity. A payload field left empty never erases a known label.
 */
export function mergeProducerNames(
  directory: CatalogueProducer[],
  rows: readonly NamedProducer[],
  matches: ProducerMatcher = matchCachedProducer
): CatalogueProducer[] {
  if (!directory.length || !rows.length) {
    return directory;
  }
  let changed = false;
  const next = directory.map((producer) => {
    const named = rows.find((row) => matches([producer], row.id));
    if (!named) {
      return producer;
    }
    const acronym = named.acronym || producer.acronym;
    const name = named.name || producer.name;
    if (acronym === producer.acronym && name === producer.name) {
      return producer;
    }
    changed = true;
    return { ...producer, acronym, name };
  });
  return changed ? next : directory;
}

/**
 * Facet labels the payload adds or changes, keyed by producer id. Empty when
 * every label is already on screen, so the caller can skip reassigning.
 */
export function producerLabelUpdates(
  current: Record<string, string> | undefined,
  rows: readonly NamedProducer[]
): Record<string, string> {
  const updates: Record<string, string> = {};
  for (const row of rows) {
    const text = row.acronym || row.name;
    if (text && current?.[row.id] !== text) {
      updates[row.id] = text;
    }
  }
  return updates;
}
