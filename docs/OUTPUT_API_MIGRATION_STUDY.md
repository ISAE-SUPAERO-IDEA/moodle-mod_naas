# Migration Study: Legacy HTML → Moodle Output API & Templates

**Plugin:** `mod_naas` — Moodle Nugget Plugin  
**Scope:** Audit of all files generating HTML outside of Moodle's Output API / Mustache template system  
**Standards referenced:**
- [Moodle Output API](https://moodledev.io/docs/apis/subsystems/output)
- [Moodle Mustache Templates Guide](https://moodledev.io/docs/guides/templates)

---

## 1. Background & Motivation

Moodle's **Output API** and **Mustache template** system were introduced to separate presentation logic from business logic. They provide:

| Benefit | Detail |
|---|---|
| **Theme override** | Any theme can override a template or renderer to change the UI without touching plugin code |
| **XSS safety** | Mustache auto-escapes variables by default; raw `echo` bypasses all protection |
| **Testability** | Renderables are plain PHP objects that are unit-testable without a full Moodle stack |
| **Maintainability** | HTML lives in `.mustache` files with its own syntax highlighting, linting, and code review tooling |
| **i18n** | Templates support `{{# str }} key, component {{/ str }}` helpers, keeping text out of PHP |
| **A11y & Moodle standards** | Moodle's CI/CD includes a Mustache linter; raw HTML bypasses that pipeline |

The current `mod_naas` codebase mixes HTML generation in PHP via three patterns, all of which should be eliminated:

1. **Raw `echo` of HTML strings** — no escaping, no theme override, hard to maintain
2. **Heredoc HTML blocks** — same issues as above, plus embedded inline `<style>` and `<script>`
3. **`html_writer` calls** — somewhat acceptable, but still not the modern approach for anything more than a single link; should be replaced by templates

---

## 2. Full Audit — Legacy HTML Locations

### 2.1 `view.php` — Main activity view page

| Lines | Pattern | Description |
|---|---|---|
| 64–65 | `echo "<div class='course-button'>..."` | Raw HTML string for "Back to course" button |
| 71–73 | `echo "<div class='next-activity hidden'>..."` | Raw HTML string for "Next activity" button |
| 79–85 | `echo "<script>..."` | Raw inline `<script>` block injecting DOM manipulation |

**Specific code:**

```php
// Lines 64–65: raw button HTML
$backcoursebutton = "<div class='course-button'><a class='btn btn-outline-secondary btn-sm'
    href=" . $courseurl . ">" . get_string('back_to_course', 'naas') . "</a></div>";

echo $backcoursebutton;
```

**Problems:**
- `$courseurl` is not escaped before being interpolated
- No XSS protection on the anchor's `href`
- Duplicated at line 87 (rendered twice, top and bottom)
- Inline `<script>` at line 79 is an anti-pattern; should use an AMD module

---

### 2.2 `index.php` — Course activity index page

| Lines | Pattern | Description |
|---|---|---|
| 79–80 | `echo '<div class="course-button mb-3">...'` | Raw HTML string for top "Back to course" button |
| 85 | `echo '<div class="alert alert-info">...'` | Raw alert HTML |
| 147–148 | `echo '<div class="course-button mt-3">...'` | Raw HTML string for bottom "Back to course" button |
| 97–107 | `html_table` + `html_writer::table()` | PHP-built table; should be a template |
| 118–122 | `html_writer::link()` | Acceptable helper, but belongs in template context |

**Specific code:**

```php
// Lines 79–80: raw nav button
echo '<div class="course-button mb-3"><a class="btn btn-outline-secondary btn-sm" href="' .
    $courseurl . '">' . get_string('back_to_course', 'naas') . '</a></div>';

// Line 85: raw alert div
echo '<div class="alert alert-info">' . get_string('nonewmodules', 'naas') . '</div>';
```

**Problems:**
- `$courseurl` concatenated directly — no `htmlspecialchars()` or `s()`
- Bootstrap class strings hardcoded in PHP; theme cannot override
- The entire table logic should live in a template

---

### 2.3 `classes/naas_widget.php` — Widget HTML generator

| Lines | Pattern | Description |
|---|---|---|
| 114 | `$html = "<div id='naas_widget'>..."` | Raw mount-point div |
| 115 | `$html .= "<script>NAAS=..."` | Inline `<script>` injecting JSON configuration |
| 116–117 | `$html .= "<script src='...'>"` | Raw `<script src>` tag loading Vue bundle |

**Specific code:**

```php
$html = "<div id='naas_widget'></div>";
$html .= "<script>NAAS=$widgetconfig</script>";
$widgetjsurl = new \moodle_url('/mod/naas/assets/vue/naas_widget-2026030300.js');
$html .= "<script src='$widgetjsurl' ></script>";

return $html;
```

**Problems:**
- `$widgetconfig` is a JSON-encoded string interpolated directly into a `<script>` — dangerous if any value contains `</script>`
- External JS file loaded via raw `<script src>` instead of `$PAGE->requires->js()` — Moodle cannot manage load order or caching
- Mount div + config injection should use a Mustache template with `$PAGE->requires->js_call_amd()`
- Configuration should be passed via AMD init function parameters

---

### 2.4 `classes/naas_lti.php` — LTI launch handler

| Lines | Pattern | Description |
|---|---|---|
| 74–95 | `echo <<<HTML` heredoc | Inline `<style>` block + error `<div>` |
| 187–205 | `echo <<<HTML` heredoc | Full LTI `<form>` with auto-submit `<script>` |
| 194 | Loop concatenating `<input>` tags | Dynamic HTML built in PHP loop |

**Specific code:**

```php
// Error block (lines 74–95)
echo <<<HTML
<style>
.error-message { color: #721c24; background-color: #f8d7da; ... }
</style>
    <div class="error-message">$errormessage</div>
HTML;

// LTI form (lines 187–206)
$html = <<<HTML
    <form id="ltiLaunchForm" name="ltiLaunchForm" method="POST" action="$launchurl">
HTML;
foreach ($launchdata as $key => $value) {
    $key   = htmlspecialchars($key, ENT_COMPAT);
    $value = htmlspecialchars($value, ENT_COMPAT);
    $html .= "  <input type=\"hidden\" name=\"{$key}\" value=\"{$value}\"/>\n";
}
```

**Problems:**
- Inline `<style>` duplicates Moodle's notification/alert component
- `$launchurl` interpolated directly into `action="..."` without escaping
- `$errormessage` directly interpolated without `htmlspecialchars()` or `s()`
- The form should be rendered via a Mustache template; the auto-submit JS should be in an AMD module

---

### 2.5 `settings.php` — Admin settings page

| Lines | Pattern | Description |
|---|---|---|
| 30–33 | `html_writer::tag()` + `html_writer::link()` | Test-connection button built with html_writer |
| 34–38 | `html_writer::div()` | Result span built with html_writer |

**Problems:**
- Mixing HTML into an `admin_setting_heading` description is fragile
- A custom `admin_setting` subclass rendering a Mustache template would be more maintainable

---

## 3. Moodle Modern Standards — Quick Reference

### 3.1 The Output API pipeline

```
Controller (*.php)
  └── creates Renderable (implements renderable + templatable)
        └── Renderer (extends plugin_renderer_base)
              └── render_from_template('mod_naas/view_page', $data)
                    └── templates/view_page.mustache
```

### 3.2 Key interfaces and classes

| Class / Interface | Role |
|---|---|
| `renderable` | Marker interface — signals an object can be rendered |
| `templatable` | Forces `export_for_template(renderer_base $output): stdClass` |
| `named_templatable` | Extends `templatable`; adds `get_template_name()` to name the template explicitly |
| `plugin_renderer_base` | Base class for all custom plugin renderers; has `render_from_template()` |
| `$OUTPUT` | Global renderer for core; plugins should use `$PAGE->get_renderer('mod_naas')` |

### 3.3 Mustache templates

Templates live in `templates/` under the plugin root (e.g., `mod/naas/templates/view_page.mustache`).  
Addressed as `mod_naas/view_page` anywhere in PHP or JavaScript.

**Key Mustache features:**

```mustache
{{! Comment }}
{{variable}}             — auto-escaped HTML output
{{{variable}}}           — raw/unescaped (use only for trusted HTML)
{{#items}}...{{/items}}  — loops and truthy sections
{{^items}}...{{/items}}  — falsy sections
{{> partial}}            — include another template
{{# str }} key, component {{/ str }} — Moodle lang string helper
{{# pix }} icon_name, component {{/ pix }} — Moodle icon helper
```

### 3.4 Loading JavaScript properly

```php
// ✅ AMD module (preferred)
$PAGE->requires->js_call_amd('mod_naas/widget_init', 'init', [$config]);

// ✅ Versioned external JS file
$PAGE->requires->js(new moodle_url('/mod/naas/assets/vue/naas_widget-2026030300.js'), true);

// ❌ Do NOT do:
echo "<script>NAAS=" . json_encode($config) . "</script>";
echo "<script src='...'></script>";
```

### 3.5 Notifications / errors

```php
// ✅ Modern way:
echo $OUTPUT->notification(get_string('cannot_get_nugget', 'naas'), \core\output\notification::NOTIFY_ERROR);

// ❌ Legacy way:
echo '<div class="error-message">' . $errormessage . '</div>';
```

---

## 4. Proposed Migration Plan

### Priority 1 — Security-critical (fix first)

#### 4.1 `classes/naas_widget.php` — Replace inline script with AMD + template

**New files needed:**
- `templates/naas_widget.mustache` — mount div
- `amd/src/widget_init.js` — AMD module

**Before:**
```php
$html = "<div id='naas_widget'></div>";
$html .= "<script>NAAS=$widgetconfig</script>";
$html .= "<script src='$widgetjsurl'></script>";
return $html;
```

**After (PHP — naas_widget.php):**
```php
public static function naas_widget_html($nuggetid, $courseid, $cmid, $component): string {
    global $CFG, $PAGE, $OUTPUT;

    $config = [
        'moodle_url'  => $CFG->wwwroot,
        'mount_point' => '#naas_widget',
        'component'   => $component,
        'nugget_id'   => $nuggetid,
        'courseId'    => $courseid,
        'cm_id'       => $cmid,
        'labels'      => self::build_labels(),
    ];

    $PAGE->requires->js(
        new \moodle_url('/mod/naas/assets/vue/naas_widget-2026030300.js'),
        true // load in footer
    );
    $PAGE->requires->js_call_amd('mod_naas/widget_init', 'init', [$config]);

    return $OUTPUT->render_from_template('mod_naas/naas_widget', []);
}
```

**After (Mustache — `templates/naas_widget.mustache`):**
```mustache
{{!
    @template mod_naas/naas_widget

    Mount point for the NaaS Vue widget.
    Initialized by AMD module mod_naas/widget_init.

    Context variables: none
}}
<div id="naas_widget"></div>
```

**After (AMD — `amd/src/widget_init.js`):**
```javascript
/**
 * Initialize the NaaS Vue widget.
 * @module mod_naas/widget_init
 */
export const init = (config) => {
    // The Vue bundle is loaded via $PAGE->requires->js() — just set the config.
    window.NAAS = config;
};
```

---

#### 4.2 `classes/naas_lti.php` — Error message via `$OUTPUT->notification()`

**Before (lines 74–95):**
```php
echo <<<HTML
<style>.error-message { ... }</style>
<div class="error-message">$errormessage</div>
HTML;
```

**After:**
```php
global $OUTPUT;
echo $OUTPUT->notification(
    get_string('cannot_get_nugget', 'naas'),
    \core\output\notification::NOTIFY_ERROR
);
return;
```

---

#### 4.3 `classes/naas_lti.php` — LTI form via Mustache template

**New file:** `templates/lti_launch_form.mustache`

**After (PHP):**
```php
global $OUTPUT;
$fields = [];
foreach ($launchdata as $key => $value) {
    $fields[] = ['name' => $key, 'value' => $value];
}
$fields[] = ['name' => 'oauth_signature', 'value' => $signature];

echo $OUTPUT->render_from_template('mod_naas/lti_launch_form', [
    'launchurl' => $launchurl,
    'fields'    => $fields,
]);
```

**After (Mustache — `templates/lti_launch_form.mustache`):**
```mustache
{{!
    @template mod_naas/lti_launch_form

    Auto-submitting LTI 1.0 launch form.

    Context variables:
    * launchurl {string} - The LTI endpoint URL
    * fields {array}    - Array of {name, value} objects for hidden inputs
}}
<form id="ltiLaunchForm" name="ltiLaunchForm" method="POST" action="{{launchurl}}">
    {{#fields}}
    <input type="hidden" name="{{name}}" value="{{value}}">
    {{/fields}}
</form>
{{#js}}
document.getElementById('ltiLaunchForm').submit();
{{/js}}
```

> **Note:** The `{{#js}}` block is Moodle's built-in mechanism for inline JavaScript in Mustache templates, and is safer than raw `<script>` tags.

---

### Priority 2 — Moderate (main view pages)

#### 4.4 `view.php` — Navigation buttons + inline script

**New files:** `templates/view_page.mustache` and `amd/src/view_page.js`

**After (PHP — view.php):**
```php
$PAGE->requires->js_call_amd('mod_naas/view_page', 'init');

echo $OUTPUT->render_from_template('mod_naas/view_page', [
    'courseurl'        => $courseurl->out(false),
    'backtocourse'     => get_string('back_to_course', 'naas'),
    'hasnextactivity'  => (bool) $nextactivityurl,
    'nextactivityurl'  => $nextactivityurl ? $nextactivityurl->link->out(false) . '&forceview=1' : '',
    'nextactivityname' => $nextactivityurl ? $nextactivityurl->name : '',
    'widgethtml'       => \mod_naas\naas_widget::naas_widget_html(...),
]);
```

**After (Mustache — `templates/view_page.mustache`):**
```mustache
{{!
    @template mod_naas/view_page

    Context variables:
    * courseurl {string}
    * backtocourse {string}
    * hasnextactivity {bool}
    * nextactivityurl {string}
    * nextactivityname {string}
    * widgethtml {string} — pre-rendered widget HTML (triple-stache)
}}
<div class="course-button">
    <a class="btn btn-outline-secondary btn-sm" href="{{courseurl}}">{{backtocourse}}</a>
</div>

{{#hasnextactivity}}
<div class="next-activity hidden">
    <a class="btn btn-outline-secondary btn-sm" href="{{nextactivityurl}}">{{nextactivityname}}</a>
</div>
{{/hasnextactivity}}

{{{widgethtml}}}

<div class="course-button mt-3">
    <a class="btn btn-outline-secondary btn-sm" href="{{courseurl}}">{{backtocourse}}</a>
</div>
```

---

#### 4.5 `index.php` — Course module list page

**New file:** `templates/index_page.mustache`

**After (PHP — index.php):**
```php
if (!$naasmodules) {
    echo $OUTPUT->notification(get_string('nonewmodules', 'naas'), 'info');
    echo $OUTPUT->continue_button($courseurl);
    echo $OUTPUT->footer();
    die();
}

$rows = [];
foreach ($naasmodules as $naasmodule) {
    $rows[] = [
        'section'      => $usesections ? get_section_name($course, $naasmodule->section) : null,
        'viewurl'      => (new moodle_url('/mod/naas/view.php', ['id' => $naasmodule->coursemodule]))->out(false),
        'name'         => format_string($naasmodule->name),
        'dimmed'       => !$naasmodule->visible,
        'intro'        => format_module_intro('naas', $naasmodule, $naasmodule->coursemodule),
        'timemodified' => userdate($naasmodule->timemodified),
    ];
}

echo $OUTPUT->render_from_template('mod_naas/index_page', [
    'courseurl'    => $courseurl->out(false),
    'backtocourse' => get_string('back_to_course', 'naas'),
    'heading'      => get_string('modulenameplural', 'naas'),
    'usesections'  => $usesections,
    'rows'         => $rows,
]);
```

---

### Priority 3 — Low (admin / settings)

#### 4.6 `settings.php` — Test connection button

Create `classes/admin/test_connection_setting.php` extending `admin_setting`:

```php
namespace mod_naas\admin;

class test_connection_setting extends \admin_setting {
    public function output_html($data, $query = '') {
        global $OUTPUT;
        return $OUTPUT->render_from_template('mod_naas/admin_test_connection', []);
    }
    public function get_setting() { return null; }
    public function write_setting($data) { return ''; }
}
```

Register in `settings.php`:
```php
$settings->add(new \mod_naas\admin\test_connection_setting(
    'naas/test_connection_ui',
    get_string('test_connection', 'naas'),
    get_string('test_connection_information', 'naas')
));
```

---

## 5. New File Structure After Migration

```
mod/naas/
├── classes/
│   ├── output/                           ← NEW
│   │   ├── view_page.php                 ← Renderable + templatable for view.php
│   │   ├── index_page.php                ← Renderable + templatable for index.php
│   │   └── lti_launch_form.php           ← Renderable + templatable for LTI form
│   ├── admin/
│   │   └── test_connection_setting.php   ← NEW custom admin_setting
│   ├── naas_widget.php                   ← MODIFY: remove raw HTML, use template
│   ├── naas_lti.php                      ← MODIFY: remove heredocs, use template
│   └── ...
├── templates/                            ← NEW
│   ├── naas_widget.mustache
│   ├── view_page.mustache
│   ├── index_page.mustache
│   ├── lti_launch_form.mustache
│   └── admin_test_connection.mustache
├── amd/
│   └── src/
│       ├── test_connection.js            ← existing
│       ├── widget_init.js                ← NEW (replaces inline <script>)
│       └── view_page.js                  ← NEW (replaces inline <script> in view.php)
├── view.php                              ← MODIFY: use render_from_template
└── index.php                             ← MODIFY: use render_from_template
```

---

## 6. Summary Table

| File | Issue | Severity | Recommended fix |
|---|---|---|---|
| `classes/naas_widget.php:114-117` | Inline `<script>` + unescaped JSON injection | 🔴 High | AMD module + Mustache template |
| `classes/naas_lti.php:74-95` | Inline `<style>` + unescaped variable | 🔴 High | `$OUTPUT->notification()` |
| `classes/naas_lti.php:187-205` | Heredoc LTI form + `$launchurl` unescaped | 🔴 High | Mustache template |
| `view.php:64-73` | Raw HTML buttons, unescaped URL | 🟠 Medium | Mustache template |
| `view.php:79-85` | Inline `<script>` DOM manipulation | 🟠 Medium | AMD module |
| `index.php:79-80,147-148` | Raw HTML button strings | 🟠 Medium | Mustache template |
| `index.php:85` | Raw alert div | 🟠 Medium | `$OUTPUT->notification()` |
| `index.php:97-144` | `html_table` + `html_writer::table` | 🟡 Low | Mustache template |
| `settings.php:30-44` | `html_writer` in admin heading description | 🟡 Low | Custom `admin_setting` subclass |

---

## 7. References

- [Output API — moodledev.io](https://moodledev.io/docs/apis/subsystems/output)
- [Templates (Mustache) — moodledev.io](https://moodledev.io/docs/guides/templates)
- [JavaScript Modules (AMD) — moodledev.io](https://moodledev.io/docs/guides/javascript/modules)
- [HTML Guidelines — docs.moodle.org](https://docs.moodle.org/dev/HTML_Guidelines)
- [Coding style — moodledev.io](https://moodledev.io/general/development/policies/codingstyle)
