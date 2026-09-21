# Security Penetration Test Report

**Generated:** 2026-09-21 14:41:27 UTC

# Executive Summary

# Executive Summary

A focused security assessment of the session-management and CSRF controls of the **DiscipLink** web application (`http://172.17.112.1:8001`, PHP 8.3 native) was performed using white-box source review combined with live black-box testing across three roles (`mahasiswa`, `dosen`, `admin`).

**Overall risk posture: Moderate, with a well-hardened core.**

The core session and CSRF protections are strong. Session fixation is defeated (the session identifier is regenerated on login), logout destroys the session server-side, and *every* state-changing endpoint rejects requests lacking a valid CSRF token (HTTP 419). Cross-session CSRF token replay, empty/absent tokens, and method-confusion attempts all failed. Server-side authorization on protected pages was consistently enforced. These controls held under direct testing.

Two weaknesses were confirmed, both concerning session hardening rather than a bypass of authentication or CSRF:

- **Session cookie not marked `Secure` on HTTPS-terminated (proxy) requests (Medium).** When TLS is offloaded to a reverse proxy that signals the scheme via `X-Forwarded-Proto`, the application treats the connection as HTTPS (it emits HSTS) yet still issues the session cookie without the `Secure` attribute. The cookie can then be transmitted over cleartext, enabling session hijacking — including of administrative sessions.
- **No absolute session lifetime and no concurrent-session invalidation (Low).** Only a 30-minute idle timeout is enforced (reset on every request); there is no absolute cap, and one account can hold unlimited simultaneous sessions with no revocation on new login.

**Business impact.** The Medium finding, if exploited in the documented proxy deployment, could yield durable capture of an authenticated session and expose violation records, user data, and administrative functions. The Low finding extends the useful lifetime of any stolen session.

**Overarching remediation theme.** Both issues stem from the same root: the session layer does not share the application's own proxy-aware HTTPS detection, and it tracks only last-activity rather than full session lifecycle state. Aligning scheme detection and adding absolute/rotational session lifecycle controls resolves both.

**Positive note.** The CSRF implementation and session-fixation defenses are robust and consistently applied — a materially better posture than typical custom PHP applications.

# Methodology

# Methodology

**Engagement type:** Authorized, gray-box assessment of a local, self-owned lab instance, scoped strictly to **session management and CSRF**.

**Framework:** OWASP Web Security Testing Guide (WSTG) — Session Management (WSTG-SESS) and CSRF (WSTG-SESS-05) categories; findings mapped to CWE and scored with CVSS v3.1.

**Scope:**
- Target: `http://172.17.112.1:8001` (DiscipLink, PHP 8.3 native).
- Source reviewed (white-box): `helpers/token_helper.php`, `helpers/route_helper.php`, `request/handler-login.php`, `request/handler-logout.php`, all state-changing handlers under `request/`, `controllers/UserController.php`, `config.php`, `.htaccess`, `router.php`.
- Roles tested: `mahasiswa`, `dosen`, `admin`.

**Activities performed:**
1. **Session fixation** — captured the session identifier before and after login and after supplying an attacker-chosen identifier.
2. **Session lifecycle** — replayed session identifiers after logout; measured idle behaviour; assessed absolute lifetime; inspected logout handling.
3. **Cookie flags** — inspected the exact `Set-Cookie` line for `HttpOnly`, `SameSite`, `Secure`, `Path`, `Domain`, including under forwarded-proto signalling.
4. **Concurrent sessions** — authenticated one account twice and verified whether either session was invalidated.
5. **CSRF token semantics** — tested stability across pages, rotation across login, cross-session replay, cross-action replay, and empty/absent/JSON-body tokens.
6. **CSRF coverage** — enumerated all state-changing endpoints and sent POST requests without a token, recording the HTTP status for each.
7. **Session-id entropy** — sampled fresh identifiers and analysed length and alphabet.
8. **Authorization after session events** — requested protected pages without a valid session and with mismatched roles, verifying server-side enforcement.

**Constraints:** Password-verification and SQL-injection issues were explicitly out of scope (covered under a separate area) and were not re-tested. Evidence for every finding and every held defense consists of captured request/response pairs.

**Note on severity:** Ratings reflect demonstrated exploitability combined with real-world deployment context; the application's documented production host uses HTTPS behind a proxy, which is what elevates the cookie-flag inconsistency above a purely theoretical configuration note.

# Technical Analysis

# Technical Analysis

**Severity model.** Ratings follow CVSS v3.1 (Impact × Likelihood), calibrated to what was demonstrated rather than hypothetical worst cases. Two findings were filed; the remainder of the tested surface produced held defenses (negative results), which are equally important to the assessment.

## Confirmed findings

1. **Session cookie without `Secure` on HTTPS-terminated requests — Medium.**
   The session cookie parameters are set in a single location and derive `secure` solely from `$_SERVER['HTTPS']`. Elsewhere, the application correctly treats `X-Forwarded-Proto: https` (and `SERVER_PORT=443`) as HTTPS — it emits `Strict-Transport-Security` on that basis. This asymmetry means that on HTTPS-equivalent requests terminated at a proxy, the cookie is issued as `PHPSESSID=...; path=/; HttpOnly; SameSite=Lax` with no `Secure`, so it can travel over cleartext. The inconsistency was reproduced live by sending a forwarded-proto header with a non-local `Host` and observing HSTS present alongside a `Secure`-less `Set-Cookie`.

2. **No absolute session lifetime / no concurrent-session invalidation — Low.**
   Liveness is governed entirely by a last-activity timestamp that is refreshed on every request, so periodic activity keeps a session alive indefinitely with no absolute ceiling. Authentication regenerates the session identifier but neither enumerates nor revokes the account's other sessions, and no cap exists on simultaneous sessions. Two logins for the same account (`mahasiswa` and `dosen`) were each verified to yield two simultaneously valid sessions (both HTTP 200 on protected pages).

## Defenses that held (negative results)

- **Session fixation:** `session_regenerate_id(true)` on successful login rotates the identifier; an attacker-supplied identifier was never authenticated, and the pre-authentication CSRF token was rejected post-login.
- **Logout:** server-side destruction; replaying the prior identifier after logout failed (302/401).
- **CSRF coverage:** all state-changing endpoints (`/action/login`, `/action/logout`, `/action/notifikasi`, `/action/upload`, `/action/pelanggaran` delete/confirm/save, `/action/news` store/delete, `/action/tatib` store/delete) returned 419 without a token. Cross-session replay, empty/absent tokens, and JSON-body tokens behaved correctly; the token rotates on login.
- **Method enforcement:** GET against POST-only actions returned 405.
- **Authorization:** unauthenticated access to protected pages returned 302 to `/login`; role mismatches were redirected server-side.
- **Session-id entropy:** 26-character identifiers from PHP's CSPRNG (adequate, not enumerable).
- **Capability tokens:** although bound to a session-identifier hash, every consumer endpoint independently checks authenticated session state, so a "token survives logout" replay was not independently exploitable.

## Systemic root cause

Both confirmed weaknesses share one theme: **the session layer does not share the application's own lifecycle and proxy-awareness logic.** Scheme detection is duplicated and inconsistent (cookie vs. HSTS), and session state tracks only last activity rather than creation time, affinity, or a per-user session inventory. Centralising scheme detection and adding lifecycle fields resolves both findings and hardens future session work.

# Recommendations

# Recommendations

## Immediate (0–7 days)

1. **Mark the session cookie `Secure` on all HTTPS-equivalent requests.** Replace the `$_SERVER['HTTPS']`-only check with the application's existing scheme detection (accept `HTTPS`, `SERVER_PORT=443`, or `X-Forwarded-Proto: https` from trusted proxies). Set the flag in one shared helper so the cookie and HSTS logic can never diverge. Consider enabling `session.cookie_secure` at the PHP configuration layer as defence in depth.
2. **Add an absolute session lifetime.** Record a session creation timestamp at login and reject the session once an absolute ceiling (e.g., 8–12 hours) is exceeded, regardless of activity, forcing re-authentication.

## Short-term (1–4 weeks)

3. **Invalidate prior sessions on authentication and credential change.** Maintain a per-user session inventory and revoke all other sessions on each successful login and on password change. Optionally cap concurrent sessions per account.
4. **Ensure HTTP-to-HTTPS redirection and HSTS** so that no cleartext request can ever carry the session cookie.

## Medium-term (1–3 months)

5. **Harden the CSRF token model (defence in depth).** The current per-session token is adequate given server-side verification, but consider per-form or single-use tokens and explicit binding to the authenticated session state to reduce the value of any token exposure.
6. **Align session garbage collection** with the intended lifetime (`gc_maxlifetime`) and document the session lifecycle (idle vs. absolute) so future changes preserve both.

## Retest & validation

Re-test the immediate items specifically: confirm the `Set-Cookie` line carries `Secure` for proxy-signalled HTTPS requests, confirm an aged session is rejected after the absolute ceiling, and confirm a second login revokes the first session. Regression tests should assert the exact `Set-Cookie` attributes and the post-login invalidation behaviour. Re-run the full CSRF coverage matrix and the session-fixation/rotation checks to confirm no regression, as these controls are currently sound and must remain so.

