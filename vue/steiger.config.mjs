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
 * Steiger (Feature-Sliced Design linter) configuration for the NaaS widget.
 *
 * Run with `pnpm lint:fsd`. A plain .mjs file: Steiger only loads a .ts
 * configuration on Node 22.18 or later.
 *
 * Steiger does not follow an import whose target is a .vue file, and does not
 * check who uses an @x entry point: src/architecture.spec.ts covers both.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { defineConfig } from "steiger";
import fsd from "@feature-sliced/steiger-plugin";

export default defineConfig([
  ...fsd.configs.recommended,
  {
    rules: {
      // The widget has two pages, so most slices have a single consumer.
      // They are kept for ownership, not for reuse.
      "fsd/insignificant-slice": "off",
      // Relative imports inside a slice, the alias and index.ts across slices.
      "fsd/import-locality": "error",
      // Public APIs list their exports by name.
      "fsd/no-wildcard-exports": "error",
    },
  },
  {
    // Steiger wants segment names that say what the code is for. "providers"
    // is the usual FSD name for app-level plugins, and the NaaS apps use it.
    files: ["./src/app/providers/**"],
    rules: {
      "fsd/segments-by-purpose": "off",
    },
  },
]);
