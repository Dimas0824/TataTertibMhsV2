# AREA 2 — SESSION MANAGEMENT & CSRF (offensive, focused, single area)

Authorized penetration test against a LOCAL, SELF-OWNED instance of DiscipLink
(PHP 8.3 native). Target: http://172.17.112.1:8001
Also mounted: the source repo (whitebox). Trace:
  helpers/token_helper.php (session + CSRF + authz), helpers/route_helper.php,
  request/handler-login.php, config.php, .htaccess.

## CONTEXT (already found in AREA 1 — do NOT re-report, build on it)
- vuln-0001 HIGH: NUL-byte truncation in password verification.
- vuln-0002 CRITICAL: login lockout is per-session only; discard cookie to bypass.
AREA 2 is about the SESSION LIFECYCLE and CSRF, not the password check.

## SCOPE: SESSION MANAGEMENT + CSRF ONLY.
Do not re-test SQLi/password-byte issues. Exhaust this surface.

## Attack classes to attempt (produce a PoC or a documented negative result)

1. Session fixation — does the session id ROTATE on successful login?
   Capture PHPSESSID before and after. If it is unchanged, an attacker who can
   plant a session id pre-login owns the authenticated session.
2. Session lifecycle — is the session destroyed server-side on logout?
   Replay the old PHPSESSID after logout; does it still authenticate?
   Are idle/absolute timeouts enforced?
3. Cookie flags — inspect PHPSESSID: HttpOnly, SameSite, Secure, Path, Domain.
   Note any missing flag with the exact Set-Cookie line.
4. Concurrent sessions — can the same account hold unlimited parallel sessions?
   Is an old session invalidated when a new login happens?
5. CSRF token semantics — is csrf_token stable per session or per request?
   Reuse a token across two different state-changing actions. Replay a token
   from session A in session B. Test token present but empty, and absent.
6. CSRF coverage — enumerate state-changing endpoints (login, logout, upload,
   pelanggaran create/edit, news CRUD, user/tatib admin actions). For EACH,
   test a POST without the CSRF token and record the HTTP status (419? 403? 302?).
   A state-changing action that succeeds without a token is a finding.
7. Session-id entropy / predictability — inspect the id format; attempt to
   enumerate or fixate valid sessions.
8. Authorization after session events — after logout or timeout, do protected
   pages (/admin/*, /pelanggaran) still serve? Verify server-side, not just UI.

## Rules of engagement
- Authorized local lab; attack ONLY the given target.
- Attach the exact request and response for every finding.
- Record defenses that HELD (payload tried, why it failed) — negative results
  are first-class evidence for the README matrix.
- Severity justified by exploitability (Impact + Likelihood + CVSS).

## Known test accounts
- mahasiswa: 2341238901 / password123
- dosen: 1234567890 / password123
- admin: ADMIN001 / admin123