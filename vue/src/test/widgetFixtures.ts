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
 * Test fixtures for component specs: config, Nuggets and a fake web-service layer.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { vi } from "vitest";
import { NAAS_API_KEY } from "@/shared/api";
import type { INaasApiService, Nugget, SearchResult } from "@/shared/api";
import type { NaasConfig, NaasLabels } from "@/shared/config";

/** English strings as lang/en/naas.php exports them to the widget. */
export function makeLabels(): NaasLabels {
  return {
    nugget_search_here: "Search here",
    nugget_search_no_result:
      "The search returned no results, please use another keyword.",
    nugget_search_collecting: "We are collecting nugget data, please wait.",
    search: "Search Nuggets",
    click_to_replace: "Replace the selected nugget",
    clear_filters: "Clear filters",
    show_more_authors: "Show more",
    hide_authors: "Hide",
    no_nugget: "No nugget",
    about: "About",
    about_title: "About: {$a}",
    close: "Close",
    labelsep: ": ",
    preview_title: "Preview: {$a}",
    back_to_course: "Back to course",
    show_more_nugget_button: "Show more",
    select_button: "Select",
    preview_button: "Preview",
    loading: "Loading",
    complete_nugget: "Complete",
    next_unit: "Next unit",
    learning_outcomes_desc: "You can now:",
    error_generic_user_message: "Something went wrong",
    error_nugget_not_found: "This Nugget is no longer available.",
    integration_code: {
      title: "Enter the integration code",
      help: "This Nugget needs the 6-digit integration code given by its authors.",
      digit: "Digit {$a}",
      confirm: "Confirm and select",
      cancel: "Cancel",
      error: "This code does not match this Nugget.",
      authors: "Nugget authors",
      authors_help: "No code yet? Ask an author of the Nugget.",
      authors_empty: "No author contact is available for this Nugget.",
      locked: "Integration code required",
    },
    retry: "Retry",
    active_filters: "Active filters",
    clear_search: "Clear search",
    remove_filter: "Remove filter {$a}",
    duration_minutes: "{$a} min",
    unknown_author: "Unknown author",
    unknown_producer: "Unknown producer",
    all_nuggets: "All Nuggets",
    view_as_cards: "Cards",
    view_as_list: "List",
    back_to_catalogue: "Back to the catalogue",
    checking_updates: "Checking for updates",
    browse_nugget_count: "Nuggets",
    browse_no_producers: "No producers yet.",
    rating: {
      title: "Rate this nugget",
      send: "Send",
      sent: "Sent",
      description: "Your rating will be used to improve the quality",
      value: "{$a} out of 5",
    },
    metadata: {
      preview: "Preview",
      description: "Description",
      in_brief: "In brief",
      about_author: "About the author",
      learning_outcomes: "Learning outcomes",
      prerequisites: "Prerequisites",
      references: "References",
      field_of_study: "Field of study",
      language: "Language",
      duration: "Duration",
      level: "Level",
      advanced: "Advanced",
      intermediate: "Intermediate",
      beginner: "Beginner",
      tags: "Tags",
      producers: "Producers",
      authors: "Authors",
      related_domains: "Field of study",
      type: "Type",
      lesson: "Lesson",
      demo: "Demo",
      tutorial: "Tutorial",
      en: "English",
      fr: "French",
      de: "German",
      es: "Spanish",
      it: "Italian",
      pl: "Polish",
      sv: "Swedish",
      publication_date: "Publication date",
      license: "License",
      license_1: "CC BY",
      filters: "Filters",
    },
  };
}

export function makeConfig(overrides: Partial<NaasConfig> = {}): NaasConfig {
  return {
    mount_point: "#naas_widget",
    component: "NuggetSearchWidget",
    moodle_url: "https://moodle.test",
    cm_id: 42,
    courseId: 7,
    catalogue_snapshot: null,
    labels: makeLabels(),
    ...overrides,
  };
}

export function makeNugget(overrides: Partial<Nugget> = {}): Nugget {
  return {
    nugget_id: "n-aircraft",
    name: "Aircraft design basics",
    resume: "<p>Wings, <b>lift</b> and drag.</p>",
    authors: ["a1"],
    authors_data: [
      {
        firstname: "Ada",
        lastname: "Lovelace",
        email: "ada@example.com",
        bio: "<p>Mathematician</p>",
      },
    ],
    domains: ["d1"],
    domains_data: [{ id: "d1", label: "Aeronautics" }],
    language: "en",
    multilanguages: [],
    version_id: "v-aircraft",
    duration: 5,
    license: 1,
    nugget_thumbnail_url: "https://naas.test/thumb.png",
    ...overrides,
  } as Nugget;
}

export function makeSearchResult(
  items: Nugget[],
  overrides: Partial<SearchResult> = {}
): SearchResult {
  return {
    items,
    results_count: items.length,
    aggregations: {},
    ...overrides,
  };
}

export type FakeService = {
  [K in keyof INaasApiService]: ReturnType<typeof vi.fn>;
};

/** Every web-service call is a spy; none reaches Moodle. */
export function makeService(overrides: Partial<FakeService> = {}): FakeService {
  return {
    getNugget: vi.fn().mockRejectedValue(new Error("not stubbed")),
    viewNugget: vi.fn().mockRejectedValue(new Error("not stubbed")),
    getPerson: vi.fn().mockResolvedValue({}),
    getDomain: vi.fn().mockResolvedValue({}),
    getStructure: vi.fn().mockResolvedValue({}),
    searchNuggets: vi.fn().mockResolvedValue(makeSearchResult([])),
    checkCatalogue: vi.fn().mockResolvedValue({
      producers_digest: "",
      producers: [],
      aggregations: {},
      results_count: 0,
    }),
    getNuggetPreview: vi.fn().mockResolvedValue("https://naas.test/preview"),
    postXapiStatement: vi.fn().mockResolvedValue(undefined),
    ...overrides,
  };
}

/** App-level provides matching src/app/main.ts. */
export function providers(
  config: NaasConfig = makeConfig(),
  service: FakeService = makeService()
): Record<string | symbol, unknown> {
  return { naasConfig: config, [NAAS_API_KEY]: service };
}
