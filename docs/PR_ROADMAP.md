# `mod_naas` — PR Roadmap (master → current state)

**Plugin:** `mod_naas` (Moodle NaaS / Nugget activity)
**Base branch:** `master` (tip: `5b13867` — Release 2.5.1, 2026-03-09)
**Current working branch:** `feat/change_search_icon` (+ uncommitted WIP)
**Reference master clone:** `/Users/t.delalbre/moodle_plugin/moodle-mod_naas`

This document inventories every modification sitting on top of `master` across the local feature branches, groups them into **EPICs → User Stories → Tasks**, and proposes a merge sequence so the team can review, split, and land the work in reasonable-size PRs.

> Scope: the current branches are **stacked** (each one is rebased on top of the previous one). Landing them in order preserves the history cleanly. Landing out of order requires rebasing / cherry-picking.

---

## 1. Branch Topology

```
master (5b13867)
  │
  └─ fix/display_with_u_url ......... 1 commit   → EPIC A
       │
       └─ feat/output_api ............ +12 commits → EPIC B
            │
            └─ chore/vue3_migration ... +7 commits  → EPIC C
                 │
                 └─ feat/global_enhancements . +13 commits → EPIC D
                      │
                      └─ security/hardening_php_side . +7 commits → EPIC E
                           │
                           └─ feat/change_search_icon . +1 commit → EPIC F
                                │
                                └─ (uncommitted working tree) → EPIC G (WIP)
```

**Totals vs master:** 41 commits committed + ~11 files of uncommitted WIP.

---

## 2. High-Level Summary

| # | EPIC | Branch | Commits | Status |
|---|------|--------|--------:|--------|
| A | Direct-URL (`?u=`) display fix | `fix/display_with_u_url` | 1 | ✅ ready to PR |
| B | Moodle Output API & Mustache migration | `feat/output_api` | 12 | ✅ ready to PR (1 sub-epic pending — see §B) |
| C | Vue 2 → Vue 3 / Vite / TypeScript migration | `chore/vue3_migration` | 7 | ✅ ready to PR |
| D | Widget UX / performance / DX enhancements | `feat/global_enhancements` | 13 | ✅ ready to PR |
| E | PHP-side security hardening | `security/hardening_php_side` | 7 | ✅ ready to PR (backlog remains — see §E) |
| F | Search-icon button restyle | `feat/change_search_icon` | 1 | ✅ ready to PR |
| G | Additional visual polish (uncommitted WIP) | working tree | — | ⚠️ needs commit + split |

Detailed backlogs already authored in this repo and referenced below:

- `OUTPUT_API_MIGRATION_STUDY.md` — audit of legacy HTML
- `OUTPUT_API_MIGRATION_BACKLOG.md` — EPIC B stories & commit messages
- `VUE3_MIGRATION_PLAN.md` — EPIC C plan, stories, acceptance criteria
- `ENHANCEMENTS_BACKLOG.md` — EPIC D items (UX / perf / DX)
- `SECURITY_HARDENING_BACKLOG.md` — EPIC E full threat-model & backlog

---

## 3. EPIC A — Fix: direct module display via `?u=` URL

**Branch:** `fix/display_with_u_url`
**Motivation:** when the activity is opened through the direct `view.php?u=<uuid>` URL (not `?id=<cmid>`), `view.php` referred to an undefined `$naas` variable and crashed with a fatal PHP error.

### Stories

- **A1 — Recover the Nugget instance from the course module when entering via `?u=`**
  - [x] A1.1 — Derive `$naasinstance` from the course module in the `?u=` branch of `view.php`
  - [x] A1.2 — Remove the undefined `$naas` reference that caused the fatal error

**Commits:**

| SHA | Message |
|-----|---------|
| c51bc86 | `fix: fix id to get recovered from instance when direct module display thru url` |

**Acceptance criteria:**
- [ ] Open `view.php?u=<uuid>` as a student → page renders, no PHP error
- [ ] Open `view.php?id=<cmid>` regression check → still renders identically
- [ ] Static analysis: no undefined `$naas` reference remains in `view.php`

**PR-ready commit message:** already good (1 self-contained commit).

---

## 4. EPIC B — Output API & Mustache template migration

**Branch:** `feat/output_api` (stacked on EPIC A)
**Motivation:** `view.php`, `index.php`, `launch.php` and `naas_widget.php` built HTML with `echo`, heredocs and inline `<script>/<style>` tags. That bypasses Moodle's theming, XSS escaping and template-lint pipeline. Migrate every output path to **Output API renderers + Mustache templates + AMD modules**.

Reference: `OUTPUT_API_MIGRATION_STUDY.md`, `OUTPUT_API_MIGRATION_BACKLOG.md`.

### Stories

#### B1 — Foundation (renderer + renderables) ✅
- [x] B1.1 — Fix undefined `$naas` → `$naasinstance` in `view.php` `?u=` branch (inherited from EPIC A, re-ensured)
- [x] B1.2 — Add `classes/renderer.php` extending `plugin_renderer_base`
- [x] B1.3 — Add `classes/output/{view_page,index_page,lti_launch_form}.php` (`renderable` + `templatable`)

#### B2 — Remove inline `<script>` from widget mount ✅ *(XSS vector S1)*
- [x] B2.1 — Replace `<script>NAAS=…</script>` in `naas_widget_html()` with `js_call_amd()`
- [x] B2.2 — Add `amd/src/widget_init.js` AMD module + compiled minified build
- [x] B2.3 — Add `templates/naas_widget.mustache` mount-point template

#### B3 — Remove heredoc HTML from LTI launch ✅ *(XSS vectors S2 & S3)*
- [x] B3.1 — Replace error heredoc + inline `<style>` with `$OUTPUT->notification()`
- [x] B3.2 — Replace LTI form heredoc with `lti_launch_form.mustache` + `clean_param(PARAM_URL)` on `launchurl`
- [-] B3.3 — Auto-submit `<script>` → AMD module: **dropped** (kept inline in mustache; static string, no user data)

#### B4 — Migrate `view.php` & `index.php` to templates ✅
- [x] B4.1 — `view.php` → `render($viewpage)` using `view_page.mustache`
- [x] B4.2 — Move About-button wiring into `amd/src/view_page.js`
- [x] B4.3 — `index.php` → `render($indexpage)` using `index_page.mustache`; replace `html_table`/`html_writer`

#### B5 — Admin settings test-connection widget ❌ **TODO** (not yet started)
- [ ] B5.1 — Create `classes/admin/test_connection_setting.php` extending `\admin_setting`
- [ ] B5.1 — Create `templates/admin_test_connection.mustache`
- [ ] B5.1 — Refactor `settings.php` to use the new class; drop remaining `html_writer` calls

#### B6 — Build & CI ⚠️ partial
- [x] B6.1 — Compile `widget_init.js` and `view_page.js` via `grunt amd`
- [-] B6.2 — `lti_autosubmit` AMD module: N/A (kept inline)
- [ ] B6.3 — Run `php admin/cli/mustache_lint.php --filter=mod_naas` and commit any fixes

**Commits in branch (12):**

| SHA | Message |
|-----|---------|
| 6f28d75 | feat(output): add plugin_renderer_base subclass classes/renderer.php |
| 098075a | feat(output): add renderable/templatable class for view.php (view_page) |
| 69920a3 | feat(output): add renderable/templatable class for index.php (index_page) |
| 4d2d718 | feat(output): add renderable/templatable class for LTI form (lti_launch_form) |
| 0ac847e | security(widget): remove inline `<script>NAAS=...</script>` injection |
| 91a08a5 | feat(amd): add widget_init.js AMD module to initialize Vue widget config |
| 6bbe11f | feat(template): add naas_widget.mustache mount-point template |
| 6cc7993 | security(lti): replace inline `<style>+div` error block with `->notification()` |
| f2e02b3 | feat(template): add lti_launch_form.mustache for LTI POST form |
| 28f9c22 | refactor: index, view and amd to use mustache templates |
| 7809d01 | fix(output): resolve renderer autoloading, AMD naming, and LTI auto-submit issues |
| fe52066 | fix(xapi): fix completion by adding … |

**Acceptance criteria:**
- [ ] Every rendered HTML goes through `$OUTPUT->render(...)` or `$OUTPUT->render_from_template(...)`
- [ ] No remaining `echo '<...>'`, no heredoc, no inline `<script>`/`<style>` in PHP (except B3.3 auto-submit)
- [ ] Mustache lint passes on all plugin templates
- [ ] Both widget modes (single Nugget view + search) render identically to master

**Pending before PR:**
- Finish B5 (admin settings widget) **or** split it to a follow-up PR
- Run B6.3 mustache lint

---

## 5. EPIC C — Vue 2 → Vue 3 / Vite / TypeScript migration

**Branch:** `chore/vue3_migration` (stacked on EPIC B)
**Motivation:** the embedded widget was Vue 2.6 + Vue CLI 4 + Options API, untyped, with a home-grown global mixin and cache. Align it with the already-migrated microlearning SPA: Vue 3 `<script setup>`, Vite 5, TypeScript, composables.

Reference: `VUE3_MIGRATION_PLAN.md` (detailed 800-line plan with Epics 1-7).

### Stories (roll-up of the 7 epics from `VUE3_MIGRATION_PLAN.md`)

#### C1 — Build tooling (Vite + TypeScript) ✅
- [x] C1.1 — Replace Vue CLI / Webpack with Vite 5 (`vite.config.ts`, `tsconfig*.json`)
- [x] C1.2 — Migrate `package.json` to Vue 3 deps (remove `vue-cli-service`, `babel-*`; add `@vitejs/plugin-vue`, `vue-tsc`)

#### C2 — Service layer adapter ✅
- [x] C2.1 — Define `INaasApiService` interface (`src/service/naas-api.interface.ts`)
- [x] C2.2 — Implement `moodle-naas-api.service.ts` wrapping Moodle `core/ajax` webservices
- [x] C2.3 — Provide the service via `src/plugins/naas-api.plugin.ts` (`app.provide('naasApi', ...)`)

#### C3 — Foundation (bootstrap & plugins) ✅
- [x] C3.1 — `src/main.ts` Vue 3 bootstrap, mounts at `window.NAAS.mount_point`
- [x] C3.2 — `vue-i18n@9` seeded from `window.NAAS.labels`
- [-] C3.3 — TanStack Query / Pinia: **dropped** (no stores needed — see enhancement D1)

#### C4 — Types & composables ✅
- [x] C4.1 — Copy TS types (`nugget.types.ts`, `person.types.ts`, `structure.types.ts`, `domain.types.ts`, `naas-config.types.ts`)
- [x] C4.2 — `useNuggetView`, `useNuggetSearch`, `useXapi`
- [x] C4.3 — Helpers: `useMoodleService`, `useNaasConfig`, `useComponentId`, `useEntityResolvers`, `useNuggetEnricher`

#### C5 — Component migration ✅
- [x] C5.1 — `Main.vue` dynamic rendering (NuggetView / NuggetSearchWidget)
- [x] C5.2 — `NuggetView.vue` migrated
- [x] C5.3 — `NuggetSearchWidget.vue` + `NuggetSearchFilter.vue` migrated
- [x] C5.4 — `NuggetPost.vue` migrated
- [x] C5.5 — `NuggetAboutModal.vue`, `NuggetCompletionModal.vue`, `NuggetViewModal.vue` migrated
- [x] C5.6 — `Loading.vue`, `RelatedDomain.vue`, `NuggetBadge.vue` migrated

#### C6 — AMD integration & versioning ✅
- [x] C6.1 — `amd/src/widget_init.js` loads the new Vite IIFE bundle
- [x] C6.2 — `vite.config.ts` outputs `assets/vue/naas_widget-YYYYMMDDXX.js` (IIFE)
- [x] C6.3 — `version.php` bumped to ship the Vue 3 bundle

#### C7 — Vue 2 cleanup ✅
- [x] C7.1 — Delete `vue/babel.config.js`, `vue/vue.config.js`, `vue/.browserslistrc`, `vue/src/main.js`, `vue/src/mixin.js`, `vue/src/cache-service.js`, `vue/src/utils.js`, `vue/src/http/moodleService.js`, `vue/public/*`, `vue/dev_config_dist.js`, `vue/package-lock.json`

#### C8 — QA & regression ❌ **TODO** (manual QA before merge)
- [ ] C8.1 — NuggetView smoke test in a real Moodle instance (iframe, modals, language, completion, xAPI)
- [ ] C8.2 — NuggetSearchWidget smoke test (search, filters, click-through, pagination)
- [ ] C8.3 — Bundle size check: new IIFE ≤ old Vue 2 bundle

**Commits in branch (7):**

| SHA | Message |
|-----|---------|
| 9d6f45a | build: replace Vue CLI with Vite 5 and TypeScript |
| 24ff36f | feat: add Moodle webservice adapter, TypeScript types, and Vue 3 app bootstrap |
| d9230b5 | feat: add Vue 3 composables for nugget view, search, xAPI, and entity resolution |
| 9f27cb1 | feat: migrate all Vue components from Vue 2 Options API to Vue 3 script setup |
| 727053d | chore: bump plugin version and ship Vue 3 bundle |
| 34d931a | chore: forgotten index |
| 7285413 | chore: remove Vue 2 source files and build artifacts |

**Pending before PR:**
- Complete C8 manual QA in a Moodle instance (required)
- Squash `34d931a chore: forgotten index` into `727053d` (fixup)

---

## 6. EPIC D — Widget enhancements (UX, performance, DX)

**Branch:** `feat/global_enhancements` (stacked on EPIC C)
**Motivation:** now that the widget is Vue 3 / TS, add the backlog of UX, performance and DX improvements.

Reference: `ENHANCEMENTS_BACKLOG.md`.

### Stories

#### D1 — Design / visual polish
- [x] **G1** — Global design enhancement: token-based styling, Moodle-theme inheritance, scoped styles, modal chrome, hover states, pill search bar, skeleton polish
  - Commit: `3591cc4 feat(general-UX): migrate all styles into <style scoped> per component …`

#### D2 — UX / learner experience
- [x] **E1** — Loading skeletons (`NuggetSkeleton`, `NuggetViewSkeleton`, `FilterSkeleton`)
  - Commits: `9531e2b`, `f7ff0a4`
- [x] **E2** — Error banner with retry in `NuggetView` and `NuggetSearchWidget`
  - Commits: `f7ff0a4`, `c94dadd`
- [-] **E4** — Completion success toast: **dropped** by user
- [x] **E5** — Previous/Next pagination replaces load-more
  - Commit: `0e209d5`
- [x] **E6** — Keyboard navigation (`role="listbox"`, arrows, Enter)
  - Commit: `0e209d5`
- [x] **E7** — Dismissible filter chips with clear-all
  - Commit: `a3bc1c8`

#### D3 — Performance
- [x] **P1** — Server-side MUC cache 24h for `get_domain`, `get_structure`, `get_person`
  - Commit: `0b2c04e` (+ `db/caches.php` definition)
- [x] **P2** — Delay xAPI `experienced` 10s via `IntersectionObserver`
  - Commit: `cd4d888`
- [x] **P3** — Preload LTI iframe (use `cm_id` synchronously)
  - Commit: `cd4d888`

#### D4 — Developer / maintainability
- [x] **D1** — Remove Pinia (dep unused; saves ~12 kB)
  - Commit: `8740cf5`
- [x] **D2** — Vue DevTools hook in dev builds
  - Commit: `06567ef`
- [ ] **D3** — Typed API responses (Zod or lightweight validator) — **TODO**
- [-] **D4** — Playwright smoke test — dropped
- [x] **D5** — `vue/scripts/bump-version.js` syncs version across `vite.config.ts`, `widget_init.js`, `version.php`
  - Commit: `2d0e059`
- [x] **D6** — Scoped styles per component
  - Combined with G1 in `3591cc4`

#### D5 — PHP / Moodle integration (all parked)
- [-] **M1** — Completion by xAPI — deferred (critical change, handled separately)
- [-] **M2** — Grade passback from rating — dropped (rating is student feedback)
- [-] **M3** — `view_nugget` event logging — intentional design (fires in `view.php`)
- [-] **M4** — Moodle 4.x secondary-nav About wiring — kept as-is

#### D6 — Fix-forward after Vue 3 migration
- [x] D6.1 — Search, select, load-more behaviour fixes
  - Commit: `b563ab6 fix(composables): small fixes on wrong behaviours (search, select, load more nuggets)`
- [x] D6.2 — CSS loading regression fix
  - Commit: `7964b17 feat(style): fix css loading and enhance components UX`
- [x] D6.3 — Rebuilt IIFE bundle checked in
  - Commit: `51cf9ea chore(build): built files`

**Commits in branch (13):**

| SHA | Message |
|-----|---------|
| 8740cf5 | chore(D1): remove Pinia — no stores existed, saves ~12 kB from bundle |
| 06567ef | feat(devtools): wire Vue DevTools global hook in dev builds |
| 2d0e059 | feat(bundle): add bump-version.js script to sync BUNDLE_VERSION across vite.config.ts, widget_init.js, and version.php |
| 3591cc4 | feat(general-UX): scoped styles + design tokens + modal polish |
| 9531e2b | feat(E1): add FilterSkeleton shimmer |
| f7ff0a4 | feat(skeletons&errors): add NuggetViewSkeleton, expose load(), error banner with retry |
| c94dadd | feat(E2): error banner with retry in NuggetView; drop E4 toast |
| 0e209d5 | feat(search): Previous/Next pagination + keyboard navigation |
| a3bc1c8 | feat(search): dismissible filter chips + clear-all |
| 0b2c04e | feat(cache): 24h MUC cache for get_domain, get_structure, get_person |
| cd4d888 | feat(xAPI): delay experienced 10s; preload LTI iframe |
| 7964b17 | feat(style): fix css loading and enhance components UX |
| b563ab6 | fix(composables): search/select/load-more fixes |
| 51cf9ea | chore(build): built files |

**Acceptance criteria:**
- [ ] Skeletons appear instead of the spinner on every load path
- [ ] Error banner with working Retry on simulated 500
- [ ] Pagination Previous/Next + keyboard navigation round-trip
- [ ] Filter chips display and clear correctly
- [ ] Server-side cache hits verified (2nd call to `get_domain` does not hit NaaS)
- [ ] xAPI `experienced` not sent if user leaves within 10 s
- [ ] `npm run bump-version` updates all three files

**Pending before PR:**
- Decide on D3 — either schedule it or move it to the backlog
- Split the PR by theme if reviewers push back (UX / perf / DX)

---

## 7. EPIC E — PHP-side security hardening

**Branch:** `security/hardening_php_side` (stacked on EPIC D)
**Motivation:** the plugin proxies NaaS API calls on behalf of authenticated Moodle users. An authenticated student could forge or replay webservice calls. Apply input validation, output re-encoding, authorisation checks and a safer default for SSL.

Reference: `SECURITY_HARDENING_BACKLOG.md` (full threat model, 7 epics S1–S7).

### Stories (the subset shipped on this branch)

#### E.S1 — Strict input validation on proxy endpoints ✅
- [x] E.S1.1 — UUID / slug allowlist validation on all proxy ID parameters
  - Commit: `5ee320e security(S1): add UUID/slug allowlist validation on all proxy ID parameters`

#### E.S2 — xAPI request hardening ✅
- [x] E.S2.1 — Allowlist verbs (`experienced`, `completed`, `rated`)
- [x] E.S2.2 — Request body size limit (4 kB default)
  - Commit: `86b3e56 security(xAPI): validate xAPI verb allowlist and body size limit`

#### E.S3 — Authorisation ✅
- [x] E.S3.1 — `view_nugget`: enforce course-enrolment check on `cm_id`
- [x] E.S3.2 — `post_xapi_statement`: enforce course-enrolment check
  - Commit: `33a6988 security(authz): enforce course enrollment check on view_nugget and post_xapi_statement`

#### E.S4 — Credential & transport hardening ✅
- [x] E.S4.1 — `naas_password` fallback via `getenv('NAAS_API_PASSWORD')` when DB value empty
- [x] E.S4.2 — Enable `CURLOPT_SSL_VERIFYPEER` by default; admin toggle for dev
  - Commit: `4a7976b security(api_password): env-var password fallback and SSL verification`

#### E.S5 — Output sanitisation ✅
- [x] E.S5.1 — Re-encode NaaS API responses server-side (`json_decode → json_encode`) before returning
  - Commit: `d74899b security(api_responses): re-encode all NaaS API responses before returning to browser`
- [x] E.S5.2 — Replace `v-html` with `{{ }}` interpolation in Vue widgets (Moodle plugin side)
  - Commit: `86ce69f security(v-html): replace v-html with safe text interpolation in Moodle plugin widgets`

#### E.S6 — Lang strings ✅
- [x] E.S6.1 — Add English lang strings for new validation errors and the SSL setting
  - Commit: `bba3d88 security: add lang strings for new validation errors and SSL setting`

#### Deferred to a follow-up security PR
- [ ] **S2** — Rate limiting per user (MUC-backed)
- [ ] **S3.2** — Split `mod/naas:search` capability from `addinstance`
- [ ] **S4.3** — cURL timeout / retry / circuit-breaker
- [ ] **S5.1** — Replace `PARAM_RAW` with `external_single_structure` in `_returns()`
- [ ] **S5.4** — LTI iframe sandbox CSP
- [ ] **S6** — Audit logging events + admin report
- [ ] **S7** — Dependency security (`npm audit`, replace `moment`, pin versions, SRI)

**Commits in branch (7):**

| SHA | Message |
|-----|---------|
| 5ee320e | security(S1): add UUID/slug allowlist validation on all proxy ID parameters |
| 86b3e56 | security(xAPI): validate xAPI verb allowlist and body size limit |
| 33a6988 | security(authz): enforce course enrollment check on view_nugget and post_xapi_statement |
| 4a7976b | security(api_password): env-var password fallback and SSL verification |
| d74899b | security(api_responses): re-encode all NaaS API responses before returning to browser |
| 86ce69f | security(v-html): replace v-html with safe text interpolation in Moodle plugin widgets |
| bba3d88 | security: add lang strings for new validation errors and SSL setting |

**Acceptance criteria:**
- [ ] Passing `../admin/users` as `nuggetId` returns a validation error, never reaches NaaS
- [ ] POSTing `{verb: "deleted"}` to xAPI returns 400, no outbound request
- [ ] POSTing 1 MB xAPI body returns 400
- [ ] Non-enrolled student calling `view_nugget` on another course's `cm_id` is rejected
- [ ] On an HTTPS NaaS with broken cert, plugin fails closed (no data leak) unless dev toggle enabled
- [ ] A nugget `description` containing `<script>alert(1)</script>` renders as escaped text in the widget

---

## 8. EPIC F — Search icon restyle

**Branch:** `feat/change_search_icon` (HEAD, stacked on EPIC E)
**Motivation:** the search PNG icon was inconsistent with the filter-toggle button style.

### Stories

- [x] F1.1 — Replace `<img>` search icon with a styled `<button>` matching the filters-toggle
  - Commit: `926ab38 style: replace search PNG icon with styled button matching filters toggle`

**Acceptance criteria:**
- [ ] Search and filter toggle share the exact same button style (colours, radius, hover, focus ring)
- [ ] Button is keyboard-focusable, has `aria-label="search"`
- [ ] No dangling reference to the deleted PNG asset

---

## 9. EPIC G — Uncommitted visual polish (WIP)

**Working tree only — not yet committed.**

### Files modified

```
M  assets/vue/naas_widget-2026030300.js   (rebuilt bundle)
M  assets/vue/style.css                   (rebuilt bundle css)
M  styles.css
M  vue/src/components/NuggetAboutModal.vue
M  vue/src/components/NuggetCompletionModal.vue
M  vue/src/components/NuggetPost.vue
M  vue/src/components/NuggetSearchFilter.vue
M  vue/src/components/NuggetSearchWidget.vue
M  vue/src/components/NuggetSkeleton.vue
M  vue/src/components/NuggetView.vue
M  vue/src/components/NuggetViewModal.vue
```

### Observed changes

- `NuggetPost.vue`: thumbnail overlay badges (duration, level), `loading="lazy"`, title tooltip, description markup cleanup
- `NuggetCompletionModal.vue`: +346/-… sizeable rework (likely rating/navigation polish)
- `NuggetAboutModal.vue` / `NuggetViewModal.vue`: modal chrome iterations
- `NuggetSearchFilter.vue` / `NuggetSearchWidget.vue`: filter/search UX iterations
- `NuggetSkeleton.vue`: skeleton layout tweaks
- `styles.css`: updated tokens / spacing
- Rebuilt bundle (`assets/vue/naas_widget-2026030300.js`, `assets/vue/style.css`)

### Stories to carve out

- [ ] **G1.1** — NuggetPost: thumbnail overlay badges + lazy image + title tooltip
- [ ] **G1.2** — NuggetCompletionModal polish (review the +346/-… diff and split if it mixes concerns)
- [ ] **G1.3** — About / View modal chrome iteration
- [ ] **G1.4** — Search / Filter UX iteration
- [ ] **G1.5** — Skeleton layout tweaks
- [ ] **G1.6** — Rebuild bundle + bump `BUNDLE_VERSION` via `npm run bump-version`

### Untracked (in working tree)

```
??  ENHANCEMENTS_BACKLOG.md
??  OUTPUT_API_MIGRATION_BACKLOG.md
??  OUTPUT_API_MIGRATION_STUDY.md
??  SECURITY_HARDENING_BACKLOG.md
??  VUE3_MIGRATION_PLAN.md
??  .DS_Store (root, amd/, classes/, lang/)
```

- `*.md` backlogs: consider whether to commit them into the repo (`/docs/`) or keep them local. They contain the threat model and migration plans — useful for reviewers.
- `.DS_Store` files: add to `.gitignore` and delete locally (never commit).

---

## 10. Recommended PR Sequence

The branches are stacked. To minimise conflicts and reviewer load, land them in this order, each as a **separate PR against `master`** (rebase-then-open, don't keep the stack in a single PR):

| Order | PR title | Base branch | Size estimate | Blockers |
|------:|----------|-------------|---------------|----------|
| 1 | **fix: recover Nugget instance when opened via `?u=` URL** | `fix/display_with_u_url` | XS (1 commit) | none |
| 2 | **refactor: migrate outputs to Output API + Mustache** | `feat/output_api` | L (12 commits, ~30 files) | PR 1 landed; finish EPIC B5 or split it |
| 3 | **chore: Vue 2 → Vue 3 / Vite / TypeScript migration** | `chore/vue3_migration` | XL (7 commits, ~70 files) | PR 2 landed; EPIC C8 manual QA done |
| 4 | **feat: widget UX, perf and DX enhancements** | `feat/global_enhancements` | L (13 commits) | PR 3 landed |
| 5 | **security: PHP-side input validation, authz, SSL, output re-encoding** | `security/hardening_php_side` | M (7 commits) | PR 4 landed |
| 6 | **style: restyle search icon as button matching filter toggle** | `feat/change_search_icon` | XS (1 commit) | PR 5 landed |
| 7 | **feat: additional visual polish (NuggetPost badges, modal & filter UX)** | new branch off step 6 | M (from WIP) | WIP committed and split |

### Before opening each PR

- Rebase on the latest `master`: `git rebase origin/master`
- Run the plugin CI locally:
  - `php admin/cli/mustache_lint.php --filter=mod_naas`
  - `vendor/bin/moodle-plugin-ci phpcs`, `phpmd`, `phpdoc`
  - `cd vue && npm run build && npm run type-check`
- Verify `version.php` bump matches release conventions (PR 3 already bumps it; others generally shouldn't touch `version.php`)
- For PRs 2–6: include a short **migration note** in the description linking to the corresponding backlog file in this repo

### If the team wants smaller reviewable PRs

- **EPIC B** can be split into three PRs:
  - B1 + B2 + B3 (foundation + widget + LTI — all security-adjacent) 
  - B4 (view.php / index.php migration)
  - B5 + B6 (admin settings + lint pass)
- **EPIC C** is genuinely monolithic — splitting risks leaving the widget in a broken half-migrated state. Keep as one PR; add a comprehensive test plan in the description.
- **EPIC D** can be split by theme:
  - D1 (design tokens + scoped styles)
  - D2 (UX: skeletons, errors, pagination, keyboard, chips)
  - D3 (performance: cache, xAPI delay, iframe preload)
  - D4 (DX: Pinia removal, DevTools, bump-version script)
  - D6 (fix-forward composable + CSS fixes + rebuilt bundle)
- **EPIC E** can be split per story (`S1`, `S2`=xAPI, `S3`=authz, `S4`=SSL/env, `S5`=output); each commit is already self-contained.

---

## 11. Cross-Cutting Follow-ups (tracked elsewhere)

| Topic | File | Status |
|-------|------|--------|
| Admin settings Output API migration (B5) | `OUTPUT_API_MIGRATION_BACKLOG.md` §EPIC-5 | todo |
| Mustache lint CI run (B6.3) | `OUTPUT_API_MIGRATION_BACKLOG.md` §EPIC-6 | todo |
| Typed API responses (D.D3) | `ENHANCEMENTS_BACKLOG.md` §D3 | todo |
| Rate limiting (S2) | `SECURITY_HARDENING_BACKLOG.md` §Epic S2 | todo |
| Capability split (S3.2) | `SECURITY_HARDENING_BACKLOG.md` §Epic S3 | todo |
| cURL timeout / retry (S4.3) | `SECURITY_HARDENING_BACKLOG.md` §Epic S4 | todo |
| Structured `_returns()` (S5.1) | `SECURITY_HARDENING_BACKLOG.md` §Epic S5 | todo |
| LTI iframe sandbox CSP (S5.4) | `SECURITY_HARDENING_BACKLOG.md` §Epic S5 | todo |
| Audit logging + admin report (S6) | `SECURITY_HARDENING_BACKLOG.md` §Epic S6 | todo |
| Dependency hygiene (S7) | `SECURITY_HARDENING_BACKLOG.md` §Epic S7 | todo |
| Completion by xAPI (M1) | `ENHANCEMENTS_BACKLOG.md` §M1 | deferred |
| Manual Moodle QA for Vue 3 widget (C8) | `VUE3_MIGRATION_PLAN.md` §Epic 7 | todo |

---

## 12. Housekeeping

- [ ] Add `.DS_Store` to `.gitignore`, delete the tracked ones in `amd/`, `classes/`, `lang/`, repo root
- [ ] Decide whether to commit the planning `*.md` files (`OUTPUT_API_MIGRATION_STUDY.md`, `OUTPUT_API_MIGRATION_BACKLOG.md`, `VUE3_MIGRATION_PLAN.md`, `ENHANCEMENTS_BACKLOG.md`, `SECURITY_HARDENING_BACKLOG.md`, this `PR_ROADMAP.md`). Suggestion: move into `docs/` on a documentation-only PR before opening the code PRs
- [ ] Squash the `chore: forgotten index` commit into the Vue 3 bundle commit (`34d931a` → `727053d`)
- [ ] Commit & push the uncommitted working tree (EPIC G) so nothing is lost locally

---

_Generated 2026-04-20 — reflects branch state at time of writing. Re-generate if the feature branches move._
