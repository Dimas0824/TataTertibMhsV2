# Security Penetration Test Report

**Generated:** 2026-09-21 22:57:46 UTC

# Executive Summary

# Executive Summary

A focused, offensive assessment of **session management and CSRF** was performed against the self-owned DiscipLink (PHP 8.3) instance at `http://172.17.112.1:8001`, supported by white-box review of `helpers/token_helper.php`, `helpers/route_helper.php`, `request/handler-login.php`, `request/handler-logout.php`, `config.php` and `.htaccess`.

**Overall risk posture: Low–Moderate.** The session and CSRF controls are materially stronger than typical custom PHP applications. Every state-changing endpoint rejects requests without a valid CSRF token, the session identifier rotates on authentication, logout destroys the session server-side, and older sessions are revoked when a new login occurs. One hardening gap was confirmed.

**Key finding**
- **PHP `session.use_strict_mode` is disabled** (Medium, CWE-384) — the server adopts any client-supplied `PHPSESSID` verbatim and persists session state (including the CSRF token) under it. This is a session-fixation enabler. It is contained in the current build because the login path regenerates the identifier and resets the CSRF token, so it is a defence-in-depth weakness rather than a working account takeover.

**Controls that held** (recorded as first-class negative evidence): session-ID rotation on login, server-side session destruction on logout, single-active-session enforcement, complete CSRF coverage (token required on every state-changing endpoint, cross-session/empty/absent/JSON-replay attempts all rejected with HTTP 419), high-entropy identifiers (26 chars, ~134 bits, no enumeration), and deny-by-default server-side authorization on protected pages.

**Business impact:** No path to account takeover or unauthorized data modification was demonstrated. The confirmed weakness slightly widens the session-fixation attack surface and allows low-cost server-side session materialization; it should be closed as routine hardening.

# Methodology

# Methodology

**Engagement type:** Gray-box offensive assessment of a locally hosted, self-owned lab instance, with full white-box access to the source repository.

**Scope:** Session management and CSRF only. Specifically: session fixation, session lifecycle (logout destruction, idle/absolute timeouts), cookie flags, concurrent-session handling, CSRF token semantics and coverage, session-identifier entropy, and post-session authorization enforcement. Password-verification and brute-force controls were out of scope (covered by a prior assessment area).

**Reference frameworks:** OWASP Web Security Testing Guide (session management and CSRF testing), OWASP Top 10:2021 A01/A07, and CWE mapping (CWE-384, CWE-613, CWE-614, CWE-352).

**Activities performed**
1. Static review of the session bootstrap (`app_session_start_if_needed`), timeout/rotation logic (`app_session_touch_or_expire`, `session_regenerate_id`), session inventory (`helpers/session_inventory_helper.php`), CSRF primitives (`app_csrf_token`, `app_verify_csrf`), router dispatch and `.htaccess` deny rules.
2. Dynamic testing against the live instance using three authenticated roles (admin, mahasiswa, dosen), capturing full request/response pairs.
3. Enumerated every registered state-changing route (`/action/login`, `/action/logout`, `/action/news`, `/action/tatib`, `/action/pelanggaran`, `/action/notifikasi`, `/action/upload`, `/action/download`) and tested each with a valid session but no token, an empty token, a cross-session token, and a JSON-body token.
4. Probed session-identifier handling, cookie attributes, fixation scenarios, and post-logout/post-timeout authorization.

**Constraints:** Testing was performed exclusively against the provided target. The `Secure` cookie flag cannot be asserted over plain HTTP (expected for a local lab); it was additionally verified by supplying a proxy-HTTPS signal.

# Technical Analysis

# Technical Analysis

**Severity model:** rated by demonstrated exploitability (Impact × Likelihood), mapped to CVSS v3.1.

## Confirmed finding

**1. `session.use_strict_mode` disabled (Medium, CVSS 4.2, CWE-384).**
The application never enables PHP's strict session mode. Sending `Cookie: PHPSESSID=<arbitrary value>` to `/login` yields a `200` response **without** a replacement `Set-Cookie`, proving the server adopted the supplied identifier. Repeating the request with the same identifier returns an **identical** `csrf_token`, proving a persistent server-side session exists under the attacker-chosen ID. Root cause is configuration (the insecure PHP default), not custom code. Impact is bounded by the login-time `session_regenerate_id(true)` and CSRF-token reset, which defeat fixation of the *authenticated* session — hence Medium, not High. Remediation is a one-line directive change.

## Controls verified as effective (negative results)

1. **Session fixation — defeated.** The identifier rotates on successful login (`Set-Cookie: PHPSESSID=<new>`); a pre-planted identifier is never authenticated (replay → `302`).
2. **Logout — server-side termination.** Logout destroys the session and clears the cookie (`PHPSESSID=deleted; Max-Age=0`); replaying the pre-logout identifier yields `302` to `/login`.
3. **Cookie flags.** `Set-Cookie: PHPSESSID=…; path=/; HttpOnly; SameSite=Lax`. `Secure` is absent on plain HTTP (lab) but is correctly emitted when a proxy-HTTPS signal (`X-Forwarded-Proto: https`) is present. `SameSite=Lax` blocks cross-site cookie delivery on POST.
4. **Concurrent sessions — revoked.** A second login for the same account invalidates the earlier session through the `USER_SESSION` inventory (CWE-613 fix active); the older session returned `302` after a new login.
5. **CSRF token semantics.** The token is stable per session (not rotated per request). Cross-session replay, empty token, absent token, and JSON-body-without-token were all rejected with HTTP `419`; a valid JSON-body token was accepted.
6. **CSRF coverage — complete.** `/action/news`, `/action/tatib`, `/action/pelanggaran`, `/action/notifikasi`, `/action/upload`, `/action/logout`, `/action/login` all returned `419` without a token. GET-based write attempts returned `403` (token-bound route protection). `/action/download` is capability-token-bound (`403`).
7. **Identifier entropy.** 26-character IDs over `[a-z0-9]` (~134 bits); no predictability or enumeration path.
8. **Authorization after session events.** All protected pages (`/admin/*`, `/pelanggaran`, `/pelaporan`, `/notifikasi`) redirect to `/login` after logout/timeout; role enforcement is deny-by-default (mahasiswa → `/admin` = `302`; admin-only actions = `403`). Sensitive paths (`/views/*`, `/request/*`, `/config.php`, `/.env`, `/storage/keys/*`) are denied case-insensitively.

## Systemic themes
Session and CSRF handling is centralized and consistently applied: the session bootstrap and timeout logic live in one helper, all dynamic traffic passes through `router.php` (which enforces idle/absolute expiry and revoked-session checks), and CSRF verification is invoked by every state-mutating handler. The single gap is a missing PHP runtime directive, which is unusual to overlook precisely because the rest of the session hardening is thorough.

## Attack-chaining consideration
The confirmed finding was assessed for chaining. Its only plausible escalation is (a) the missing `Secure` flag under plaintext HTTP plus (b) a cookie-injection primitive (sibling-subdomain XSS or a network man-in-the-middle) to plant the session identifier. Both prerequisites are environmental and neither was independently confirmed; more importantly, the login-time identifier regeneration and CSRF-token reset break the chain at the authentication step. No end-to-end higher-impact path was demonstrable, and no other finding is of a class that chains with this one.

# Recommendations

# Recommendations

## Immediate
1. **Enable strict session mode.** Set `session.use_strict_mode = 1` in the effective `php.ini` (or via `ini_set()` before `session_start()` in the bootstrap). This makes PHP reject unknown client-supplied session IDs and issue a server-generated one, closing the residual fixation gap. Add a regression assertion that a randomised, previously-unseen `PHPSESSID` is replaced by the server.

## Short-term
2. **Force HTTPS and the `Secure` flag in production.** Serve all traffic over TLS, redirect HTTP → HTTPS, and ensure the session cookie always carries `Secure`. Verify that reverse-proxy HTTPS signalling is trustworthy and cannot be spoofed by clients.
3. **Reduce session-fixation surface further** by regenerating the session identifier on any privilege transition and on sensitive actions (already done at login — extend to role elevation if introduced).

## Medium-term
4. **Consider per-request or short-lived CSRF tokens**, or at minimum bind tokens to a short validity window, to limit the value of a leaked token (currently valid for the whole session). This is optional given the strong existing controls.
5. **Add automated regression coverage** for the session lifecycle (identifier rotation on login, revocation of prior sessions, timeout enforcement, cookie attributes) and the CSRF coverage matrix so these controls remain verified in CI.

## Retest & validation
6. Re-test after the `use_strict_mode` change: confirm a randomised, unseen `PHPSESSID` is now replaced with a new server value and that no state (CSRF token) persists under the attacker-supplied identifier. Re-run the full session-fixation, logout-replay, concurrent-session, CSRF-coverage, and post-timeout authorization checks to confirm no regression.

