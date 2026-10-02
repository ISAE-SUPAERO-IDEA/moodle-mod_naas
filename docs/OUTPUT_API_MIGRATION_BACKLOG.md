# Output API Migration — Epics, User Stories & Commits

**Plugin:** `mod_naas`
**Moodle standards:** [Output API](https://moodledev.io/docs/apis/subsystems/output) · [Templates](https://moodledev.io/docs/guides/templates) · [AMD](https://moodledev.io/docs/guides/javascript/modules)

Status: `[x]` done · `[ ]` todo · `[-]` dropped / N/A

---

## Overall Status

| Epic | Status |
|------|--------|
| EPIC-1 Foundation | ✅ done |
| EPIC-2 Widget script security | ✅ done |
| EPIC-3 LTI heredoc security | ✅ done |
| EPIC-4 view.php / index.php templates | ✅ done |
| EPIC-5 Admin settings widget | ❌ todo |
| EPIC-6 Build & CI | ⚠️ partial (lint pending) |

---

## EPIC-1 · Foundation: Output API scaffolding

> Lay the base infrastructure (renderer class, output renderables) that every subsequent epic depends on.

**Stories:**
- [x] US-1.1 Fix undefined `$naas` → `$naasinstance` in `view.php` `?u=` branch
- [x] US-1.2 Create plugin renderer class extending `plugin_renderer_base`
- [x] US-1.3 Create `renderable` + `templatable` output classes for view, index, LTI form

**Commit:**
```
feat(output): add Output API foundation — renderer and renderable classes

- Fix undefined $naas variable in view.php on ?u= branch (fatal PHP error)
- Add classes/renderer.php extending plugin_renderer_base with
  render_view_page(), render_index_page(), render_lti_launch_form()
- Add classes/output/view_page.php, index_page.php, lti_launch_form.php
  implementing renderable + templatable with typed constructors and
  scalar-only export_for_template()
```

```bash
git add view.php classes/renderer.php classes/output/view_page.php classes/output/index_page.php classes/output/lti_launch_form.php
```

---

## EPIC-2 · Security: Remove inline `<script>` from widget

> **Risk: HIGH** — unescaped JSON injected into a `<script>` tag. XSS vector S1.

**Stories:**
- [x] US-2.1 Replace `<script>NAAS=…</script>` with `js_call_amd()` in `naas_widget_html()`
- [x] US-2.2 Create `amd/src/widget_init.js` AMD module + compiled build
- [x] US-2.3 Create `templates/naas_widget.mustache` mount-point template

**Commit:**
```
security(widget): remove inline <script>NAAS=...</script> injection

Config was interpolated directly into a raw <script> tag — any value
containing </script> could break out of the script context.

Config is now passed as a typed PHP array to js_call_amd(), which
Moodle encodes and injects safely. Mount point moved to a Mustache
template. Vue bundle loaded via dynamic script tag in the AMD module.

Resolves XSS vector S1.
```

```bash
git add classes/naas_widget.php amd/src/widget_init.js amd/build/widget_init.min.js amd/build/widget_init.min.js.map templates/naas_widget.mustache
```

---

## EPIC-3 · Security: Remove heredoc HTML from LTI launch

> **Risk: HIGH** — `$errormessage` and `$launchurl` interpolated into heredoc HTML without escaping. XSS vectors S2 and S3.

**Stories:**
- [x] US-3.1 Replace error heredoc + inline `<style>` with `$OUTPUT->notification()`
- [x] US-3.2 Replace LTI form heredoc with `lti_launch_form.mustache` + `clean_param` validation on `launchurl`
- [-] Auto-submit `<script>` to AMD — kept inline in mustache (fixed string, no user data interpolated)

**Commit:**
```
security(lti): replace heredoc HTML with mustache template and notification()

Error block: removed heredoc + custom <style>; replaced with
$OUTPUT->notification(NOTIFY_ERROR) — theme-aware and XSS-safe.

LTI form: replaced heredoc with render_from_template(). All field
values double-stache escaped. launchurl validated with
clean_param(PARAM_URL) before template context — empty result
triggers error notification instead of rendering an invalid form.
OAuth signature moved into $fields array loop.

Resolves XSS vectors S2 and S3.
```

```bash
git add launch.php templates/lti_launch_form.mustache
```

---

## EPIC-4 · Refactor: Migrate `view.php` and `index.php` to templates

> **Risk: MEDIUM** — raw HTML strings with unescaped URLs and inline `<script>` for About-button wiring.

**Stories:**
- [x] US-4.1 Migrate `view.php` buttons and widget area to `view_page.mustache`
- [x] US-4.2 Move About-button wiring to `amd/src/view_page.js` AMD module
- [x] US-4.3 Migrate `index.php` to `index_page.mustache`, replace `html_table` / `html_writer`

**Commit:**
```
refactor: migrate view.php and index.php to Output API renderer pattern

view.php: raw echo/HTML replaced with $renderer->render($viewpage).
  Inline <script> for About-button wiring moved to view_page.js AMD module
  called via js_call_amd(). Back-to-course and next-activity buttons
  rendered via view_page.mustache.

index.php: html_table and html_writer::table() replaced with
  render() on index_page renderable. Raw alert div replaced with
  $OUTPUT->notification(NOTIFY_INFO). All URLs use moodle_url->out(false).
  Section column conditional via {{#usesections}} in index_page.mustache.
```

```bash
git add view.php index.php templates/view_page.mustache templates/index_page.mustache amd/src/view_page.js amd/build/view_page.min.js amd/build/view_page.min.js.map
```

---

## EPIC-5 · Refactor: Admin settings test-connection widget

> **Risk: LOW** — `html_writer` calls in `admin_setting_heading` description are fragile and not theme-overridable.

**Stories:**
- [ ] US-5.1 Create `classes/admin/test_connection_setting.php` extending `\admin_setting`
- [ ] US-5.1 Create `templates/admin_test_connection.mustache` with button + result span
- [ ] US-5.1 Refactor `settings.php` to use the new class, remove all `html_writer` calls

**Acceptance criteria:**
- [ ] `output_html()` returns `$OUTPUT->render_from_template('mod_naas/admin_test_connection', [])`
- [ ] `get_setting()` returns `null`; `write_setting()` returns `''`
- [ ] Button ID and result div ID unchanged so `test_connection.js` AMD module keeps working
- [ ] `defined('MOODLE_INTERNAL') || die()` guard + GPL header in new class

**Commit:**
```
refactor(admin): replace html_writer calls with admin_setting subclass

Add classes/admin/test_connection_setting.php extending \admin_setting.
Rendering delegated to new templates/admin_test_connection.mustache,
keeping existing button/result IDs so test_connection.js AMD module
requires no changes.

Removes html_writer::tag(), ::link(), ::div() from settings.php.
```

```bash
git add classes/admin/test_connection_setting.php templates/admin_test_connection.mustache settings.php
```

---

## EPIC-6 · Build & CI: AMD compilation and linting

**Stories:**
- [x] US-6.1 Compile `widget_init.js` and `view_page.js` via `grunt amd`
- [-] US-6.1 `lti_autosubmit` AMD module — N/A, auto-submit kept inline in mustache template
- [ ] US-6.2 Run `mustache_lint` on all plugin templates

**Command to run for US-6.2:**
```bash
php admin/cli/mustache_lint.php --filter=mod_naas
```

**Commit (after lint passes):**
```
test(templates): all mustache templates pass mustache_lint

Ran: php admin/cli/mustache_lint.php --filter=mod_naas
Templates verified: naas_widget, view_page, index_page, lti_launch_form.
No triple-stache on user-supplied data.
```

```bash
# Only if lint required fixes to template files:
git add templates/
```

---

## Commit Convention Reference

| Type | When to use |
|---|---|
| `feat` | New file or capability added |
| `fix` | Bug correction |
| `refactor` | Code restructure with no behaviour change |
| `security` | Addresses an XSS / injection / escaping issue |
| `build` | AMD compilation, grunt, tooling |
| `test` | Lint runs, unit test additions |
