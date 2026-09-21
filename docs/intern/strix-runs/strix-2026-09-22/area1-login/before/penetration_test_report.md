# Security Penetration Test Report

**Generated:** 2026-09-21 22:52:38 UTC

# Executive Summary

# Executive Summary

A focused, offensive security assessment of the **login and authentication** surface of the DiscipLink student-discipline application was performed against the authorized local instance `http://172.17.112.1:8001`, combining live attack traffic with source-code review of the login flow.

**Overall risk posture: Low-to-Moderate.** The authentication surface is well hardened. Of the classes tested — SQL injection, authentication bypass, user enumeration, brute-force/lockout, session management, CSRF, open redirect, information disclosure and password storage — all controls held under direct attack **except one**, a brute-force throttling weakness.

**Key finding**

- **Login lockout bypass via case-varied identifiers (High).** Account identifiers are matched case-insensitively in the database, but the failed-login throttle counts attempts against the *exact* submitted spelling. An attacker can vary the letter case of a target identifier (`ADMIN001`, `admin001`, `Admin001`, …) to obtain multiple independent attempt budgets for the *same* account, multiplying the number of password guesses before the 5-failures/15-minute lock engages.

**Controls confirmed effective (negative results, published as evidence)**

- No SQL injection (prepared statements, no error/timing/union signal); no authentication bypass (empty password, NUL byte, array injection, type juggling, JSON); no user enumeration (identical message and timing for valid and invalid users); session identifier rotates on login with `HttpOnly`/`SameSite` cookies; sessions are invalidated on logout; CSRF tokens are required and rotate on login; no open redirect; no stack traces or verbose errors; passwords stored as bcrypt cost-12 only.

**Business impact**

The single weakness does not by itself grant access, but it meaningfully shortens the path to credential compromise for accounts with predictable identifiers — including the administrator account — and therefore to the discipline-management data behind login. It is bounded by a per-source-IP throttle and is most relevant where an attacker can distribute attempts across source addresses. All other tested controls restrict unauthorized access as intended.

**Remediation theme**

Canonicalize the account identifier so that authentication and throttling key on the same value, and add a regression test that locks one spelling and proves a differently-cased spelling is also locked. Prioritized, concrete steps are given in the Recommendations section; the fix is low-effort and localized to the throttle helper and the login handler.

# Methodology

# Methodology

A **white-box, offensive** assessment of the **login / authentication** surface only, per the engagement's single-area scope.

**Frameworks:** OWASP WSTG (Authentication Testing: WSTG-ATHN-01/02/03/04/05; Session Management: WSTG-SESS-01/02/03/05; Input Validation) and CWE/OWASP Top 10:2021.

**Engagement type:** Gray-box — live black-box attacks against `http://172.17.112.1:8001` paired with source review of the mounted repository to trace the exact request path:

- `request/handler-login.php` — request flow, input guards, flash messages, throttle call.
- `controllers/UserController.php` — role sequence, session establishment, rotation.
- `models/User.php` — the credential lookup and bcrypt verification.
- `helpers/token_helper.php` — CSRF/session start, session expiry.
- `helpers/login_throttle_helper.php` — the durable (server-side) throttle and credential-input validation.
- `helpers/audit_helper.php`, `helpers/session_inventory_helper.php`, `router.php`, `config.php`.

**Scope:** `POST /action/login` plus the login page and the session/CSRF controls that gate it. News, uploads and admin CRUD were out of scope and untouched.

**Activities performed:**

1. **SQL injection** — error-based, boolean-blind, time-based (`SLEEP(5)`, `SLEEP` subquery, `pg_sleep`), UNION, stacked, comment (`--`, `#`), mixed-case, URL-encoded, backslash, and injection into `user_type`; all measured against a clean timing baseline.
2. **Authentication bypass** — empty password, NUL byte (username & password), array injection (`username[]`, `password[]`), type juggling, magic-hash values, trailing-space and non-breaking-space normalization, `application/json` body.
3. **User enumeration** — valid-user/wrong-password vs nonexistent-user differential on status, body message, redirect target and response timing.
4. **Brute-force / lockout** — confirmed the 5-failures/15-minute rule, then attempted to bypass it via fresh sessions, `X-Forwarded-For` rotation, case/encoding variation, and cross-account scope; verified window recovery.
5. **Session flaws** — identifier rotation on login (fixation), `Set-Cookie` flags, post-logout validity, replay of pre-login and post-login identifiers.
6. **CSRF** — presence/absence/wrong/cross-session token, and pre-login token reuse post-login.
7. **Open redirect** — `next` / `return` / `redirect` / `url` on GET and POST.
8. **Information disclosure** — stack traces, SQL errors, PHP warnings, HTTP method handling, sensitive-path access, and 404/405/419 shapes.
9. **Password storage & policy** — inferred hash type and fallback handling from source; observed policy from seeded data.

Every probe was executed against the live service; results were correlated against proxy-captured request/response timing.

**Constraints:** The durable throttle keys on the client's source IP, so failure-heavy probe suites locked the tester's own source address for 900 s twice during the run; this bounded the number of failure-generating probes and prevented one planned end-to-end confirmation (see Technical Analysis).

# Technical Analysis

# Technical Analysis

**Severity model:** Critical / High / Medium / Low / Info, justified by exploitability (Impact × Likelihood) and expressed as a CVSS v3.1 vector.

## Findings overview

| # | Finding | Severity | Status |
|---|---------|----------|--------|
| F-01 | Case-variant lockout bypass (CI lookup vs case-sensitive throttle key) | **High** (report vector scored 9.1; see calibration note) | Confirmed (lookup + key asymmetry), final vary-step bounded by IP cap |
| — | All other login controls | — | **Held** (see matrix) |

## F-01 — Case-variant lockout bypass

The authentication lookup is **case-insensitive** while the durable failed-login throttle keys on the **exact submitted string**:

```php title=models/User.php startLineNumber=34 endLineNumber=36
$stmt = $this->connect->prepare("SELECT * FROM {$table} WHERE {$identifierColumn} = ? LIMIT 1");
$stmt->execute([$username]);
```

```php title=helpers/login_throttle_helper.php startLineNumber=105 endLineNumber=105
$actor = substr($username, 0, 32);
```

The identity columns (`NIP`, `nidn`, `nim`) inherit the case-insensitive `utf8mb4_unicode_ci` collation, so `ADMIN001`, `admin001`, `Admin001` … all resolve to the *same account row* — but each produces its own throttle bucket, multiplying the per-account brute-force budget by the number of case permutations. Confirmed live: a wrong password for both `ADMIN001` and `admin001` reached the bcrypt cost-12 verifier (≈0.71–0.73 s), demonstrating the same row matched for both spellings.

**Calibration note:** the platform derived 9.1 (Critical) from the submitted vector (`C:H/I:H`, `AC:L`, `PR:N`, `UI:N`). The finding is stated as **High** here: the per-source-IP cap (15 failures / 900 s) still bounds a single-source attacker, and the final "lock one spelling, then succeed on a variant" step was not demonstrated end-to-end because the shared source IP was itself throttled. It is most impactful against distributed sources or when chained with the small, predictable identifier space.

## Defense matrix — controls that HELD (primary result of this run)

The login surface is **hardened**; the overwhelming majority of attempted attacks failed closed, each with recorded request/response evidence:

- **SQL injection (all variants)** — uniformly `302 → /login` at the baseline ~0.68–0.71 s, including `' OR SLEEP(5)-- -` (no delay) and `' UNION SELECT …`. Prepared statements with `ATTR_EMULATE_PREPARES=false` and bound parameters; `user_type` selects only from a fixed whitelist. No error, boolean, timing, or union signal.
- **Authentication bypass** — empty password ("wajib diisi"), NUL byte in username/password (rejected by `app_login_input_invalid`), array injection (`(string)` cast, no crash), magic-hash/type-juggling (`password_verify`, bcrypt-only rows), trailing/nbsp variants, and JSON bodies all failed to authenticate.
- **User enumeration** — valid-user/wrong-password and nonexistent-user responses are **identical** in status (200), message ("Invalid username or password.") and timing (0.682 s vs 0.676 s); the `DUMMY_PASSWORD_HASH` bcrypt verify equalizes the miss path.
- **Lockout** — 5 failures → locked on the 6th (same session); a **fresh session does not bypass** it (durable, audit-log-backed, CWE-307 fix); a different account is unaffected at the same IP; the lock survives a full 15-minute window and release was observed after it elapsed. `X-Forwarded-For` is ignored (throttle uses `REMOTE_ADDR` only).
- **Session management** — `PHPSESSID` **rotates** on successful login (`eqc57c…` → `vkbi8s…`); the pre-login identifier becomes invalid; `Set-Cookie` carries `HttpOnly; SameSite=Lax` (no `Secure` on plain HTTP, as expected); logout destroys the session and a replayed identifier is refused.
- **CSRF** — missing, wrong, and cross-session tokens all yield `419 Invalid CSRF token`; the token rotates on login, preventing pre-login reuse.
- **Open redirect** — `next`/`return`/`redirect`/`url` never influence the post-login target; canonical-host handling redirects only to a fixed host.
- **Information disclosure** — `display_errors=0` (no stack traces/SQL errors); `403` on `/.env`, `/config.php`, `/request/handler-login.php`; `405` on non-POST; clean `404`.
- **Password storage** — bcrypt cost-12 only; non-bcrypt rows are rejected at login; `password_needs_rehash` upgrades stale hashes. Password policy (no complexity/length enforced; weak seeded secrets `password123`/`admin123`) is informational and outside the auth-exploit surface.

## Systemic themes

1. **Comparison-semantics mismatch (F-01).** Authentication and its throttle should key on one canonicalized identifier; here they diverge on case, the sole gap found.
2. **Strong overall posture.** Parameterized queries, strict verifier semantics, server-side durable throttling, session rotation, uniform error surfaces and CSRF enforcement are all implemented consistently.
3. **Defense-in-depth limit.** The per-source-IP throttle is currently the compensating control that bounds F-01; any distributed-source brute force removes it.

# Recommendations

# Recommendations

## Immediate

1. **Canonicalize the account identifier (F-01).** Normalize the submitted username **once** at the entry point and use that single value for the database lookup, the audit `actor_id`, and the throttle key — or make identity comparison case-sensitive end to end (e.g. declare `NIP`/`nidn`/`nim` with `utf8mb4_bin`). This guarantees the throttle bucket and the authentication lookup agree, closing the case-variant budget multiplication.
   - Suggested change: `$actor = substr(strtolower(trim($username)), 0, 32);` in `helpers/login_throttle_helper.php`, plus a one-time `strtolower()` of the submitted username in `request/handler-login.php`.
2. **Add a regression test** that locks one case spelling and asserts a differently-cased spelling of the same identifier is *also* locked (extend `tests/security/LoginBruteForceSuite.php`).

## Short-term

1. **Harden the throttle against distributed sources.** Add a progressive delay / exponential back-off and a global per-account cap in addition to the per-IP cap, so multiplying identifiers (case or otherwise) yields diminishing returns. Consider a CAPTCHA after sustained failures.
2. **Keep the trusted-IP derivation as-is** (`REMOTE_ADDR` only) — it is correct; continue to ignore `X-Forwarded-For` for keying.

## Medium-term

1. **Enforce a password policy** (minimum length and screening of common/known-weak secrets) and rotate the seeded `password123` / `admin123` demo credentials before any non-lab deployment.
2. **Preserve the existing controls** that held in this assessment — session rotation, CSRF enforcement, uniform error surfaces, NUL/length guards — and guard them with regression tests so hardening is not regressed.

## Retest & validation

Re-run the failure-heavy login suites after the F-01 fix, confirming (a) case-variant identifiers share a single throttle bucket, (b) the 5-failures/15-minute rule still triggers, (c) a fresh session still cannot reset the lock, and (d) valid logins for all three roles continue to succeed. Validate the fix from at least two distinct source IPs to confirm the per-IP cap still behaves as a looser, NAT-friendly layer beneath the per-account lock.
