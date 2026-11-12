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
 * Keyboard moves between the cards of the results list.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Arrow key → how many cards to move. */
export const CARD_GRID_STEPS: Readonly<Record<string, number>> = {
  ArrowRight: 1,
  ArrowDown: 1,
  ArrowLeft: -1,
  ArrowUp: -1,
};

/**
 * Keydown handler for the list: arrow keys jump between cards, focus lands
 * on the Select button of the next or previous card. Tab still walks every
 * button in order, and other keys keep their default behaviour.
 */
export function onCardGridKeydown(e: KeyboardEvent): void {
  const step = CARD_GRID_STEPS[e.key];
  const list = e.currentTarget;
  const target = e.target instanceof HTMLElement ? e.target : null;
  if (!step || !(list instanceof HTMLElement) || !target) return;
  const buttons = Array.from(
    list.querySelectorAll<HTMLElement>(".nugget-grid-item .nugget-btn-select")
  );
  const card = target.closest(".nugget-grid-item");
  const index = buttons.findIndex((button) => card?.contains(button));
  if (index < 0) return;
  e.preventDefault();
  buttons[Math.min(Math.max(index + step, 0), buttons.length - 1)].focus();
}
