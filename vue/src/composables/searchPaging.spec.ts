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
