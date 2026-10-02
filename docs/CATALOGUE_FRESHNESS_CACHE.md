# NaaS Plugin — Cached cards & catalogue freshness

Paint the catalogue with **zero blocking requests**, revalidate invisibly, and let the cache self-heal when NaaS changes.

Status: `[ ]` todo · `[x]` done · `[~]` partial · `[-]` dropped

> **Implemented in bundle `2026091609`.** Epics F1–F6 are built and covered by
> 50 PHPUnit tests (`catalogue_cache`, `search_cache`, `vocabulary_lookup`,
> `nugget_cache`, `naas_widget`) and 72 Vitest tests. F7 (TanStack) and F8 (push
> invalidation) remain open by design. Sections 1–5 describe the reasoning that
> led here and are kept as the design record; section 11 lists what changed
> versus the plan.
>
> Both blocking open questions are now answered from the NaaS sources
> (section 10): the `page_size=1` probe **is** valid, and the `producers`
> aggregate **is** capped at 10 buckets — which turns out to be a pre-existing
> ceiling on the landing page itself, fixable only in the NaaS contribution.

**Primary files:** `classes/catalogue_cache.php`, `classes/external/proxy_naas_api.php`, `db/caches.php`, `vue/src/composables/useNuggetSearch.ts`, `vue/src/composables/catalogueSnapshot.ts`, `vue/src/components/NuggetBrowseLanding.vue`

**Related:** [NUGGET_SELECTION_CACHE.md](../NUGGET_SELECTION_CACHE.md) (selected Nugget, folded in as Epic F5), [FILTER_LOADING_ANALYSIS.md](FILTER_LOADING_ANALYSIS.md) (N+1 vocabulary calls, duplicate search), [CACHE_SIMPLIFICATION.md](CACHE_SIMPLIFICATION.md) (review of what this plan produced: collapsing the four layers onto one catalogue stamp)

---

## 1. Verdict — is this already done?

| Ask | Status | Reality today |
|---|---|---|
| Cache Nugget name, images, everything a card needs | `[~]` | Cached for **one query only** (the unfiltered landing page, 9 cards), and the payload is **incomplete** — `resume` and resolved author names are missing |
| Producer cards: detect a new / removed producer, refresh counts | `[~]` | Membership and counts *do* refresh, as a side effect of always re-running the full landing search. But the cached producer **name / logo / banner** list is only ever written by *Test connection*, so a new producer has no visuals and a removed one is never evicted |
| Nugget list per producer or all Nuggets: check freshness, auto-update | `[ ]` | Not done. Only the landing query is cached. Every filtered or paged list is a blocking live fetch with a skeleton, and there is no revalidation protocol |

About a third of it exists, for exactly one query, and only after an admin has pressed **Test connection**.

---

## 2. What runs today

```
Teacher opens the activity form
   │
   ├─ PHP: naas_widget / output\widget
   │     └─ catalogue_cache::export_for_widget()   → window.NAAS.catalogue_snapshot
   │            (landing search: 9 slim items + aggregations, + producer list)
   │
   └─ Vue onMounted
         ├─ hydrateFromSnapshot()   → paints 9 cards instantly (no resume, no author names)
         ├─ facetAggregations = snapshot aggregations  → producer cards + counts paint instantly
         └─ doSearch()              → FULL live search_nuggets, unconditionally replaces everything
                └─ enrichMany()     → N+1 get_person / get_domain to fill author + domain names
```

Producer cards in `NuggetBrowseLanding` are built from `aggregations.producers.buckets` (id + count), then each key is resolved to visuals via `getStructureVisuals` → snapshot producers → `get_structure` → `producer_catalog_v3`.

Snapshot writes happen in exactly two places:

| Trigger | Writes `search` | Writes `producers` |
|---|---|---|
| `test_config` → `catalogue_cache::warm()` | yes | yes |
| Landing search → `catalogue_cache::remember_search()` | yes | **no** |

That asymmetry is the root of the producer staleness.

---

## 3. The cost model this plan optimises

Not all requests are equal, and the design follows from the gap between them.

| Path | Cost | Visible to the user? |
|---|---|---|
| Injected into the page at render (`window.NAAS`) | 0 requests | Never |
| Moodle AJAX → warm MUC | ~50–100 ms, no NaaS hop | Barely |
| Moodle AJAX → NaaS | ~0.5–2 s | Very |

**The goal is zero *blocking* requests, not zero requests.** A freshness check is itself a request; once you have paid the round-trip you may as well have fetched the data. So the rule everywhere below is: paint from cache synchronously, revalidate in the background, and swap only when something actually changed.

Eliminating the Moodle hop is not worth engineering. Eliminating the **NaaS hop from the user's critical path** is the entire point.

---

## 4. Gaps, precisely

**G1 — The cached card is not a complete card.** `slim_search()` stores `name`, `nugget_thumbnail_url`, `duration`, `license`, `authors`, `domains`, … but **not `resume`**, which `NuggetPost` renders as the description. `authors`/`domains` are raw opaque keys, so the author line stays blank until `enrichMany` finishes its N+1 lookups. The "instant" snapshot card is a thumbnail, a title, and two empty lines.

**G2 — Only the landing query is cached.** `is_landing_search()` rejects anything with a facet, a fulltext term, or `page > 0`. Clicking a producer, paging, or returning from a selection is always a cold blocking fetch.

**G3 — Revalidation is "throw it all away".** `doSearch()` overwrites `nuggets.value` and `facetAggregations` even when nothing changed, re-triggering `enrichMany` and re-rendering the grid. There is no comparison, so there is no cheap path.

**G4 — Producer visuals never self-heal.** A producer created since the last *Test connection* is absent from `snapshot.producers`, so each of its cards costs a `get_structure` round-trip. A removed producer stays forever. Renames hide behind the 24 h TTL on `structurelabel_*` and `producer_catalog_v3`.

**G5 — No scheduled warm.** `warm()` runs only from `test_config`, an admin action performed once at setup.

**G6 — The selected Nugget is never cached.** `get_nugget` always hits `/nuggets/{id}/default_version`. Specced in [NUGGET_SELECTION_CACHE.md](../NUGGET_SELECTION_CACHE.md), still `[ ]`.

---

## 5. Design

### 5.1 Producers page: zero requests, one invisible probe

**Producer membership and counts do not come from `/structures`.** There is no producer endpoint carrying nugget counts — `NuggetBrowseLanding` reads them from `aggregations.producers.buckets` on a *nugget* search. So a "check producers only" call against `/structures` would give stale membership and no counts at all.

The correct producers probe is a nugget search with `page_size=1`. Aggregations are computed over the whole match set, not the page, so one request returns every producer bucket, every count, and the Open Access count, with a one-item payload. No NaaS change needed. `/structures` drops back to a rare fallback for a producer whose visuals were never cached.

Sequence on form open:

```
render  → producers painted from window.NAAS      (0 requests, 0 ms)
mount   → probe: search page_size=1               (background, invisible)
        → producers_digest unchanged? do nothing
        → changed? reconcile + repaint the strip
```

No nugget list is fetched until the teacher enters a section or sets a filter — as specified.

### 5.2 Cache the whole search hit, not a trimmed card

Deferring About-modal fields to a later request would be a **regression**. `NuggetAboutModal` takes `:nugget="nugget"` as a prop and fetches nothing: description, prerequisites, learning outcomes, references and the in-brief panel all render from the search hit already in memory. Clicking About is instant today; splitting the payload would add a request and a spinner where none exists.

There is also nothing to save. Those fields arrive in the same search response whether we keep them or not, a hit is one text payload, and nine of them are tens of kB. `resume` — the biggest text field — is a front-card field anyway.

The split that does make sense is **by call, not by field**:

| Data | When |
|---|---|
| Everything in the search hit (card + About) | Cached with the list |
| Full `get_nugget` default_version document | On selection (Epic F5) |
| `preview_url` | On Preview click |
| Author bios / domain labels | Denormalized into the cached hit from the vocabulary MUC |

### 5.3 Per-query cache with stale-while-revalidate (fixes G2, G3)

Keyed by the canonical query rather than a single landing slot.

**Key:** `sha1(fingerprint | canonical_query)`, where `canonical_query` is the search options with keys sorted, array values sorted, `page` and `page_size` included, and the site `nql` filter appended. `fingerprint` is the existing `catalogue_cache::fingerprint()`, so a credential or tenant change invalidates everything at once.

**Value:** `{ fingerprint, cached_at, results_count, max_modification_date, id_digest, items[], aggregations{} }`, where `id_digest` is `sha1` of the ordered `nugget_id` list of that page.

**The freshness triple is `(results_count, id_digest, max_modification_date)`.** Because the same query is always compared against itself at the same page size, this needs no sorting support and no NaaS change:

| Change in NaaS | Caught by |
|---|---|
| Nugget added | `results_count`; the new Nugget's recent date also moves `max_modification_date` |
| Nugget deleted | `results_count`, and the page composition shifts → `id_digest` |
| Nugget edited (title, thumbnail, résumé) | `modification_date` → `max_modification_date` |
| Simultaneous add + delete (count unchanged) | `id_digest` and `max_modification_date` both change |

**Read path:**

1. `search_nuggets` returns `{payload, cache: {hit, cached_at, digest}}`. The Vue unwrap contract is unchanged — still peel `payload`.
2. Vue paints the cached page immediately: no skeleton, no `loading` flip.
3. The live search runs in the background. Triple identical → **do not reassign** `nuggets.value`; no re-render, no re-enrich. Different → swap the grid and enrich only the new keys.

An in-memory `Map` keyed by the same canonical query makes browse → producer → *Back to catalogue* free of any round-trip within one page session.

**Eviction.** MUC application stores have no LRU. Keep a bounded `search_index` (the N most recent query keys, N ≈ 64) beside the entries and drop the tail on write, so repeated fulltext typing cannot grow the store without bound. This index doubles as the input to the refresh task in §5.5.

### 5.4 Producer reconciliation (fixes G4)

`remember_search()` gains a producer diff instead of leaving `producers` untouched:

- key in the aggregations, absent from `snapshot.producers` → resolve visuals from `producer_catalog_v3`, append; if unknown there too, store a placeholder with the derived thumbnail/banner URLs and flag `needs_resolve`
- key in `snapshot.producers`, absent from the aggregations → remove (aggregations are authoritative — but see open question 2)
- store `count` per producer, so the landing paints counts from cache, and `seen_at` for debugging

`export_for_widget()` exposes a `producers_digest` (sorted `id:count` pairs, hashed). `NuggetBrowseLanding` repaints only when the digest changes — the same no-flicker rule as the grid.

### 5.5 Adaptive warm: refresh what is warm, don't prewarm what isn't (fixes G5)

Blind prewarming of every producer is affordable but wasteful: on a site where 3 of 30 producers are ever browsed, 27 lists are refetched nightly for nothing.

Instead the scheduled task **refreshes entries that already exist** in `search_index`. Cost equals the real working set and never exceeds it — day one that is one entry (the landing), and it converges on actual usage by itself.

Bounds, so the nightly cost is predictable:

- at most **the whole search-cache keyspace** (`search_cache::MAX_ENTRIES`, 64) per run, ordered most-recently-used first. The admin setting `naas_refresh_limit` can lower that; **0** means the same full pass
- skip anything not read in **30 days**, and drop it from the index
- purge `producer_catalog_v3` first so renames propagate
- on a cold site, seed **Open Access plus every producer on the landing** so the first clicks are already warm — the NaaS producers aggregate is capped at 10, so this stays a small set

For scale: a NaaS search is ~0.5–2 s, so 64 sequential refreshes is a couple of minutes of cron. One teacher opening the filter panel cold already costs ~120 calls ([FILTER_LOADING_ANALYSIS.md](FILTER_LOADING_ANALYSIS.md)).

### 5.6 Why not a real-time architecture

There is no subscription channel and the catalogue changes on the order of days. Revalidate-on-navigation plus a nightly refresh is the right fidelity. True real-time would require NaaS to push change events to Moodle (Epic F7) — tracked, not scheduled.

---

## 6. Backlog

### Epic F1 — Complete the cached card payload

- [x] F1.1 — Add `resume` to `catalogue_cache::slim_search()`, capped (~600 chars) to keep the injected snapshot small
- [x] F1.2 — `denormalize_vocabulary()` fills `authors_data` / `domains_data` from the `vocabulary_entries` MUC **only** — never a network call while building the cache; an unresolved key degrades exactly as today
- [x] F1.3 — `useNuggetSearch` skips `enrichMany` for items that already carry `authors_data` and `domains_data`
- [ ] F1.4 — Measure the injected `window.NAAS.catalogue_snapshot`; if it exceeds ~120 kB, drop `tags` and truncate `resume` harder. **Not measured** — needs a real catalogue; `resume` is capped at 600 chars and the vocabulary is deduplicated, so the growth term is bounded, but the number has not been taken

### Epic F2 — Producers page with zero blocking requests

- [x] F2.1 — Confirmed from the NaaS sources: the `nugget_search` page provider declares Elasticsearch `terms` aggregates, which ES computes over the whole query result set independently of `pageSize`. See section 10, question 1
- [x] F2.2 — `check_catalogue(courseId)` webservice: `page_size=1` landing search returning aggregations + `results_count` only
- [x] F2.3 — `producers_digest` in `export_for_widget()`; `NuggetBrowseLanding` repaints only on digest change
- [x] F2.4 — Remove the unconditional `doSearch()` from `onMounted`; the landing fetches **no** nugget list until a section or filter is chosen
- [~] F2.5 — A bucket with no cached row becomes a `needs_resolve` placeholder carrying URL-derived media links; the widget's existing `getStructureVisuals` path resolves the real name. **No write-back to the snapshot** — the name is re-resolved per page load until the nightly task re-warms

### Epic F3 — Per-query search cache

- [x] F3.1 — MUC area `search_results` in `db/caches.php` (application, TTL 7 days, `simplekeys`, `simpledata`)
- [x] F3.2 — `classes/search_cache.php`: `canonical_key()`, `get()`, `store()`, `digest()`, `prune()`
- [x] F3.3 — `search_nuggets` reads the cache and returns `{payload, cache:{hit, cached_at, digest}}`
- [x] F3.4 — `search_nuggets` writes **every** result; keep `is_landing_search` only for choosing what to inject into the widget
- [x] F3.5 — Bounded `search_index` with tail eviction and `last_read_at` per entry
- [x] F3.6 — Purge on fingerprint mismatch and from the settings page

### Epic F4 — Stale-while-revalidate in the widget

- [x] F4.1 — In-memory `Map<canonicalKey, SearchResult>` in `useNuggetSearch`; `search()` paints from it synchronously when present
- [x] F4.2 — Compare the freshness triple; when unchanged, skip reassigning `nuggets` / `searchResult` / `facetAggregations`
- [x] F4.3 — Skeleton only on a true cold miss; a revalidating grid shows a discreet "refreshing" affordance
- [x] F4.4 — `backToBrowse()` and `clearSelection()` reuse the in-memory entry instead of re-searching

### Epic F5 — Producer reconciliation & adaptive refresh task

- [x] F5.1 — `remember_search()` diffs `aggregations.producers.buckets` against `snapshot.producers`: add new, drop missing, refresh `count`
- [~] F5.2 — Placeholder fallback done. Reconciliation deliberately makes **no** `producer_catalog_v3` lookup: it runs inside a user-facing request and must stay free of network calls, so resolution is left to the lazy widget path and the nightly re-warm
- [x] F5.3 — `db/tasks.php` + `classes/task/refresh_catalogue.php`, daily
- [x] F5.4 — Task refreshes existing `search_index` entries only: whole keyspace (64) by default, most-recently-used first, skipping entries unread for 30 days
- [x] F5.5 — First run seeds Open Access and every producer on the landing
- [x] F5.6 — Task purges `producer_catalog_v3` before re-warming so renames propagate
- [x] F5.7 — Admin setting for the per-run cap, defaulting to the whole cache (64). 0 means the same

### Epic F6 — Selected Nugget cache

From [NUGGET_SELECTION_CACHE.md](../NUGGET_SELECTION_CACHE.md), cheaper to build once F3 provides the store and the date comparison.

- [x] F6.1 — Seed the selected card from `search_cache` / snapshot items when the id matches, so *Select* and form reopen paint with no round-trip
- [x] F6.2 — `get_nugget` compares cached `modification_date` / `version_id` before returning; full `GET` only when stale or absent
- [x] F6.3 — Background swap if the live document is newer

### Epic F7 — TanStack Query evaluation *(costed follow-up, decide after F4)*

Worth it only if the measured DX and bug-fix win beats ~13 kB gzipped — note the team removed Pinia to save ~12 kB.

**What it would replace:** F4.1 and F4.2 largely disappear. `staleTime` gives SWR directly; query keys are the canonical key from F3.2; **structural sharing** preserves object identity when a refetch deep-equals the cache, so Vue does not re-render — the F4.2 requirement, for free. Request deduplication also fixes the duplicate search documented in [FILTER_LOADING_ANALYSIS.md](FILTER_LOADING_ANALYSIS.md), and `placeholderData: keepPreviousData` gives flicker-free paging.

**What it does not replace:** the MUC layer. TanStack is per-browser; MUC is shared across every teacher and is what actually shields NaaS. Both are needed regardless.

**The blocker to design around:** the activity form is **not an SPA**. Each form open is a fresh JS context, so an in-memory query cache is empty at precisely the moment that matters.

- [ ] F7.1 — Spike on a branch: `@tanstack/vue-query` behind the existing `INaasApiService`, measuring bundle delta and lines removed
- [ ] F7.2 — `persistQueryClient` + IndexedDB persister, or the spike is pointless — without it TanStack contributes nothing to first paint
- [ ] F7.3 — Seed `initialData` from `window.NAAS.catalogue_snapshot` so the zero-request paint of F2 survives
- [ ] F7.4 — Confirm the persisted client is keyed by the connection fingerprint, so a credential change cannot serve another tenant's cards
- [ ] F7.5 — Go / no-go against F4, on bundle delta and net code removed

### Epic F8 — Push invalidation *(parked, needs a NaaS change)*

- [ ] F8.1 — NaaS webhook on Nugget/structure change → Moodle endpoint purging the affected cache keys. The only design with true real-time correctness and no polling.

---

## 7. Acceptance criteria

- [ ] Opening the activity form paints the producers page with **zero** network requests and no skeleton
- [ ] No nugget list is fetched until a section is entered or a filter is set
- [ ] Reopening the form shows complete cards — thumbnail, title, **description**, **author names** — with no `get_person` / `get_domain` calls
- [ ] Clicking About is instant, with no request, exactly as today
- [ ] Entering a producer section a second time paints instantly from cache, then silently confirms
- [ ] Editing a Nugget title in NaaS → the new title appears on the next visit to that section, with no manual purge
- [ ] Deleting a Nugget → its card disappears from the cached list on the next visit
- [ ] Adding a Nugget under a producer → that producer's count increments on the next form open
- [ ] Creating a producer in NaaS → its card appears with real acronym and logo, no *Test connection* required
- [ ] Removing a producer → its card disappears and it is evicted from `snapshot.producers`
- [ ] When nothing changed, the grid does not re-render and the network tab shows one probe, not a search plus N vocabulary calls
- [x] The nightly task refreshes the whole search cache by default (64) and touches no list unread for 30 days
- [ ] Changing endpoint or credentials invalidates every cached list, producer and card

---

## 8. Tests

Written and green: 50 PHPUnit, 71 Vitest. Behat is still outstanding.

**PHP** (`tests/search_cache_test.php`, extending `tests/catalogue_cache_test.php`)

- [x] canonical key is stable across key order and array order, and differs on `page` / `page_size`
- [x] fingerprint mismatch ignores a stored entry
- [x] digest changes on add, on delete, and on an edit that only moves `modification_date`
- [x] `search_index` evicts the oldest entry past the bound and records `last_read_at`
- [x] `remember_search` adds a producer present in the aggregations but absent from the snapshot, and drops one absent from them
- [x] `denormalize_vocabulary` fills names from a warm MUC and issues no HTTP call on a cold one
- [~] the cap, the 30-day cutoff and the empty-index case are covered on `search_cache::refreshable()`, which is the mechanism the task drives. `refresh_catalogue::execute()` itself is untested — it needs an injectable client

**Vue** (`useNuggetSearch.spec.ts`, `catalogueSnapshot.spec.ts`)

- [x] an identical live result does not reassign `nuggets` (assert object identity)
- [x] a changed digest swaps the grid and re-enriches only new keys
- [x] the in-memory map serves a repeated query without calling the service
- [x] cards carrying `authors_data` skip `enrichMany`
- [ ] mounting the landing issues the probe but **no** `search_nuggets` — needs a component mount test for `NuggetSearchWidget`, which has no harness yet

- [ ] **Behat** — still to add, see [BEHAT_PLAN.md](BEHAT_PLAN.md): landing paints with no skeleton; second visit to a producer section is instant.

---

## 9. Sequencing

| Order | Epic | Size | Why here |
|---:|---|---|---|
| 1 | F1 — complete card payload | S | Standalone; fixes the visible empty description today, no new machinery |
| 2 | F2 — zero-request producers page | M | Biggest perceived win; depends only on F1 and the page_size=1 check |
| 3 | F3 — per-query cache | M | The store everything else builds on |
| 4 | F4 — stale-while-revalidate | M | Needs F3 |
| 5 | F5 — reconciliation + adaptive task | M | Independent of F3/F4; can land in parallel |
| 6 | F6 — selected Nugget | S | Cheap once F3 exists |
| 7 | F7 — TanStack spike | S | Decide against a working F4 baseline, not in the abstract |
| — | F8 — push invalidation | — | Parked |

F1, F2 and F5 are each shippable alone and each give a visible win.

---

## 10. Open questions

Questions 1 and 2 are answered from the NaaS sources, not by inference. The
search endpoint is a thin proxy: `NuggetsView.search()` calls
`getNuggetSearchBuilder(params).search()`, which builds
`/api/v1/search/pp/nugget_search/execute?…` against Nuxeo
(`AbstractSearchBuilder.getExecutionURL`), mapping `page_size` → `pageSize` and
`page` → `currentPageIndex`. The `nugget_search` page provider is declared in
`nuxeo_naas-core/src/main/resources/OSGI-INF/search-contrib.xml`.

1. **Does `/nuggets/search?page_size=1` return complete aggregations?** **Yes — confirmed.** Aggregations are Elasticsearch `terms` aggregates declared on the page provider, so ES computes them over the whole query result set; `pageSize` becomes the ES hit `size` and has no bearing on them. The provider class is `unit.naas.Search.NaasElasticSearchNxqlPageProvider`, a subclass of Nuxeo's stock `ElasticSearchNxqlPageProvider` whose only override injects `extraNxql` into the `WHERE` clause — it never touches aggregation handling. **`check_catalogue()` is correct as built; no change needed.**
2. **Do the `producers` buckets return all producers, or only the top N?** **Only the top 10 — confirmed.** The `producers` aggregate carries `<property name="size">10</property>`. The distinction is deliberate in that file: `related_domains` and `authors` use `size` `0`, `producers` and the rest use `10`. This makes the merge-not-replace behaviour of `reconcile_producers()` **required**, not merely cautious — see the ceiling note below, which is a separate pre-existing issue.
3. The snapshot is site-wide and injected for anyone with `mod/naas:addinstance`. Confirm no per-course visibility rule on Nuggets that a shared cache could leak across courses. **Unchanged by this work** — the new caches are keyed by connection fingerprint exactly like the existing snapshot, so they neither add nor remove exposure.
4. ~~Is 40 refreshes per night the right cap?~~ **No — raised to the whole cache.** Default is `search_cache::MAX_ENTRIES` (64). 0 in the setting means the same. A cold run seeds Open Access and every landing producer, not only the top 5.

### Pre-existing: the landing can never show more than 10 producers

Unrelated to caching, but found while answering question 2 and worth its own
ticket. `NuggetBrowseLanding.vue` builds the producer strip **only** from
`props.aggregations.producers.buckets`. With the aggregate capped at 10, a
catalogue with more than ten producers can never surface the eleventh on the
landing — no amount of caching changes that, because the cache faithfully
reproduces a truncated bucket list.

Two consequences worth noting:

- `catalogue_cache::warm()` pages through `/structures` for up to
  `PRODUCER_PAGE_SIZE × PRODUCER_MAX_PAGES` = 500 producers. Only ten can ever
  reach the strip; the rest serve the `get_structure` fast path and filter
  labels, which is still useful but far more than the landing needs.
- A producer that falls out of the top ten stops having its `seen_at` refreshed
  and is dropped after `PRODUCER_MAX_AGE`. It is re-added without a `seen_at` by
  the next `warm()`, so this self-heals nightly and costs at most one live
  `get_structure`.

**The fix is in the NaaS contribution, not the plugin:** set the `producers`
aggregate `size` to `0` in `search-contrib.xml`, as `authors` and
`related_domains` already do. Nothing in this plugin needs to change to benefit
— `reconcile_producers()` and `producers_digest()` already handle an
arbitrary-length bucket list.

---

## 11. Where the build differs from the plan

| Plan said | Built instead | Why |
|---|---|---|
| F1.2 `denormalize_vocabulary()` embeds `authors_data` on each item | A deduplicated `vocabulary` side table (`{persons, domains}`) that the widget joins onto each item's key arrays | An author on nine cards was stored nine times, bios included. The side table stores them once, which is what made shipping full bios affordable — and bios are what keep the About modal instant |
| F3.3 returns `{payload, cache:{…}}` | Cache metadata rides *inside* the payload as `_cache` | `unwrapNaasPayload` peels `payload` envelopes and would have discarded a `cache` sibling. No client change was needed this way |
| F4.1 in-memory map, F4.2 compare digests | Same, plus a two-call protocol: `mode=cache_first` then `mode=revalidate`, and the second call is **skipped** when the first reports `hit: false` | A cold key is already a live call; revalidating it would double every first fetch |
| F4.4 `backToBrowse()` reuses the in-memory entry | `refreshResults()` issues **no** search at all when the view is the catalogue landing | Clearing the last filter returns to the producer strip, which renders no grid. The old code fetched a list nobody could see |
| F6.2 compares dates before a full `GET` | `nugget_cache` + the same `cache_first` / `revalidate` protocol | There is no NaaS endpoint that returns a document's date more cheaply than the document, so the comparison has to happen *after* a live call. Serving the cached copy first and comparing in the background gets the same instant paint without inventing a probe |

**One bug caught in review, worth recording:** `silentUntilLive` suppressed the loading state after snapshot hydration, on the assumption that the next search was the same landing query. Once the landing stopped searching eagerly, the next search became a *producer* query — so the flag would have suppressed the skeleton and left the landing's cards on screen under a producer heading. The flag is gone; the snapshot is now filed in the in-memory map under the landing key instead, which makes "don't flash" a property of matching queries rather than of timing. `useNuggetSearch.spec.ts` covers both directions.
