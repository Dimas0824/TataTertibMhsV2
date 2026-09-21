# AREA 1 ? LOGIN / AUTHENTICATION (offensive, focused, single area)

Authorized penetration test against a LOCAL, SELF-OWNED instance of DiscipLink
(student-discipline web app, PHP 8.3 native). Target: http://172.17.112.1:8001
Also mounted: the source repo (whitebox) ? use it to trace the login flow:
  request/handler-login.php, controllers/UserController.php, models/User.php,
  helpers/token_helper.php (CSRF + session), config.php.

## SCOPE: LOGIN AND AUTHENTICATION ONLY.
Do not test news, uploads, or admin CRUD in this run. Exhaust this one surface.

## Approach: be an offensive red-team operator, not a checklist reader.
For each class below, attempt real attacks and produce a working PoC, or a
documented negative result with the exact request/response as evidence.

1. SQL injection ? username / password / user_type. error-based, boolean-blind,
   time-based (SLEEP/SLEEP(2)/pg_sleep), UNION, stacked, comment tricks,
   mixed case, URL-encoded, backslash. Measure timing against a baseline.
2. Authentication bypass ? empty password, null byte, array injection
   (username[]=, password[]=), type juggling, magic-hash (0e...), trailing
   space / Unicode normalization on the username, JSON body content-type.
3. User enumeration ? compare a valid user with a wrong password vs a
   nonexistent user: response status, message, redirect target, and timing.
4. Brute-force / lockout ? the app claims 5 failures -> 15 min lock, per session.
   Confirm it. Then try to defeat it: new PHPSESSID, X-Forwarded-For rotation,
   case/encoding variation, concurrent bursts (race), logout/login cycling.
5. Session flaws ? does the session id ROTATE on successful login (fixation)?
   cookie flags (Secure/HttpOnly/SameSite), session still valid after logout,
   concurrent sessions.
6. CSRF ? is the login POST token-protected? Does a pre-login token get reused
   post-login? Replay a token cross-session.
7. Open redirect ? post-login redirect / next / return params.
8. Information disclosure ? stack traces, SQL errors, PHP warnings, 500 vs 401
   shapes, verbose error bodies that leak internals.
9. Password storage / policy ? infer hash type from the source (whitebox),
   test for plaintext fallback or weak policy.

## Rules of engagement
- Authorized local lab; attack ONLY the given target.
- Attach the exact request and response for every finding.
- Record defenses that HELD too (payload tried, why it failed) ? negative
  results are first-class evidence and will be published in a README matrix.
- Severity must be justified by exploitability (Impact + Likelihood + CVSS).

## Known test accounts (post-auth checks only)
- mahasiswa: 2341238901 / password123
- dosen: 1234567890 / password123
- admin: ADMIN001 / admin123
