# NaaS Plugin — Administration Page UX Refresh

Refresh the **Site administration → Plugins → Activity modules → Nugget** page so a Moodle admin can configure the plugin without guessing labels, mixing dangerous options with everyday ones, or testing a connection before credentials are saved.

This document is the task list **and** the validation checklist. Tick items in the summary table as they land; use the per-task **Validate** sections before calling a story done.

Status: `[ ]` todo · `[x]` done · `[-]` dropped

**Page:** `/admin/settings.php?section=modsettingnaas`
**Primary files:** `settings.php`, `lang/en/naas.php`, `amd/src/test_connection.js`
**Related:** [OUTPUT_API_MIGRATION_BACKLOG.md](OUTPUT_API_MIGRATION_BACKLOG.md) EPIC-5 (Mustache test-connection widget), [QUALITY_PHP.md](QUALITY_PHP.md) L4 (grouping), [SECURITY_HARDENING_BACKLOG.md](SECURITY_HARDENING_BACKLOG.md) S4 (credentials)

---

## Constraint

This is a Moodle **admin settings** page, not a Vue screen. Stay inside `admin_setting_*` (headings, help icons, checkboxes, password-unmask, textareas) plus one custom setting for the connection test. Do not rebuild the page as a standalone SPA. Theme, layout grid, Save button, and breadcrumbs stay Moodle’s.

---

## Current state (audit)

Today the page is a single flat list. Connection test sits **above** the fields it depends on. Required credentials sit next to CSS, NQL filter, SSL bypass, and privacy toggles with almost no grouping.

| Setting | Type | Default today | Problem |
|---|---|---|---|
| Test connection | Heading + `<a href="#">` + empty result div | — | First thing on the page; tests **saved** config, not the form; no loading state; generic “Success!” / “Failed!” |
| NaaS settings (heading) | Heading | Vague “see the API docs” | Mixes setup, appearance, search, and developer options |
| API endpoint | Text | `https://api.naas-edu.eu/api` | Help is “Enter the NaaS API endpoint” |
| API user | Text | Real OER username shipped in source | Looks already configured; secret-ish value in git |
| API structure | Text | Real structure UUID | Label says “structure”; README says “institute” |
| API password | Password unmask | Real password in source | Credential in git; field looks pre-filled on a fresh install |
| Timeout | Integer | `10` | Advanced; sits in the main list |
| Disable SSL verification | Checkbox | Off | Dangerous production toggle next to everyday fields |
| Extra CSS | Textarea | Empty | Typo in help (“ressources)”); no example; no note that it is injected into Nugget LTI |
| Search filter | Textarea | Empty | “A query to filter search results” — no NQL hint |
| Feedback | Checkbox | On | Buried; unclear that this is the learner rating prompt |
| Privacy (heading) | Heading | Copy typos / double space | Defaults both collect name and email (on) |
| Collect emails | Checkbox | On | Privacy-sensitive default is the least private option |
| Collect names | Checkbox | On | Trailing period in the label; inconsistent with sibling |

Other friction:

- Test button is a link (`<a href="#">`), not a button; result region has no `aria-live`.
- Button does not disable while the request runs; double-clicks fire two tests.
- `html_writer` is inlined into a heading description (fragile, not theme-overridable). See EPIC-5.
- `$string['pluginadministration']` is empty.
- English copy is uneven (structure vs institute, tautological help, typos).

---

## Target information architecture

Order the page as an admin would actually work: connect → prove it works → decide what learners see → decide what data leaves Moodle → only then advanced knobs.

```
1. About this plugin          short intro + link to NaaS docs / how to get institute keys
2. Connection                 endpoint, user, password, institute ID
3. Test connection            after the fields, with status, loading, and human errors
4. Privacy                    name / email, with a clear anonymous-mode explanation
5. Learner experience         feedback (rating) toggle
6. Catalogue                  search filter (NQL), with a short example
7. Appearance                 extra CSS, with a one-line example
8. Advanced                   timeout, SSL verification bypass (warned)
```

Visual grouping uses `admin_setting_heading` (and optional “Advanced” collapse if Moodle core hide/show is enough). No custom layout grid.

---

## Summary

| # | Status | Theme | Title |
|---|--------|-------|-------|
| A1 | [x] | Structure | Group settings into the eight sections above |
| A2 | [x] | Structure | Move test connection **below** connection fields |
| A3 | [x] | Copy | Rewrite labels and help; fix typos; align “institute” wording |
| A4 | [x] | Copy | Intro heading with how-to-get-keys and docs link |
| B1 | [x] | Connection test | Custom `admin_setting` + Mustache widget (EPIC-5) |
| B2 | [x] | Connection test | Loading, disabled button, `aria-live`, real `<button>` |
| B3 | [x] | Connection test | Actionable success / error messages (not “Success!” / “Failed!”) |
| B4 | [x] | Connection test | Drop jQuery; use `core/ajax` + native DOM |
| C1 | [x] | Credentials | Empty password default; mark OER defaults as public demo keys |
| C2 | [x] | Credentials | Warn when SSL verification is disabled |
| D1 | [x] | Privacy | Clearer anonymous-mode copy; consistent checkbox labels |
| E1 | [x] | Catalogue | NQL filter help with example |
| E2 | [x] | Appearance | CSS help with example and scope note |
| F1 | [x] | Tests | Behat coverage for layout, copy, and connection-test UX |
| F2 | [x] | Tests | PHPUnit still asserts the new setting keys after regrouping |

---

## A — Page structure and copy

### A1 — Group settings into named sections

Split the current single “NaaS settings” bucket. Keep existing `naas/*` config keys (no rename) so upgrades do not reset values.

**Files:** `settings.php`, `lang/en/naas.php`

**Validate**

- [ ] Opening the page, an admin sees distinct headings in this order: About, Connection, Test connection, Privacy, Learner experience, Catalogue, Appearance, Advanced.
- [ ] Existing config values survive an upgrade / settings save (keys unchanged).
- [ ] Save still persists every field; no field disappeared.
- [ ] `tests/root_scripts_test.php` still finds `naasnaas_endpoint` (and the other registered keys).
- [ ] Page remains usable on Boost and a second core theme (Classic), desktop width ~1280px and ~768px.

### A2 — Put test connection after the credentials

The help already says settings must be saved first. The control must sit where that sentence makes sense.

**Validate**

- [ ] Test connection is **not** the first block on the page.
- [ ] It sits immediately under endpoint / user / password / institute.
- [ ] Help text still states that unsaved form values are not tested (saved config only), in plain language.
- [ ] After Save, the same page still shows the test control (no jump to another tab).

### A3 — Rewrite labels and help

Replace tautologies (“Enter the NaaS API user”) with one sentence of purpose. Align vocabulary with the README: **institute** (keep `naas_structure_id` as the config key).

Suggested label map (English):

| Current label | Proposed label |
|---|---|
| NaaS API endpoint | NaaS API URL |
| NaaS API user | API username |
| NaaS API password | API password |
| NaaS API structure | Institute ID |
| NaaS API timeout | Request timeout (seconds) |
| Disable SSL certificate verification | Disable SSL certificate verification (development only) |
| NaaS CSS | Extra CSS for Nugget player |
| NaaS search filter | Catalogue search filter |
| NaaS feedback | Allow learners to rate Nuggets |
| Collect nugget learners emails | Send learner email to NaaS |
| Collect nugget learners names. | Send learner name to NaaS |

Fix: `ressources)` → `resources`; double space in privacy intro; trailing period only where every sibling has one.

**Validate**

- [ ] No tautological help that only repeats the label.
- [ ] “Institute” appears in the UI; “structure” is not the primary label (internal key may still say structure).
- [ ] `lang/en/naas.php` has no `ressources`, no `"may  collect"`.
- [ ] Help icons (`?`) on every setting; none empty.
- [ ] Moodle language customisation still finds the old string identifiers if we **reuse** keys; if we add new keys, old unused keys are removed in the same change.

### A4 — Intro / getting-started heading

One short paragraph: what this page does, that public Open Education keys work for OER Nuggets, and that private institute keys come from `idea.lab@isae-supaero.fr` (same as README). Link the NaaS site and the privacy notice already referenced in README.

**Validate**

- [ ] Admin who has never seen NaaS can tell whether they must request keys.
- [ ] Links open in a way consistent with Moodle admin (prefer same tab or explicit `target` + `rel`).
- [ ] No raw URLs as the only affordance; visible link text.
- [ ] Contact / docs links are not hardcoded in three different wordings.

---

## B — Connection test experience

### B1 — Custom admin setting + Mustache (lands EPIC-5)

Replace `html_writer` in the heading description with `classes/admin/test_connection_setting.php` and `templates/admin_test_connection.mustache`.

`get_setting()` returns `null`; `write_setting()` returns `''` (display-only control).

**Validate**

- [ ] `settings.php` has no `html_writer::`.
- [ ] Template renders the button and the result region.
- [ ] `php admin/cli/mustache_lint.php --filter=mod_naas` is clean for the new template.
- [ ] GPL header + `defined('MOODLE_INTERNAL') || die()` on the new class.
- [ ] Existing AMD module still binds (stable `id`s, or AMD updated in the same change).

### B2 — Loading, disable, accessibility

While `mod_naas_test_config` runs: button disabled, visible spinner or “Testing…”, result region announced.

**Validate**

- [ ] Control is a `<button type="button">`, not `<a href="#">`.
- [ ] Result container has `role="status"` and `aria-live="polite"`.
- [ ] During the request, the button is `disabled` and a loading label is visible.
- [ ] A second click during flight does not start a second webservice call.
- [ ] Keyboard: Tab reaches the button; Enter / Space activates it.
- [ ] Screen reader (or Accessibility Inspector): result text is announced when it appears.
- [ ] Success uses `alert-success` (or Moodle notification); failure uses `alert-danger` / `NOTIFY_ERROR`.

### B3 — Human-readable outcomes

Map known NaaS / Moodle failures to the existing `error:naas_api:*` strings (invalid credentials, invalid endpoint, invalid institute, unauthorized, server unavailable). Keep a generic fallback.

Success should confirm more than “Success!” — e.g. that the API accepted the **saved** credentials and returned a catalogue response.

**Validate**

- [ ] Wrong password (saved) → credentials message, not a raw stack / debuginfo.
- [ ] Wrong institute ID → institute / structure message.
- [ ] Unreachable endpoint → unavailable / timeout message.
- [ ] Happy path → success banner; no JSON dump in the UI.
- [ ] `connection_test_success` / `connection_test_failed` are no longer the only two user-facing strings, or they are expanded to full sentences.
- [ ] Webservice exception `debuginfo` is not concatenated into the banner (teachers/admins on production must not see curl internals).

### B4 — AMD without jQuery

Rewrite `amd/src/test_connection.js` with `core/ajax`, `core/str`, and `document` APIs. Rebuild `amd/build/`.

**Validate**

- [ ] Source has no `$()` / `jquery` dependency.
- [ ] `grunt amd` (or the plugin’s usual build) updates min.js + map.
- [ ] Behaviour matches B2/B3 on a hard-refreshed admin page.

---

## C — Credentials and dangerous options

### C1 — Defaults that do not look like a finished setup

Shipping a real password in `settings.php` is a security smell (**QUALITY_PHP.md C2**) and a UX lie (the form looks done). Product constraint from README: public OER keys should still make the plugin usable out of the box.

Preferred approach:

- Keep the public **endpoint** default.
- Keep public username + institute ID only if they are documented in the heading as “Open Education (OER) demo credentials”.
- **Do not** ship the password as a default in source. Empty password + help: “OER password is published in the plugin README / obtained from IDEA” **or** document `NAAS_API_PASSWORD` (already read in `naas_client.php`).

Decide explicitly in the PR description which of those two OER behaviours we ship; do not leave a live password in git.

**Validate**

- [ ] `settings.php` default for `naas_password` is `''`.
- [ ] `git grep` in `mod/naas` no longer finds the old default password string.
- [ ] Fresh install: password field is empty; admin understands why Test connection may fail until they save credentials.
- [ ] README “Plugin settings” section matches the new defaults (no contradiction).
- [ ] Existing sites that already saved a password are unaffected (config in DB, not the PHP default).

### C2 — SSL bypass as an advanced, warned control

Move under Advanced. Description must say production must keep verification **on**. Optional: Moodle notification on the page when the checkbox is currently saved as enabled.

**Validate**

- [ ] Setting is under Advanced, not next to username.
- [ ] Help mentions self-signed / development only.
- [ ] Default remains off.
- [ ] Enabling it, saving, and reloading still shows it as on (no silent reset).

---

## D — Privacy

### D1 — Anonymous mode explained next to the checkboxes

Admins currently get two independent checkboxes defaulting to **on**, plus a heading that is easy to skip. State the consequence in the heading: if name or email is off, traces stay anonymous on NaaS (match README “Anonymous mode”).

**Validate**

- [ ] Heading explains what is sent when both are on, and what “anonymous” means when either is off.
- [ ] Labels have no trailing-period mismatch.
- [ ] Changing a checkbox and saving is reflected in a Nugget launch / xAPI payload (spot-check: name/email omitted or replaced by anonymous placeholders as in `naas_lti.php` / `xapi.php`).
- [ ] Defaults: **do not change** on/off behaviour in this UX pass unless product explicitly asks; if defaults change, call it out in the PR and in README.

---

## E — Catalogue and appearance

### E1 — Search filter help

The value is sent as NQL (`nql` query param). Help should say so and give one example (`type:video` is already used in unit tests).

**Validate**

- [ ] Help mentions that the filter applies to teacher search in the activity chooser / Nugget picker, not to already-added activities.
- [ ] Example is copy-pasteable.
- [ ] Empty filter still means “no extra restriction”.
- [ ] Invalid NQL: either NaaS error is understandable after Test connection / search, or we document “invalid filter = empty catalogue” — pick one and match the UI.

### E2 — Extra CSS help

State that CSS is passed to the Nugget player (LTI `css` param), not applied to the whole Moodle theme.

**Validate**

- [ ] Help no longer says “ressources)”.
- [ ] One-line example (e.g. a class already used by the player, or a harmless `body { }`).
- [ ] Empty value still means “no extra CSS”.

---

## F — Automated and manual validation

### F1 — Behat

Extend `tests/behat/mod_naas_settings.feature` beyond “I should see NaaS API endpoint”.

**Validate (scenarios)**

- [ ] Admin opens **Plugins → Activity modules → Nugget** and sees each new heading.
- [ ] Connection labels (URL, username, password, institute) are present.
- [ ] Test connection button is present **after** those fields.
- [ ] Privacy checkboxes are present with the new labels.
- [ ] Clicking Test connection without waiting forever: either a success or a failure banner appears (depends on env); the button is not a dead link.
- [ ] Non-admin cannot open the page (core behaviour; skip if already covered by Moodle).

### F2 — PHPUnit

**Validate**

- [ ] `test_settings_php_registers_admin_settings` still passes after regrouping.
- [ ] If a new `test_connection_setting` class exists, it has a small unit test: `get_setting()` / `write_setting()` contract.
- [ ] `test_config_requires_admin` still passes.

---

## Manual walkthrough (do this before merging)

Run as a site admin on a Boost theme. Treat this as the usability sign-off, not a substitute for Behat.

1. **First visit (empty / OER defaults)**  
   Can you tell what to fill first? Is the password empty or obviously a public demo key? Does anything look “already done” when it is not?

2. **Save then test**  
   Fill valid credentials, click Test **without** Save → confirm the UI tells you it uses saved values (or fails honestly). Save, then Test → success banner, no JSON.

3. **Wrong credentials**  
   Save a bad password → Test → readable error. Restore.

4. **Privacy**  
   Read the heading once. Can you predict what NaaS will receive? Toggle, Save, open a Nugget as a student (or inspect LTI/xAPI) if you are on a full stack.

5. **Advanced**  
   Timeout and SSL are not in the way of the setup path. SSL help is scary enough.

6. **Keyboard / zoom**  
   Tab through the page. 200% zoom: labels still readable, test result not clipped.

7. **Regression**  
   Add a Nugget in a course, search, preview, launch. Admin CSS/filter/feedback still apply as before.

Sign-off:

- [ ] Walkthrough 1–7 done on Boost
- [ ] Spot-check Classic (or the institution theme)
- [ ] README plugin-settings screenshots / labels updated if they show the old UI

---

## Out of scope (this refresh)

- Vue Nugget search / player UI (already covered in [ENHANCEMENTS_BACKLOG.md](ENHANCEMENTS_BACKLOG.md)).
- Renaming `naas_structure_id` in the database.
- Storing the API password only in the environment (document in C1; do not require it for this UX pass).
- Live-testing **unsaved** form values (would need a new webservice that accepts posted secrets — extra security review).
- Collapsible Moodle admin categories as a separate plugin settings category (keep one `modsettingnaas` page unless grouping proves too long).
- Changing privacy checkbox defaults without a product decision.

---

## Suggested implementation order

1. A3 copy + A1/A2 regroup (pure `settings.php` / lang — visible win, easy revert).
2. C1 password default + README alignment.
3. B1 Mustache widget, then B2–B4 behaviour.
4. D1 / E1 / E2 help polish (can ship with step 1 if the strings are ready).
5. F1 / F2 tests and the manual walkthrough.
