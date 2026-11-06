# Output API — Manual Fixup Checklist

Changes to apply manually to `feat/output_api` after aborting the failed stash pop.
Delete this file once all fixups are committed.

---

## Step 0 — Abort the stash pop (if not done yet)

```bash
git restore --staged .
git restore .
```

---

## Group 1 — PHP code quality (target: early output_api commits)

Apply each file's changes, then `git commit --fixup=<hash>` as indicated.
Run `git rebase -i --autosquash master` at the end to collapse all fixups.

---

### `classes/output/renderer.php` → fixup `6f28d75`

- [ ] Add `use plugin_renderer_base;` after the `namespace` line
- [ ] Change `class renderer extends \plugin_renderer_base` → `extends plugin_renderer_base`
- [ ] Add `@package    mod_naas` to file-level docblock
- [ ] Change copyright `2019` → `2019 onwards` (file and class docblocks)
- [ ] Add `@since      mod_naas 1.0.0` to class docblock
- [ ] Change `@param \mod_naas\output\view_page` → `@param view_page` (all 3 render methods)
- [ ] Change `@param \mod_naas\output\index_page` → `@param index_page`
- [ ] Change `@param \mod_naas\output\lti_launch_form` → `@param lti_launch_form`
- [ ] Add `@throws \moodle_exception If the template cannot be rendered` to all 3 render methods
- [ ] Fix method signatures: remove FQCN from type hints
  - `render_view_page(\mod_naas\output\view_page $page)` → `render_view_page(view_page $page)`
  - `render_index_page(\mod_naas\output\index_page $page)` → `render_index_page(index_page $page)`
  - `render_lti_launch_form(\mod_naas\output\lti_launch_form $form)` → `render_lti_launch_form(lti_launch_form $form)`
- [ ] Fix docblock punctuation: "Render the naas view page" → "Render the naas view page." (add dots)
- [ ] "Render the lti launch form" → "Render the LTI launch form."

---

### `classes/output/view_page.php` → fixup `098075a`

- [ ] Add `use` block after `namespace` line:
  ```php
  use moodle_url;
  use renderable;
  use renderer_base;
  use stdClass;
  use templatable;
  ```
- [ ] Add `@package    mod_naas` to file-level docblock
- [ ] Change copyright `2019` → `2019 onwards` (file and class docblocks)
- [ ] Add `@since      mod_naas 1.0.0` to class docblock
- [ ] `class view_page implements \renderable, \templatable` → `implements renderable, templatable`
- [ ] `public \moodle_url $courseurl` → `public moodle_url $courseurl`
- [ ] `public ?\stdClass $nextactivity` → `public ?stdClass $nextactivity`
- [ ] Constructor `@param` tags: remove `\` from `\moodle_url` and `\stdClass`
- [ ] Expand constructor signature to multiline (one param per line)
- [ ] `export_for_template($output): array` → `export_for_template(renderer_base $output): stdClass`
- [ ] Change `@param \renderer_base` → `@param renderer_base`
- [ ] Change `@return array` → `@return stdClass`
- [ ] Rewrite `export_for_template` body: replace array literal with `stdClass` object:
  ```php
  $context = new stdClass();
  $context->courseurl = $this->courseurl->out(false);
  $context->backtocourse = $this->backtocourse;
  $context->widgethtml = $this->widgethtml;
  $context->hasnextactivity = false;
  if (!empty($this->nextactivity)) {
      $context->hasnextactivity = true;
      $context->nextactivityname = $this->nextactivity->name;
      $nexturl = new moodle_url($this->nextactivity->link);
      $nexturl->param('forceview', 1);
      $context->nextactivityurl = $nexturl->out(false);
  }
  return $context;
  ```
  Note: `new \moodle_url(...)` → `new moodle_url(...)` (no backslash, covered by use import)

---

### `classes/output/index_page.php` → fixup `69920a3`

- [ ] Add `use` block after `namespace` line:
  ```php
  use moodle_url;
  use renderable;
  use renderer_base;
  use stdClass;
  use templatable;
  ```
- [ ] Add `@package    mod_naas` to file-level docblock
- [ ] Change copyright `2019` → `2019 onwards` (file and class docblocks)
- [ ] Add `@since      mod_naas 1.0.0` to class docblock
- [ ] `class index_page implements \renderable, \templatable` → `implements renderable, templatable`
- [ ] `public \moodle_url $courseurl` → `public moodle_url $courseurl`
- [ ] Constructor `@param \moodle_url` → `@param moodle_url`
- [ ] Expand constructor signature to multiline
- [ ] `export_for_template($output): array` → `export_for_template(renderer_base $output): stdClass`
- [ ] Change `@param \renderer_base` → `@param renderer_base`
- [ ] Change `@return array` → `@return stdClass`
- [ ] Rewrite `export_for_template` body with `stdClass`:
  ```php
  $context = new stdClass();
  $context->courseurl = $this->courseurl->out(false);
  $context->backtocourse = $this->backtocourse;
  $context->heading = $this->heading;
  $context->usesections = $this->usesections;
  $context->rows = $this->rows;
  return $context;
  ```

---

### `classes/output/lti_launch_form.php` → fixup `4d2d718`

- [ ] Add `use` block after `namespace` line:
  ```php
  use renderable;
  use renderer_base;
  use stdClass;
  use templatable;
  ```
- [ ] Add `@package    mod_naas` to file-level docblock
- [ ] Change copyright `2019` → `2019 onwards` (file and class docblocks)
- [ ] Add `@since      mod_naas 1.0.0` to class docblock
- [ ] `class lti_launch_form implements \renderable, \templatable` → `implements renderable, templatable`
- [ ] `export_for_template($output): array` → `export_for_template(renderer_base $output): stdClass`
- [ ] Change `@param \renderer_base` → `@param renderer_base`
- [ ] Change `@return array` → `@return stdClass`
- [ ] Rewrite `export_for_template` body with `stdClass`:
  ```php
  $context = new stdClass();
  // The launchurl is expected to be validated via clean_param before
  // instantiating this class, so it's safe to pass to the template here.
  $context->launchurl = $this->launchurl;
  $context->fields = $this->fields;
  return $context;
  ```

---

## Group 2 — Widget loading mechanism (target: fixup `7809d01`)

Both files must be committed together as a single fixup.

### `classes/naas_widget.php`

- [ ] Remove the line: `$PAGE->requires->js_call_amd('mod_naas/widget_init', 'init', []);`
- [ ] Add `'moodle_url' => $CFG->wwwroot,` to the `render_from_template` call context array
- [ ] Reformat the `render_from_template` call to multiline:
  ```php
  return $OUTPUT->render_from_template('mod_naas/naas_widget', [
      'configjson' => json_encode($widgetconfig),
      'moodle_url' => $CFG->wwwroot,
  ]);
  ```

### `templates/naas_widget.mustache`

- [ ] Replace the single `<div>` line with:
  ```html
  <script>
      window.NAAS = {{{configjson}}};
  </script>

  <div id="naas_widget"></div>

  <script src="{{moodle_url}}/mod/naas/assets/vue/naas_widget-2026030300.js"></script>
  ```
  Note: triple-mustache `{{{configjson}}}` (unescaped) is intentional — the value is JSON, not HTML.

---

## Step N — Collapse fixups and cascade rebase

```bash
# Collapse fixup commits into their targets
git rebase -i --autosquash master

# Fast-forward feat/vue3_migration (it has no unique commits)
git checkout feat/vue3_migration
git reset --hard feat/output_api

# Rebase the main work branch
git checkout chore/small_enhancements_2025
git rebase feat/output_api
```
