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
| `pnpm format:check` / `pnpm format` | Check / apply the Prettier formatting |
| `pnpm build` | Type-check, then write the bundle (CSS included) to `../assets/vue/` |
| `pnpm bump-version [YYYYMMDDnn]` | Rename the bundle (defaults to today's date) |

About twenty files still have the formatting of Prettier 2: ESLint reports them as
`prettier/prettier` warnings. `pnpm lint` and `pnpm format` rewrite them, and a change in a
`.vue` file changes the bundle, so rebuild and commit it in the same commit.

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
