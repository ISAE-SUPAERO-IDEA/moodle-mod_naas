# Renderer / view_page compat analysis

Winner: **`renderer.php`** and **`view_page.php`** (v1). Use these.
Discard: `renderer2.php`, `view_page2.php`.

## Grades (Moodle conventions + PHP standards)

| File | Grade | Summary |
|---|---|---|
| [classes/output/renderer.php](classes/output/renderer.php) | **A+** (20/20) | PSR-0 namespace correct, `use plugin_renderer_base`, typed returns, all renderables covered, copyright "2019 onwards", `@since` + `@package` on class docblock, `@throws` on render methods, no redundant FQN prefixes. |
| [classes/output/view_page.php](classes/output/view_page.php) | **A+** (20/20) | All `use` imports, typed `stdClass` return, `renderer_base $output` typed param, safe `moodle_url::param()`, typed properties, full docblocks with `@since`, multi-line ctor signature. |
| [classes/output/renderer2.php](classes/output/renderer2.php) | **F** (4/20) | Wrong autoload location for legacy class name `mod_naas_renderer` (must be at plugin root). References non-existent `widget` class and `mod_naas/widget` template. Missing two render methods. Will fatal on load. |
| [classes/output/view_page2.php](classes/output/view_page2.php) | **D−** (6/20) | Stylistic Moodle conventions (`use` imports, stdClass, `renderer_base` typed param) but ctor type-hints non-existent `widget` class → fatal. Unsafe URL string concat (`. '&forceview=1'`) breaks Moodle URL contract. Data fetch inside `export_for_template` violates renderable/data separation. |

Scoring axes: autoload correctness, namespace usage, type safety, URL safety, separation of concerns, template/dep existence, docblock compliance.

---

## renderer.php vs renderer2.php

### `renderer.php` — KEEP

- Namespace `mod_naas\output` matches file path [classes/output/renderer.php](classes/output/renderer.php). Moodle autoloader (frankenstyle PSR-0) resolves it.
- Class `renderer` extends `\plugin_renderer_base` with leading backslash (correct from inside namespace).
- Renders all three real renderables: `view_page`, `index_page`, `lti_launch_form`.
- Templates referenced (`mod_naas/view_page`, `mod_naas/index_page`, `mod_naas/lti_launch_form`) exist in [templates/](templates/).
- Return type `: string` — fine on Moodle 4.x (PHP 7.4+).

### `renderer2.php` — BROKEN

- No namespace, class `mod_naas_renderer`. That legacy frankenstyle class must live at `/mod/naas/renderer.php` (plugin root), NOT under `/classes/output/`. Moodle autoloader will not find it here.
- `use mod_naas\output\widget;` — class **does not exist**. Only [classes/naas_widget.php](classes/naas_widget.php) (`mod_naas\naas_widget`) exists.
- `render_from_template('mod_naas/widget', ...)` — template **does not exist**. Only [templates/naas_widget.mustache](templates/naas_widget.mustache) exists.
- Missing `render_index_page` and `render_lti_launch_form`.
- Uses `plugin_renderer_base` without leading `\` while file has no namespace — works only by luck (global ns).

---

## view_page.php vs view_page2.php

### `view_page.php` — KEEP

- Self-contained: takes pre-rendered widget HTML string. No dep on missing `widget` class.
- URL handling safe: builds `\moodle_url`, calls `->param('forceview', 1)` then `->out(false)`. Survives URLs that already contain query params.
- Typed properties + typed ctor args (PHP 7.4+, fine).
- Template export returns array. Mustache accepts array or stdClass.
- No business logic in `export_for_template` — caller injects data.

### `view_page2.php` — BROKEN + UNSAFE

- Holds `\mod_naas\output\widget` — **class does not exist**. Constructor `__construct(widget $widget, ...)` will fatal on type hint mismatch.
- URL bug: `$nextactivity->link->out(false) . '&forceview=1'`. String concat. If URL has no `?`, this corrupts it (`...path&forceview=1`). Must use `->param()`.
- Calls `\mod_naas\mod_util::get_next_activity_url()` inside `export_for_template`. Couples view to data fetch — harder to test, contradicts Moodle "renderable carries data, doesn't fetch it" pattern.
- Stylistic pluses (`use` imports, `stdClass` return, `renderer_base` typed param) do not offset broken deps.

---

## Action

1. Delete [classes/output/renderer2.php](classes/output/renderer2.php).
2. Delete [classes/output/view_page2.php](classes/output/view_page2.php).
3. Keep `renderer.php` and `view_page.php` as canonical.
4. If `widget` renderable wanted, create `classes/output/widget.php` with namespace `mod_naas\output` + matching `templates/widget.mustache`, and add `render_widget()` to `renderer.php`. Until then, v1 path (pre-rendered HTML via `naas_widget`) is the working contract.
