# PHP Code Quality — mod_naas

## Overall score: 3.1 / 5

---

## Scores by category

| Category | Score | Justification |
|---|---|---|
| Architecture & design | 3.5 / 5 | Clean namespace structure, proper Moodle hooks, renderable/templatable pattern in place. Static-only utility classes (`naas_widget`, `mod_util`) limit testability. No repository or service layer. |
| Error handling | 2.5 / 5 | Good HTTP error handling in `naas_client.php`. Inconsistent elsewhere: silent failures in some paths, missing null checks in `outcome.php`, weak error context in exception messages. |
| Security | 3.0 / 5 | Capability checks, PARAM_* filtering, SSL verification, privacy API, UUID allowlist. Offset by hardcoded test credentials in `settings.php`, unvalidated XML in `outcome.php` (XXE risk), and `PARAM_TEXT` where a stricter type would fit. |
| Type safety | 2.5 / 5 | Docblocks present but inconsistent. Most functions untyped. Mixed return types (`handle_result()` returns mixed). No interfaces on public API classes. Recent output classes improved with proper type hints. |
| Documentation | 3.5 / 5 | Moodle-standard docblocks on most functions. GPL headers present. Inline comments sparse on complex logic. `@throws` missing on many throwing functions. Generic variable names (`$res`, `$data`, `$config`). |
| Code consistency | 2.5 / 5 | Mixed array syntax (`[]` vs `array()`). Mixed naming conventions (`nugget_id` vs `courseId` in the same config array). Incomplete refactoring markers in `lib.php`. Some dead code paths. |
| Moodle standards | 4.0 / 5 | Follows plugin structure, uses Moodle APIs (forms, capabilities, events, privacy). DB schema uses standard upgrade mechanism. Output API correctly implemented. |
| Testing | 1.0 / 5 | No unit tests. No integration tests. Manual test-connection in settings only. Critical paths (LTI flow, xAPI outcome, grading) fully untested. |

---

## Enhancement backlog

### Critical (security / correctness)

| # | File | Issue | Suggested fix |
|---|---|---|---|
| C1 | `outcome.php` | Unvalidated XML parsed with SimpleXML — XXE risk | Add `LIBXML_NONET` flag and schema validation before parsing |
| C2 | `settings.php` | Hardcoded test credentials (`api_url`, `api_key`, `api_secret`) shipped in source | Remove defaults entirely or replace with placeholder strings |
| C3 | `outcome.php` | No null check on `$records` before `foreach` | Add `if (empty($records))` guard |
| C4 | `naas_lti.php` | `export_for_template()` called directly with `$OUTPUT` (bootstrap_renderer) | Call through `$OUTPUT->render($form)` instead of direct call |

### High (code quality)

| # | File | Issue | Suggested fix |
|---|---|---|---|
| H1 | `naas_client.php` | Mixed HTTP transport and business logic in one class | Extract `NaasHttpClient` (raw curl) from `NaasApiClient` (response parsing) |
| H2 | `lib.php` | Direct DB calls scattered in lifecycle functions | Introduce a `NaasRepository` class |
| H3 | all classes | No interfaces on public API classes | Define `INaasClient`, `INaasRepository` interfaces |
| H4 | `naas_widget.php` | `nugget_id` (snake_case) mixed with `courseId` (camelCase) in same array | Standardise to `snake_case` throughout config payload |
| H5 | all | `$res`, `$data`, `$config`, `$params` as variable names | Use descriptive names: `$nuggetData`, `$launchConfig`, etc. |

### Medium (type safety / robustness)

| # | File | Issue | Suggested fix |
|---|---|---|---|
| M1 | all | Functions without return type hints | Add PHP 8 return types (`string`, `array`, `?stdClass`) |
| M2 | all | Mixed `[]` / `array()` syntax | Standardise to short `[]` syntax |
| M3 | `naas_client.php` | `handle_result()` returns mixed | Split into typed methods per return shape |
| M4 | `proxy_naas_api.php` | `structure_id` validated with `PARAM_TEXT` | Use `PARAM_ALPHANUMEXT` or custom regex |
| M5 | all | Missing `@throws` in docblocks | Document all thrown exceptions |

### Low (polish)

| # | Area | Issue | Suggested fix |
|---|---|---|---|
| L1 | `lib.php` | Magic grading constants (`NAAS_GRADEHIGHEST` etc.) without description | Add inline docblock explaining grading semantics |
| L2 | DB schema | `sourced_id` not marked unique | Add unique index in `upgrade.php` |
| L3 | DB schema | No foreign key constraints visible | Document expected relationships in `install.xml` comments |
| L4 | `settings.php` | 13 flat config options with no grouping | Group into collapsible sections (basic, advanced, debug) |
| L5 | all | No PHPUnit test suite | Add `tests/` directory with at minimum unit tests for `naas_client` and `naas_lti` |
