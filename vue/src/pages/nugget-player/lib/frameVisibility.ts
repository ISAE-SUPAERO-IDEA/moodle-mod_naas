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
 * When the Nugget frame counts as seen, for the "experienced" xAPI statement.
 *
 * iframe-resizer grows the frame to the Nugget's full height (often taller than
 * the screen, nearly 2 800 px on a phone), so "half of the frame is visible"
 * can never be met. The frame is also seen once a good part of the screen
 * shows it.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Visible height that counts as seen, in CSS pixels (half the screen on small screens). */
export const MIN_VISIBLE_PX = 300;

/** Ratios the observer reports at, so scrolling through a tall frame is followed. */
export const VISIBILITY_THRESHOLDS = Array.from(
  { length: 21 },
  (_, i) => i / 20
);

/** The parts of an IntersectionObserverEntry this rule reads. */
export interface FrameVisibilityEntry {
  isIntersecting: boolean;
  intersectionRatio?: number;
  intersectionRect?: { height: number };
  rootBounds?: { height: number } | null;
}

/**
 * Whether the frame is visible enough to count as seen.
 *
 * @param entry Observer entry for the frame
 * @param viewportHeight Fallback screen height when the entry has no rootBounds
 * @returns True when half the frame, or MIN_VISIBLE_PX (at most half the screen), is visible
 */
export function isFrameSeen(
  entry: FrameVisibilityEntry,
  viewportHeight: number = window.innerHeight
): boolean {
  if (!entry.isIntersecting) {
    return false;
  }
  // Entries without geometry (very old browsers) keep the plain "on screen" rule.
  if (entry.intersectionRatio === undefined) {
    return true;
  }
  if (entry.intersectionRatio >= 0.5) {
    return true;
  }
  const screen = entry.rootBounds?.height || viewportHeight;
  const visible = entry.intersectionRect?.height ?? 0;
  return visible >= Math.min(MIN_VISIBLE_PX, screen / 2);
}
