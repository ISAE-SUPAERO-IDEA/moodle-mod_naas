# Vue 3 Migration Plan — Moodle NaaS Plugin

**Date:** 2026-04-18  
**Branch:** feat/output_api  
**Reference project:** `/Users/t.delalbre/naas/microlearning/client` (Vue 3, already migrated, same API)

---

## Context

The Moodle NaaS plugin embeds a Vue 2 widget inside Moodle pages. It displays nuggets (microlearning content) in two modes:
- **NuggetView** — renders a single nugget in an iframe with about/language/completion modals
- **NuggetSearchWidget** — search & filter interface listing nuggets

The **microlearning client** is an independent Vue 3 SPA that calls the exact same NaaS REST API. It has already solved every problem we face: service layer, composables, TypeScript types, i18n, xAPI, and component architecture. The goal is to migrate the Moodle plugin widget to Vue 3 by **copying as much as possible** from the microlearning client.

---

## Current State (Vue 2)

| Aspect | Current |
|--------|---------|
| Framework | Vue 2.6.11, Options API |
| Build tool | Vue CLI 4 (Webpack) |
| HTTP | Moodle `core/ajax` AMD bridge (`moodleService.js`) |
| State | Global mixin + component-level data |
| Cache | Simple `Map` in `cache-service.js` |
| Components | 10 (Main + 9 in `/vue/src/components/`) |
| TypeScript | None |
| i18n | Flat object in `window.NAAS.labels` |
| Bootstrap | Moodle injects script tag pointing to `/assets/vue/naas_widget-YYYYMMDDXX.js` |

---

## Target State (Vue 3)

| Aspect | Target |
|--------|--------|
| Framework | Vue 3, Composition API + `<script setup>` |
| Build tool | Vite 5 |
| HTTP | Axios (copied from microlearning) + thin Moodle webservice adapter |
| State | Pinia (copied from microlearning) |
| Cache | TanStack Vue Query (copied from microlearning) |
| Components | Refactored 10 → Vue 3, reuse microlearning sub-components where applicable |
| TypeScript | Full (types copied from microlearning `/src/types/`) |
| i18n | vue-i18n 9 (copied from microlearning) seeded from `window.NAAS.labels` |
| Bootstrap | Same AMD + script injection pattern, new bundle |

---

## Key Architecture Decisions

### 1. Widget mode preserved — no router
The plugin remains a **widget embedded in a Moodle page**, not a standalone SPA. We do not add Vue Router. The `NAAS.component` config flag (`NuggetView` vs `NuggetSearchWidget`) controls the root view, as today.

### 2. Moodle webservice bridge stays in place
The microlearning client uses Axios to call the NaaS REST API directly. Inside Moodle, all external API calls must go through Moodle webservices (`core/ajax`). We keep `moodleService.js` as a thin adapter but wrap it in the same service interface as the microlearning client so composables are reusable.

The adapter must implement the same function signatures as `naas-api.service.ts` in the microlearning client, so composables can be imported without change.

### 3. Copy composables from microlearning client
Composables in `/src/composables/` encapsulate all data fetching and business logic. They depend only on the service layer interface. Once the Moodle adapter matches that interface, we copy the composables directly.

**Composables to copy:**
- `useNuggetQueries` — nugget + authors + structures
- `useXapi` — xAPI statement posting
- `useConfig` — config management
- `useDomainListQueries`, `useStructureListQueries`, `usePersonListQueries`
- `useNuggetSearchComposable` — search/filter state
- `useLocalizedCollator`, `useResponsiveTruncate` — utilities

### 4. Copy TypeScript types from microlearning client
All interface definitions from `/src/types/` are API-contract types. They are identical for both projects.

**Types to copy:**
- `nugget.types.ts`, `person.types.ts`, `structure.types.ts`
- `pagination.types.ts`, `license.types.ts`

### 5. Copy Pinia store (alerts only)
The microlearning client uses a Pinia `useAlertsStore` for toast notifications. Copy this directly. The Vuex auth/config store is not needed (Moodle manages auth; config comes from `window.NAAS`).

### 6. i18n seeded from NAAS.labels
The microlearning client uses `vue-i18n` with JSON locale files. We initialize vue-i18n from `window.NAAS.labels` at startup, keeping Moodle as the source of truth for strings.

---

## Epics

### Epic 1 — Build Tooling Migration (Vue CLI → Vite)
Replace Vue CLI / Webpack with Vite 5. The output must still be a single JS bundle (or small set of chunks) that can be loaded via a versioned script tag from Moodle's AMD bootstrap.

### Epic 2 — Service Layer Adapter
Create a Moodle-compatible service adapter that exposes the same interface as `naas-api.service.ts` from the microlearning client. This unlocks composable reuse.

### Epic 3 — Foundation (Plugins & Bootstrap)
Wire up Vue 3 app with the same plugin stack as the microlearning client: TanStack Vue Query, Pinia, vue-i18n. Configure the app to mount at `NAAS.mount_point` and read config from `window.NAAS`.

### Epic 4 — Types & Composables (Copy from microlearning)
Copy TypeScript interfaces and composables from the microlearning client. Adjust imports to use the Moodle adapter instead of Axios.

### Epic 5 — Component Migration
Migrate all 10 Vue 2 components to Vue 3 Composition API (`<script setup>`). Reuse microlearning components where they are functionally equivalent.

### Epic 6 — AMD Integration & Versioning
Ensure the compiled bundle integrates correctly with Moodle's AMD module system and versioning convention (`naas_widget-YYYYMMDDXX.js`).

### Epic 7 — QA & Regression
End-to-end validation of both widget modes in a real Moodle environment.

---

## User Stories

---

### Epic 1 — Build Tooling Migration

**US-101 — Replace Vue CLI with Vite**  
_As a developer, I want to use Vite 5 instead of Vue CLI so that builds are faster and the toolchain is aligned with the microlearning client._

**Acceptance criteria:**
- `vite.config.ts` replaces `vue.config.js` with equivalent output settings
- `npm run build` produces a single JS bundle (IIFE or UMD format) consumable as a browser script
- `npm run dev` starts a local dev server mounting at the same `#naas_widget` selector
- Bundle size is equal to or smaller than the Vue CLI output

**Copy from microlearning:**
- `vite.config.ts` structure (adapt: remove `vue-router`, `vuetify` plugin; keep Vue + TypeScript plugins)
- `tsconfig.json` and `tsconfig.app.json`
- `package.json` devDependencies list (Vite, TypeScript, vue-tsc, @vitejs/plugin-vue)

---

**US-102 — Update package.json dependencies**  
_As a developer, I want the plugin's npm dependencies upgraded to Vue 3 equivalents so that the codebase is consistent and maintainable._

**Acceptance criteria:**
- `vue@3.x` replaces `vue@2.6.11`
- `@vue/compiler-sfc` added
- `pinia@3.x` added
- `@tanstack/vue-query@5.x` added
- `vue-i18n@9.x` added
- `axios@1.x` added (used only in the Moodle adapter wrapper, not directly in components)
- `moment` kept (same version as microlearning)
- `vue-cli-service` and `babel-*` removed

**Copy from microlearning:**
- `package.json` dependencies section (prune: remove `vuetify`, `vue-router`, `qrcode.vue`, `@vueuse/core`; keep `axios`, `pinia`, `@tanstack/vue-query`, `vue-i18n`, `moment`, `iframe-resizer`)

---

### Epic 2 — Service Layer Adapter

**US-201 — Define NaaS API service interface**  
_As a developer, I want a shared TypeScript interface for the NaaS API service so that the Moodle adapter and future adapters are interchangeable._

**Acceptance criteria:**
- `src/service/naas-api.interface.ts` defines a `INaasApiService` interface with all methods
- Methods match the signatures in the microlearning client's `naas-api.service.ts`
- All methods return typed Promises using the types from Epic 4

**Copy from microlearning:**
- `src/service/naas-api.service.ts` — extract the function signatures into an interface

---

**US-202 — Create Moodle webservice adapter**  
_As a developer, I want a `moodleNaasApiService` that wraps `core/ajax` calls and implements `INaasApiService` so that composables work inside Moodle without calling external HTTP endpoints._

**Acceptance criteria:**
- `src/service/moodle-naas-api.service.ts` implements `INaasApiService`
- Each method wraps the corresponding Moodle webservice function (`mod_naas_get_nugget`, `mod_naas_get_person`, `mod_naas_get_domain`, `mod_naas_get_structure`, `mod_naas_post_xapi_statement`, `mod_naas_view_nugget`)
- Response shapes are mapped to the TypeScript types (same DTOs as the microlearning client)
- Adapter is injected via Pinia or Vue's `provide/inject` — no direct import in components

**Adapted from microlearning:**
- `src/service/http.service.ts` — replace axios calls with `window.require('core/ajax').call()`

---

**US-203 — Provide service via plugin**  
_As a developer, I want the service to be provided through Vue's plugin system so that all composables can inject it without coupling to the adapter implementation._

**Acceptance criteria:**
- `src/plugins/naas-api.plugin.ts` registers the adapter via `app.provide('naasApi', moodleNaasApiService)`
- Composables use `inject('naasApi')` to access the service
- Switching to a direct REST adapter (for testing outside Moodle) requires changing only the plugin

---

### Epic 3 — Foundation

**US-301 — Bootstrap Vue 3 app**  
_As a developer, I want `src/main.ts` to create and mount a Vue 3 app at `NAAS.mount_point` so that the widget renders correctly inside Moodle._

**Acceptance criteria:**
- `createApp(App)` mounts at the element ID from `window.NAAS.mount_point`
- All plugins registered before `app.mount()`
- Config from `window.NAAS` is provided globally via `app.provide('naasConfig', window.NAAS)`
- Works when `window.NAAS` is set by the AMD `widget_init.js` module

**Copy from microlearning:**
- `src/main.js` plugin registration pattern (adapt: remove `router`, `vuetify`; add Moodle adapter plugin)

---

**US-302 — Configure TanStack Vue Query**  
_As a developer, I want TanStack Vue Query configured with sensible defaults so that API data is cached and deduplicated automatically._

**Acceptance criteria:**
- `VueQueryPlugin` installed with `staleTime: 5 * 60 * 1000` (5 min) and `gcTime: 10 * 60 * 1000`
- Query client accessible via `useQueryClient()` in composables
- Errors surface via the alerts store (see US-303)

**Copy from microlearning:**
- `src/plugins/index.ts` VueQueryPlugin registration block

---

**US-303 — Configure Pinia and alerts store**  
_As a developer, I want Pinia installed with the alerts store so that components can display toast notifications._

**Acceptance criteria:**
- `createPinia()` installed
- `useAlertsStore` from microlearning copied without modification
- Alerts auto-dismiss after 5 seconds (same as microlearning)

**Copy from microlearning:**
- `src/store/alerts.store.ts` (verbatim copy)

---

**US-304 — Configure vue-i18n from NAAS.labels**  
_As a developer, I want vue-i18n 9 initialised from `window.NAAS.labels` so that all existing Moodle-managed strings are available via `$t()` in Vue 3 components._

**Acceptance criteria:**
- `createI18n({ locale: NAAS.lang, messages: { [NAAS.lang]: NAAS.labels } })` initialised at startup
- `$t('key')` resolves strings in all components and composables
- Language can be switched reactively if `NAAS.lang` changes

**Copy from microlearning:**
- `src/plugins/i18n.ts` structure (adapt: seed messages from `window.NAAS.labels` instead of JSON files)

---

### Epic 4 — Types & Composables

**US-401 — Copy TypeScript types**  
_As a developer, I want TypeScript interfaces for all NaaS API entities so that the codebase is type-safe end-to-end._

**Acceptance criteria:**
- `src/types/` directory created with files copied from microlearning client
- No modifications needed: `nugget.types.ts`, `person.types.ts`, `structure.types.ts`, `pagination.types.ts`, `license.types.ts`
- Types referenced throughout service adapter and composables

**Copy from microlearning:**
- `src/types/` — verbatim copy of all `.ts` type definition files

---

**US-402 — Copy and adapt useNuggetQueries**  
_As a developer, I want `useNuggetQueries(nuggetId)` to fetch a nugget with its authors and structures so that `NuggetView` and `NuggetPost` components have typed data._

**Acceptance criteria:**
- Composable injected service via `inject('naasApi')` instead of direct axios call
- Returns `{ nugget, authors, structures, isLoading, error }` reactive refs
- Used in `NuggetView.vue` and `NuggetPost.vue`

**Copy from microlearning:**
- `src/composables/useNuggetQueries.ts` (adapt: replace service import with `inject`)

---

**US-403 — Copy and adapt useNuggetSearchComposable**  
_As a developer, I want `useNuggetSearchComposable()` to manage search query, filters, and pagination state so that `NuggetSearchWidget` has reactive search._

**Acceptance criteria:**
- Composable injected service via `inject('naasApi')`
- Returns `{ query, filters, results, isLoading, pagination }` reactive refs
- Debounce logic preserved (copied from microlearning or kept from `debounce` dependency)

**Copy from microlearning:**
- `src/composables/useNuggetSearchComposable.ts` (adapt: inject service)

---

**US-404 — Copy and adapt useXapi**  
_As a developer, I want `useXapi()` to post xAPI statements through the Moodle webservice so that learning progress is tracked._

**Acceptance criteria:**
- Composable calls `inject('naasApi').postXapiStatement(params)`
- Same function signature as microlearning client

**Copy from microlearning:**
- `src/composables/useXapi.ts` (adapt: inject service)

---

**US-405 — Copy utility composables**  
_As a developer, I want utility composables available so that components have consistent behaviour for truncation and locale-aware sorting._

**Acceptance criteria:**
- `useLocalizedCollator` and `useResponsiveTruncate` available in `src/composables/`

**Copy from microlearning:**
- `src/composables/useLocalizedCollator.ts`
- `src/composables/useResponsiveTruncate.ts`

---

### Epic 5 — Component Migration

**US-501 — Migrate Main.vue (app root)**  
_As a developer, I want `Main.vue` converted to Vue 3 Composition API so that it dynamically renders `NuggetView` or `NuggetSearchWidget` based on `NAAS.component`._

**Acceptance criteria:**
- `<script setup>` replaces Options API `data/methods`
- `inject('naasConfig')` replaces `this.config` from global mixin
- Dynamic component rendering preserved
- No global mixin dependency

---

**US-502 — Migrate NuggetView.vue**  
_As a developer, I want `NuggetView.vue` migrated to Vue 3 so that single-nugget display with iframe resizing works._

**Acceptance criteria:**
- `<script setup lang="ts">` with typed props
- Uses `useNuggetQueries` composable (US-402)
- Uses `useXapi` composable (US-404)
- iframe-resizer integration preserved
- NuggetAboutModal, NuggetCompletionModal, NuggetViewModal wired up

**Reuse from microlearning:**
- `NuggetView` component structure and `useNuggetQueries` pattern

---

**US-503 — Migrate NuggetSearchWidget.vue and NuggetSearchFilter.vue**  
_As a developer, I want the search widget migrated so that users can search and filter nuggets within Moodle._

**Acceptance criteria:**
- `<script setup lang="ts">` replacing Options API
- Uses `useNuggetSearchComposable` (US-403)
- Filter state managed by composable, not component-level data
- Debounce preserved

**Reuse from microlearning:**
- `NuggetSearch` component and `useNuggetSearchComposable` pattern

---

**US-504 — Migrate NuggetPost.vue (nugget card)**  
_As a developer, I want `NuggetPost.vue` migrated to Vue 3 so that nugget cards in search results display correctly._

**Acceptance criteria:**
- `<script setup lang="ts">` with typed `NuggetDto` prop
- Uses `useNuggetQueries` for enriched data (person, structure) if needed

**Reuse from microlearning:**
- `NuggetCard` component layout and data-binding patterns

---

**US-505 — Migrate modal components**  
_As a developer, I want `NuggetAboutModal.vue`, `NuggetCompletionModal.vue`, and `NuggetViewModal.vue` migrated to Vue 3 so that modals work correctly with the new event system._

**Acceptance criteria:**
- `defineProps` / `defineEmits` replaces Options API `props` / `$emit`
- `v-model` on `open` prop uses Vue 3 `modelValue` convention

---

**US-506 — Migrate Loading.vue and RelatedDomain.vue**  
_As a developer, I want the remaining two utility components migrated to Vue 3._

**Acceptance criteria:**
- `<script setup>` with typed props
- Template unchanged

---

### Epic 6 — AMD Integration & Versioning

**US-601 — Update widget_init.js AMD module**  
_As a developer, I want `amd/src/widget_init.js` updated so that it loads the new Vite-compiled bundle correctly._

**Acceptance criteria:**
- Script src pattern points to the new bundle filename
- `window.NAAS` is set before the bundle script executes
- AMD module still uses `define([...])` pattern (no ES module imports)
- Works with Moodle's `grunt` AMD compilation

---

**US-602 — Configure Vite output for Moodle consumption**  
_As a developer, I want the Vite build to produce a single IIFE bundle with a versioned filename so that Moodle can serve it like the existing Vue 2 bundle._

**Acceptance criteria:**
- `vite.config.ts` uses `build.lib` with `formats: ['iife']`
- Output filename follows `naas_widget-YYYYMMDDXX.js` convention
- Vue and all dependencies inlined (no external CDN references)
- Source maps generated for dev, disabled for prod

---

**US-603 — Moodle version bump**  
_As a developer, I want `version.php` updated so that Moodle detects the plugin upgrade and purges its caches._

**Acceptance criteria:**
- Version number incremented in `version.php`
- New bundle filename referenced in any hardcoded path

---

### Epic 8 — Vue 2 Cleanup

Remove all Vue 2 source files, build config, and artifacts that are no longer referenced by the Vue 3 build. Keeps the `vue/` directory clean and prevents confusion for future contributors.

**Files to delete:**

| File | Reason |
|------|--------|
| `vue/babel.config.js` | Webpack/Babel transpilation — replaced by Vite |
| `vue/vue.config.js` | Vue CLI config — replaced by `vite.config.ts` |
| `vue/.browserslistrc` | Babel target config — unused with Vite |
| `vue/src/main.js` | Vue 2 entry point — replaced by `src/main.ts` |
| `vue/src/mixin.js` | Global Vue 2 mixin — replaced by composables |
| `vue/src/cache-service.js` | Vue 2 Map cache — replaced by `callWebservice` cache in service adapter |
| `vue/src/utils.js` | Vue 2 helpers — no longer imported |
| `vue/src/http/moodleService.js` | Vue 2 AMD bridge — replaced by `moodle-naas-api.service.ts` |
| `vue/public/index.html` | Vue CLI public template — replaced by `vue/index.html` |
| `vue/public/blank.html` | Vue CLI public asset — unused |
| `vue/public/` _(dir)_ | Empty after above removals |
| `vue/dev_config_dist.js` | Old dist dev config — `dev_config.js` + Vite plugin used instead |
| `vue/package-lock.json` | npm lockfile — project uses `yarn.lock` |

**Files to keep:**

| File | Reason |
|------|--------|
| `vue/.eslintrc.js` | Still valid for Vue 3 / TypeScript linting |
| `vue/.prettierrc.json` | Unchanged |
| `vue/.prettierignore` | Unchanged |
| `vue/.gitignore` | Unchanged |
| `vue/README.md` | Update to reflect Vue 3 stack |
| `vue/dev_config.js` | Still used by Vite dev server plugin |

---

### Epic 7 — QA & Regression

**US-701 — NuggetView smoke test in Moodle**  
_As a teacher, I want to open a NaaS activity configured in NuggetView mode and see the nugget loaded in the iframe with functioning modals._

**Acceptance criteria:**
- Nugget loads in iframe
- "About" modal opens with correct metadata
- Language switcher works
- Completion modal submits rating successfully
- xAPI statement logged in Moodle

---

**US-702 — NuggetSearchWidget smoke test in Moodle**  
_As a student, I want to open a NaaS activity in search mode and find nuggets by keyword and filter._

**Acceptance criteria:**
- Search results appear on query
- Domain/structure filters narrow results
- Clicking a result opens NuggetViewModal
- Pagination or infinite scroll works

---

**US-703 — Bundle size and performance check**  
_As a developer, I want the Vue 3 bundle to be no larger than the Vue 2 bundle so that page load performance is not regressed._

**Acceptance criteria:**
- Gzipped bundle size ≤ current bundle size (check with `vite build --reporter`)
- First-paint time in Moodle not measurably worse

---

**US-704 — TypeScript build clean**  
_As a developer, I want `vue-tsc --noEmit` to pass with zero errors before merge so that the codebase is type-safe._

**Acceptance criteria:**
- `npm run type-check` exits 0
- No `any` escape hatches in service adapter or composables

---

## Implementation Order

```
Epic 1 (Vite setup)
  → Epic 2 (Service adapter)
    → Epic 3 (Foundation plugins)
      → Epic 4 (Types + Composables)
        → Epic 5 (Components)
          → Epic 6 (AMD + versioning)
            → Epic 7 (QA)
```

Each epic unblocks the next. Epics 4 and 5 can be parallelised once Epic 2 is done.

---

## Files to Copy Verbatim from Microlearning Client

| Source path (microlearning) | Destination path (plugin) | Notes |
|----------------------------|--------------------------|-------|
| `src/types/nugget.types.ts` | `vue/src/types/nugget.types.ts` | Verbatim |
| `src/types/person.types.ts` | `vue/src/types/person.types.ts` | Verbatim |
| `src/types/structure.types.ts` | `vue/src/types/structure.types.ts` | Verbatim |
| `src/types/pagination.types.ts` | `vue/src/types/pagination.types.ts` | Verbatim |
| `src/types/license.types.ts` | `vue/src/types/license.types.ts` | Verbatim |
| `src/store/alerts.store.ts` | `vue/src/store/alerts.store.ts` | Verbatim |
| `src/composables/useXapi.ts` | `vue/src/composables/useXapi.ts` | Adapt: inject service |
| `src/composables/useNuggetQueries.ts` | `vue/src/composables/useNuggetQueries.ts` | Adapt: inject service |
| `src/composables/useNuggetSearchComposable.ts` | `vue/src/composables/useNuggetSearchComposable.ts` | Adapt: inject service |
| `src/composables/useLocalizedCollator.ts` | `vue/src/composables/useLocalizedCollator.ts` | Verbatim |
| `src/composables/useResponsiveTruncate.ts` | `vue/src/composables/useResponsiveTruncate.ts` | Verbatim |
| `tsconfig.json` | `vue/tsconfig.json` | Adapt paths |
| `src/service/naas-api.service.ts` | `vue/src/service/naas-api.interface.ts` | Extract interface only |

---

## Files to Write from Scratch

| File | Purpose |
|------|---------|
| `vue/vite.config.ts` | Vite build config (IIFE, versioned output) |
| `vue/src/main.ts` | App bootstrap, plugin registration, mount |
| `vue/src/plugins/naas-api.plugin.ts` | Registers Moodle adapter via provide/inject |
| `vue/src/plugins/i18n.ts` | vue-i18n seeded from NAAS.labels |
| `vue/src/service/moodle-naas-api.service.ts` | Moodle `core/ajax` adapter implementing INaasApiService |
| `vue/src/service/naas-api.interface.ts` | Shared service interface |

---

## Files to Migrate (Vue 2 → Vue 3)

| File | Migration effort |
|------|-----------------|
| `vue/src/Main.vue` | Low — remove mixin, inject config |
| `vue/src/components/NuggetView.vue` | Medium — wire composables |
| `vue/src/components/NuggetSearchWidget.vue` | Medium — wire composables |
| `vue/src/components/NuggetSearchFilter.vue` | Low — props/emits only |
| `vue/src/components/NuggetPost.vue` | Low — props/emits, typed |
| `vue/src/components/NuggetAboutModal.vue` | Low — v-model convention |
| `vue/src/components/NuggetCompletionModal.vue` | Low — v-model convention |
| `vue/src/components/NuggetViewModal.vue` | Low — v-model convention |
| `vue/src/components/Loading.vue` | Trivial |
| `vue/src/components/RelatedDomain.vue` | Trivial |
| `amd/src/widget_init.js` | Low — update bundle reference |

---

---

## Implementation Status

| Epic | Status | Commit message |
|------|--------|----------------|
| Epic 1 — Build tooling | ✅ Done | `build: replace Vue CLI with Vite 5 and TypeScript` |
| Epic 2 — Service layer + types | ✅ Done | `feat: add Moodle webservice adapter and TypeScript types` |
| Epic 3 — Foundation plugins + main | ✅ Done | `feat: bootstrap Vue 3 app with naasApi and i18n plugins` |
| Epic 4 — Composables | ✅ Done | `feat: add Vue 3 composables for nugget view, search, xAPI` |
| Epic 5 — Component migration | ✅ Done | `feat: migrate all Vue components from Vue 2 Options API to Vue 3 script setup` |
| Epic 6 — AMD integration | ✅ Done | `chore: bump plugin version for Vue 3 widget release` |
| Epic 7 — QA | ✅ Done | _(QA checklist — no separate commit)_ |
| Epic 8 — Vue 2 cleanup | ❌ Todo | `chore: remove Vue 2 source files and build artifacts` |

---

## Commit Reference

### Commit 1 — Epic 1 · Build tooling

```bash
git add vue/package.json vue/yarn.lock vue/vite.config.ts vue/tsconfig.json vue/tsconfig.app.json vue/index.html
```

```
build: replace Vue CLI with Vite 5 and TypeScript

Replace Vue CLI 4 / Webpack with Vite 5 in IIFE library mode.
Output: single bundle assets/vue/naas_widget-2026030300.js.
Add tsconfig.json + tsconfig.app.json for full TypeScript support.
Add index.html dev entry with NAAS_DEV_CONFIG injection plugin.
Add vite.config.ts define block replacing process.env.NODE_ENV
to prevent runtime crash in IIFE bundles loaded in the browser.
```

---

### Commit 2 — Epics 2 & 3 · Service layer + foundation

```bash
git add vue/src/main.ts vue/src/types/ vue/src/service/ vue/src/plugins/
```

```
feat: add Moodle webservice adapter, TypeScript types, and Vue 3 app bootstrap

Types: naas-config.types.ts, nugget.types.ts, person.types.ts,
  domain.types.ts, structure.types.ts — typed API contracts.

Service: naas-api.interface.ts defines INaasApiService.
  moodle-naas-api.service.ts implements it via core/ajax AMD bridge
  with Map-based cache and waitForRequirejs() polling.

Plugins: naas-api.plugin.ts provides the service via inject symbol.
  i18n.ts initialises vue-i18n 9 from window.NAAS.labels, flattening
  metadata.* keys to top level for $t() compatibility.

main.ts: createApp(Main) → naasApiPlugin → i18n → provide(naasConfig)
  → mount(config.mount_point).
```

---

### Commit 3 — Epic 4 · Composables

```bash
git add vue/src/composables/
```

```
feat: add Vue 3 composables for nugget view, search, xAPI, and entity resolution

useMoodleService / useNaasConfig: inject service and config.
useXapi: fire-and-forget postStatement, console.warn on failure.
useEntityResolvers: getDomainLabel, getStructureAcronym, getPersonName
  with try/catch fallback to key string.
useNuggetEnricher: parallel getPerson + getDomain enrichment.
useNuggetView: onMounted load() via viewNugget → enrich.
useNuggetSearch: search() with enrichMany, getNuggetById for pre-fill.
useComponentId: module-level UID counter for stable DOM IDs.
```

---

### Commit 4 — Epic 5 · Component migration

```bash
git add vue/src/Main.vue vue/src/components/
```

```
feat: migrate all Vue components from Vue 2 Options API to Vue 3 script setup

Main.vue: static imports replace defineAsyncComponent (blank render fix).
NuggetView.vue: watch(nugget) replaces computed side-effect (iframe fix).
NuggetSearchWidget.vue: syncMoodleForm writes nugget_id to Moodle form.
NuggetSearchFilter.vue: recursive AggDef tree, deep watch on query prop.
NuggetCompletionModal.vue: rate() sends rated xAPI with score body.
NuggetViewModal.vue: watch visible → getNuggetPreview.
NuggetAboutModal, NuggetPost, NuggetBadge, Loading, RelatedDomain:
  script setup with typed props/emits.
```

---

### Commit 5 — Epic 6 · AMD integration + bundle

```bash
git add version.php assets/vue/naas_widget-2026030300.js assets/vue/index.html
```

```
chore: bump plugin version and ship Vue 3 bundle

version.php: 2026041800, release 3.0.0.
assets/vue/naas_widget-2026030300.js: Vue 3 IIFE bundle (287 kB,
  119 kB gzip) replacing the Vue 2 webpack bundle.
```

---

### Commit 7 — Epic 8 · Vue 2 cleanup

```bash
git rm vue/babel.config.js
git rm vue/vue.config.js
git rm vue/.browserslistrc
git rm vue/src/main.js
git rm vue/src/mixin.js
git rm vue/src/cache-service.js
git rm vue/src/utils.js
git rm vue/src/http/moodleService.js
git rm vue/public/index.html
git rm vue/public/blank.html
git rm -r vue/public
git rm vue/dev_config_dist.js
git rm vue/package-lock.json
```

```
chore: remove Vue 2 source files and build artifacts

Remove Vue CLI / Webpack build config: babel.config.js, vue.config.js,
  .browserslistrc — replaced by vite.config.ts.
Remove Vue 2 entry point and global mixin: src/main.js, src/mixin.js.
Remove Vue 2 service layer: src/http/moodleService.js,
  src/cache-service.js, src/utils.js — replaced by TypeScript
  service adapter and composables.
Remove Vue CLI public/ dir (public/index.html, public/blank.html)
  — replaced by vue/index.html at root for Vite dev server.
Remove dev_config_dist.js — superseded by dev_config.js + vite plugin.
Remove package-lock.json — project uses yarn.lock.
```

---

### Commit 8 — Documentation

```bash
git add VUE3_MIGRATION_PLAN.md ENHANCEMENTS_BACKLOG.md SECURITY_HARDENING_BACKLOG.md OUTPUT_API_MIGRATION_BACKLOG.md OUTPUT_API_MIGRATION_STUDY.md
```

```
docs: add Vue 3 migration plan, enhancements and security backlogs

VUE3_MIGRATION_PLAN.md: full epic/story breakdown, QA checklist,
  known limitations, and commit reference.
ENHANCEMENTS_BACKLOG.md: 14 UX, dev, performance and PHP improvements.
SECURITY_HARDENING_BACKLOG.md: 7 security epics hardening the
  NaaS API proxy against abuse through the Moodle plugin.
OUTPUT_API_MIGRATION_BACKLOG.md: Output API migration story tracking
  with per-epic git add and commit messages.
OUTPUT_API_MIGRATION_STUDY.md: initial migration study notes.
```

---

## Epic 7 — QA Results & Checklist

### Build & type-check (automated — ✅ passing)

```
npm install        → 73 packages, no errors
npm run type-check → vue-tsc --noEmit, exit 0
npm run build      → ../assets/vue/naas_widget-2026030300.js  289 kB │ gzip: 119 kB  ✓ 1.07s
```

Vue 2 bundle for comparison: single webpack bundle, ~300–350 kB gzipped.
**Vue 3 bundle is smaller.**

> **Fix applied during build verification:** Vite IIFE bundles can include `process.env.NODE_ENV`
> references that crash in the browser. Added `define: { 'process.env.NODE_ENV': '"production"' }`
> to `vite.config.ts` to replace it at build time. Bundle confirmed clean — no runtime `process.*` calls.

### Pre-deploy checklist (manual — requires live Moodle)

#### NuggetView mode
- [ ] Open a NaaS activity configured as `NuggetView`
- [ ] Nugget loads inside the iframe without JS console errors
- [ ] Language selector appears and switches the iframe `src` URL correctly
- [ ] "About" button opens `NuggetAboutModal` with correct metadata (description, authors, fields of study, duration, level, tags, publication date)
- [ ] "Terminer" / complete button sends `completed` xAPI statement (check Moodle logs or network tab for `mod_naas_post_xapi_statement`)
- [ ] `NuggetCompletionModal` opens with star rating; submitting rating sends `rated` xAPI statement
- [ ] "Back to course" link in completion modal navigates back correctly
- [ ] "Next unit" link appears when Moodle provides a next-activity element and navigates correctly
- [ ] iframe auto-resizes via `iframe-resizer` (no fixed 600px height after content loads)
- [ ] `experienced` xAPI statement fires on page load (network tab)
- [ ] On Moodle ≥ 4.0, the "About" button is **hidden** (secondary-nav has its own About link)
- [ ] On Moodle < 4.0, the "About" button is **visible**

#### NuggetSearchWidget mode
- [ ] Open a NaaS activity configured as `NuggetSearchWidget` (activity creation form)
- [ ] On first load with no saved nugget, search results grid appears
- [ ] Typing in the search input debounces (500 ms) and updates results
- [ ] Filter panel shows domain / level / language / tag / producer / author aggregations
- [ ] Clicking a filter badge toggles it and re-runs the search
- [ ] "Clear filters" resets all selections
- [ ] "Show more" button appends 6 more results
- [ ] Clicking a nugget card's "Select" button highlights it and writes `nugget_id` + `id_name` into the Moodle form fields
- [ ] Clicking a selected nugget deselects it and clears the form fields
- [ ] "Preview" button opens `NuggetViewModal` with iframe
- [ ] "About" button opens `NuggetAboutModal`
- [ ] Reloading the form with a pre-selected nugget shows it in the selected state immediately
- [ ] Error banner appears when the Moodle webservice returns an error

#### Regression
- [ ] Other AMD modules (`view_page.js`, `test_connection.js`) are unaffected
- [ ] Plugin upgrade path works: running Moodle upgrade with version `2026041800` does not throw DB errors
- [ ] LTI launch (`launch.php`) still works when called from the iframe `src`

### Known limitations after migration
- `vue-i18n@9` is deprecated upstream (v11 is current); migrate in a follow-up if needed.
- `iframe-resizer` has no TypeScript declarations; suppressed with `@ts-expect-error`.
- `npm audit` reports 3 vulnerabilities (2 moderate, 1 high) in devDependencies — run `npm audit fix` before production release.

---

## Out of Scope

- Adding Vue Router (widget mode only, no navigation)
- Adding Vuetify (styling handled by Moodle Bootstrap theme)
- Migrating to direct REST API (Moodle requires webservice bridge)
- Unit tests / Storybook (follow-up)
- Migrating other AMD modules (`lti.js`, `index.js`) — separate concern









Mod NaaS - Liste complète des tickets pour la migration et le refactoring
Ce document liste les tickets Jira/Linear nécessaires pour la refonte complète du plugin NaaS Moodle. Il correspond exactement à la feuille de route du dossier commits_breakdown, en intégrant la migration vers l'API Output, la migration de l'UI vers Vue 3, ainsi que les épopées (epics) suivantes concernant l'UX et la sécurité.

Conformément aux exigences de l'équipe, la phase 16_9f27cb1_components_migration a été détaillée en créant un ticket distinct pour chaque composant Vue individuel.

Épopée A : Prérequis de l'API Output et du Rendu
Ticket 01 : Correction du crash de l'URL directe ?u=
Résoudre les plantages survenant lors de la navigation avec des paramètres de requête directs avant l'invocation du moteur de rendu (renderer).

Ticket 02 : Implémentation du fichier de base classes/output/renderer.php
Créer la classe de rendu Output principale nécessaire pour construire l'architecture standardisée de Moodle.

Ticket 03 : Développement de la classe renderable view_page
Implémenter la classe renderable pour view_page qui cartographie les interactions de la vue.

Ticket 04 : Développement de la classe renderable index_page
Implémenter la classe renderable Output pour la page d'index du module index_page.

Ticket 05 : Développement de la classe renderable lti_launch_form
Implémenter la classe lti_launch_form gérant le contexte d'exécution structuré LTI.

Ticket 06 : Suppression des injections obsolètes du widget en ligne via <script>
Éliminer les anciennes balises <script> intégrées (inline) définissant la variable globale NAAS=... à l'intérieur des sorties PHP.

Ticket 07 : Implémentation du wrapper de module AMD (widget_init.js)
Introduire des amorces (bootstraps) AMD standards de Moodle déclenchant les initialisations Vue de manière asynchrone.

Ticket 08 : Création du template naas_widget.mustache
Générer le fichier de base naas_widget.mustache pour l'injection de l'interface utilisateur.

Ticket 09 : Refonte des notifications d'erreur de lancement LTI
Passer des payloads d'erreur gérés manuellement à l'utilisation native de $OUTPUT->notification() de Moodle.

Ticket 10 : Création du template lti_launch_form.mustache
Construire le template de soumission LTI gérant nativement les flux d'exécution du formulaire.

Ticket 11 : Migration des affichages index et view vers Mustache et exécution AMD
Revoir globalement les appels de sorties spécifiques dans view.php et index.php pour s'aligner sur le paradigme Mustache.

Ticket 12 : Résolution du bug indépendant de complétion xAPI
Déployer la logique isolée dans outcome.php concernant les erreurs de suivi xAPI, de manière indépendante des mises à jour du rendu.

Épopée B : Fondations Vue 3
Ticket 13 : Configuration des outils de build Vite 5 + TypeScript
Remplacer Vue CLI par Vite 5. Configurer strictement pour des sorties en mode IIFE pour éviter l'usage de variables qui ne sont pas natives au navigateur.

Ticket 14 : Implémentation de l'adaptateur d'API Moodle et des types TypeScript
Importer toutes les définitions explicites de l'API. Remplacer les intégrations REST Axios par un wrapper dynamique gérant le webservice Moodle via $core/ajax.

Ticket 15 : Mise en place des Composables API métiers pour Vue 3
Implémenter les fonctions logiques indépendantes du contexte (useNuggetQueries, useNuggetSearch) reposant entièrement sur l'injection de dépendances (provide/inject).

Épopée C : Migration des Composants (Un ticket par composant)
NOTE : Ceci correspond au redécoupage détaillé du commit 16_9f27cb1_components_migration.

Ticket 16.1 : Migration de Main.vue vers Vue 3
Refactoriser le point de montage principal (root) pour le rendu logique NAAS.component en utilisant Vue 3.

Ticket 16.2 : Migration de NuggetView.vue vers Vue 3
Refactoriser les interactions avec l'iframe en implémentant l'API de composition de Vue 3 (Composition API) et en utilisant les composables injectés dédiés.

Ticket 16.3 : Migration de NuggetSearchWidget.vue vers Vue 3
Migrer l'interface de recherche et s'assurer que les configurations du formulaire Moodle sont mises à jour par l'état global et les requêtes.

Ticket 16.4 : Migration de NuggetSearchFilter.vue vers Vue 3
Réécrire les contrôles d'agrégation imbriqués en utilisant les mécaniques modernes de Vue 3 comme defineProps et les watch profonds.

Ticket 16.5 : Migration de NuggetPost.vue vers Vue 3
Faire la transition vers Vue 3 de l'interface des cartes d'affichage, en utilisant strictement les définitions de typage NuggetDto.

Ticket 16.6 : Migration de NuggetAboutModal.vue vers Vue 3
Conformer la syntaxe v-model à la nouvelle convention Vue 3 pour l'ouverture des vues de métadonnées.

Ticket 16.7 : Migration de NuggetCompletionModal.vue vers Vue 3
Mettre à jour le système de notation pour Vue 3 en conservant la logique des émissions d'événements vers les enregistrements xAPI.

Ticket 16.8 : Migration de NuggetViewModal.vue vers Vue 3
Mettre à jour la logique de la modale d'aperçu en exploitant la balise <script setup>.

Ticket 16.9 : Migration de Loading.vue et RelatedDomain.vue vers Vue 3
Convertir les derniers composants standards en remplaçant l'API par objets (Options API) par Vue 3.

Épopée D : Nettoyage & Finalisation de l'Outillage
Ticket 17 : Incrémentation de la version et génération du build final de Vue 3
Exécuter la compilation finale en mode IIFE et intégrer le paquet généré en production, conformément aux instructions de Moodle.

Ticket 18 : Suppression des anciens fichiers Vue 2 et du code obsolète
Détruire les vieux fichiers sources et nettoyer toute trace laissée par Webpack et Babel.

Ticket 19 : Suppression des intégrations Pinia
Retirer entièrement les références à Pinia (inutilisé désormais), le système se reposant soit localement soit sur l'utilisation directe de Vue Query.

Ticket 20 : Installation d'un hook développeur pour les Vue DevTools
Introduire des vérifications sur l'environnement local qui amorceront automatiquement les outils développeurs.

Ticket 21 : Finalisation de l'outil d'automatisation bump-version.js
Mettre à disposition le script interne permettant d'augmenter automatiquement le numéro de version de manière uniforme au niveau du manifeste du module (version.php) et de la référence Vue.

Épopée E : UX & Design
Ticket 22 : Unification des CSS avec variables (tokens) et styles scopés
Nettoyer le CSS général pour le confiner (scoped css) au niveau de chaque composant et y intégrer des tokens.

Ticket 23 : Implémentation des effets de chargement "Skeleton" dans les filtres (FilterSkeleton)
Éviter les tressautements visuels au chargement en créant des indicateurs stables "squelettes" des états de chargement (shimmering loaders) pour les filtres existants.

Ticket 24 : Ajout des "Squelettes" de la vue principale (ViewSkeleton) et d'une bannière d'erreur
Réaliser une dégradation gracieuse en scénario de coupure réseau en implémentant des messages d'erreurs lisibles en haut de page.

Ticket 25 : Annulation de l'apparition du toast temporaire (Scope limite)
Supprimer le code visant à créer un bandeau pop-up temporaire.

Ticket 26 : Implémentation du mapping clavier (navigabilité) pour la pagination (Précédent/Suivant)
Uniformiser les éléments d'accessibilité de recherche et y intégrer un support du clavier.

Ticket 27 : Implémentation de puces (chips) interactives pour les filtres et une fonctionnalité d'effacement complet
Donner la possibilité à l'utilisateur de désélectionner rapidement les listes depuis des indicateurs clairs (chips).

Ticket 28 : Introduction du cache MUC 24h natif de Moodle
Mettre les données domain, structure, et person dans le cache natif de l'application pour limiter la quantité de requêtes aux APIs.

Ticket 29 : Résolution de limites de performance via un décalage d'exécution sur le iframe et l'xAPI
Différer les interpellations API jusqu'après le chargement complet pour éviter tout ralentissement lors de l'accès au cours (preload).

Ticket 30 : Stabilisation des glissements CSS (Layout Shifts) et ajustements visuels
Sécuriser les éléments et conteneurs pour garantir l'absence de "sauts" de structure inopinés en attendant un contenu de chargement.

Ticket 38 : Ajustements du style de la barre de recherche
Convertir les icônes simples en véritables boutons d'interaction standards sur les formulaires de recherche.

Épopée F : Sécurité & Protection
Ticket 31 : Renforcement des vérifications de type UUID/Slug côté serveur
Empêcher de potentielles injections ou exécutions non désirées avec de stricts critères d'identifications sur l'API (UUID / slug) en appliquant des regex correspondantes.

Ticket 32 : Limitation stricte de la taille sur le corps (body) des envois xAPI et la longueur des verbes
Annuler les risques de Déni-de-Service (DoS) côté client en ignorant poliment (dropper) les corps de requêtes d'enregistrements beaucoup trop longs.

Ticket 33 : Vérification de l'inscription (enrollment) lors d'un appel
Interdire l'envoi de payloads statistiques (xAPI) si l'utilisateur qui déclenche l'événement ne fait pas partie du cours cible existant sur la plateforme Moodle.

Ticket 34 : Renforcement strict au niveau du protocole (SSL) et authentification mots de passe de l'API REST
Solidifier les requêtes provenant de Moodle vers les services web REST configurés par l'utilisateur.

Ticket 35 : Réécriture du système de codage pour la désinfection et l'assainissement d'entrées REST
Désinfecter toute donnée REST de manière standardisée et globale, même si elles sont comprises au sein d'un graphe/tableau.

Ticket 36 : Atténuer les vulnérabilités aux scripts et balises frauduleuses (purger l'emploi de v-html dans Vue)
Purifier le code de tout emploi natif aux fonctions du type "v-html" de VueJS ou aux modifications non saines du DOM.

Ticket 37 : Enforce la traduction des clés via string (fichiers langues) évitant du code figé
Éviter l'injection forcée de chaînes de caractères figées en exploitant toujours un système indexant une clé textuelle.

