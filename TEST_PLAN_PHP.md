# PHP Test Plan — mod_naas

Target: **90 % line coverage**, all critical paths covered.
Framework: **PHPUnit** via Moodle's test runner.

---

## 🎯 Current Status & Next Steps (Updated)

Based on the latest coverage report (Total: 63.41%), several components are highly covered (`completion`, `output`, `privacy`, `naas_lti`, `external`). The remaining missing coverage is primarily due to two factors:

1. **Undiscovered Test Files (`mod_util`, `naas_widget`, `event`)**: 
   The tests for these classes (`mod_util_test.php`, `naas_widget_test.php`, `renderer_test.php`) have already been written but are showing 0% coverage. This is because Moodle's `phpunit.xml` configuration has not been rebuilt to discover the new test files. 
   * **Action**: Run `php admin/tool/phpunit/cli/init.php` inside the Docker container, then run PHPUnit again. This will instantly jump the coverage up.

2. **`naas_client.php` (25.62% covered)**:
   The coverage is low because `naas_client_test.php` uses a subclass `testable_naas_client` that entirely overrides `request_raw()`. Since `request_raw()` contains 50+ lines of network connection logic, those lines are completely skipped during tests.
   * **Action**: We need to implement an `HttpAdapter` interface (as defined in `QUALITY_PHP.md`) to inject a fake HTTP layer into `naas_client`. This will allow us to test the real `request_raw()` logic without making actual network calls.

---

## 1. Setup

### Directory structure to create
```
tests/
├── fixtures/
│   ├── lti_response_valid.xml
│   ├── lti_response_invalid.xml
│   ├── nugget_api_response.json
│   └── xapi_statement_valid.json
├── lib_test.php
├── naas_client_test.php
├── naas_lti_test.php
├── outcome_test.php
├── completion/
│   └── custom_completion_test.php
├── external/
│   ├── proxy_naas_api_test.php
│   └── xapi_test.php
├── output/
│   ├── index_page_test.php
│   ├── lti_launch_form_test.php
│   └── view_page_test.php
└── privacy/
    └── provider_test.php
```

### Run tests
```bash
# All plugin tests
vendor/bin/phpunit --testsuite mod_naas

# Single file
vendor/bin/phpunit mod/naas/tests/naas_client_test.php

# With coverage report (requires Xdebug or PCOV)
vendor/bin/phpunit --testsuite mod_naas --coverage-html coverage/
```

### phpunit.xml entry to add
```xml
<testsuite name="mod_naas">
    <directory suffix="_test.php">mod/naas/tests</directory>
</testsuite>
```

---

## 2. Test files

---

### `tests/naas_client_test.php`

Class under test: `mod_naas\naas_client`
Base class: `advanced_testcase`
Dependencies to mock: `curl` (use a test double or HTTP adapter injection)

**Prerequisite refactor:** extract an `HttpAdapter` interface so tests can inject a fake HTTP layer without real network calls.

| Test method | Scenario | Expected result |
|---|---|---|
| `test_get_nugget_returns_parsed_object` | Valid JSON response from API | Returns typed nugget object |
| `test_get_nugget_throws_on_404` | API returns 404 | Throws `moodle_exception` |
| `test_get_nugget_throws_on_network_error` | curl fails (no connection) | Throws `moodle_exception` with meaningful message |
| `test_search_nuggets_returns_array` | Valid search response | Returns non-empty array |
| `test_search_nuggets_empty_query_returns_all` | Empty query string | Returns results without filtering |
| `test_auth_header_uses_configured_credentials` | Credentials set in config | Auth header matches expected format |
| `test_ssl_verification_disabled_when_configured` | `naas_ssl` config = false | curl option `CURLOPT_SSL_VERIFYPEER` = false |
| `test_cache_hit_skips_http_call` | Second call for same resource | HTTP adapter called once only |
| `test_cache_miss_after_ttl_expires` | TTL exceeded | HTTP adapter called again |
| `test_get_domain_returns_cached_result` | Domain fetched twice | Second call served from cache |
| `test_password_read_from_env_var` | `NAAS_PASSWORD` env set | Client uses env value, not DB config |

---

### `tests/naas_lti_test.php`

Class under test: `mod_naas\naas_lti`
Base class: `advanced_testcase`
Dependencies: real DB (use `resetAfterTest`), mock `naas_client`

| Test method | Scenario | Expected result |
|---|---|---|
| `test_lti_launch_generates_valid_form` | Valid nugget and user | Returns HTML containing `<form>` with correct action URL |
| `test_lti_launch_throws_on_invalid_nugget_id` | Non-existent nugget | Throws `moodle_exception` |
| `test_lti_launch_includes_oauth_signature` | Standard launch | Rendered form contains `oauth_signature` field |
| `test_lti_launch_records_session_in_db` | Successful launch | Row inserted in `naas_activity_outcome` |
| `test_lti_launch_reuses_existing_session` | Session already exists | No duplicate row inserted |
| `test_lti_launch_with_bootstrap_renderer` | `$OUTPUT` is `bootstrap_renderer` | No TypeError — renders correctly |
| `test_oauth_signature_changes_per_request` | Two launches same nugget | `oauth_nonce` differs between calls |

---

### `tests/outcome_test.php`

File under test: `outcome.php`
Base class: `advanced_testcase`

| Test method | Scenario | Expected result |
|---|---|---|
| `test_valid_outcome_updates_grade` | Well-formed XML, valid session | Grade updated in gradebook |
| `test_malformed_xml_throws_exception` | Garbage XML body | Throws exception, no DB write |
| `test_xxe_payload_rejected` | XML with `DOCTYPE` entity expansion | Entity not resolved, exception thrown |
| `test_unknown_sourced_id_throws` | `sourcedId` not in DB | Throws `moodle_exception` |
| `test_score_boundary_at_zero` | Score = 0.0 | Grade set to 0 |
| `test_score_boundary_at_one` | Score = 1.0 | Grade set to max grade |
| `test_score_out_of_range_throws` | Score = 1.5 | Throws `invalid_parameter_exception` |
| `test_requires_valid_session_token` | Tampered session token | Request rejected |

---

### `tests/external/proxy_naas_api_test.php`

Class under test: `mod_naas\external\proxy_naas_api`
Base class: `externallib_advanced_testcase`

| Test method | Scenario | Expected result |
|---|---|---|
| `test_get_nugget_requires_login` | Unauthenticated call | Throws `require_login` exception |
| `test_get_nugget_requires_enrolment` | User not enrolled in course | Throws `moodle_exception` |
| `test_get_nugget_valid_uuid` | Valid UUID format | Returns nugget data |
| `test_get_nugget_invalid_uuid_rejected` | UUID with SQL characters | Throws `invalid_parameter_exception` |
| `test_get_nugget_slug_with_path_traversal_rejected` | Slug = `../../etc/passwd` | Throws `invalid_parameter_exception` |
| `test_search_nuggets_returns_array` | Valid search call | Returns array matching schema |
| `test_get_domain_caches_result` | Domain fetched twice | Second call returns identical result |
| `test_get_structure_requires_valid_id` | Structure ID with spaces | Throws `invalid_parameter_exception` |
| `test_post_xapi_statement_requires_valid_verb` | Verb not in allowlist | Throws `invalid_parameter_exception` |
| `test_post_xapi_statement_body_size_limit` | Body > allowed size | Throws `invalid_parameter_exception` |

---

### `tests/external/xapi_test.php`

Class under test: `mod_naas\external\xapi`
Base class: `externallib_advanced_testcase`

| Test method | Scenario | Expected result |
|---|---|---|
| `test_post_statement_valid` | Valid xAPI statement | Returns success, statement forwarded |
| `test_post_statement_invalid_verb` | Verb not in allowlist | Exception thrown |
| `test_post_statement_requires_enrolment` | Unenrolled user | Exception thrown |
| `test_completion_triggered_on_completed_verb` | Verb = `completed` | Activity marked complete in Moodle |
| `test_completion_not_triggered_on_other_verbs` | Verb = `experienced` | Completion status unchanged |

---

### `tests/output/view_page_test.php`

Class under test: `mod_naas\output\view_page`
Base class: `basic_testcase`

| Test method | Scenario | Expected result |
|---|---|---|
| `test_export_for_template_returns_stdclass` | Valid construction | `export_for_template()` returns `stdClass` |
| `test_courseurl_exported_as_string` | `moodle_url` passed | `context->courseurl` is a string |
| `test_no_next_activity_sets_flag_false` | `$nextactivity = null` | `context->hasnextactivity === false` |
| `test_next_activity_sets_flag_and_url` | Valid `$nextactivity` | `context->hasnextactivity === true`, URL contains `forceview=1` |
| `test_next_activity_url_has_forceview_param` | Next activity with link | URL contains `forceview=1` |

---

### `tests/output/index_page_test.php`

Class under test: `mod_naas\output\index_page`
Base class: `basic_testcase`

| Test method | Scenario | Expected result |
|---|---|---|
| `test_export_for_template_returns_stdclass` | Valid construction | Returns `stdClass` |
| `test_rows_exported_correctly` | Array of rows passed | `context->rows` matches input |
| `test_usesections_flag_exported` | `$usesections = true` | `context->usesections === true` |

---

### `tests/output/lti_launch_form_test.php`

Class under test: `mod_naas\output\lti_launch_form`
Base class: `basic_testcase`

| Test method | Scenario | Expected result |
|---|---|---|
| `test_export_for_template_returns_stdclass` | Valid construction | Returns `stdClass` |
| `test_fields_exported_correctly` | Array of fields passed | `context->fields` matches input |
| `test_launchurl_exported` | Valid URL passed | `context->launchurl` matches input |
| `test_accepts_any_renderer_type` | Pass `bootstrap_renderer` | No TypeError thrown |

---

### `tests/completion/custom_completion_test.php`

Class under test: `mod_naas\completion\custom_completion`
Base class: `advanced_testcase`

| Test method | Scenario | Expected result |
|---|---|---|
| `test_incomplete_by_default` | New activity, no xAPI | Returns `COMPLETION_INCOMPLETE` |
| `test_complete_after_completed_statement` | xAPI completed received | Returns `COMPLETION_COMPLETE` |
| `test_completion_isolated_per_user` | User A complete, User B not | Each user has independent state |
| `test_completion_isolated_per_activity` | Two activities, one completed | Only correct activity marked complete |

---

### `tests/privacy/provider_test.php`

Class under test: `mod_naas\privacy\provider`
Base class: `provider_testcase`

| Test method | Scenario | Expected result |
|---|---|---|
| `test_get_contexts_for_userid` | User has activity data | Returns correct context list |
| `test_export_user_data` | User data exists | Export contains nugget IDs and sessions |
| `test_delete_data_for_user` | Delete called for user | All `naas_activity_outcome` rows removed |
| `test_delete_data_for_context` | Delete called for context | Only rows for that context removed |
| `test_no_data_returns_empty_context_list` | User with no activity | Returns empty context list |

---

### `tests/lib_test.php`

Functions under test: `naas_add_instance`, `naas_update_instance`, `naas_delete_instance`, `naas_get_completion_state`
Base class: `advanced_testcase`

| Test method | Scenario | Expected result |
|---|---|---|
| `test_add_instance_creates_db_record` | Valid form data | Row created in `naas` table |
| `test_add_instance_returns_id` | Valid form data | Returns integer ID |
| `test_update_instance_modifies_record` | Updated nugget ID | DB row reflects new value |
| `test_delete_instance_removes_record` | Valid instance ID | Row removed from `naas` table |
| `test_delete_instance_removes_outcomes` | Instance with outcomes | `naas_activity_outcome` rows also removed |
| `test_grading_options_returns_all_modes` | Call `naas_get_grading_options()` | Returns array with 3 grading strategies |

---

## 3. Coverage targets

| Area | Target |
|---|---|
| `naas_client.php` | 95 % |
| `naas_lti.php` | 90 % |
| `outcome.php` | 95 % |
| `external/proxy_naas_api.php` | 90 % |
| `external/xapi.php` | 90 % |
| `output/*.php` | 100 % |
| `privacy/provider.php` | 95 % |
| `completion/custom_completion.php` | 100 % |
| `lib.php` (hooks only) | 80 % |
| **Overall** | **90 %** |

---

## 4. Implementation order

| Phase | Tasks | Effort |
|---|---|---|
| 1 — Infrastructure | Create `tests/` directory, `phpunit.xml` entry, fixture files, `HttpAdapter` interface | 1 day |
| 2 — Output classes | `view_page_test`, `index_page_test`, `lti_launch_form_test` — pure unit tests, no DB | 0.5 day |
| 3 — Core logic | `naas_client_test`, `outcome_test` | 2 days |
| 4 — LTI flow | `naas_lti_test` with DB and renderer | 1.5 days |
| 5 — External API | `proxy_naas_api_test`, `xapi_test` | 1.5 days |
| 6 — Privacy & completion | `provider_test`, `custom_completion_test` | 1 day |
| 7 — Lib hooks | `lib_test` | 0.5 day |
| 8 — CI integration | Add PHPUnit step to GitHub Actions workflow | 0.5 day |

**Total estimate: ~8.5 days**

---

## 5. CI integration (GitHub Actions)

```yaml
# .github/workflows/phpunit.yml
- name: Run PHPUnit
  run: |
    php admin/tool/phpunit/cli/init.php
    vendor/bin/phpunit --testsuite mod_naas --coverage-clover coverage.xml

- name: Upload coverage
  uses: codecov/codecov-action@v4
  with:
    files: coverage.xml
    flags: php
```
