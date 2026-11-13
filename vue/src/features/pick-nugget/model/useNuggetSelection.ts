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
 * activity form: hidden nugget_id and integration_code, name, description
 * and validation error.
 *
 * When the site requires an integration code, picking a non-public Nugget
 * first asks for its code (codeNugget); the Nugget saved with the activity
 * never does, since Moodle only checks a Nugget that changes. When Moodle
 * refuses a save for a wrong code, the posted Nugget comes back in the form:
 * its code is asked again instead of showing it as chosen.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { ref } from "vue";
import {
  readNuggetFormError,
  setActivityDetailsVisible,
  syncMoodleNuggetFields,
} from "../lib/moodleActivityForm";
import { needsIntegrationCode } from "@/entities/nugget";
import type { Nugget } from "@/shared/api";

export interface NuggetSelectionOptions {
  /** Admin setting "Require an integration code". */
  requireIntegrationCode?: boolean;
  /** Nugget saved with the activity, "" for a new one (not the one a refused save posted back). */
  savedNuggetId?: string;
}

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

export function useNuggetSelection(options: NuggetSelectionOptions = {}) {
  const selectedNugget = ref<Nugget | null>(null);
  /** Nugget waiting for its integration code: the code dialog is open. */
  const codeNugget = ref<Nugget | null>(null);
  const savedId = options.savedNuggetId ?? "";
  /** Kept apart from selectedNugget: a saved id may never resolve to a card. */
  const selectedId = ref<string | null>(null);
  /** The saved Nugget is being fetched: the panel shows a skeleton. */
  const selectedNuggetLoading = ref(false);
  // Moodle puts "no Nugget chosen" on the name field, hidden until a Nugget is selected.
  const formError = ref(readNuggetFormError());

  function applySelection(nugget: Nugget, integrationCode = ""): void {
    formError.value = "";
    selectedId.value = nugget.nugget_id;
    selectedNugget.value = nugget;
    syncMoodleNuggetFields(nugget, integrationCode);
  }

  /** Drop the choice and empty the Moodle fields it filled. */
  function clearSelection(): void {
    selectedNugget.value = null;
    selectedId.value = null;
    syncMoodleNuggetFields(null);
  }

  /**
   * Select on a card: picks it, or drops it when it is already the pick.
   * A Nugget that needs its integration code waits in codeNugget instead.
   */
  function toggleNugget(nugget: Nugget): void {
    if (selectedId.value === nugget.nugget_id) {
      clearSelection();
      return;
    }
    if (needsCode(nugget)) {
      codeNugget.value = nugget;
      return;
    }
    applySelection(nugget);
  }

  function needsCode(nugget: Nugget): boolean {
    return (
      nugget.nugget_id !== savedId &&
      needsIntegrationCode(nugget, options.requireIntegrationCode)
    );
  }

  /** The code dialog accepted a code: pick the waiting Nugget with it. */
  function confirmIntegrationCode(code: string): void {
    const nugget = codeNugget.value;
    codeNugget.value = null;
    if (nugget) {
      applySelection(nugget, code);
    }
  }

  function cancelIntegrationCode(): void {
    codeNugget.value = null;
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
      if (selectedId.value === storedId) {
        selectedNugget.value = fresh;
      }
    });
    if (found) {
      selectedNugget.value = found;
    }
    selectedNuggetLoading.value = false;
    const restored = selectedNugget.value;
    if (restored && needsCode(restored)) {
      // A save refused for its code: keep Moodle's error and ask for the code again.
      clearSelection();
      codeNugget.value = restored;
    } else if (restored) {
      formError.value = "";
    }
  }

  return {
    selectedNugget,
    selectedId,
    selectedNuggetLoading,
    formError,
    codeNugget,
    toggleNugget,
    confirmIntegrationCode,
    cancelIntegrationCode,
    clearSelection,
    restoreSavedNugget,
  };
}
