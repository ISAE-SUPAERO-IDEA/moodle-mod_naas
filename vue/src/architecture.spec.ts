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
 * Architecture rules that Steiger (`pnpm lint:fsd`) cannot check.
 *
 * Steiger checks the direction of imports between layers and slices. It does
 * not follow an import whose target is a .vue file, skips the folders that are
 * not FSD layers, does not check who uses an @x entry point, and does not read
 * vi.mock() paths. This spec reads every source file as text and checks that:
 *
 * - an import that leaves a slice (or a shared segment) targets the index.ts
 *   of the other one, or the @x entry point made for the importing slice;
 * - code in a layer never imports the folders from before the migration;
 * - imports and vi.mock() paths point to a file;
 * - src/test is imported by specs only;
 * - every .ts and .vue file has the Moodle GPL header;
 * - src/ holds only the layers, src/test and the entries listed in LEGACY.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { describe, expect, it } from "vitest";

/**
 * Folders and files from before the migration to Feature-Sliced Design.
 * Remove an entry when its last file has moved; the list ends up empty.
 */
const LEGACY = [
  "components",
  "composables",
  "plugins",
  "service",
  "styles",
  "types",
  "utils",
  "Main.vue",
  "Main.spec.ts",
  "main.ts",
];

const SLICED_LAYERS = ["pages", "widgets", "features", "entities"];
const LAYERS = ["app", ...SLICED_LAYERS, "shared"];
const ROOT_FILES = ["vite-env.d.ts"];
const HEADER = "This file is part of Moodle - http://moodle.org/";

/** Path relative to src/ → file content. */
type Sources = Record<string, string>;

const IMPORT_PATTERNS = [
  /(?:import|export)\s[^'"]*?\bfrom\s*["']([^"']+)["']/g,
  /\bimport\s*["']([^"']+)["']/g,
  /\bimport\(\s*["']([^"']+)["']\s*\)/g,
];
const MOCK_PATTERN = /\bvi\.(?:mock|doMock)\(\s*["']([^"']+)["']/g;

function captures(text: string, pattern: RegExp): string[] {
  return [...text.matchAll(pattern)].map((match) => match[1]);
}

/** The slice, shared segment or special folder of a file; null for legacy code. */
function unitOf(path: string): string | null {
  const [top, second] = path.split("/");
  if (SLICED_LAYERS.includes(top) || top === "shared") {
    return `${top}/${second}`;
  }
  return top === "app" || top === "test" ? top : null;
}

function normalize(path: string): string {
  const parts: string[] = [];
  for (const part of path.split("/")) {
    if (part === "..") {
      parts.pop();
    } else if (part !== "." && part !== "") {
      parts.push(part);
    }
  }
  return parts.join("/");
}

/** The imported file; null for a package; undefined when no file matches. */
function resolveImport(
  from: string,
  specifier: string,
  sources: Sources
): string | null | undefined {
  const path = specifier.split("?")[0];
  let base: string;
  if (path.startsWith("@/")) {
    base = path.slice(2);
  } else if (path.startsWith(".")) {
    base = normalize(`${from}/../${path}`);
  } else {
    return null;
  }
  return [base, `${base}.ts`, `${base}.vue`, `${base}/index.ts`].find(
    (candidate) => Object.prototype.hasOwnProperty.call(sources, candidate)
  );
}

/** Whether code of `fromUnit` may import the file `to` of another unit. */
function isPublicApi(
  to: string,
  toUnit: string,
  fromUnit: string | null
): boolean {
  if (to === `${toUnit}/index.ts`) {
    return true;
  }
  const crossImport = /^(\w+)\/[^/]+\/@x\/([^/]+)\/index\.ts$/.exec(to);
  return (
    crossImport !== null && fromUnit === `${crossImport[1]}/${crossImport[2]}`
  );
}

function checkImport(from: string, to: string): string | null {
  const fromUnit = unitOf(from);
  const toUnit = unitOf(to);
  if (toUnit === fromUnit) {
    return null;
  }
  if (toUnit === null) {
    return fromUnit === "test"
      ? null
      : `${from} imports ${to}: code in a layer must not import code from before the migration`;
  }
  if (toUnit === "test") {
    return from.endsWith(".spec.ts")
      ? null
      : `${from} imports ${to}: src/test is for specs only`;
  }
  return isPublicApi(to, toUnit, fromUnit)
    ? null
    : `${from} imports ${to}: import ${toUnit} through its index.ts`;
}

function importProblem(
  from: string,
  specifier: string,
  sources: Sources
): string | null {
  const to = resolveImport(from, specifier, sources);
  if (to === undefined) {
    return `${from} imports "${specifier}", which is not a file`;
  }
  return to === null ? null : checkImport(from, to);
}

function mockProblem(
  from: string,
  specifier: string,
  sources: Sources
): string | null {
  return resolveImport(from, specifier, sources) === undefined
    ? `${from} mocks "${specifier}", which is not a file`
    : null;
}

function checkFile(from: string, sources: Sources): string[] {
  if (!/\.(ts|vue)$/.test(from)) {
    return [];
  }
  const text = sources[from];
  const imports = IMPORT_PATTERNS.flatMap((pattern) => captures(text, pattern));
  const problems = [
    ...imports.map((specifier) => importProblem(from, specifier, sources)),
    ...captures(text, MOCK_PATTERN).map((specifier) =>
      mockProblem(from, specifier, sources)
    ),
    text.slice(0, 200).includes(HEADER) ? null : `${from} has no GPL header`,
  ];
  return [
    ...new Set(
      problems.filter((problem): problem is string => problem !== null)
    ),
  ];
}

function checkTopLevel(sources: Sources, legacy: string[]): string[] {
  const entries = new Set(
    Object.keys(sources).map((path) => path.split("/")[0])
  );
  const allowed = [...LAYERS, "test", ...ROOT_FILES, ...legacy];
  return [
    ...[...entries]
      .filter((entry) => !allowed.includes(entry))
      .map((entry) => `src/${entry} is neither a layer nor listed in LEGACY`),
    ...legacy
      .filter((entry) => !entries.has(entry))
      .map((entry) => `LEGACY lists ${entry}, which no longer exists`),
  ];
}

/** Every broken rule in `sources`, as one readable line each. */
function checkArchitecture(sources: Sources, legacy: string[]): string[] {
  return [
    ...checkTopLevel(sources, legacy),
    ...Object.keys(sources)
      .sort()
      .flatMap((path) => checkFile(path, sources)),
  ];
}

/** A made-up src/ tree: every file gets the GPL header, then its code. */
function tree(files: Record<string, string>): Sources {
  return Object.fromEntries(
    Object.entries(files).map(([path, code]) => [path, `// ${HEADER}\n${code}`])
  );
}

describe("architecture of the widget sources", () => {
  it("follows the rules Steiger cannot check", () => {
    const raw = import.meta.glob<string>("./**/*.{ts,vue,css}", {
      query: "?raw",
      import: "default",
      eager: true,
    });
    const sources: Sources = {};
    for (const [key, text] of Object.entries(raw)) {
      const path = key.replace(/^\.\//, "");
      // This file quotes broken imports on purpose, in the cases below.
      if (path !== "architecture.spec.ts") {
        sources[path] = text;
      }
    }

    expect(Object.keys(sources).length).toBeGreaterThan(100);
    expect(checkArchitecture(sources, LEGACY)).toEqual([]);
  });
});

describe("architecture rules", () => {
  it("accept index.ts, relative imports in a slice and the right @x", () => {
    const sources = tree({
      "shared/lib/index.ts": 'export { fill } from "./fill";',
      "shared/lib/fill.ts": "",
      "entities/catalogue/index.ts": 'export { names } from "./api/names";',
      "entities/catalogue/api/names.ts": 'import { fill } from "@/shared/lib";',
      "entities/catalogue/@x/nugget/index.ts":
        'export { names } from "../../api/names";',
      "entities/nugget/index.ts":
        'export { default as Card } from "./ui/Card.vue";',
      "entities/nugget/ui/Card.vue":
        'import { names } from "@/entities/catalogue/@x/nugget";\n' +
        'import About from "./About.vue";\nimport { ref } from "vue";',
      "entities/nugget/ui/About.vue": "",
      "widgets/results/ui/Grid.vue":
        'import { Card } from "@/entities/nugget";',
    });

    expect(checkArchitecture(sources, [])).toEqual([]);
  });

  it("reject a .vue file imported from outside its slice", () => {
    const sources = tree({
      "entities/nugget/ui/Card.vue": "",
      "widgets/results/ui/Grid.vue":
        'import Card from "@/entities/nugget/ui/Card.vue";',
    });

    expect(checkArchitecture(sources, [])).toEqual([
      "widgets/results/ui/Grid.vue imports entities/nugget/ui/Card.vue: import entities/nugget through its index.ts",
    ]);
  });

  it("reject a deep import into a shared segment, from legacy code too", () => {
    const sources = tree({
      "shared/lib/fill.ts": "",
      "components/Old.vue": 'import { fill } from "@/shared/lib/fill";',
    });

    expect(checkArchitecture(sources, ["components"])).toEqual([
      "components/Old.vue imports shared/lib/fill.ts: import shared/lib through its index.ts",
    ]);
  });

  it("reject an @x entry point used by another slice", () => {
    const sources = tree({
      "entities/catalogue/@x/nugget/index.ts": "",
      "features/pick/model/pick.ts":
        'import { names } from "@/entities/catalogue/@x/nugget";',
    });

    expect(checkArchitecture(sources, [])).toEqual([
      "features/pick/model/pick.ts imports entities/catalogue/@x/nugget/index.ts: import entities/catalogue through its index.ts",
    ]);
  });

  it("reject layer code that imports code from before the migration", () => {
    const sources = tree({
      "utils/debounce.ts": "",
      "shared/lib/wait.ts": 'import { debounce } from "@/utils/debounce";',
      "test/fixtures.ts": 'import { debounce } from "@/utils/debounce";',
    });

    expect(checkArchitecture(sources, ["utils"])).toEqual([
      "shared/lib/wait.ts imports utils/debounce.ts: code in a layer must not import code from before the migration",
    ]);
  });

  it("reject imports and mocks of a missing file, accept a mock in another slice", () => {
    const sources = tree({
      "features/filter/ui/Filter.vue": "",
      "widgets/bar/ui/Bar.spec.ts":
        'vi.mock("@/features/filter/ui/Filter.vue", () => ({}));\n' +
        'vi.mock("./Gone.vue");\nimport { bar } from "./missing";',
    });

    expect(checkArchitecture(sources, [])).toEqual([
      'widgets/bar/ui/Bar.spec.ts imports "./missing", which is not a file',
      'widgets/bar/ui/Bar.spec.ts mocks "./Gone.vue", which is not a file',
    ]);
  });

  it("keep src/test for specs", () => {
    const sources = tree({
      "test/mount.ts": "",
      "shared/lib/wait.ts": 'import { mount } from "@/test/mount";',
      "shared/lib/wait.spec.ts": 'import { mount } from "@/test/mount";',
    });

    expect(checkArchitecture(sources, [])).toEqual([
      "shared/lib/wait.ts imports test/mount.ts: src/test is for specs only",
    ]);
  });

  it("require the GPL header on .ts and .vue files", () => {
    const sources = {
      "shared/lib/wait.ts": "export const wait = 1;",
      "shared/ui/Banner.vue": "<template><p /></template>",
      "app/styles/theme.css": ".naas {}",
    };

    expect(checkArchitecture(sources, [])).toEqual([
      "shared/lib/wait.ts has no GPL header",
      "shared/ui/Banner.vue has no GPL header",
    ]);
  });

  it("keep src/ to the layers and the listed legacy entries", () => {
    const sources = tree({ "helpers/wait.ts": "", "vite-env.d.ts": "" });

    expect(checkArchitecture(sources, ["utils"])).toEqual([
      "src/helpers is neither a layer nor listed in LEGACY",
      "LEGACY lists utils, which no longer exists",
    ]);
  });
});
