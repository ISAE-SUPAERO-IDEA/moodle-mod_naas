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
 * The Nugget picked in the search widget, kept in step with the Moodle
 * activity form: hidden nugget_id, name, description and validation error.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { ref, watch } from "vue";
import {
  readNuggetFormError,
  setActivityDetailsVisible,
  syncMoodleNuggetFields,
} from "../lib/moodleActivityForm";
import type { Nugget } from "@/shared/api";

/** The parts of useNuggetSearch() that reload a saved Nugget. */
export interface SavedNuggetLoader {
  /** A hit already cached for any query, or null. */
  seedFromCache: (nuggetId: string) => Nugget | null;
  getNuggetById: (
    nuggetId: string,
    onRevalidated?: (nugget: Nugget) => void
  ) => Promise<Nugget | null>;
}

/**
 * The Nugget id saved in the form when an existing activity is edited.
 *
 * @returns The id, or "" for a new activity.
 */
export function readStoredNuggetId(): string {
  const field = document.getElementsByName("nugget_id")[0] as
    | HTMLInputElement
    | undefined;
  const stored = field?.value ?? "";
  // An unfilled template once left the field name as its value.
  return stored === "nugget_id" ? "" : stored;
}

export function useNuggetSelection() {
  const selectedNugget = ref<Nugget | null>(null);
  /** Kept apart from selectedNugget: a saved id may never resolve to a card. */
  const selectedId = ref<string | null>(null);
  /** The saved Nugget is being fetched: the panel shows a skeleton. */
  const selectedNuggetLoading = ref(false);
  // Moodle puts "no Nugget chosen" on the name field, hidden until a Nugget is selected.
  const formError = ref(readNuggetFormError());

  watch(selectedNugget, (nugget) => {
    if (nugget) formError.value = "";
  });

  function applySelection(nugget: Nugget): void {
    selectedId.value = nugget.nugget_id;
    selectedNugget.value = nugget;
    syncMoodleNuggetFields(nugget);
  }

  /** Drop the choice and empty the Moodle fields it filled. */
  function clearSelection(): void {
    selectedNugget.value = null;
    selectedId.value = null;
    syncMoodleNuggetFields(null);
  }

  /** Select on a card: picks it, or drops it when it is already the pick. */
  function toggleNugget(nugget: Nugget): void {
    if (selectedId.value === nugget.nugget_id) {
      clearSelection();
      return;
    }
    applySelection(nugget);
  }

  /**
   * Show the Nugget saved with the activity: from cache at once when
   * possible, then as Moodle returns it (and again if NaaS has a newer one).
   */
  async function restoreSavedNugget(
    storedId: string,
    loader: SavedNuggetLoader
  ): Promise<void> {
    selectedId.value = storedId;
    setActivityDetailsVisible(true);
    const seeded = loader.seedFromCache(storedId);
    selectedNugget.value = seeded;
    selectedNuggetLoading.value = seeded === null;
    const found = await loader.getNuggetById(storedId, (fresh) => {
      // Only fires when NaaS reported a newer document than the cached one.
      selectedNugget.value = fresh;
    });
    if (found) {
      selectedNugget.value = found;
    }
    selectedNuggetLoading.value = false;
  }

  return {
    selectedNugget,
    selectedId,
    selectedNuggetLoading,
    formError,
    toggleNugget,
    clearSelection,
    restoreSavedNugget,
  };
}
