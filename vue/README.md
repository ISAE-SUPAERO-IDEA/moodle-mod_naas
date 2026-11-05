# NaaS widget (Vue 3)

The Nugget search widget (activity form) and the Nugget view (activity page) are a
Vue 3 + TypeScript application built with Vite into a single IIFE bundle,
`assets/vue/naas_widget-<version>.js`. Moodle loads it from `templates/widget.mustache`
and passes its configuration in `window.NAAS`. Every call to NaaS goes through
Moodle web services (`core/ajax`), never directly from the browser.

## Requirements

- Node 22
- Yarn 1.22

## Commands

Run them from this `vue/` folder.

| Command | What it does |
|---|---|
| `yarn install --frozen-lockfile` | Install the locked dependencies |
| `yarn dev` | Start the Vite dev server with `dev_config.js` as `window.NAAS` |
| `yarn type-check` | Type-check the application (`vue-tsc -p tsconfig.app.json`) |
| `yarn test` | Run the unit tests (Vitest, jsdom) |
| `yarn build` | Type-check, then write the bundle and `style.css` to `../assets/vue/` |
| `yarn bump-version [YYYYMMDDnn]` | Rename the bundle (defaults to today's date) |
| `yarn lint` | Format the sources with Prettier |

## Releasing a new bundle

1. `yarn bump-version`: updates `BUNDLE_VERSION` in `vite.config.ts` and
   `widget::BUNDLE_VERSION` in `classes/output/widget.php`. The new name stops
   browsers from reusing a cached bundle. `version.php` is not touched.
2. `yarn build`
3. Commit `vite.config.ts`, `classes/output/widget.php` and `assets/vue/` (the new
   bundle and the removal of the previous one).

The CI `widget` job rebuilds the bundle and fails if `assets/vue/` differs from the
committed files, so always commit the output of `yarn build`.

## Development server

`yarn dev` serves `index.html` and injects `dev_config.js` as `window.NAAS`. Edit that
file to choose the component (`NuggetSearchWidget` or `NuggetView`), the Moodle URL,
a `sesskey` from a logged-in session, and a course or course-module id. The browser
then calls the Moodle web services of that site, so it must accept requests from the
dev server origin.

Most changes are easier to check in Moodle itself: run `yarn build`, purge the Moodle
caches, and reload the activity form or the activity page.
