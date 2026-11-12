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
 * Tests for the search paging helpers.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it } from "vitest";
import { SEARCH_FIRST_PAGE, withSearchPage } from "./searchPaging";

describe("searchPaging", () => {
  it("starts at page 0 so the first request matches Vue 2 (no page param)", () => {
    expect(SEARCH_FIRST_PAGE).toBe(0);
    expect(withSearchPage({ page_size: 9, level: ["advanced"] }, 0)).toEqual({
      page_size: 9,
      level: ["advanced"],
    });
  });

  it("omits page on the first page so a 4-hit facet is not skipped", () => {
    const first = withSearchPage(
      { page_size: 9, fulltext: "" },
      SEARCH_FIRST_PAGE
    );
    expect(first).not.toHaveProperty("page");
  });

  it("sends page only after the first page", () => {
    expect(withSearchPage({ page_size: 9 }, 1)).toEqual({
      page_size: 9,
      page: 1,
    });
  });
});
