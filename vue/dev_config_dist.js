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
 * Distribution configuration template for NAAS Vue application.
 *
 * @copyright  2019 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

module.exports = {
  mount_point: "#app",
  component: "NuggetView",
  //"component": "NuggetSearchWidget",
  moodle_url: "http://moodle.local.isae.fr/",
  labels: {
    nugget_search_here: "nugget_search_here",
    nugget_search_no_result: "nugget_search_no_result",
    search: "search",
    click_to_replace: "click_to_replace",
    clear_filters: "clear_filters",
    show_more_authors: "show_more_authors",
    hide_authors: "hide_authors",
    no_nugget: "no_nugget",
    about: "about",
    back_to_course: "back_to_course",
    show_more_nugget_button: "show_more_nugget_button",
    select_button: "select_button",
    preview_button: "preview_button",
    loading: "loading",
    metadata: {
      preview: "preview",
      description: "description",
      in_brief: "in_brief",
      about_author: "about_author",
      learning_outcomes: "learning_outcomes",
      prerequisites: "prerequisites",
      references: "references",
      field_of_study: "field_of_study",
      language: "language",
      duration: "duration",
      level: "level",
      structure_id: "structure_id",
      advanced: "advanced",
      intermediate: "intermediate",
      beginner: "beginner",
      tags: "tags",
      producers: "producers",
      authors: "authors",
      related_domains: "related_domains",
      type: "type",
      lesson: "lesson",
      demo: "demo",
      tutorial: "tutorial",
      en: "en",
      fr: "fr",
      de: "de",
      es: "es",
      it: "it",
      pl: "pl",
      sv: "sw",
      publication_date: "publication_date",
    },
  },
  nugget_id: "",
  //nugget_id: "338b4335-204f-4166-8e64-581425de5b1e",
};
