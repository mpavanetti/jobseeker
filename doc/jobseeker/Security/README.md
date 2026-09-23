# Security

This directory records the JobSeeker security model and the hardening work done
against it.

- [command-hardening.md](command-hardening.md) - screening operator-authored job
  commands and the container controls that contain a hostile job.

## Model (summary)

- **Authentication**: database-backed sessions (`sess_driver = database`),
  `password_hash`/`password_verify` (bcrypt), session ID regenerated on login,
  `sess_regenerate_destroy = TRUE`. Cookies are `httponly`; `cookie_secure`
  follows `JOBSEEKER_COOKIE_SECURE` (enable for HTTPS).
- **Authorization**: `ROLE_ADMIN` / `ROLE_MANAGER` / `ROLE_EMPLOYEE`. Every
  interactive controller calls `isLoggedIn()` in its constructor; job- and
  pipeline-management actions additionally check the manager/admin roles.
- **CSRF**: `csrf_protection = TRUE` for all state-changing requests. The two
  excluded routes authenticate by other means - `jenkins/proxy` verifies the
  CSRF hash itself with `hash_equals`, and `connector-runtime` requires the
  `JOBSEEKER_CONNECTOR_API_TOKEN` bearer token.
- **Jenkins**: the browser never receives Jenkins credentials. All Jenkins
  traffic is proxied server-side; the proxy builds its own `Authorization`
  header from the runtime config.
- **Secrets**: connector values and governed data-source credentials are
  encrypted at rest with the CodeIgniter Encryption library
  (`JOBSEEKER_ENCRYPTION_KEY`). Connector secrets are materialized only for the
  duration of a build.
- **Insight Studio**: datasets and fields resolve through server-side
  allowlists; the connected-database path parameterizes all values and quotes
  every identifier (`quoteIdentifier`).

## Hardening pass - 2026-09

### Fixed

| Severity | Issue | Fix |
| --- | --- | --- |
| **High** | The setup wizard (`/setup/*`) was reachable **unauthenticated** on a provisioned instance. `POST /setup/saveJenkins` rewrites `application/config/config.json` (Jenkins URL + credentials); `POST /setup/databaseCheck` opens a server-side database connection to an arbitrary host (SSRF / internal port probe); `testJenkinsApi` enumerates Jenkins. | First guarded with `Setup::guardSetupAccess()` (admin session required once the instance was provisioned), then **removed entirely** - see the note below. |
| **Medium** | `jenkins.authorization` (the Basic-auth blob from the runtime config) was emitted into page source as `var jenkins_authorization = '...'` on Job List, Job Creation and TMF. Dead in the current code, but a real credential leak the moment an operator fills the setup "API Authorization" field. | Removed every emission (`BaseController`, `Setup`, `jobList.php` x4, `jobCreation.php`, `tmf.php`). Jenkins auth stays server-side only. |
| **Low** | `users.php` reflected the `searchText` query value into an `<input value="...">` without escaping (reflected XSS). | `html_escape()`. |
| **Low** | In production the only secret check was `empty()` on `JOBSEEKER_ENCRYPTION_KEY` - the documented placeholder values and short keys passed. | `config.php` now logs a clear error when `JOBSEEKER_ENCRYPTION_KEY` or `JOBSEEKER_CONNECTOR_API_TOKEN` is still a shipped placeholder or below the recommended length. (A warning, not a hard stop, because the documented local-evaluation stack also runs with `ENVIRONMENT=production`.) |

**Update - the setup wizard has since been deleted.** Nothing in the application
linked to it, two of its four steps were inert, the database step only tested a
connection and never wrote `database.php`, and `saveJenkins()` rewrote
`application/config/config.json` from a fixed template - silently dropping
`environment_slots`, `environment_agents_enabled` and `environment_agent_labels`,
which `BaseController` reads. The credentials it wrote were overridden by
`JOBSEEKER_JENKINS_INTERNAL_URL` / `_USER` / `_TOKEN` in every shipped deployment
anyway. Runtime configuration now comes from `.env` and the Kubernetes ConfigMap
only; `scripts/test-security-hardening.js` asserts the wizard stays deleted.

### Follow-up pass - configuration and transport

| Severity | Issue | Fix |
| --- | --- | --- |
| **Medium** | `application/config/config.json` is tracked in git and carried `jenkins.username` / `jenkins.token`. Nothing read them - Docker Compose and Kubernetes both set `JOBSEEKER_JENKINS_USER` / `_TOKEN`, which take precedence - but the slot invited a real token being committed. | Both keys removed from the file and from the Kubernetes ConfigMap, and `BaseController::jenkinsConnection()` no longer accepts a credential from the runtime config at all. `scripts/test-jenkins-runtime-config.js` asserts they stay out. |
| **Low** | The session cookie carried no `SameSite` attribute. CodeIgniter 3.1.10 sets its cookies through the positional `setcookie()` / `session_set_cookie_params()` signatures, neither of which takes one. Browsers default an unset value to Lax, but only after a grace period on top-level POSTs. | `docker/php/security.ini` sets `session.cookie_samesite = Lax`, installed into the image's `conf.d`. Verified on the wire: `ci_sessions=...; HttpOnly; SameSite=Lax`. The CSRF cookie still lacks it - CI sets that one directly - but it carries only the token, and the protection is the token matching a value an attacker cannot read. |
| **Low** | `X-Powered-By: PHP/8.3.33` disclosed the exact patch version on every response. | `expose_php = Off` in the same ini. |

**Also reviewed in this pass, no change needed:** the connector runtime endpoint
(`/connector-runtime`) authenticates with `hash_equals` against
`JOBSEEKER_CONNECTOR_API_TOKEN`, rejects an empty expected token, normalises
environment and job name against strict allowlists, scopes every lookup through
parameterised `where_in` on environment plus job, sends `no-store`, and writes a
granted/failed audit row per connector. No SQL is built by concatenating request
data; the identifiers interpolated in `Context_model` / `Visualization_model` /
`Dashboard_model` are code literals, never request input. No application-level
shell execution exists outside the CodeIgniter framework's own helpers.

### Reviewed - no change needed

- **SQL injection**: the query builder is used with bound parameters or
  `$this->db->escape()` throughout; dynamic SQL fragments in `Dashboard_model`
  are static expressions or escaped values; the connected-DB path in
  `Visualization_model` binds every value and quotes identifiers with
  backtick/quote doubling and a control-character reject.
- **Command execution**: no `shell_exec`/`exec`/`system`/`eval`/`unserialize`
  of request data anywhere in the application. Job commands run only inside
  Jenkins (see command-hardening.md).
- **File upload** (`Upload::do_upload`): extension allowlist, `safeRelativePath`,
  `safeUploadFileName`, and a `realpath` within-base check before
  `move_uploaded_file`.
- **Password reset**: the token is stored as a SHA-256 hash, not in the clear.

### Open items / recommendations

- **Framework**: CodeIgniter **3.1.10**. 3.1.13 is the last 3.x release and
  carries security fixes (XSS in the Security library, cookie `SameSite`
  support). Plan an upgrade to 3.1.13; until then, set `SameSite=Lax` on the
  session/CSRF cookies at the reverse proxy.
- **Front-end dependencies** (not bumped here - each needs a visual regression
  pass of the AdminLTE UI):
  - `jquery-ui-dist` **1.12.1** (2016) has known XSS advisories
    (CVE-2021-41182/41183/41184, CVE-2022-31160). Move to 1.13.3.
  - `ckeditor` **4.12.1** is past end-of-life with several XSS CVEs. Used only
    for admin/manager-authored email templates rendered outside the app, so
    impact is limited, but move to CKEditor 4 LTS or 5.
  - `chart.js` 2.9.4 and `bootstrap-sass` 3.4.3 are EOL major lines; schedule
    upgrades.
  Run `npm audit` (and GitHub Dependabot) in CI and triage on each release.
- **Least privilege**: `Upload` accepts any authenticated role; consider
  restricting to manager/admin.
- **Transport**: terminate TLS at a trusted proxy, set `JOBSEEKER_COOKIE_SECURE=true`,
  and apply the container controls in command-hardening.md for any shared
  deployment.
