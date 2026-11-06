# Vue / TypeScript Test Plan — mod_naas

Target: **90 % line coverage** on composables and service layer, **80 %** on components.
Frameworks: **Vitest** + **@vue/test-utils** + **MSW** (Mock Service Worker).

---

## 1. Setup

### Packages to install
```bash
cd vue
npm install -D vitest @vue/test-utils @vitejs/plugin-vue jsdom
npm install -D msw @mswjs/data
npm install -D @vitest/coverage-v8
```

### Directory structure to create
```
vue/src/
├── composables/
│   └── __tests__/
│       ├── useNuggetSearch.test.ts
│       ├── useNuggetView.test.ts
│       ├── useXapi.test.ts
│       ├── useEntityResolvers.test.ts
│       ├── useNuggetEnricher.test.ts
│       ├── useNaasConfig.test.ts
│       └── useMoodleService.test.ts
├── components/
│   └── __tests__/
│       ├── NuggetSearchWidget.test.ts
│       ├── NuggetView.test.ts
│       ├── NuggetPost.test.ts
│       ├── FilterChips.test.ts
│       ├── NuggetSearchFilter.test.ts
│       ├── NuggetAboutModal.test.ts
│       └── NuggetCompletionModal.test.ts
├── service/
│   └── __tests__/
│       └── moodle-naas-api.service.test.ts
└── __tests__/
    └── widget_init.test.ts
```

### `vitest.config.ts`
```ts
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/__tests__/setup.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'lcov'],
      include: ['src/**/*.{ts,vue}'],
      exclude: ['src/types/**', 'src/main.ts'],
      thresholds: { lines: 90, functions: 90, branches: 85 },
    },
  },
});
```

### `src/__tests__/setup.ts`
```ts
import { beforeAll, afterAll, afterEach } from 'vitest';
import { server } from './mocks/server';

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());
```

### Run tests
```bash
# All tests
npm run test

# Watch mode
npm run test -- --watch

# With coverage
npm run test -- --coverage
```

### `package.json` scripts to add
```json
"test": "vitest run",
"test:watch": "vitest",
"test:coverage": "vitest run --coverage"
```

---

## 2. MSW mock server

Create `src/__tests__/mocks/` with handlers for all Moodle webservice calls:

```ts
// src/__tests__/mocks/handlers.ts
import { http, HttpResponse } from 'msw';
import { nuggetFixture, searchResultFixture } from '../fixtures';

export const handlers = [
  http.post('/lib/ajax/service.php', ({ request }) => {
    // route by methodname
  }),
];
```

Fixture files to create in `src/__tests__/fixtures/`:
- `nugget.fixture.ts` — full Nugget object
- `search-result.fixture.ts` — paginated search response
- `domain.fixture.ts` — Domain object
- `structure.fixture.ts` — Structure object
- `naas-config.fixture.ts` — valid `NaasConfig` object

---

## 3. Test files

---

### `composables/__tests__/useNuggetSearch.test.ts`

| Test | Scenario | Expected result |
|---|---|---|
| `initial state is empty` | Composable created | `nuggets = []`, `loading = false`, `error = null` |
| `search triggers loading state` | `search()` called | `loading = true` during fetch |
| `search populates nuggets on success` | MSW returns results | `nuggets` matches fixture |
| `search clears previous results` | Second search different query | Old nuggets replaced, not appended |
| `search debounce prevents rapid calls` | Query typed fast | API called once after debounce delay |
| `empty query returns all nuggets` | Query = `''` | Request sent without query param |
| `filter applied to request` | Filter object set | Request includes filter params |
| `clear filters resets state` | Filters then clear | `nuggets` and filters both reset |
| `concurrent requests cancelled` | Two rapid searches | Only last response applied to state |
| `API error sets error state` | MSW returns 500 | `error` set, `nuggets` unchanged |
| `retry after error clears error` | Error then success | `error = null` after retry |
| `next page appends results` | `loadMore()` called | Results appended to existing list |
| `previous page replaces results` | `previousPage()` called | Results replaced with previous page |

---

### `composables/__tests__/useNuggetView.test.ts`

| Test | Scenario | Expected result |
|---|---|---|
| `initial state has no nugget` | Composable created | `nugget = null`, `loading = false` |
| `load() fetches nugget by id` | Valid nugget ID | `nugget` populated from MSW |
| `load() sets loading true during fetch` | Fetch in progress | `loading = true` |
| `load() clears loading after fetch` | Fetch complete | `loading = false` |
| `load() sets error on failure` | API returns 404 | `error` set with message |
| `reload() re-fetches same nugget` | `reload()` after load | API called twice with same ID |
| `expose load() publicly` | Call `load()` directly | Refetch works |

---

### `composables/__tests__/useXapi.test.ts`

| Test | Scenario | Expected result |
|---|---|---|
| `sendExperienced posts correct verb` | Nugget viewed | xAPI call contains `experienced` verb |
| `sendCompleted posts correct verb` | Nugget completed | xAPI call contains `completed` verb |
| `statement includes nugget id` | Any send call | Nugget ID present in statement object |
| `statement includes actor from config` | Config has user info | Actor field matches config |
| `failure does not throw to caller` | API returns error | No uncaught exception, warning emitted |
| `experienced delayed 10s` | `sendExperienced()` called | Post delayed by 10 seconds |
| `delay cancelled on unmount` | Component unmounted before 10s | No API call made |

---

### `composables/__tests__/useEntityResolvers.test.ts`

| Test | Scenario | Expected result |
|---|---|---|
| `resolveDomain returns domain object` | Valid domain ID | Returns domain fixture |
| `resolveStructure returns structure object` | Valid structure ID | Returns structure fixture |
| `resolvePerson returns person object` | Valid person ID | Returns person fixture |
| `unknown id returns null` | ID not in API | Returns `null` |
| `parallel resolution all succeed` | Multiple IDs | All resolved correctly |
| `results cached across calls` | Same ID called twice | API called once |

---

### `composables/__tests__/useNuggetEnricher.test.ts`

| Test | Scenario | Expected result |
|---|---|---|
| `enriches nugget with domain name` | Nugget with domain ID | Enriched nugget has `domainName` |
| `enriches nugget with structure name` | Nugget with structure ID | Enriched nugget has `structureName` |
| `handles nugget with no domain` | `domain_id = null` | No error, field omitted |
| `batch enrichment all succeed` | Array of nuggets | All nuggets enriched |

---

### `composables/__tests__/useNaasConfig.test.ts`

| Test | Scenario | Expected result |
|---|---|---|
| `reads config from window.NAAS` | Valid config set | Returns typed config object |
| `throws on missing window.NAAS` | `window.NAAS` undefined | Throws with descriptive error |
| `throws on malformed config` | Missing required fields | Throws with field name in message |
| `moodle_url exposed correctly` | Config with wwwroot | `moodleUrl` returned as string |

---

### `service/__tests__/moodle-naas-api.service.test.ts`

| Test | Scenario | Expected result |
|---|---|---|
| `callWebservice sends POST to service.php` | Any method call | Request to `/lib/ajax/service.php` |
| `callWebservice passes methodname` | Specific method | `methodname` in request body |
| `callWebservice returns typed response` | MSW returns data | Data matches expected type |
| `callWebservice throws on Moodle exception` | Moodle returns `{error: true}` | Throws `Error` with message |
| `AMD require polled until available` | `window.require` set late | Service waits and resolves |
| `AMD require timeout after 5s` | `window.require` never set | Rejects with timeout error |
| `cache returns stored value` | Same call twice | Second returns cached value |
| `cache expires after TTL` | Call after TTL | API called again |

---

### `components/__tests__/NuggetSearchWidget.test.ts`

| Test | Scenario | Expected result |
|---|---|---|
| `renders search input` | Component mounted | `<input>` with search role present |
| `renders nugget list on results` | `useNuggetSearch` returns nuggets | `NuggetPost` components rendered |
| `shows skeleton during loading` | `loading = true` | Skeleton components visible |
| `shows empty state when no results` | Empty nuggets array | Empty state message displayed |
| `shows error banner on error` | `error` set | Error banner with retry button |
| `retry button calls search again` | Click retry | `search()` called |
| `typing triggers debounced search` | User types query | Input value bound, search called |
| `filter chips show active filters` | Filters applied | Filter chip components rendered |
| `clear all removes all filters` | Click clear all | Filters reset, chips gone |
| `pagination prev/next rendered` | Multiple pages | Prev/Next buttons present |
| `next page button calls loadMore` | Click next | `loadMore()` called |

---

### `components/__tests__/NuggetView.test.ts`

| Test | Scenario | Expected result |
|---|---|---|
| `renders skeleton while loading` | `loading = true` | `NuggetViewSkeleton` visible |
| `renders nugget content after load` | Nugget loaded | Title, description visible |
| `renders LTI iframe` | Nugget with launch URL | `<iframe>` present |
| `shows error banner on fetch failure` | Error state | Error message with retry |
| `retry button calls reload()` | Click retry | `load()` called again |
| `xAPI experienced sent after 10s` | Component mounted 10s | `sendExperienced()` called |
| `xAPI experienced cancelled on unmount` | Unmount before 10s | No xAPI call |
| `completion modal shown on complete` | xAPI completed | Modal appears |
| `back to course link rendered` | Config has courseUrl | Link with correct href |

---

### `components/__tests__/FilterChips.test.ts`

| Test | Scenario | Expected result |
|---|---|---|
| `renders one chip per active filter` | 3 filters active | 3 chip elements |
| `remove chip emits remove event` | Click chip X | `remove` event with filter key |
| `clear all emits clearAll event` | Click clear all | `clearAll` event emitted |
| `no chips when no filters` | Empty filters | No chip elements |

---

### `__tests__/widget_init.test.ts`

| Test | Scenario | Expected result |
|---|---|---|
| `parses window.NAAS config` | Valid JSON in window | Vue app mounts without error |
| `throws readable error on missing config` | `window.NAAS` undefined | Error with helpful message |
| `throws on invalid JSON in data attr` | Malformed JSON | Does not crash silently |
| `mounts Vue app on #naas_widget` | Valid config, element present | App mounted to DOM |
| `uses mount_point from config` | Custom mount point set | App mounted to correct selector |

---

## 4. Coverage targets

| Area | Target |
|---|---|
| `composables/useNuggetSearch.ts` | 95 % |
| `composables/useNuggetView.ts` | 95 % |
| `composables/useXapi.ts` | 90 % |
| `composables/useEntityResolvers.ts` | 90 % |
| `composables/useNuggetEnricher.ts` | 90 % |
| `composables/useNaasConfig.ts` | 100 % |
| `service/moodle-naas-api.service.ts` | 90 % |
| `components/NuggetSearchWidget.vue` | 85 % |
| `components/NuggetView.vue` | 85 % |
| `components/FilterChips.vue` | 100 % |
| `components/NuggetPost.vue` | 80 % |
| Other components | 70 % |
| **Overall** | **90 %** |

---

## 5. Implementation order

| Phase | Tasks | Effort |
|---|---|---|
| 1 — Infrastructure | Install packages, `vitest.config.ts`, MSW server + handlers, fixtures | 1 day |
| 2 — Config & service | `useNaasConfig.test.ts`, `moodle-naas-api.service.test.ts` | 1 day |
| 3 — Core composables | `useNuggetSearch.test.ts`, `useNuggetView.test.ts` | 2 days |
| 4 — Side composables | `useXapi.test.ts`, `useEntityResolvers.test.ts`, `useNuggetEnricher.test.ts` | 1.5 days |
| 5 — Key components | `NuggetSearchWidget.test.ts`, `NuggetView.test.ts` | 2 days |
| 6 — Small components | `FilterChips.test.ts`, `NuggetPost.test.ts`, etc. | 1 day |
| 7 — Bootstrap | `widget_init.test.ts` | 0.5 day |
| 8 — CI integration | Add Vitest step to GitHub Actions | 0.5 day |

**Total estimate: ~9.5 days**

---

## 6. Pre-requisite fixes before testing

These blockers must be addressed first or tests will be structurally impossible to write:

| # | Fix needed | Why |
|---|---|---|
| F1 | Add `AbortController` to `useNuggetSearch` | Required to test request cancellation |
| F2 | Validate `window.NAAS` at bootstrap | Required to test `useNaasConfig` error paths |
| F3 | Wrap `JSON.parse` in `widget_init.js` with try/catch | Required to test malformed config |
| F4 | Replace AMD polling with proper callback | Required to make service layer testable without timers |

---

## 7. CI integration (GitHub Actions)

```yaml
# .github/workflows/vitest.yml
- name: Install dependencies
  run: cd vue && npm ci

- name: Run Vitest
  run: cd vue && npm run test:coverage

- name: Upload coverage
  uses: codecov/codecov-action@v4
  with:
    files: vue/coverage/lcov.info
    flags: vue
```
