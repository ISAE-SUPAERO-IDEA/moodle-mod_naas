# Vue / TypeScript Code Quality — mod_naas

## Overall score: 3.4 / 5

---

## Scores by category

| Category | Score | Justification |
|---|---|---|
| Architecture & component design | 4.0 / 5 | Clean composable-based architecture (`useNuggetSearch`, `useNuggetView`, `useXapi`). Good separation of concerns. Vite IIFE build well-suited for Moodle. AMD bridge is the only structural awkwardness. |
| Type safety | 3.5 / 5 | Solid interface definitions for domain models (`Nugget`, `Domain`, `Person`). Generics on service layer. Undermined by `any` casts in the Moodle adapter and unvalidated `window.NAAS` config at runtime. TypeScript strict mode not enabled. |
| State management | 3.0 / 5 | Composables isolate state cleanly. No global store (Pinia removed intentionally). Race conditions possible on concurrent search requests — no request cancellation. Filters lost on page reload. |
| Error handling | 2.5 / 5 | Error banners with retry in NuggetView and NuggetSearchWidget. xAPI failures silenced with `console.warn` only. No retry logic with backoff. No request timeout handling. |
| Performance | 3.5 / 5 | Debounced search, CSS inlined, 24h response caching in Moodle service layer. No virtual scrolling on large result sets. RequireJS polling (100ms interval) is wasteful. No image lazy loading. |
| Documentation | 3.5 / 5 | GPL headers on all source files. JSDoc on key functions. Inline comments sparse in complex composable logic (`useNuggetSearch`). Accessibility patterns not documented. |
| Testing | 1.0 / 5 | No unit tests for components or composables. No integration tests. ESLint present but minimal config (`vue/essential` only). No E2E tests. |
| Security / input validation | 3.0 / 5 | No v-html usage (replaced). API responses re-encoded before use. `window.NAAS` config injected globally without runtime schema validation. `JSON.parse()` in `widget_init.js` without try/catch. |

---

## Enhancement backlog

### Critical (correctness / security)

| # | File | Issue | Suggested fix |
|---|---|---|---|
| C1 | `widget_init.js` | `JSON.parse(dataConfig)` without try/catch — crashes widget on malformed config | Wrap in try/catch, show user-facing error |
| C2 | `moodle-naas-api.service.ts` | `window.NAAS` config consumed without runtime validation | Add a config schema check (zod or manual) at bootstrap |
| C3 | `useNuggetSearch.ts` | Concurrent search requests fire without cancellation — race condition on fast typing | Use `AbortController` to cancel in-flight requests |

### High (code quality)

| # | File | Issue | Suggested fix |
|---|---|---|---|
| H1 | `moodle-naas-api.service.ts` | RequireJS presence polled with `setInterval(100ms)` — unreliable and wasteful | Replace with Moodle AMD `require(['core/first'], cb)` or a one-shot MutationObserver |
| H2 | `useXapi.ts` | xAPI failures reported via `console.warn` only — invisible in production | Report to Moodle notification API or a dedicated error composable |
| H3 | all composables | No request timeout — calls can hang indefinitely | Add `AbortSignal.timeout(10000)` on all fetch calls |
| H4 | `vite.config.ts` + `widget_init.js` | Bundle version must be updated manually in two places | Centralise in a single `version.ts` constant, read by both |
| H5 | `NuggetView.vue` | Direct `document.querySelector()` calls mixed with Vue reactivity | Replace with `ref` / `useTemplateRef` |

### Medium (type safety / robustness)

| # | File | Issue | Suggested fix |
|---|---|---|---|
| M1 | `tsconfig.json` | `strict` mode not enabled | Enable `"strict": true` and fix resulting type errors |
| M2 | `moodle-naas-api.service.ts` | `any` casts on Moodle webservice responses | Type response shapes with explicit interfaces |
| M3 | all components | Props not validated with `PropType` | Add runtime prop validators on all component inputs |
| M4 | `useNuggetSearch.ts` | Search state (filters, pagination) lost on page reload | Persist to `sessionStorage` with a thin composable wrapper |
| M5 | `SearchWidget.vue` | Variable names `typed` / `debouncedTyped` ambiguous | Rename to `searchQuery` / `debouncedQuery` |

### Low (polish / performance)

| # | Area | Issue | Suggested fix |
|---|---|---|---|
| L1 | Search results grid | No virtual scrolling — large result sets render all DOM nodes | Integrate `@vueuse/components` virtual list or `vue-virtual-scroller` |
| L2 | Nugget thumbnails | Images load eagerly | Add `loading="lazy"` on `<img>` tags |
| L3 | Components | Hardcoded Bootstrap class names (`btn`, `btn-primary`) couple UI to theme | Extract to CSS custom properties or a theme token composable |
| L4 | all | ESLint config minimal (`vue/essential` only) | Upgrade to `vue/recommended` + `@typescript-eslint/recommended` |
| L5 | all | No unit tests | Add Vitest with `@vue/test-utils` for composables and key components |
| L6 | ARIA | No accessibility audit visible | Add `axe-core` or `eslint-plugin-vuejs-accessibility` |
