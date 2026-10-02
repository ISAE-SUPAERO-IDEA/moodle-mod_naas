# NaaS Plugin — Enhancement Backlog

Potential improvements to the Vue 3 widget and surrounding PHP layer, grouped by theme.

Status: `[ ]` todo · `[x]` done · `[-]` dropped


---


## Design / Visual Polish

| # | Status | Title |
|---|--------|-------|
| G1 | [x] | Global design enhancement |

### G1 — Global design enhancement
Audit all Vue components for visual consistency: replace magic colour values with CSS custom-property tokens or, even better, make the plugin inherit colors from moodle theme itself,
Other things to watch : tighten spacing, improve card hover states, modernise modal chrome (close button, header divider, backdrop blur), and make buttons feel crisper across NuggetPost, NuggetView, and both modals. No routing or external library changes — pure CSS and minor template tweaks.

**Done:** Extended token set in `styles.css` to include `--naas-surface`, `--naas-text`, `--naas-border`, `--naas-radius-*`, and `--naas-shadow-*` — all inheriting from the Moodle Boost theme. `NuggetPost` now has a thumbnail overlay badge (duration + level), `line-clamp` body text, and a three-variant button footer (filled / outlined / ghost). `NuggetSearchWidget` search bar replaced with a pill-shaped field (integrated icon + clear button). All three modals (`NuggetAboutModal`, `NuggetCompletionModal`, `NuggetViewModal`) share a consistent backdrop-blur treatment, `border-radius: 16px`, and a slide-up/scale-in entry animation. `NuggetCompletionModal` navigation arrows replaced with icon buttons; rating stars are keyboard-accessible. Completion button in `NuggetView` is pill-shaped with lift-on-hover. Language `<select>` is custom-styled (pill, no native arrow). `NuggetSearchFilter` dropdowns get a slide-down transition. Skeleton shimmer uses staggered `animation-delay` for a more premium pulse. All hardcoded colours replaced with tokens.

---

## UX / Learner Experience

| # | Status | Title |
|---|--------|-------|
| E1 | [x] | Loading skeletons |
| E2 | [x] | Error state with retry |
| E4 | [-] | Completion flow feedback — dropped by user |
| E5 | [x] | Pagination controls in search widget |
| E6 | [x] | Keyboard navigation in search |
| E7 | [x] | Filter chip display |

### E1 — Loading skeletons
Replace the blank space during `loading = true` with skeleton cards matching the nugget card shape. Avoids layout shift and signals to the learner that content is on the way.

**Done:** Added `NuggetSkeleton.vue` (shimmer card for search grid), `NuggetViewSkeleton.vue` (toolbar + iframe placeholder for the view page), and `FilterSkeleton.vue` (filter panel placeholder). All three replace the old GIF spinner.

### E2 — Error state with retry
When `useNuggetView` or `useNuggetSearch` catches an error, show a human-readable message with a **Retry** button that calls `load()` again. Currently errors are silently swallowed in the view.

**Done:** `useNuggetView` now exposes `load()`. Both `NuggetView` and `NuggetSearchWidget` show a styled error banner with a Retry button on failure.

### E4 — Completion flow feedback
After the user clicks **Complete**, show a brief success toast (e.g. "Marked as complete") before the modal appears, so the action feels instant even if the xAPI call is slow.

**Dropped** at user's request.

### E5 — Pagination controls in search widget
The search already supports `page` and `page_size` via the API. Add Previous / Next buttons and a page indicator to the `NuggetSearchWidget` so teachers can browse beyond the first 6 results.

**Done:** Replaced "load more" accumulation with proper Previous/Next buttons and a `page / totalPages` indicator. Page resets to 1 on new searches or filter changes.

### E6 — Keyboard navigation in search
Make the nugget result list navigable with arrow keys and selectable with Enter, following WAI-ARIA `listbox` pattern. Improves accessibility for keyboard and screen-reader users.

**Done:** Results grid has `role="listbox"`, each card has `role="option"` with `aria-selected` and managed `tabindex`. Arrow keys move focus; Enter selects. Focus index resets on each new search.

### E7 — Filter chip display
Show active filters as dismissible chips above the results list so teachers can see and remove individual active filters without opening the filter panel.

**Done:** Added `FilterChips.vue`. Chips appear above the results grid when filters are active. Each chip has an ×-button to remove that single filter. A "clear all" chip appears when more than one filter is active.

---

## Developer / Maintainability

| # | Status | Title |
|---|--------|-------|
| D1 | [x] | Remove Pinia |
| D2 | [x] | Vue DevTools integration |
| D3 | [ ] | Typed API responses |
| D4 | [-] | Automated Playwright smoke test — dropped, not needed at this stage |
| D5 | [x] | Bundle version auto-sync script |
| D6 | [x] | CSS encapsulation (`<style scoped>`) |

### D1 — Remove Pinia
`pinia` is installed and `createPinia()` is called in `main.ts` but no stores exist. The nugget is not shared across unrelated trees — `NuggetCompletionModal` receives it as a prop from `NuggetView`, so there is no sharing problem to solve. Remove the dependency entirely to save ~12 kB from the bundle.

**Done:** Removed `pinia` from `package.json` and `createPinia()` from `main.ts`.

### D2 — Vue DevTools integration
Add a `__VUE_DEVTOOLS_GLOBAL_HOOK__` check in `main.ts` dev builds so the Vue DevTools browser extension can connect to the IIFE app. Currently the app is invisible to DevTools.

**Done:** `main.ts` emits `app:init` on `__VUE_DEVTOOLS_GLOBAL_HOOK__` in development mode. Guarded behind `process.env.NODE_ENV === 'development'` so it tree-shakes out of the production IIFE.

### D3 — Typed API responses
`callWebservice` currently returns `PARAM_RAW` strings parsed with `JSON.parse(...).payload`. Introduce Zod or a lightweight validator to assert the response shape at runtime and surface API contract breaks early.

### D5 — Bundle version auto-sync script
`BUNDLE_VERSION` in `vite.config.ts` and the reference in `amd/src/widget_init.js` must be kept in sync manually. Write a small Node script (`scripts/bump-version.js`) that updates both files and `version.php` in one command.

**Done:** `vue/scripts/bump-version.js` updates `vite.config.ts`, `amd/src/widget_init.js`, and `version.php` atomically. Run via `npm run bump-version [VERSION]`.

### D6 — CSS encapsulation
All component styles are currently global. Migrate to `<style scoped>` in each component to avoid leaking classes like `.language-select` or `.gallery` into the Moodle page.

**Done:** All 10 Vue components now have `<style scoped>` blocks. `styles.css` retains only page-level layout, Moodle/Bootstrap overrides, and the shared CSS custom-property token definitions. Combined with G1 polish pass.

---

## Performance

| # | Status | Title |
|---|--------|-------|
| P1 | [x] | Server-side cache for proxy responses |
| P2 | [x] | Delay xAPI "experienced" statement by 30 s |
| P3 | [x] | Preload LTI iframe |

### P1 — Server-side cache for proxy responses
`get_domain`, `get_structure`, and `get_person` are called repeatedly for the same keys across page loads. Add a Moodle `cache_definition` (MUC) to store these vocabulary entries server-side with a TTL of 24 h, reducing outbound calls to the NaaS API.

**Done:** Added `db/caches.php` with a `vocabulary_entries` MUC definition (application-mode, 24 h TTL). `get_domain`, `get_structure`, and `get_person` in `proxy_naas_api.php` now check/populate the cache before making an outbound request.

### P2 — Delay xAPI "experienced" statement
The `experienced` xAPI statement is sent as soon as the nugget loads. If the learner lands on the page accidentally, it is still recorded. Delay the statement by 30 s of confirmed iframe visibility (using `IntersectionObserver`) before sending.

**Done:** `NuggetView.vue` uses `IntersectionObserver` (50% threshold) on `#lti-frame`. The 30-second countdown starts when the iframe becomes visible and resets if it leaves the viewport before the timer fires.

### P3 — Preload LTI iframe
When `NuggetView` loads, the iframe `src` is set only after the nugget API call resolves. Consider setting a placeholder `src` to the launch URL immediately (the `cm_id` is available synchronously from `window.NAAS`) so the browser can start the TCP handshake earlier.

**Done:** `NuggetView.vue` sets `preloadUrl = launch.php?id=<cm_id>&triggerview=0` synchronously. The iframe renders as soon as loading finishes (not waiting for language), using the preload URL until `iframeUrl` (with language) is computed.

---

## PHP / Moodle Integration

| # | Status | Title |
|---|--------|-------|
| M1 | [-] | Completion by xAPI — deferred, critical change, handled separately |
| M2 | [-] | Grade passback from rating — dropped, rating is student feedback, not course completion input |
| M3 | [-] | `view_nugget` event logging — intentional, `naas_view()` fires in `view.php`, not the webservice |
| M4 | [-] | Moodle 4.x secondary nav About wiring — kept as-is for now |
