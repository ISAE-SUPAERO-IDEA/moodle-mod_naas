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
 * Tests for isFrameSeen().
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it } from "vitest";
import {
  isFrameSeen,
  MIN_VISIBLE_PX,
  VISIBILITY_THRESHOLDS,
} from "./frameVisibility";

/** Entry for a frame of the given height, of which `visible` px are on a screen of `screen` px. */
function entry(height: number, visible: number, screen: number) {
  return {
    isIntersecting: visible > 0,
    intersectionRatio: visible / height,
    intersectionRect: { height: visible },
    rootBounds: { height: screen },
  };
}

describe("isFrameSeen", () => {
  it("counts a frame that is half visible", () => {
    expect(isFrameSeen(entry(600, 300, 1000))).toBe(true);
  });

  it("counts a frame taller than the screen once enough of the screen shows it", () => {
    // Phone: a 2 789 px Nugget filling 600 px of an 844 px screen (21 % of the frame).
    expect(isFrameSeen(entry(2789, 600, 844))).toBe(true);
    // Laptop without scrolling: 389 px of a 786 px frame (49 %) on a 1000 px screen.
    expect(isFrameSeen(entry(786, 389, 1000))).toBe(true);
  });

  it("does not count a sliver of the frame", () => {
    expect(isFrameSeen(entry(2789, 120, 844))).toBe(false);
    expect(isFrameSeen(entry(786, MIN_VISIBLE_PX - 1, 1000))).toBe(false);
  });

  it("asks for half the screen on small screens", () => {
    expect(isFrameSeen(entry(2000, 250, 480))).toBe(true);
    expect(isFrameSeen(entry(2000, 230, 480))).toBe(false);
  });

  it("is never seen off screen", () => {
    expect(isFrameSeen(entry(600, 0, 1000))).toBe(false);
  });

  it("uses the window height when the entry has no root bounds", () => {
    const noRoot = { ...entry(2789, 260, 844), rootBounds: null };
    expect(isFrameSeen(noRoot, 500)).toBe(true);
    expect(isFrameSeen(noRoot, 1000)).toBe(false);
    expect(
      isFrameSeen({ isIntersecting: true, intersectionRatio: 0.1 }, 1000)
    ).toBe(false);
  });

  it("keeps the plain on-screen rule for entries without geometry", () => {
    expect(isFrameSeen({ isIntersecting: true })).toBe(true);
  });

  it("reports every 5 % so scrolling through a tall frame is followed", () => {
    expect(VISIBILITY_THRESHOLDS).toHaveLength(21);
    expect(VISIBILITY_THRESHOLDS[0]).toBe(0);
    expect(VISIBILITY_THRESHOLDS[1]).toBe(0.05);
    expect(VISIBILITY_THRESHOLDS[20]).toBe(1);
  });
});
