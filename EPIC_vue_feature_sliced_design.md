# EPIC — Refactor the Vue widget to Feature-Sliced Design

| | |
|---|---|
| **Scope** | `vue/` only (NaaS Vue 3 widget, IIFE bundle `assets/vue/naas_widget-*.js`), plus the CI and helper scripts that call its toolchain |
| **Base** | `fix/small_modifications_ux` at a734c88. PR #132 is not merged yet: rebase onto `develop` once it is. |
| **Working branch** | `chore/fsd_migration`, one commit (or short commit series) per story |
| **Baseline** | a734c88: 62 source files (~9.6 k lines), 53 spec files, 100 % coverage on lines, statements, functions and branches |
| **Status** | 🟡 In progress. Planned 2026-11-10, reviewed against the code and the NaaS workspace on 2026-11-12 (§11). S0a to S3, S4a, S4b and S4c done on 2026-11-12; next: S4d. |
| **Estimate** | ≈ 8 to 9 working days |

---

## 1. Why

Today `vue/src/` is split by technical type: `components/` (18 `.vue`), `composables/` (28 `.ts`, many of them pure helpers that are not composables), `types/`, `service/`, `plugins/`, `utils/`. With the widget now at ~9.6 k lines, this layout has the following problems:

- **No visible ownership.** You can't tell which files make up the facet filter, the catalogue landing or the Nugget picker without reading the imports.
- **Hidden coupling.** `NuggetPost.vue` (the card used everywhere) imports `NuggetViewModal.vue` (preview through Moodle). `facetColumns.ts` and `searchAggregations.ts` get `isRawEntityLabel` from `structureVisuals.ts`, a producer payload parser. `catalogueSnapshot.ts` and `producerDirectory.ts` import each other's concepts.
- **`composables/` is a catch-all.** It holds real composables (`useNuggetSearch`), pure functions (`facetColumns`, `searchPaging`), a Moodle DOM bridge (`moodleActivityForm`), and generic helpers (`formatDate`, `mediaUrls`).
- **No guardrail.** Nothing stops a new import from reaching across the widget.
- **A toolchain of its own.** The widget uses yarn 1 and has no working linter, while the other NaaS front ends (`pnpm-workspace/apps/author`, `libs/sdk`) share pnpm, ESLint 9, Prettier 3 and Feature-Sliced Design.

Feature-Sliced Design (FSD) gives a well-known layout with **one-way dependencies** that tooling can check.

## 2. Goals and non-goals

**Goals**

1. Reorganise `vue/src/` into FSD layers: `app → pages → widgets → features → entities → shared`.
2. Give each slice, and each `shared/` segment, a **public API** (`index.ts`). Code outside imports only from that file.
3. Remove the existing layer violations with small, tested changes (§5).
4. Enforce the rules in CI with the official FSD linter **`steiger`** (`pnpm lint:fsd`), plus a short Vitest spec for what it cannot see.
5. Use the **same toolchain as the NaaS pnpm workspace**: pnpm 10.28, ESLint 9 with the same plugins, Prettier 3.6.2, TypeScript 5.9 (D7).
6. Keep CI green at every story: `pnpm type-check`, 100 % coverage, committed bundle matching a fresh build.

**Non-goals**

- No behaviour or UI change. Behat scenarios, CSS class names and Moodle web service calls stay the same.
- No new runtime dependency. No router, no Pinia, no Vuetify (Moodle-plugin constraint). The bundle stays a single IIFE mounted from `window.NAAS`.
- `@naas/sdk` is not used. It is a source-exported workspace package (not on npm) and its peers are Vuetify, vue-router, axios and TanStack Query.
- No change of build majors. Vite 6.4, Vitest 4.1 and vue-tsc 3 stay (D7).
- No reformat. Prettier moves to 3.6.2 but keeps the widget's current options. The workspace's style (no semicolons, 100 columns, organised imports) is not adopted here.
- No test rewrite. The specs keep `src/test/mountComponent.ts`; `@vue/test-utils` is not added.
- No file renames beyond what the layer move needs. File names are kept so `git log --follow` and review diffs stay readable. The `config.component === "NuggetView"` contract with PHP is unchanged.
- Splitting the large files (`NuggetBrowseLanding.vue` 557 lines, `NuggetPost.vue` 491, `NuggetAboutModal.vue` 480, `useNuggetSearch.ts` 458, `structureVisuals.ts` 458) is out of scope. It becomes easier afterwards (§9).
- Lot H (batch id params) stays postponed and is unrelated.

## 3. Target architecture

### 3.1 Layers in this widget

| Layer | Meaning in this widget | Can import |
|---|---|---|
| `app/` | Entry point, root component, Vue plugins/providers, global theme | everything below |
| `pages/` | The two **widget modes** chosen by `config.component`: Nugget picker (activity form) and Nugget player (course page). They play the role of routes. | widgets, features, entities, shared |
| `widgets/` | Large self-contained blocks of a page: search bar, results, catalogue landing, selected Nugget | features, entities, shared |
| `features/` | User actions that bring business value: filter, search, pick, preview, rate | entities, shared |
| `entities/` | NaaS business objects and their UI and API: nugget, catalogue (producers + snapshot), person, domain | shared, and one `@x` cross-import (D9) |
| `shared/` | No business knowledge: Moodle API adapter, config, generic lib, generic UI | nothing above |

Rules (FSD 2.1, the same as in the NaaS workspace):
- **R1:** a module imports only from lower layers. Within `shared/` and `app/`, which have no slices, segments can import each other. Such an import is relative and targets the other segment's `index.ts` (`../api`): Steiger asks for it.
- **R2:** no import between two slices of the same layer, for example `features/filter-nuggets` → `features/search-nuggets` is forbidden. The only way across is an `@x` public API: `entities/<a>/@x/<b>/index.ts` exposes to slice `b` what it needs from slice `a`.
- **R3:** from outside, a slice (and each `shared/` segment) is imported **only through its `index.ts`**: `@/features/filter-nuggets`, never `@/features/filter-nuggets/lib/facetColumns`. Inside a slice, imports are relative and never go through the slice's own `index.ts`. An `index.ts` uses named exports, no `export *`.
- **R4:** standard segments: `ui/`, `model/` (state, composables), `api/` (calls through Moodle), `lib/` (pure helpers), `config/` (constants).
- **Specs:** a spec imports its neighbour file directly. A spec may `vi.mock()` a file inside another slice, because a mock must target the real module, not the `index.ts` that re-exports it.
- `src/test/` (mount helpers, fixtures) stays outside the layers. Only `*.spec.ts` may import it.

### 3.2 Target tree (file → new place)

```
vue/src/
├── app/
│   ├── main.ts                         ← main.ts
│   ├── App.vue                         ← Main.vue (only rename: it is the app root)
│   ├── providers/naas-api.plugin.ts    ← plugins/naas-api.plugin.ts (without NAAS_API_KEY)
│   └── styles/naas-theme.css           ← styles/naas-theme.css
├── pages/
│   ├── nugget-picker/
│   │   ├── ui/NuggetSearchWidget.vue   ← components/NuggetSearchWidget.vue
│   │   ├── ui/naas-activity-form.css   ← styles/naas-activity-form.css
│   │   └── index.ts
│   └── nugget-player/
│       ├── ui/NuggetView.vue           ← components/NuggetView.vue
│       ├── ui/NuggetViewSkeleton.vue   ← components/NuggetViewSkeleton.vue
│       ├── model/useNuggetView.ts      ← composables/useNuggetView.ts
│       ├── lib/frameVisibility.ts      ← composables/frameVisibility.ts
│       └── index.ts
├── widgets/                            (each slice has an index.ts)
│   ├── search-bar/ui/NuggetSearchBar.vue                 ← components/
│   ├── search-results/
│   │   ├── ui/NuggetSearchResults.vue                    ← components/
│   │   ├── ui/NuggetResultsGrid.vue                      ← components/
│   │   └── lib/cardGridKeys.ts                           ← composables/
│   ├── catalogue-landing/
│   │   ├── ui/NuggetBrowseLanding.vue                    ← components/
│   │   └── model/useCatalogueCheck.ts                    ← composables/
│   └── selected-nugget/ui/NuggetSelectedPanel.vue        ← components/
├── features/                           (each slice has an index.ts)
│   ├── filter-nuggets/
│   │   ├── ui/NuggetSearchFilter.vue, FilterFacetColumn.vue,
│   │   │   FilterChips.vue, FilterSkeleton.vue           ← components/
│   │   ├── model/useFacetLabels.ts                       ← composables/
│   │   └── lib/facetColumns.ts, searchAggregations.ts    ← composables/
│   ├── search-nuggets/
│   │   ├── model/useNuggetSearch.ts, useSearchWidgetQuery.ts  ← composables/
│   │   └── lib/searchPaging.ts                           ← composables/
│   ├── pick-nugget/
│   │   ├── model/useNuggetSelection.ts                   ← composables/
│   │   └── lib/moodleActivityForm.ts                     ← composables/
│   ├── preview-nugget/
│   │   └── ui/NuggetViewModal.vue                        ← components/
│   └── rate-nugget/
│       └── ui/NuggetCompletionModal.vue                  ← components/
├── entities/                           (each slice has an index.ts)
│   ├── nugget/
│   │   ├── ui/NuggetPost.vue, NuggetSkeleton.vue, NuggetAboutModal.vue   ← components/
│   │   ├── api/useNuggetEnricher.ts                      ← composables/
│   │   └── lib/nuggetLicence.ts                          ← composables/
│   ├── catalogue/          (producers + snapshot: they reference each other)
│   │   ├── lib/catalogueSnapshot.ts, producerDirectory.ts, structureVisuals.ts  ← composables/
│   │   ├── model/useProducerDirectory.ts                 ← composables/
│   │   ├── api/structureResolvers.ts                     (split out of useEntityResolvers)
│   │   └── @x/nugget/index.ts                            (the structure resolver, for the About sheet)
│   ├── domain/api/domainResolvers.ts                     (split out of useEntityResolvers)
│   └── person/api/personResolvers.ts                     (split out of useEntityResolvers)
├── shared/
│   ├── api/
│   │   ├── moodle-naas-api.service.ts, naas-api.interface.ts,
│   │   │   unwrapNaasPayload.ts                          ← service/
│   │   ├── naasApiKey.ts                                 (NAAS_API_KEY, out of plugins/naas-api.plugin.ts)
│   │   ├── useMoodleService.ts                           ← composables/
│   │   ├── media.ts                                      ← composables/mediaUrls.ts
│   │   ├── xapi.ts                                       ← composables/useXapi.ts
│   │   ├── errorMessage.ts                               ← utils/ (reads Moodle core/ajax rejections)
│   │   ├── types/  nugget, person, domain, structure DTOs ← types/*.types.ts
│   │   └── index.ts
│   ├── config/
│   │   ├── naas-config.types.ts, readNaasConfig.ts, useNaasConfig.ts
│   │   ├── licenceCounts.ts                              ← composables/ (a predicate on the config)
│   │   └── index.ts
│   ├── lib/
│   │   ├── debounce.ts, fillLabel.ts                     ← utils/
│   │   ├── formatDate.ts                                 ← composables/
│   │   ├── entityKeys.ts   (isOpaqueEntityKey, isRawEntityLabel, lookupLabel,
│   │   │                    normalizeStructureKey, normalizePersonKey, out of structureVisuals)
│   │   └── index.ts
│   └── ui/
│       ├── NuggetLoadingBanner.vue                       ← components/ (used by 2 widgets)
│       ├── clickOutside.ts                               ← utils/
│       ├── useModalA11y.ts, useLoadMoreSentinel.ts       ← composables/
│       └── index.ts
├── test/                    (unchanged: mountComponent, modalKeys, widgetFixtures)
├── architecture.spec.ts     (what Steiger cannot check: .vue imports, @x users, vi.mock() paths, headers)
└── vite-env.d.ts
```

### 3.3 Decisions

| # | Decision | Reason | Alternative considered |
|---|---|---|---|
| D1 | NaaS **DTO types** (`Nugget`, `Person`, `Domain`, `Structure`, aggregations, `SearchResult`) live in `shared/api/types` | `Nugget` embeds `Person[]` and `Domain[]`. Putting them in entities would force `@x` cross-imports between entities. The FSD guidance allows API contracts in `shared/api`. | `entities/*/model/types.ts` + `@x` notation: more files, no real gain at this size |
| D2 | **One `entities/catalogue` slice** for producers, snapshot and producer directory | `producerDirectory → catalogueSnapshot`, `catalogueSnapshot → structureVisuals`, and `useProducerDirectory` uses both. They are one concept: the catalogue Moodle injects and then checks. | `entities/producer` + `entities/catalogue` with `@x`: a 2-way coupling with no benefit |
| D3 | `useXapi` → `shared/api/xapi.ts` | It is a thin, fire-and-forget wrapper over a Moodle web service, used by the player page and by `rate-nugget` | `entities/nugget/api`: also valid |
| D4 | `useSearchWidgetQuery` goes in `features/search-nuggets` (not in the page) | It is the search query state (text, filters, paging) and is unit-tested on its own | `pages/nugget-picker/model`: closer to "orchestration", but it would make the page fat again |
| D5 | Architecture checked by **`steiger`** (`pnpm lint:fsd`) plus a Vitest spec (`src/architecture.spec.ts`). Validated 2026-11-12, tool behaviour measured in S0b. | `steiger` is the official FSD linter and the one the NaaS epics plan (`EPIC-author-v2-fsd-client` A5, `quality-gates` D2). It reads imports written inside `.vue` files and accepts `@x`. It has blind spots, which the spec covers: it does not follow an import whose **target** is a `.vue` file, does not check who uses an `@x` entry point, skips the old folders, and does not read `vi.mock()` paths. | Home-made Vitest spec only (first version of this epic): no new dependency, but a different tool from NaaS. |
| D6 | **No file renames** except `Main.vue → App.vue` | Readable review diffs (`git mv` + import changes only) and `--follow` history | The NaaS suffix naming (`*.composable.ts`, `*.api.ts`, `*.test.ts`) and names like `NuggetCard`: possible later in their own PR |
| D7 | **Toolchain aligned with the NaaS pnpm workspace**: pnpm 10.28, ESLint 9 with the same plugins, Prettier 3.6.2, TypeScript ^5.9.3. Vite 6.4, Vitest 4.1, vue-tsc 3 and the current formatting stay. Validated 2026-11-12. | One toolchain for the NaaS front ends. The workspace *declares* Vite ^6.4.3 and Vitest ^3.2.7; it only *resolves* 8.3.5 and 5.0.5 through overrides, which its own `quality-gates` D3 wants to undo. A major build change would also change the bundle. | Also adopt the workspace's Prettier options: one reformat commit on every file. Left for later. |
| D8 | **`NuggetPost` keeps its buttons and only emits.** It emits a new `PreviewButton` event and no longer imports the preview modal; the two widgets that show cards render `NuggetViewModal`. Validated 2026-11-12. | The card holds no pick logic today, it already emits `SelectButton` and `ReplaceButton`. The modals are teleported to `body`, so moving the modal changes nothing in the DOM. No new component; same CSS and accessibility attributes. | Feature button components in slots (first version): Replace is the × at the top of the card and About sits between Select and Preview, so it needs three scoped slots exposing the title id, and the buttons' scoped CSS has to move. |
| D9 | **One `@x` cross-import**: `entities/catalogue/@x/nugget` gives the About sheet the structure resolver | `NuggetAboutModal` shows producer acronyms. The NaaS SDK does the same (`entities/structure/@x/nugget`). | Resolve the names in the widgets and pass them as a prop: more plumbing through `NuggetPost` for no gain |
| D10 | **Slices with one consumer are kept** (the four widgets, `search-nuggets`, `rate-nugget`); the "insignificant slice" rule of `steiger` is turned off with a comment | FSD 2.1 would fold them into the page. With two pages, that puts most of the widget in `pages/nugget-picker` and loses the ownership this epic is after. | Pages-first layout: fewer folders, one very large page slice |

## 4. Constraints to keep in mind for every story

- **GPL header** on every new `.ts`/`.vue`/`.js`/`.mjs` file, including each `index.ts` (Moodle code-checker rule).
- **Committed bundle.** The CI step "Committed bundle matches the sources" rebuilds `assets/vue/`. Moving a `.vue` file changes its scoped-style id (`data-v-xxxx`, hashed from the file path), so **each story that moves a `.vue` file must run `pnpm build` and commit the new bundle**. `BUNDLE_VERSION` (`pnpm bump-version` + `version.php`) is bumped **once**, in S7.
- **`vi.mock()` paths are strings.** `vue-tsc` does not check them. Today there are 4 (`./NuggetSearchFilter.vue` ×2, `@/components/NuggetView.vue`, `@/components/NuggetSearchWidget.vue`), plus one package mock (`iframe-resizer`). `architecture.spec.ts` checks that each path resolves to a file (S0b).
- **A file moves only when everything it imports has moved.** `architecture.spec.ts` forbids code in a layer to import the old folders, and forces every import that leaves a slice to go through an `index.ts`. That is what bottom-up means in practice.
- **Specs move with their source file** (colocation kept). `nuggetFreshness.spec.ts` tests `useNuggetSearch` exports and moves with it. `skeletons.spec.ts` covers three skeletons that go to three slices: it is split in three.
- **100 % coverage** must hold after each story. New `index.ts` files contain only re-exports, so v8 should count no executable lines. Check this in S0b.
- **One commit per story**, or two when a story mixes moves and a logic change: commit 1 = `git mv` + imports (no behaviour change), commit 2 = the logic change. That keeps the move diff reviewable.
- Moodle stays the only path from the browser to NaaS, including images (`shared/api/media.ts`) (Moodle-plugin constraint).

## 5. Layer violations to fix (more than moving files)

| # | Today | Problem in FSD | Fix | Story |
|---|---|---|---|---|
| V1 | `NuggetPost.vue` imports `NuggetViewModal.vue` (preview through Moodle) | entity → feature | D8: the card emits `PreviewButton`. `NuggetResultsGrid` and `NuggetSelectedPanel` render one `NuggetViewModal` each. | S3 |
| V2 | `NuggetPost.vue` draws the Select and Replace buttons | None: the card only emits `SelectButton` / `ReplaceButton`, the pick logic is in `useNuggetSelection` | Nothing to do (D8) | — |
| V3 | `facetColumns`, `searchAggregations`, `useFacetLabels`, `NuggetBrowseLanding` take key helpers from `structureVisuals` | feature → entity internals, and a generic helper hidden in a producer parser | Extract `entityKeys.ts` to `shared/lib` | S1 |
| V4 | `useEntityResolvers` resolves domain + structure + person in one composable | spans 3 entities | Split into `entities/domain/api`, `entities/catalogue/api`, `entities/person/api`. It has three users: `useFacetLabels` (feature), `NuggetBrowseLanding` (widget) and `NuggetAboutModal` (entity, see V7). **Keep the per-page caches** (same keys, resolved once). | S2 |
| V5 | `NuggetLoadingBanner` and `NuggetSkeleton` are used by two widgets (`search-results`, `selected-nugget`) | widget → widget would be forbidden | Banner → `shared/ui`, skeleton → `entities/nugget/ui` | S1, S3 |
| V6 | Nugget/Person/Domain types import each other | entity ↔ entity | D1: DTOs in `shared/api/types` | S1 |
| V7 | `NuggetAboutModal.vue` calls `getStructureAcronym` from `useEntityResolvers` | entity (`nugget`) → entity (`catalogue`) | D9: `entities/catalogue/@x/nugget/index.ts` exports the structure resolver. This is why the catalogue story comes before the nugget story. | S3 |
| V8 | `useMoodleService.ts` imports `NAAS_API_KEY` from `plugins/naas-api.plugin.ts`. So do 10 specs and `test/widgetFixtures.ts`. | shared → app | `NAAS_API_KEY` moves to `shared/api/naasApiKey.ts`. The plugin, the specs and the fixtures import it from `@/shared/api`. | S1 |

## 6. Stories

Each story leaves releasable code: type-check ✅, `pnpm coverage` 100 % ✅, `pnpm lint:check` ✅, `pnpm build` + bundle committed ✅, `pnpm lint:fsd` ✅ from S0b on.

### S0a — Toolchain (≈ 1 d)

No source file moves in this story, and the bundle must come out **byte-identical**.

- **pnpm 10.28.** `pnpm import` converts `yarn.lock` into `pnpm-lock.yaml` with the same resolved versions; delete `yarn.lock`. `packageManager` and `engines` in `package.json`. The yarn `resolutions` become `pnpm.overrides`.
- **ESLint 9** flat config (`vue/eslint.config.mjs`) with the workspace's plugins: `typescript-eslint`, `eslint-plugin-vue`, `eslint-plugin-sonarjs`, `eslint-plugin-unused-imports`, `eslint-config-prettier`. Same rule levels as `pnpm-workspace/eslint.config.mjs`. Delete the dead `vue/.eslintrc.js` (Vue CLI leftover). Scripts `lint` and `lint:check`.
- **Prettier 3.6.2** with the current options. Prettier 3 changed the default of `trailingComma`, so `.prettierrc.json` pins `"es5"`. The old `lint` script (Prettier) becomes `format`, with `format:check`.
- **TypeScript** range raised to `^5.9.3` (already the resolved version).
- Callers: `ci.yml` (widget job, with a new lint step), `dependency-audit.yml` (`pnpm audit --prod`), `run_build.sh`, `run_test.sh`, `README.md`, `vue/README.md`, and the messages in `bump-version.js` and `vite.config.ts`.

**Done when** `pnpm install --frozen-lockfile`, `type-check`, `coverage` (100 %), `lint:check` (0 error) and `build` pass, and `git status -- assets/vue` is empty after the build.

**Result (2026-11-12): done.** All five commands pass, 471 tests at 100 %, the bundle is byte-identical, `pnpm audit --prod` reports nothing. Left for later:

- `lint:check` reports 116 warnings and 0 error: 79 are Prettier 3 formatting in 22 files (about 270 lines, mostly multi-value `transition` rules in `<style>` blocks), 37 are complexity and Vue style rules. The size and repetition rules are off in specs.
- Formatting those 22 files changes `.vue` sources, so the bundle: do it in one `style:` commit inside a story that rebuilds the bundle anyway (S1).
- Five files have no GPL header: `vitest.config.ts` and the specs `searchPaging`, `useMoodleService`, `useNaasConfig`, `useXapi`. Add it when each file is moved.
- The two workflow files use `pnpm/action-setup@v4`; they run for the first time on the next push.

### S0b — Guardrails (≈ 0.5 d)

- Add `steiger` and its FSD plugin as dev dependencies, a `steiger.config.mjs` and a `lint:fsd` script. **First prove what it reads** with deliberate violations.
- Add `vue/src/architecture.spec.ts` for what Steiger cannot check. Its `LEGACY` list names the old folders (`components/`, `composables/`, …); it shrinks story by story and must be empty in S7. "Insignificant slice" is off (D10).
- Prove that a re-export-only `index.ts` does not change coverage (empty `shared/lib/index.ts` as the probe).
- Add an "Architecture" section to `vue/README.md`: layers, rules, the "where does my file go?" table (§8).
- CI: `pnpm lint:fsd` in the widget job.

**Done when** the linter runs in CI, an injected violation fails it, and coverage is still 100 %.

**Result (2026-11-12): done.** `steiger` 0.10.0 with `@feature-sliced/steiger-plugin` 0.8.2 (exact versions: the tool is in beta), `steiger.config.mjs`, `pnpm lint:fsd` in CI and in `run_test.sh`. 481 tests at 100 %, bundle byte-identical. What the probes showed:

- **Reads `.vue` sources:** an upward import written in `<script setup lang="ts">` is reported, with the `@/` alias resolved through `tsconfig.app.json`.
- **Does not follow `.vue` targets:** `import X from "@/features/a/ui/X.vue"` is invisible to it, whatever file contains it. `architecture.spec.ts` therefore requires every import that leaves a slice to target an `index.ts`, where Steiger then checks the direction.
- **`@x` is accepted** as a folder with an `index.ts` (the NaaS form) and as a single file. Steiger does not check who imports it; the spec does.
- **Old folders are skipped**, except `src/types`, which it takes for a misspelt `pages`: one block in the config silences that until S1 deletes the folder.
- **Two optional rules are on:** `fsd/import-locality` (relative inside a slice) and `fsd/no-wildcard-exports`. `fsd/insignificant-slice` is off (D10).
- **Node:** Steiger 0.7 and later ask for Node 22.18. A `.ts` config crashes on Node 22.14; the `.mjs` config works there.
- **Coverage:** a re-export-only `index.ts` that no test imports counts 0 / 0 on the four metrics.
- **Spec content:** one test on the real sources, nine on made-up trees (one per rule), so each rule is proven to fire. An injected import from a layer to an old folder failed it as expected.
- The five files without a GPL header got one, because the spec now checks headers.
- `EPIC_*.md` is now left out of the moodle.org archive (`.gitattributes`).

### S1 — `shared/` layer (≈ 1 d)

- `shared/api`: service, interface, `unwrapNaasPayload`, `useMoodleService`, **`naasApiKey.ts`** (V8), `media.ts`, `xapi.ts`, `errorMessage.ts`, DTO `types/`.
- `shared/config`: `NaasConfig` and friends, `readNaasConfig`, `useNaasConfig`, `licenceCounts.ts`. Catalogue types (`CatalogueSnapshot`, `CatalogueProducer`) stay in `naas-config.types.ts` for now: they describe the injected JSON (shared contract).
- `shared/lib`: `debounce`, `fillLabel`, `formatDate`, **new `entityKeys.ts`** (V3) with tests moved from `structureVisuals.spec.ts`.
- `shared/ui`: `clickOutside`, `useModalA11y`, `useLoadMoreSentinel`, `NuggetLoadingBanner` (V5).
- One `index.ts` per segment, named exports only.
- First commit (`style:`), done on 2026-11-12 before any move: the 22 files Prettier 3 reported are formatted. 37 lint warnings are left (complexity and Vue style rules). The rebuilt bundle is identical to the previous one once the scoped-style ids are masked (10 ids changed).
- Delete `service/`, `utils/`, `types/`, their three entries in `LEGACY` (`architecture.spec.ts`) and the `src/types` block in `steiger.config.mjs`. Update `vitest.config.ts` (`exclude: src/types/*` → `src/shared/api/types/*`), the 10 specs and `test/widgetFixtures.ts` that import `NAAS_API_KEY`.

**Done when** `shared/` imports nothing outside itself and there is no `@/types/`, `@/service/` or `@/utils/` import left.

**Result (2026-11-12): done**, in one commit after the `style:` commit. 38 files moved (git records them all as renames), 70 files with rewritten imports, 7 new files: four `index.ts`, `naasApiKey.ts` (V8), `entityKeys.ts` and its spec (V3). `service/`, `types/` and `utils/` are gone. All checks pass: 482 tests at 100 % with the same statement, branch and function counts as before, `lint:fsd` and `lint:check` with 0 error.

- **V3 and V8 are in the same commit as the moves.** Both only relocate code, and layer code may not import the old folders, so `useMoodleService` could not move without the key.
- **Key-helper tests** left `structureVisuals.spec.ts` for `shared/lib/entityKeys.spec.ts` (one test added for `isOpaqueEntityKey`, which was only asserted inside a `structureVisuals` test).
- **Public APIs export only what is used outside the segment** (22 names in `shared/api`, 8 in `config`, 8 in `lib`, 4 in `ui`). A script checked that none is unused.
- **Imports are merged per public API:** one value import and one `import type` per `@/shared/<segment>`.
- **Bundle:** same CSS rules and same string literals as before. It is 797 bytes larger because the minifier keeps each module's licence comment (46 before, 48 now: the two new modules). Without comments the size differs by 12 bytes. 15 scoped-style ids changed: the id is a hash of the file path **and its source**, so editing an import line in a `.vue` file changes it too.
- `plugins/naas-api.plugin.ts` no longer re-exports `INaasApiService` (nothing imported it from there).
- The moves are scripted: a small Python script takes a table "old path → new path" and rewrites the imports. The same script will serve S2 to S6.

### S2 — `entities/catalogue`, `entities/domain`, `entities/person` (≈ 1 d)

- `catalogue`: `catalogueSnapshot`, `producerDirectory`, `structureVisuals` (without the key helpers), `useProducerDirectory`, `structureResolvers` (structure part of `useEntityResolvers`, including `visualsFromSnapshot`). The `@x/nugget/index.ts` entry point (V7) is created in S3, with its first user.
- `domain` / `person`: one resolver each, split out of `useEntityResolvers` (V4).
- Delete `useEntityResolvers.ts`. Split its spec into the three slices **without lowering branch coverage** (the cache and error-fallback cases must each have a test).

**Done when** no import goes from one entity to another except through `@x`, and the facet labels behave the same (existing `useFacetLabels` specs pass unchanged).

**Result (2026-11-12): done**, in one commit. 10 files moved to `entities/catalogue`, 9 files with rewritten imports, and `useEntityResolvers` split in three: `useStructureResolvers` (catalogue), `useDomainResolvers`, `usePersonResolvers`. All checks pass: 485 tests at 100 %, `lint:fsd` and `lint:check` with 0 error. The `useFacetLabels` specs pass unchanged.

- **No cache to keep.** `useEntityResolvers` held no cache of its own: the "resolved once per page" memory is `resolvedLabels` in `useFacetLabels` and the web service cache in `shared/api`. Both are untouched.
- **`stringField`** (first non-blank text among several keys) was private to the old file and is needed by the domain and person resolvers: it is now `shared/lib/stringField.ts`, with a spec.
- **The eleven tests are split** by entity: five for structures, two for domains, four for persons. Their common `withProviders` helper moved to `src/test/withProviders.ts`.
- **`@x/nugget` is not created yet.** Until S3 the About sheet is still in `components/` and takes `useStructureResolvers` from `@/entities/catalogue`. The entry point comes in S3 with its first user, so that no file sits unused.
- **Coverage counts** rose by 8 statements and 2 functions: three composables each inject the service and the configuration, where one did before.
- **Bundle:** same CSS rules; three more licence comments (four new modules, one removed) and 92 bytes more code without comments. 3 scoped-style ids changed.
- git records `useEntityResolvers.ts` as deleted and `structureResolvers.ts` as new: two thirds of the file left it.

### S3 — `entities/nugget` (≈ 1 d)

- **Commit 1 (logic, V1), files still in `components/`:** `NuggetPost` emits `PreviewButton` and no longer imports `NuggetViewModal`. `NuggetResultsGrid` and `NuggetSelectedPanel` hold the previewed Nugget and render the modal. The preview tests move from `NuggetPost.spec.ts` to the two widget specs. The logic change comes first because, once in `entities/`, the card may not import `components/NuggetViewModal.vue`.
- **Commit 2 (moves):** `NuggetPost`, `NuggetSkeleton`, `NuggetAboutModal`, `useNuggetEnricher`, `nuggetLicence`. Create `entities/catalogue/@x/nugget/index.ts`; `NuggetAboutModal` takes `useStructureResolvers` from it. The `NuggetSkeleton` part of `skeletons.spec.ts` moves with it.
- Keep the a11y tests: focus goes back to the Preview button on close (`useModalA11y` remembers the opener), `aria-pressed` on Select, `aria-describedby` on the three buttons.

**Done when** `entities/nugget` imports only `shared/` and `entities/catalogue/@x/nugget`.

**Result of commit 1 (2026-11-12): done.** `NuggetPost` emits `PreviewButton` and no longer imports the preview modal (V1). 490 tests at 100 %, `lint:fsd` and `lint:check` with 0 error.

- **Results grid:** one `NuggetViewModal` per card, next to it in its list item, open when `previewedId` is that card's id. This keeps one modal instance per card as before, so the modal still sees its `visible` prop change and loads the preview URL.
- **Selected panel:** one `NuggetViewModal` next to the card, driven by a `showPreview` flag.
- **A preview does not outlive its card.** Before, the open state lived in the card and was lost with it. Now a watcher in the grid clears `previewedId` when the previewed Nugget leaves the list, and the panel clears its flag when the card goes away. Without this, a Nugget that came back in the list would show an open, empty preview.
- **Tests:** the two preview tests of `NuggetPost.spec.ts` became one ("emits PreviewButton and opens no dialog itself"); the grid spec got four preview tests and the panel spec two.
- **DOM:** unchanged. The modals are teleported to `body`, the Preview button is still inside `.nugget-post`, and `#nugget-preview-modal` keeps its id, which the two Behat preview scenarios use.
- The three listeners in the two widgets are now written hyphenated (`@select-button`, `@replace-button`, `@preview-button`), which Vue treats the same and removes two lint warnings.

**Result of commit 2 (2026-11-12): done.** 9 files moved to `entities/nugget` (all recorded as renames), 6 files with rewritten imports, 4 new files: the slice's `index.ts`, `entities/catalogue/@x/nugget/index.ts` (V7), and `NuggetSkeleton.spec.ts`. 490 tests at 100 % with unchanged counts; `lint:fsd` and `lint:check` with 0 error.

- `entities/nugget` imports `shared/api`, `shared/config`, `shared/lib`, `shared/ui`, `src/test` (specs only) and `entities/catalogue/@x/nugget`, nothing else.
- Its public API has four names: `NuggetPost`, `NuggetSkeleton`, `NuggetAboutModal`, `useNuggetEnricher`. `nuggetLicence` stays internal.
- `skeletons.spec.ts` lost its `NuggetSkeleton` cases to the new spec; the `FilterSkeleton` and `NuggetViewSkeleton` cases follow in S4a and S6.
- **Bundle:** same CSS rules, same licence comments, 18 bytes more code; 6 scoped-style ids changed.

### S4 — `features/` (≈ 1.5 d, 4 commits)

- **S4a `filter-nuggets`**: `NuggetSearchFilter`, `FilterFacetColumn`, `FilterChips`, `FilterSkeleton` (with its part of `skeletons.spec.ts`), `useFacetLabels`, `facetColumns`, `searchAggregations`. Update the two `vi.mock("./NuggetSearchFilter.vue")`.
- **S4b `search-nuggets`**: `useNuggetSearch` (+ `nuggetFreshness.spec.ts`), `useSearchWidgetQuery`, `searchPaging`.
- **S4c `pick-nugget`**: `useNuggetSelection`, `moodleActivityForm`.
- **S4d `preview-nugget` + `rate-nugget`**: `NuggetViewModal`, `NuggetCompletionModal`.

**Done when** no feature imports another feature and the `index.ts` files export only what widgets and pages use.

**Result of S4a (2026-11-12): done.** 13 files moved to `features/filter-nuggets` (all recorded as renames), 7 files with rewritten imports, 2 new files (`index.ts`, `FilterSkeleton.spec.ts`). 490 tests at 100 % with unchanged counts; `lint:fsd` and `lint:check` with 0 error.

- The public API has two names, `NuggetSearchFilter` and `FilterChips`: everything else is used only inside the slice.
- The two mocks now target `@/features/filter-nuggets/ui/NuggetSearchFilter.vue`. Mocking the file, not the `index.ts`, still replaces what the `index.ts` re-exports; `architecture.spec.ts` checks that the path exists.
- `skeletons.spec.ts` now holds only the `NuggetViewSkeleton` case; it is renamed with the player page in S6.
- **Bundle:** same CSS rules and licence comments, 6 bytes more code; 6 scoped-style ids changed.

**Result of S4b (2026-11-12): done.** 7 files moved to `features/search-nuggets` (all recorded as renames, `nuggetFreshness.spec.ts` included), one new `index.ts` exporting `useNuggetSearch` and `useSearchWidgetQuery`. Only `NuggetSearchWidget.vue` imports the feature. 490 tests at 100 % with unchanged counts; the bundle code is the same size, with one scoped-style id changed.

**Result of S4c (2026-11-12): done.** 4 files moved to `features/pick-nugget` (all recorded as renames), one new `index.ts` with six names: `useNuggetSelection`, `readStoredNuggetId` and the four Moodle form helpers the search widget and the selected panel call. 490 tests at 100 % with unchanged counts; the bundle code is the same size, with two scoped-style ids changed.

### S5 — `widgets/` (≈ 1 d)

- `search-bar` (`NuggetSearchBar` → `features/filter-nuggets`).
- `search-results` (`NuggetSearchResults`, `NuggetResultsGrid`, `cardGridKeys`).
- `catalogue-landing` (`NuggetBrowseLanding`, `useCatalogueCheck`).
- `selected-nugget` (`NuggetSelectedPanel`).

**Done when** no widget imports another widget and `components/` + `composables/` hold only page-level files.

### S6 — `pages/` and `app/` (≈ 0.5 d)

- `pages/nugget-picker` (`NuggetSearchWidget`, `naas-activity-form.css`), `pages/nugget-player` (`NuggetView`, `NuggetViewSkeleton` with the last part of `skeletons.spec.ts`, `useNuggetView`, `frameVisibility`).
- `app/main.ts`, `app/App.vue`, `app/providers/naas-api.plugin.ts`, `app/styles/naas-theme.css`.
- Update `vite.config.ts` (`build.lib.entry`), `index.html` (`/src/app/main.ts`), `vitest.config.ts` (`exclude: src/app/main.ts`), the 2 page `vi.mock` paths in `Main.spec.ts`.
- Delete `components/`, `composables/`, `plugins/`, `styles/`.

**Done when** `src/` contains only `app/ pages/ widgets/ features/ entities/ shared/ test/ vite-env.d.ts` (+ `architecture.spec.ts`).

### S7 — Lock in and release (≈ 0.5 d)

- `LEGACY` in `architecture.spec.ts` is empty and `steiger.config.mjs` has no block for an old folder. From now on, any violation fails CI. Fix or document what is left.
- `pnpm bump-version` (bundle + `widget::BUNDLE_VERSION`), `version.php` bump, `CHANGES.md` entry ("internal: widget sources reorganised by Feature-Sliced Design, built with pnpm, no functional change").
- Full CI: PHPUnit, Behat (they must pass unchanged, which proves no UI regression), widget coverage 100 %, PHPStan.
- Check that the moodle.org zip still includes `vue/` sources (moodle.org packaging rule).

## 7. Order and dependencies

```
S0a ─► S0b ─► S1 ─► S2 ─► S3 ─► S4a ─► S4b ─► S4c ─► S4d ─► S5 ─► S6 ─► S7
              (S2 before S3: the About sheet needs entities/catalogue/@x/nugget;
               S4a–S4d are independent once S3 is done)
```

Work bottom-up: each story moves the files that every later layer depends on, so only the layer being moved is in flux.

## 8. "Where does my file go?" (to copy into `vue/README.md`)

| The code… | Goes in |
|---|---|
| calls Moodle, is a NaaS response type, or is config injected by PHP | `shared/api`, `shared/config` |
| is generic (no Nugget, producer or facet vocabulary) | `shared/lib`, `shared/ui` |
| shows or loads **one** business object (Nugget, producer, domain, person) | `entities/<object>` |
| is an action of the teacher or learner (filter, search, pick, preview, rate) | `features/<verb-object>` |
| assembles several features/entities into a block of the page | `widgets/<block>` |
| is a widget mode selected by `config.component` | `pages/<mode>` |
| bootstraps Vue, registers plugins, global CSS | `app/` |

## 9. Risks and follow-ups

| Risk | Mitigation |
|---|---|
| Large diff at once, hard to review | One story = one commit (or a short series). Moves and logic changes in separate commits. `git diff -M` shows moves as renames. |
| The pnpm lockfile resolves other versions than `yarn.lock` and the bundle changes | `pnpm import` keeps the resolved versions. S0a is done only when the rebuilt bundle is byte-identical. |
| `steiger` does not follow imports of `.vue` files, and is in beta | `architecture.spec.ts` forces those imports through `index.ts` files, which Steiger does follow. Versions are pinned exactly; re-run the probes of S0b before an upgrade. |
| Bundle diff on every story (scoped ids) | Expected. Rebuild and commit. One version bump at the end (S7). |
| Unchecked `vi.mock` strings → a test silently mocks nothing | `architecture.spec.ts` checks that each path resolves. Coverage at 100 % would also drop if a mock stopped working. |
| Splitting `useEntityResolvers` breaks the "resolve once per page" cache | Keep module-level caches with the same keys. Existing specs on repeated calls must pass. |
| Barrel `index.ts` files create import cycles | R3 forbids importing your own slice through its `index.ts`. The IIFE build (`inlineDynamicImports`) fails on unresolved cycles. |
| Conflicts with other work on `vue/src` during the EPIC | Freeze widget changes on `fix/small_modifications_ux` and `develop` during S1–S6, or rebase each story right away. |

**Follow-ups this EPIC unlocks (separate tickets):** split `NuggetBrowseLanding` (producer card → `entities/catalogue/ui`); split `useNuggetSearch` (cache vs. freshness vs. live call); NaaS file naming and `NuggetCard` / `NuggetPickerPage` renames (D6); the workspace's Prettier style (D7); lot H3 (batch resolvers) lands cleanly in `entities/*/api`.

## 10. Definition of done (EPIC)

- [ ] `vue/src/` follows §3.2. The old folders `components/`, `composables/`, `types/`, `service/`, `plugins/`, `utils/`, `styles/` are gone.
- [ ] Every slice and every `shared/` segment has an `index.ts` with a GPL header. No deep import from outside a slice.
- [ ] V1 and V3–V8 fixed. `pnpm lint:fsd` reports 0 error and `LEGACY` in `architecture.spec.ts` is **empty**.
- [ ] `pnpm type-check` ✅, `pnpm lint:check` ✅, `pnpm coverage` 100 / 100 / 100 / 100 ✅, committed bundle matches the sources ✅.
- [ ] Behat and PHPUnit unchanged ✅ (no functional change).
- [ ] `vue/README.md` documents the toolchain, the layers, the rules and the §8 table.
- [ ] Bundle and plugin version bumped once. `CHANGES.md` updated.

## 11. Review of 2026-11-12

The first version of this epic was checked against the import graph of `vue/src` at a734c88 and against the NaaS pnpm workspace (`apps/author`, `libs/sdk`, and its epics on FSD and quality gates). What changed:

- **Baseline and base branch** updated (62 source files, not 47; PR #132 not merged).
- **Four files added since the first baseline got a place:** `frameVisibility.ts`, `nuggetLicence.ts`, `licenceCounts.ts`, `errorMessage.ts`.
- **Two violations added:** V7 (About sheet → structure resolver) and V8 (`NAAS_API_KEY` in the plugin file). The catalogue story now comes before the nugget story.
- **V1 simplified, V2 dropped** (D8): no feature button components, no slots.
- **Toolchain story S0a added** (D7) and the guardrail changed to `steiger` (D5).
- **Rules completed:** `index.ts` for the `shared/` segments, named exports, `@x`, and the `vi.mock()` exception for specs. `skeletons.spec.ts` is split in three.
- **Verified in S0b:** `steiger` reads imports inside `.vue` files but does not follow imports *of* `.vue` files; the Vitest spec closes that gap (D5).
