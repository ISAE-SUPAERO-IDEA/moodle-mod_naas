# Behat plan for `mod_naas`

End-to-end browser tests for Moodle integration (login, course, activity, view shell). Deep NAAS/LTI/xAPI behaviour stays in PHPUnit; Behat covers user-visible Moodle surfaces.

## Goals

| Priority | Goal | Out of scope (initially) |
|----------|------|----------------------------|
| P0 | CI runs meaningful plugin Behat scenarios (`moodle-plugin-ci behat`) | Assertions inside cross-origin iframes |
| P1 | Smoke: seeded NAAS activity opens for teacher/student without fatal errors | Real NAAS API or LRS |
| P2 | Optional: mod form / add-activity UI (flakier, slower) | Replacing PHPUnit for HTTP/xAPI/LTI |

## Deliverables

| Item | Path / artefact | Notes |
|------|-----------------|-------|
| Feature file(s) | `tests/behat/*.feature` | Tag `@mod @mod_naas`; use `@javascript` when needed (CI uses Chrome). |
| Custom steps (only if needed) | `tests/behat/behat_mod_naas.php` | Prefer core Behat steps + `mod_naas_generator` defaults. |
| Local run doc | This file § Runbook | Align with full Moodle tree or `moodle-plugin-ci`. |

## Phases

1. **Scaffold** — Add `tests/behat/` and one feature with 1–2 scenarios (generator-backed activity, open `view.php`).
2. **Assertions** — Stable selectors: page title/heading, wrapper `id` or `data-*` from PHP templates (not Vue internals).
3. **Roles** — Student with `mod/naas:view`; optional negative (no capability) if product requires it.
4. **Hardening** — Fix flakiness (waits, theme); expand only if stable.
5. **Optional** — Teacher creates activity via UI (separate feature, higher maintenance).

## Agent achievement log

Use one row per agent session (or per merged PR). **Agent** = Cursor agent / contributor id or date. **Achievement** = concrete outcome; link PR or commit when applicable.

| Date | Agent / author | Phase | Achievement | Status |
|------|----------------|-------|---------------|--------|
| 2026-05 | — | 1–2 | `tests/behat/*.feature` + `behat_mod_naas.php` (view, index, launch, settings) | ☑ |
| 2026-05-25 | Antigravity | 3 Roles | Added negative capability test in `mod_naas_roles.feature` | ☑ |
| 2026-05-25 | Antigravity | 4 Hardening | Handled LTI exception gracefully in `launch.php` and fixed strict text matching in roles | ☑ |
| 2026-05-25 | Antigravity | 5 Optional UI | Skipped due to extreme flakiness of JS edit mode in CI | ☑ |

**Status legend:** ☐ not started · ◐ in progress · ☑ done (update table after each session).

## Runbook (local)

From a full Moodle install with Behat configured, or using the same flow as `.github/workflows/ci.yml` (`moodle-plugin-ci install` then `moodle-plugin-ci behat --profile chrome`). Adjust paths to your Moodle root and `MOODLE_DATAROOT`.

Example (typical Moodle Behat, not copy-paste gospel):

```bash
# From Moodle dir, after admin/tool/behat CLI init — see Moodle docs for your branch.
php admin/tool/behat/cli/run.php --tags="@mod_naas"
```

## Including in full-site (“global”) Behat

Moodle does **not** require a manifest in the plugin: any `mod/<plugin>/tests/behat/*.feature` file is picked up when Behat runs against an install that contains the plugin.

1. **Install the plugin** in the Moodle tree your Docker (or CI) instance uses (`mod/naas` present and upgraded).
2. **Refresh the Behat test environment** after adding or changing feature files (from Moodle root, inside the PHP container if you use Docker). Typical sequence is `php admin/tool/behat/cli/util.php --install` (or `init.php` if that is what your image documents); that regenerates the Behat config so new feature paths are registered.
3. **Run the full suite** with no `--tags` (or only exclusions you intend). Example from Moodle root:
   `php admin/tool/behat/cli/run.php --profile chrome`
   Your scenarios are tagged **`@mod`** and **`@mod_naas`**, so they are included whenever the tag expression allows `@mod` (or is empty).
4. **If your global command passes `--tags`**, extend it so `@mod` or `@mod_naas` is not excluded. For example, a filter that only runs `@core` will **skip** all activity-module features unless you add something like `(@core||@mod_naas)` (adjust to your team’s convention).
5. **If you set `$CFG->behat_config['<profile>']['filters']['tags']` in `config.php`**, merge in `@mod_naas` or `@mod` the same way: profile tags apply unless overridden by the CLI `--tags` argument (see `admin/tool/behat/cli/run.php`).

Docker: run the same `php` commands **inside the container** whose filesystem is the Moodle code root; Selenium must reach `$CFG->behat_wwwroot` from the browser container.

### Moodle Docker (`moodlehq/moodle-docker`)

The stock **`config.docker-template.php`** in the moodle-docker repo already defines Behat. If `init.php` fails with *`$CFG->behat_dataroot, $CFG->behat_prefix and $CFG->behat_wwwroot need to be set`*, your **`$MOODLE_DOCKER_WWWROOT/config.php`** is not that template (for example a hand-written `config.php`). Add at least:

```php
$CFG->behat_wwwroot   = 'http://webserver';
$CFG->behat_dataroot  = '/var/www/behatdata';
$CFG->behat_prefix    = 'b_';
```

Then add Selenium wiring like the template (so the browser can run scenarios), for example:

```php
$CFG->behat_profiles = array(
    'default' => array(
        'browser' => getenv('MOODLE_DOCKER_BROWSER') ?: 'firefox',
        'wd_host' => 'http://selenium:4444/wd/hub',
    ),
);
```

Copy the full Behat block from [moodle-docker `config.docker-template.php`](https://github.com/moodlehq/moodle-docker/blob/main/config.docker-template.php) (lines with `behat_*` and `behat_faildump_path` if you use faildumps) and merge into your `config.php`. Use `bin/moodle-docker-compose` with the **selenium** service up (default stacks include it). Official steps: moodle-docker README section *Use containers for running behat tests*.

## References

- CI: `.github/workflows/ci.yml` (Behat step + faildump upload).
- Data generator defaults: `tests/generator/lib.php` (`mod_naas_generator`).
