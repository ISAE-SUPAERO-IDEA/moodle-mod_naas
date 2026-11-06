# NaaS Plugin — Slow search filters

Why the filter panel feels long to load, and how to make it fast **without** a NaaS API change.

Status: `[ ]` todo · `[x]` done · `[~]` partial · `[-]` dropped

**Primary files:** `vue/src/components/NuggetSearchFilter.vue`, `NuggetSearchWidget.vue`, `composables/useNuggetSearch.ts`, `composables/useEntityResolvers.ts`, `composables/useNuggetEnricher.ts`, `service/moodle-naas-api.service.ts`

**Related:** [HASHED_INSERTION_CODE.md](HASHED_INSERTION_CODE.md) (picker UX), [ENHANCEMENTS_BACKLOG.md](ENHANCEMENTS_BACKLOG.md) (skeletons already landed)

---

## Symptom

Opening **Filters** (or waiting for the filter pills to fill) takes much longer than the nugget cards. Teachers sit on skeletons while the panel is still resolving names.

---

## What actually runs today

Two independent `useNuggetSearch()` instances, plus one webservice **per aggregation bucket**.

```
Teacher types / page loads
        │
        ├─ Widget.doSearch()
        │     └─ search_nuggets  →  enrich each card (get_person + get_domain per nugget)
        │
        └─ (when Filters panel is open, or on remount)
              Filter.watch(query) → search() AGAIN
                    └─ search_nuggets  →  enrichMany again (unused)
                    └─ for EVERY bucket:
                          related_domains → get_domain
                          producers       → get_structure
                          authors         → get_person
```

`NuggetSearchFilter` is mounted with `v-if="filtersOpen"`. Closing and reopening the panel **throws the component away** and runs the whole path again.

`searchNuggets` is **not** cached in the JS client (`useCache` is false). Vocabulary lookups are cached in PHP (24h) and in a JS `Map` after the first hit — the first open is still one Moodle ajax call per key.

---

## Cost model (why it feels “very long”)

| Work | Needed for filters? | Typical size | Cost |
|---|---|---|---|
| `search_nuggets` for the **grid** | Yes (cards) | 1 request | One NaaS search (already includes `aggregations` + `results_count`) |
| Second `search_nuggets` inside the filter | **No** | 1 request | Duplicate of the widget search |
| `enrichMany` on that second search | **No** | ~9 × (authors + domains) | Extra `get_person` / `get_domain` the filter never displays |
| Resolve **all** facet keys up front | Only labels | Tens to hundreds | 1 Moodle WS each (`get_domain`, `get_structure`, `get_person`) |
| `(docCount)` in the caption | Cosmetic | 0 extra requests | Already on each aggregation bucket |

The slow part is **not** drawing `(12)` next to a label. Counts ride on the search JSON. Removing them changes almost nothing.

The slow part is:

1. A **second full search** (and enrich) just to paint filters.
2. **N+1 vocabulary calls** for every author / domain / producer **before** the teacher opens a dropdown.
3. Doing (1) and (2) **again** every time the panel remounts or `fulltext` changes while it is open.

Example: 80 author buckets + 25 domains + 15 producers ≈ **120 extra ajax calls** on first filter open, each Moodle → (cache miss) → NaaS. Parallel `Promise.all` still saturates the browser and PHP workers.

Language, level, and type already use **local** lang strings. They do not need those lookups.

---

## Your ideas, judged

| Idea | Verdict | Why |
|---|---|---|
| Drop result **counts** on facet rows | Optional UX | `docCount` is already in the payload. Removing `(12)` does not cut requests. Fine if you want quieter labels. |
| Avoid **multiple** requests | **Do this** | Filter must not call `search()` itself. One search, owned by the widget, pass `aggregations` down. |
| Only request when a **select** is clicked | **Do this** (refined) | Keep the 8 filter **pills** immediately (names are local). Fetch bucket **labels** when that pill opens, not for all facets at once. |
| Hide `results_count` / “load more” | Unrelated | Grid pagination uses `results_count`. That is not why the filter panel is slow. |

---

## Better design (recommended)

Keep one search. Paint pills instantly. Resolve names lazily per open dropdown.

### F1 — One search owner

`NuggetSearchWidget` already receives `aggregations` on `doSearch()`. Pass them into the filter as a prop. Delete `useNuggetSearch()` + `watch(query)` + `load()` from `NuggetSearchFilter.vue`.

The filter becomes a **pure UI** over data the widget already has.

### F2 — Do not remount the panel

Use `v-show` (or keep the instance alive and only hide the backdrop) so opening Filters does not re-run resolution. First open pays once; later opens are instant.

### F3 — Lazy labels on pill click (your “request when select is clicked”)

On first render of a facet:

- Show the pill immediately (`Authors`, `Producers`, …).
- On first **open**, resolve captions for **that** facet’s keys only.
- Until then, show the raw key or a short skeleton **inside the dropdown**.

Language / level / type: no network, ever.

### F4 — Do not enrich cards on the aggregation path

`useNuggetSearch.search()` always calls `enrichMany`. If anything still searches only for facets, add `search(options, { enrich: false })`. After F1 this is only needed for the grid (cards still want author/domain names).

### F5 — Optional: freeze facets while typing

Today every debounced keystroke can rebuild aggregations (new search + new N+1). For beta, keep **facet buckets from the last “empty or applied-filter” search** and only refresh aggregations when the teacher **applies** a facet or clicks Search — not on every letter. Grid results can still update live.

---

## Alternatives (if F1–F3 are not enough)

| Approach | Gain | Cost |
|---|---|---|
| Batch WS `get_entities({ domains: [], people: [], structures: [] })` | One PHP/NaaS round-trip per facet | Needs a new Moodle function + NaaS bulk endpoint (or loop in PHP, still N NaaS calls but one browser call) |
| Ask NaaS search to return `label` on each bucket | Zero vocabulary calls | NaaS API change |
| Static allowlists for language/level/type only | Already mostly true | Does not help authors/producers/domains |
| Drop authors facet in the picker | Removes the fattest N+1 | Product decision |

A PHP batch that loops NaaS server-side is a good **phase 2** if lazy-per-pill is still slow on Authors (many keys). Phase 1 should stay in Vue.

---

## What not to do

- Do not add a third search “for counts only”.
- Do not resolve every bucket in `handleAggregations` just to sort by caption — sort by `key` until labels arrive, then re-sort that facet.
- Do not wait for all facets before showing the pill row.

---

## Target sequence after change

```
Page load
  └─ 1× search_nuggets
        ├─ paint 9 cards (enrich those 9 only)
        └─ keep aggregations in memory (keys + docCount)

Open Filters
  └─ paint 8 pills immediately (no network)

Open “Authors”
  └─ get_person only for author keys (cached next time)

Apply a facet
  └─ 1× search_nuggets (grid + updated aggregations)
```

---

## Implementation backlog

| # | Status | Title |
|---|--------|-------|
| F1 | [ ] | Pass `aggregations` from the widget; remove the filter’s own `search()` |
| F2 | [ ] | Keep filter instance mounted (`v-show` / no destroy on close) |
| F3 | [ ] | Resolve domain / producer / author labels on first open of that pill |
| F4 | [ ] | `search(..., { enrich })` so facet-only paths never call `enrichMany` |
| F5 | [ ] | Do not rebuild all facets on every keystroke (refresh on apply) |
| F6 | [ ] | Optional: drop `(docCount)` from captions — UX only |
| F7 | [ ] | Later: batch vocabulary WS if Authors is still slow |
| F8 | [ ] | Check: opening Filters twice does not repeat uncached NaaS calls |

---

## Validate

- [ ] First paint of the **grid** still one `mod_naas_search_nuggets` (Network tab).
- [ ] Opening Filters adds **zero** search calls.
- [ ] Opening one pill adds lookups **only** for that facet.
- [ ] Closing and reopening Filters adds no new lookups (JS cache + mounted instance).
- [ ] Applying a facet still updates the grid; wrong-code / empty search still works.
- [ ] Language / level / type never call `get_person` / `get_domain` / `get_structure`.

---

Measured from current Vue: `NuggetSearchFilter.load()` → `search()` + `handleAggregations()` `Promise.all` per bucket; widget `doSearch()` is a second `useNuggetSearch` store. Counts come from `b.docCount` in the search payload.
