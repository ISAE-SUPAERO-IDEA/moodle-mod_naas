# NaaS Plugin — Security Hardening Backlog

Epics to protect the NaaS API from being abused through this Moodle plugin acting as a proxy.

The threat model: an authenticated Moodle user (student, teacher, or compromised account) crafts or replays webservice calls to extract data or exhaust NaaS API resources. The NaaS API credentials live in Moodle config and are invisible to the browser, but every authenticated Moodle user can call the webservices listed in `db/services.php`.

---

## Epic S1 — Strict Input Validation on All Proxy Endpoints

**Risk:** `PARAM_TEXT` allows arbitrary strings. A caller can pass a path-traversal segment as `nuggetId`, `versionId`, `domainKey`, `structureKey`, or `personKey` (e.g. `../admin/users`) and the plugin will forward it to the NaaS API URL without sanitisation.

**Stories:**

- S1.1 — Define and enforce an allowlist regex for ID/key parameters (UUIDs, slugs). Reject anything not matching before the URL is built. Use `PARAM_ALPHANUMEXT` or a custom `clean_param` filter.
- S1.2 — Validate that `version_id` in `post_xapi_statement` is a known UUID format (RFC 4122) before forwarding to NaaS.
- S1.3 — Validate the `verb` parameter in `post_xapi_statement` against a fixed allowlist (`experienced`, `completed`, `rated`). Any other verb must return a 400 and must not reach the NaaS API.
- S1.4 — Validate the `body` JSON in `post_xapi_statement` against a schema before forwarding. Reject bodies exceeding a configurable size limit (default 4 kB).
- S1.5 — Add a unit test fixture for each endpoint with boundary and injection inputs to lock the validation in place.

---

## Epic S2 — Rate Limiting per User

**Risk:** Any enrolled student can call `search_nuggets` or `get_person` in a tight loop, either to enumerate NaaS data or to exhaust the API quota associated with the Moodle credentials.

**Stories:**

- S2.1 — Implement a Moodle MUC-backed rate limiter class (`\mod_naas\rate_limiter`) that tracks call counts per `(user_id, function_name)` with a rolling 60 s window.
- S2.2 — Apply the rate limiter to all proxy endpoints. Default limits: `search_nuggets` 10 req/min, entity lookups (`get_person`, `get_domain`, `get_structure`) 60 req/min, `view_nugget` 5 req/min, `post_xapi_statement` 20 req/min.
- S2.3 — Surface limits as Moodle admin settings so operators can tune them without a code change.
- S2.4 — On limit exceeded, return a Moodle exception with a 429-equivalent user message (no internal detail exposed).

---

## Epic S3 — Capability Audit and Least-Privilege Enforcement

**Risk:** `mod_naas:addinstance` is granted to teachers/editors, but `search_nuggets` and `get_nugget_preview` require it, meaning any teacher in any course can call these endpoints to search the full NaaS catalogue regardless of whether they have a NaaS activity in that course.

**Stories:**

- S3.1 — Audit every webservice capability requirement against who should realistically call it. Document the decision in `db/services.php` comments.
- S3.2 — Introduce a dedicated `mod/naas:search` capability, separate from `addinstance`, assigned to teachers by default. This decouples catalogue browsing from module creation rights.
- S3.3 — For `view_nugget`, add a check that the `cmId` belongs to a course the calling user is actually enrolled in (beyond `require_capability`), preventing cross-course data leaks if a user guesses a valid `cmId`.
- S3.4 — For `post_xapi_statement`, verify that the `id` (cmId) refers to a module the calling user can currently view, not just any naas module site-wide.

---

## Epic S4 — Credential and Secret Management

**Risk:** NaaS credentials (`naas_username`, `naas_password`) are stored in Moodle config as plain text and visible to any Moodle admin. If the admin panel or database is compromised, the NaaS API credentials leak.

**Stories:**

- S4.1 — Add a `naas_password` field in `admin_settings.php` typed as `admin_setting_configpasswordunmask` so the value is obfuscated in the UI.
- S4.2 — Document a recommended pattern for storing the password in an environment variable (`NAAS_API_PASSWORD`) and reading it in `naas_client.php` as `getenv('NAAS_API_PASSWORD') ?: get_config('naas', 'naas_password')`, so production deployments can avoid storing secrets in the DB.
- S4.3 — Add `curl` timeout, retry, and circuit-breaker logic in `naas_client` so a slow or misbehaving NaaS endpoint cannot hold Moodle PHP workers indefinitely.
- S4.4 — Enable `CURLOPT_SSL_VERIFYPEER` by default; the current code sets it to `false`. Provide an admin toggle only for development environments.

---

## Epic S5 — Output Sanitisation and Response Hardening

**Risk:** The plugin forwards raw NaaS API responses to the browser via `PARAM_RAW`. If the NaaS API were to return a response containing script tags or HTML (e.g. in a `description` field), they would be passed directly to the Vue layer and potentially rendered as HTML.

**Stories:**

- S5.1 — In each webservice `_returns()` declaration, replace `PARAM_RAW` with a structured `\external_single_structure` matching the expected NaaS response schema. Moodle will then strip unexpected fields.
- S5.2 — Where structured returns are not feasible short-term, parse and re-encode the JSON server-side (`json_decode` → `json_encode`) to strip any binary or control characters before returning to the browser.
- S5.3 — In Vue components, ensure nugget text fields (`name`, `description`, `in_brief`) are always rendered via `{{ }}` interpolation (auto-escaped), never via `v-html`. Audit all templates and add a linting rule (`vue/no-v-html`) to enforce this.
- S5.4 — Set a `Content-Security-Policy` for the NaaS LTI iframe using the `sandbox` attribute and `allow-scripts allow-same-origin`. This limits what a compromised or malicious NaaS content page can do within Moodle.

---

## Epic S6 — Audit Logging

**Risk:** There is currently no record of which Moodle user called which NaaS API endpoint, making it impossible to investigate abuse after the fact.

**Stories:**

- S6.1 — Add Moodle event classes for `nugget_viewed`, `nugget_searched`, `xapi_statement_sent` under `classes/event/`. Fire them at the start of each webservice call (before the NaaS request).
- S6.2 — Log the `nuggetId` / `cmId` / `verb` in each event so audit logs show exactly what was accessed, without logging credential material.
- S6.3 — Add an admin report page (`admin/naas_audit.php`) that queries the Moodle logstore and shows recent NaaS activity per user, filterable by date and event type.
- S6.4 — Trigger a Moodle notification to the admin if a single user exceeds 500 NaaS calls in a day (anomaly threshold, configurable).

---

## Epic S7 — Dependency and Build Security

**Risk:** The IIFE bundle ships `moment.js`, `vue-i18n`, `pinia`, and `iframe-resizer`. Any vulnerability in these dependencies is shipped directly to every learner's browser on every page load.

**Stories:**

- S7.1 — Add a `npm audit` step to the CI/CD pipeline that fails the build on high/critical vulnerabilities.
- S7.2 — Replace `moment.js` (40 kB gzip, unmaintained) with `date-fns` or native `Intl.DateTimeFormat`. This reduces bundle size and removes a known source of CVEs.
- S7.3 — Pin exact dependency versions in `package.json` (remove `^`) and commit a `package-lock.json` so the build is reproducible and dependency upgrades are explicit and reviewable.
- S7.4 — Add a `Subresource Integrity` check or hash comparison in `widget_init.js` to verify the loaded bundle matches the expected hash, catching CDN or file-system tampering.
