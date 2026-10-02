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
 * Moodle webservice adapter — wraps core/ajax AMD calls behind INaasApiService.
 * This is the only file that knows about Moodle's RequireJS loader.
 *
 * @copyright  2024 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type { INaasApiService } from "./naas-api.interface";
import type {
  CatalogueCheck,
  Nugget,
  SearchResult,
  XapiParams,
} from "../types/nugget.types";
import type { Person } from "../types/person.types";
import type { Domain } from "../types/domain.types";
import type { Structure } from "../types/structure.types";
import { unwrapNaasPayload } from "./unwrapNaasPayload";

// Moodle's RequireJS loader — (deps, callback) — differs from Node's require(id).
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type MoodleRequire = (
  deps: string[],
  callback: (...args: any[]) => void
) => void;

const cache = new Map<string, unknown>();

function cacheKey(method: string, args: Record<string, unknown>): string {
  return JSON.stringify({ method, args });
}

function waitForRequirejs(): Promise<MoodleRequire> {
  return new Promise((resolve) => {
    const get = () =>
      (window as unknown as { require?: MoodleRequire }).require;
    if (get()) {
      resolve(get()!);
      return;
    }
    const interval = setInterval(() => {
      if (get()) {
        clearInterval(interval);
        resolve(get()!);
      }
    }, 100);
  });
}

async function callWebservice<T>(
  methodname: string,
  args: Record<string, unknown> = {},
  useCache = false
): Promise<T> {
  const key = cacheKey(methodname, args);
  if (useCache && cache.has(key)) return cache.get(key) as T;

  const require = await waitForRequirejs();

  return new Promise<T>((resolve, reject) => {
    require(["core/ajax"], (ajax: {
      call: (calls: unknown[]) => Promise<unknown>[];
    }) => {
      ajax
        .call([{ methodname, args }])[0]
        .then((response) => {
          const payload = unwrapNaasPayload<T>(response);
          if (useCache) cache.set(key, payload);
          resolve(payload);
        })
        .catch(reject);
    });
  });
}

export const moodleNaasApiService: INaasApiService = {
  getNugget: (nuggetId, courseId, mode = "cache_first") =>
    callWebservice<Nugget>("mod_naas_get_nugget", {
      nuggetId,
      courseId,
      mode,
    }),

  viewNugget: (cmId) =>
    callWebservice<Nugget>("mod_naas_view_nugget", { cmId }, true),

  getPerson: (personKey, courseId) =>
    callWebservice<Person>(
      "mod_naas_get_person",
      { personKey, courseId },
      true
    ),

  getDomain: (domainKey, courseId) =>
    callWebservice<Domain>(
      "mod_naas_get_domain",
      { domainKey, courseId },
      true
    ),

  getStructure: (structureKey, courseId) =>
    callWebservice<Structure>(
      "mod_naas_get_structure",
      { structureKey, courseId },
      true
    ),

  searchNuggets: (searchOptions, courseId, mode = "cache_first") =>
    callWebservice<SearchResult>("mod_naas_search_nuggets", {
      searchOptions: searchOptions as unknown as Record<string, unknown>,
      courseId,
      mode,
    }),

  checkCatalogue: (courseId) =>
    callWebservice<CatalogueCheck>("mod_naas_check_catalogue", { courseId }),

  getNuggetPreview: (versionId, courseId) =>
    callWebservice<string>("mod_naas_get_nugget_preview", {
      versionId,
      courseId,
    }),

  postXapiStatement: (params: XapiParams) =>
    callWebservice<void>(
      "mod_naas_post_xapi_statement",
      params as unknown as Record<string, unknown>
    ),
};
