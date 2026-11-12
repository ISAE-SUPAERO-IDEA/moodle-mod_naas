# NaaS widget (Vue 3)

The Nugget search widget (activity form) and the Nugget view (activity page) are a
Vue 3 + TypeScript application built with Vite into a single IIFE bundle,
`assets/vue/naas_widget-<version>.js`. Moodle loads it from `templates/widget.mustache`
and passes its configuration in `window.NAAS`. Every call to NaaS goes through
Moodle web services (`core/ajax`), never directly from the browser.

## Requirements

- Node 22
- pnpm 10.28. With Corepack (`corepack enable`), `pnpm` runs the version named by
  `packageManager` in `package.json`.

The toolchain follows the NaaS pnpm workspace (`pnpm-workspace/` in the NaaS repository):
pnpm, ESLint 9 with the same plugins, Prettier 3 and TypeScript 5.9.

## Commands

Run them from this `vue/` folder.

| Command | What it does |
|---|---|
| `pnpm install --frozen-lockfile` | Install the locked dependencies |
| `pnpm dev` | Start the Vite dev server with `dev_config.js` as `window.NAAS` |
| `pnpm type-check` | Type-check the application (`vue-tsc -p tsconfig.app.json`) |
| `pnpm test` | Run the unit tests (Vitest, jsdom) |
| `pnpm coverage` | Run the unit tests with coverage; fails under 100 % |
| `pnpm lint:check` | Run ESLint; fails on errors, reports warnings |
| `pnpm lint` | Run ESLint and apply its automatic fixes, Prettier formatting included |
| `pnpm lint:fsd` | Check the architecture with Steiger (see [Architecture](#architecture)) |
| `pnpm format:check` / `pnpm format` | Check / apply the Prettier formatting |
| `pnpm build` | Type-check, then write the bundle (CSS included) to `../assets/vue/` |
| `pnpm bump-version [YYYYMMDDnn]` | Rename the bundle (defaults to today's date) |

`pnpm lint` and `pnpm format` rewrite files. Any change in a `.vue` file, even of formatting,
changes its scoped-style id and so the bundle: rebuild and commit it in the same commit.
Markdown files are left out of Prettier (`.prettierignore`).

## Architecture

`src/` is being reorganised by [Feature-Sliced Design](https://fsd.how/) (FSD 2.1), like the
other NaaS front ends. The plan and its progress are in
`EPIC_vue_feature_sliced_design.md` at the plugin root.

| Layer | In this widget | May import |
|---|---|---|
| `app/` | Entry point, root component, Vue plugins, global theme | every layer below |
| `pages/` | The two widget modes chosen by `config.component`: Nugget picker and Nugget player | widgets, features, entities, shared |
| `widgets/` | Large blocks of a page: search bar, results, catalogue landing, selected Nugget | features, entities, shared |
| `features/` | Actions of the teacher or learner: filter, search, pick, preview, rate | entities, shared |
| `entities/` | NaaS business objects with their UI and API: nugget, catalogue, domain, person | shared |
| `shared/` | No business knowledge: Moodle API adapter, config, generic helpers and UI | nothing above |

A layer is split in **slices** (`features/filter-nuggets`), a slice in **segments**: `ui/`,
`model/` (state, composables), `api/` (calls through Moodle), `lib/` (pure helpers),
`config/`. `shared/` and `app/` have segments only.

Rules:

1. A file imports only from the layers below its own.
2. Two slices of the same layer do not import each other. The one exception is an `@x`
   entry point: `entities/catalogue/@x/nugget/index.ts` holds what `entities/nugget` may use
   from `entities/catalogue`.
3. From outside, a slice or a `shared/` segment is imported only through its `index.ts`
   (`@/features/filter-nuggets`). Inside it, imports are relative. An `index.ts` lists its
   exports by name, without `export *`.
4. A spec imports the file it tests directly. It may `vi.mock()` a file of another slice.
5. `src/test/` (mount helper, fixtures) is for specs only.
6. Every `.ts` and `.vue` file starts with the Moodle GPL header, `index.ts` included.

Where does a file go?

| The code… | Goes in |
|---|---|
| calls Moodle, is a NaaS response type, or is config injected by PHP | `shared/api`, `shared/config` |
| is generic (no Nugget, producer or facet vocabulary) | `shared/lib`, `shared/ui` |
| shows or loads **one** business object (Nugget, producer, domain, person) | `entities/<object>` |
| is an action of the teacher or learner (filter, search, pick, preview, rate) | `features/<verb-object>` |
| assembles several features and entities into a block of the page | `widgets/<block>` |
| is a widget mode selected by `config.component` | `pages/<mode>` |
| starts Vue, registers plugins, holds global CSS | `app/` |

Two tools check the rules, and both run in CI:

- `pnpm lint:fsd` runs [Steiger](https://github.com/feature-sliced/steiger), the FSD linter,
  with `steiger.config.mjs`. It checks rules 1 to 3 for the imports it can follow. Steiger
  asks for Node 22.18 or later; with a `.mjs` configuration it also runs on Node 22.14.
- `src/architecture.spec.ts` runs with the unit tests. It covers what Steiger cannot see:
  imports of `.vue` files (Steiger does not follow them), who uses an `@x` entry point,
  `vi.mock()` paths, rules 5 and 6, and the folders from before the migration. Its `LEGACY`
  list names those folders: code in a layer may not import them, and an entry is removed
  when its last file has moved.

## Releasing a new bundle

1. `pnpm bump-version`: updates `BUNDLE_VERSION` in `vite.config.ts` and
   `widget::BUNDLE_VERSION` in `classes/output/widget.php`. The new name stops
   browsers from reusing a cached bundle. `version.php` is not touched.
2. `pnpm build`
3. Commit `vite.config.ts`, `classes/output/widget.php` and `assets/vue/` (the new
   bundle and the removal of the previous one).

The CI `widget` job rebuilds the bundle and fails if `assets/vue/` differs from the
committed files, so always commit the output of `pnpm build`.

## Development server

`pnpm dev` serves `index.html` and injects `dev_config.js` as `window.NAAS`. Edit that
file to choose the component (`NuggetSearchWidget` or `NuggetView`), the Moodle URL,
a `sesskey` from a logged-in session, and a course or course-module id. The browser
then calls the Moodle web services of that site, so it must accept requests from the
dev server origin.

Most changes are easier to check in Moodle itself: run `pnpm build`, purge the Moodle
caches, and reload the activity form or the activity page.
