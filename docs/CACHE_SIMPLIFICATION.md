# NaaS Plugin — Simplifying cache management

Deep analysis of the caching built in bundles `2026091607`–`2026091609`, and how to reach the same UX with **one freshness concept, one store abstraction, and roughly a quarter of the requests**.

Status: steps 1–4 implemented in bundle `2026091710` · steps 5–6 (store collapse) still open

**Reviewed files:** `classes/catalogue_cache.php`, `classes/catalogue_stamp.php`, `classes/naas_payload.php`, `classes/search_cache.php`, `classes/nugget_cache.php`, `classes/vocabulary_lookup.php`, `classes/task/refresh_catalogue.php`, `classes/external/proxy_naas_api.php`, `db/caches.php`, `vue/src/composables/useNuggetSearch.ts`, `catalogueSnapshot.ts`, `vue/src/service/moodle-naas-api.service.ts`, `NuggetSearchWidget.vue`, `NuggetSearchFilter.vue`

**Related:** [CATALOGUE_FRESHNESS_CACHE.md](CATALOGUE_FRESHNESS_CACHE.md) (the design that produced the current code), [FILTER_LOADING_ANALYSIS.md](FILTER_LOADING_ANALYSIS.md)

---

## 1. Summary

The current caching works and the UX goal is largely met: the landing paints with zero blocking requests. But it got there by adding **six cache layers with three different freshness models**, and it pays for freshness **per query, repeatedly**, when one catalogue-wide answer would do.

Three findings drive everything below.

**Finding 1 — every warm read costs a second request.** `search_nuggets` in `cache_first` mode answers from MUC without touching NaaS, then the widget immediately calls again in `revalidate` mode, which *always* hits NaaS. So a cache hit costs two Moodle calls and one NaaS call — exactly what a cache hit was supposed to avoid. Worse, this repeats for every section, every time, for the whole session.

**Finding 2 — the probe that could replace all of it already exists, but is too weak and used for the wrong thing.** `check_catalogue` already spends one NaaS hit to fetch `results_count` plus every aggregation over the whole match set. It only uses that to refresh the producer strip. It returns no modification stamp, so it cannot answer "is anything I cached still valid?".

**Finding 3 — NaaS supports sorting, so a complete catalogue stamp costs one hit.** `AbstractSearchBuilder::addMetaParameters()` declares `sort_by` (mapped through `ToParamInputModifier` to the Nuxeo attribute) and `sort_order`, and `NuggetSearchBuilder` declares `modification_date` → `naas_core:modified`. This answers the question [CATALOGUE_FRESHNESS_CACHE.md](CATALOGUE_FRESHNESS_CACHE.md) §10 left open and gated Epic F6 on.

```
GET /nuggets/search?is_default_version=true&page_size=1
                   &sort_by=modification_date&sort_order=DESC
→ items[0].modification_date  = newest document in the catalogue
→ results_count               = size of the whole match set
→ aggregations                = every producer, licence, language, level bucket
```

One response, one hit, and it is a **sound** change detector for the entire catalogue (§5).

---

## 2. What exists today

```
          ┌─ window.NAAS.catalogue_snapshot ── injected, 0 requests
          │     (landing cards + producers + vocabulary + producers_digest)
 BROWSER ─┤
          ├─ moodle-naas-api.service.ts  Map            ← client cache #1
          └─ useNuggetSearch.memory      Map (30 pages) ← client cache #2
                          │
                          │  mod_naas_search_nuggets (mode: cache_first | revalidate)
                          │  mod_naas_get_nugget     (mode: cache_first | revalidate)
                          │  mod_naas_check_catalogue
                          │  mod_naas_get_person / get_domain / get_structure
                          ▼
 MOODLE ──┬─ catalogue_snapshot  MUC  (30 d)  one slot,  written by warm() + remember_search()
          │                                              + remember_aggregations()
          ├─ search_results      MUC  (7 d)   64 entries + MRU index
          ├─ nugget_documents    MUC  (7 d)   per nugget
          └─ vocabulary_entries  MUC  (24 h)  person_* / domain_* / structurelabel_*
                                              + producer_catalog_v3
                          ▼
                       NaaS API
```

Four MUC areas, two in-browser caches, one injected blob.

### Three freshness models

| Layer | Freshness rule |
|---|---|
| `vocabulary_entries` | 24 h TTL, no revalidation |
| `search_results` | triple `(results_count, id_digest, max_modification_date)`, revalidated per query by the widget |
| `nugget_documents` | pair `(modification_date, version_id)`, revalidated per nugget by the widget |
| `catalogue_snapshot` | `producers_digest`, revalidated by `check_catalogue` |

Four rules, four comparison helpers, four sets of tests.

### Duplication inventory

| Duplicated thing | Copies | Where |
|---|---:|---|
| Peel the `{payload:…}` envelope | 5 | `search_cache::decode_search`, `nugget_cache::decode`, `vocabulary_lookup::unwrap`, `catalogue_cache::unwrap_payload`, `unwrapNaasPayload.ts` |
| `fingerprint` guard + JSON encode/decode + `get`/`store`/`purge` | 3 | `search_cache`, `nugget_cache`, `catalogue_cache` |
| `max_modification_date()` | 2 | `search_cache`, `catalogue_cache` |
| Producer identity/visual data | 3 stores | `catalogue_snapshot.producers`, `producer_catalog_v3`, `structurelabel_*` |
| Canonical query normalisation | 2 (by necessity) | `search_cache::canonical_query` (PHP) + `queryKey()` (TS) |
| Snapshot writers | 3 | `warm()`, `remember_search()`, `remember_aggregations()` |

---

## 3. Measured request budget

Counted from the code paths, per user action. "NaaS" is the expensive hop (~0.5–2 s); "WS" is Moodle AJAX (~50–100 ms warm).

| Action | WS | NaaS | Notes |
|---|---:|---:|---|
| Open form, landing | 1 | 1 | `check_catalogue`; cards come from the injected snapshot |
| Enter a section, cold in MUC | 1 | 1 | `cache_first` misses, goes live |
| Enter a section, warm in MUC | **2** | **1** | hit, then a mandatory `revalidate` |
| Re-enter that section later in the session | **1** | **1** | memory paints it, but it revalidates *again* |
| Open the filters panel | **O(buckets)** | 0–O(buckets) | `prefetchNetworkLabels()`, one call per bucket |
| Reopen a form with a saved nugget | **2** | **1** | `cache_first` + `revalidate`; no catalogue check on this path |

**A representative session** — open the form, browse six producer sections, open the filters panel once, select a nugget:

```
  1  check_catalogue                     1 NaaS
 12  six warm sections (2 each)          6 NaaS
 ~20  filter panel labels                0–20 NaaS
  2  selection                           1 NaaS
────
 ~35 WS                                  ~8 NaaS
```

On a catalogue where **nothing changed**, every one of those 8 NaaS hits returns data identical to what Moodle already held.

---

## 4. The five structural problems

**S1 — Freshness is asked per query instead of once.** Revalidation is scoped to a cache key, so its cost scales with how much the teacher browses. Nothing carries the answer across queries, and nothing remembers within a session that the question was already answered.

**S2 — `check_catalogue` cannot gate anything.** It returns `producers_digest`, `producers`, `aggregations`, `results_count` — no modification stamp. It is also uncached (a NaaS hit on every form open) and skipped entirely when a nugget is already selected.

**S3 — Facet labels are the last true N+1.** `vocabulary_lookup` denormalises author and domain names onto *cards*, but the filter panel still resolves every bucket label over the wire, one call per bucket, through `getDomainLabel` / `getPersonName` / `getStructureAcronym`. Yet `check_catalogue` already returns exactly the bucket list that needs labelling.

**S4 — `catalogue_snapshot` is a fourth store for data the other stores already hold.** The landing is just the canonical query `{page: 0, page_size: 9, is_default_version: true}` — an ordinary `search_results` entry. Keeping a separate area forces `is_landing_search()`, a second item-slimming path, three writers, and the `warm()`/`remember_search()` asymmetry that caused the original producer staleness.

**S5 — Two client caches, one of them harmful.** `moodle-naas-api.service.ts` memoises by `{method, args}` with `args` including `mode`, and `getNugget` passes `useCache = true`. So a `revalidate` response is cached for the page's lifetime: the second revalidation of the same nugget returns the first answer. It is latent today (one revalidation per page open) but it is a trap, and it duplicates `useNuggetSearch.memory`.

---

## 5. The simplification: one stamp

### 5.1 Definition

```
stamp = (results_count, newest_modification_date)   over the unfiltered catalogue
                                                    (with the site nql filter applied)
```

Obtained from the single sorted probe in §1.

### 5.2 Why it is sound

Nuxeo sets `naas_core:modified` to *now* on every write, so:

| Change in NaaS | Effect on the stamp |
|---|---|
| Nugget edited | `newest` becomes now → **moves** |
| Nugget added | `newest` becomes now, `results_count` +1 → **moves** |
| Nugget deleted | `results_count` −1 → **moves** |
| Add + delete in the same window | `results_count` unchanged, but `newest` becomes now → **moves** |
| Nothing | **unchanged** |

Therefore **stamp unchanged ⇒ no add, no delete, no edit anywhere in the catalogue ⇒ every cached page and every cached document is still valid.** One comparison authorises skipping *all* revalidation.

The converse is deliberately weak: a moved stamp says "something changed somewhere", not what. That is enough — revalidate only what is on screen, lazily.

### 5.3 What it replaces

| Today | With the stamp |
|---|---|
| `search_cache` triple per query | still stored, but only *compared* when the stamp moved |
| `nugget_cache` pair per document | same |
| `producers_digest` | the aggregation hash rides along on the same probe |
| `revalidate` mode called per section | called only after the stamp moved, only for the visible query |
| Per-query revalidation on every session re-entry | never; the session-level stamp already answered |

### 5.4 Protocol

```
form open
  └─ mod_naas_catalogue_stamp            1 WS, 1 NaaS
       → { stamp, aggregations, labels, producers }
       │
       ├─ stamp == stamp stored with the caches?
       │      YES → every cached page is authoritative for this session.
       │             Sections paint from MUC/memory with NO revalidation.
       │      NO  → store the new stamp, mark caches "suspect".
       │             The first read of each query revalidates once, then is trusted.
       │
       └─ aggregations + labels + producers hydrate the landing and the filter panel
```

The stamp response is itself cached in MUC for a short window (say 60 s) so a teacher opening three activity forms in a row spends one NaaS hit, not three.

---

## 6. Target architecture

### 6.1 Collapse the stores

| Now | Target |
|---|---|
| `catalogue_snapshot` (area + 3 writers + `is_landing_search`) | **deleted.** The injected blob is *rendered* from the `search_results` entry for the landing query plus the producer directory |
| `search_results` | kept, unchanged in shape |
| `nugget_documents` | kept |
| `vocabulary_entries` (person/domain/structurelabel/producer_catalog) | split: `vocabulary` (person/domain) and a single-slot **producer directory** |
| `producer_catalog_v3`, `structurelabel_*` | **deleted**, folded into the producer directory |

Three areas instead of four, and the awkward one — the single-slot snapshot written from three places — disappears. `export_for_widget()` becomes a pure projection with no store behind it, which also means the injected blob can never disagree with what `search_nuggets` serves.

### 6.2 Collapse the code

- **`mod_naas\naas_payload::unwrap()`** — one envelope peeler. Removes four PHP copies (~80 lines).
- **`mod_naas\cache\entry`** — one small base holding `key()`, the fingerprint guard, JSON encode/decode, `get`/`store`/`purge`. `search_cache`, `nugget_cache` and the producer directory become thin subclasses (~120 lines removed, and one place to get the fingerprint check right).
- **One `stamp` value object** with `equals()`, in PHP and TS. Replaces the triple helper, the pair helper and the producers digest as three separate concepts.
- **Delete the service-level client `Map`** (§S5). `useNuggetSearch.memory` stays as the only client cache.
- **Delete `prefetchNetworkLabels()`, `resolvedLabels`, `resolvingNames`** from `NuggetSearchFilter.vue` — labels arrive with the stamp response (~60 lines and all the bookkeeping).

### 6.3 Labels ride on the stamp

`check_catalogue` already returns every bucket. Extend it to resolve those bucket labels server-side from the vocabulary MUC — the same read-only, never-blocking `vocabulary_lookup` path used for cards — and return:

```
{ stamp, aggregations, labels: { producers: {...}, related_domains: {...}, authors: {...} }, producers }
```

Cold keys are simply absent, and the widget keeps its existing per-key fallback. So this is a pure win with no new failure mode: O(buckets) requests become 0.

---

## 7. Request budget after

Same representative session — open the form, six sections, filter panel, selection:

| Action | Now (WS / NaaS) | Target (WS / NaaS) |
|---|---:|---:|
| Open form, landing | 1 / 1 | 1 / 1 |
| Six warm sections | 12 / 6 | 6 / 0 |
| Re-entering a visited section | 1 / 1 each | 0 / 0 |
| Filter panel | ~20 / 0–20 | 0 / 0 |
| Selection | 2 / 1 | 1 / 0 |
| **Total** | **~35 / ~8** | **~8 / 1** |

On an **unchanged** catalogue a whole browsing session costs **one NaaS hit**. When the stamp has moved, it degrades to one extra revalidation per query actually visited — never more than today.

---

## 8. Migration, smallest first

Each step is shippable and independently valuable.

| # | Step | Removes | Risk |
|---:|---|---|---|
| 1 | `naas_payload::unwrap()`, replace the four copies | ~80 l | none, pure refactor | `[x]` |
| 2 | Delete the service-level client `Map` for `getNugget`; keep it for vocabulary | latent bug | low | `[x]` |
| 3 | Labels on the `check_catalogue` response; seed the panel from them | ~60 l of N+1 | low — per-key fallback stays | `[x]` |
| 4 | Add `sort_by`/`sort_order` to the probe, return the stamp, store it with each cache write; gate revalidation on it | the per-query revalidation storm | **medium** — needs the NaaS check in §9 | `[x]` |
| 5 | `cache\entry` base; rebase `search_cache` / `nugget_cache` / directory on it | ~120 l | low | `[ ]` |
| 6 | Delete the `catalogue_snapshot` area; render the blob from `search_results` + directory; drop `is_landing_search`, `remember_search`, `remember_aggregations` | ~200 l + a whole area | medium — touches the injected payload | `[ ]` |

Steps 1–3 are safe and immediate. Step 4 is the one that buys the request reduction. Steps 5–6 are the readability payoff.

---

## 9. What must be verified first

1. **Sorting actually works end to end.** The builder declares `sort_by`/`sort_order` and `modification_date` → `naas_core:modified`, but Nuxeo also needs that field sortable in the page-provider contribution (`search-contrib.xml`). Confirm with one manual request before building step 4; if sorting is rejected, the stamp falls back to `(results_count, aggregation_hash)`, which catches adds and deletes but **not edits** — in that case keep per-query revalidation for edits and still take the win on the rest.
2. **`results_count` is the match-set size, not the page size,** when `page_size=1`. The existing `check_catalogue` already assumes this for aggregations; confirm it for the count.
3. **The catalogue is identical for every user.** Search is proxied with the site credentials (`naas_client($config)`), so a site-wide stamp is legitimate. Re-confirm no per-course or per-user filter is ever applied, or the stamp must be keyed accordingly.
4. **The `producers` aggregate is capped at 10 buckets** (already noted in [CATALOGUE_FRESHNESS_CACHE.md](CATALOGUE_FRESHNESS_CACHE.md) §10). Unrelated to caching, but it bounds the landing and the seeding in `refresh_catalogue`, and it means an aggregation-based stamp cannot see producers beyond the cap.

---

## 10. Tests

**Add**

- stamp moves on add, on delete, on edit, and on add+delete in the same window; unchanged when nothing happened
- an unchanged stamp suppresses revalidation entirely across several queries
- a moved stamp triggers exactly one revalidation per visited query, not per read
- the stamp response is served from MUC inside the short window
- `naas_payload::unwrap()` handles every envelope the four old copies did (port their cases)
- bucket labels resolve from a warm MUC and are absent, not blocking, from a cold one
- the injected blob and `search_nuggets` return the same items for the landing query

**Retire**

- the three separate digest-comparison suites, replaced by one `stamp` suite
- `is_landing_search` cases
- `remember_search` / `remember_aggregations` cases

---

## 11. Open questions

1. Should the stamp be per-`nql`-filter? It is today by construction (the fingerprint includes `naas_filter`), but if per-course filtering is ever added the stamp key must follow.
2. Is a 60 s stamp window right? Longer means fewer hits and a slower reaction to an edit made seconds ago by the same person; shorter costs a hit per form open.
3. Should `refresh_catalogue` also refresh the stamp, so the first teacher of the day finds it warm? Cheap, and it makes the nightly task the only thing that ever pays a cold probe.
4. Worth surfacing "catalogue last checked at …" to admins? The stamp makes it free, and it turns cache behaviour from invisible into diagnosable.
