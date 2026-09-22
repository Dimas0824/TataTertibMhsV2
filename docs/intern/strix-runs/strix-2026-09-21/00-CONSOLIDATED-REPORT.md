# DiscipLink - Consolidated STRIX Pentest (2026-09-21)

Five focused areas were scanned with Strix (white-box: live target + source mounted),
one area per run, `reasoning=low`, RPM-safe. Each area keeps its raw Strix output under
`<area>/before/` and the post-fix verification under `<area>/after/`.

| Area | Label | Findings | SARIF results | After-fix |
|---|---|---|---|---|
| `area1-login` | LOGIN / AUTHENTICATION | 2 | 2 | yes |
| `area2-session-csrf` | SESSION MANAGEMENT & CSRF | 2 | 2 | yes |
| `area3-upload-idor` | UPLOAD / DOWNLOAD / IDOR / ACCESS CONTROL | 0 | 0 | yes |
| `area4-pelanggaran` | VIOLATION WORKFLOW (BUSINESS LOGIC / INPUT) | 1 | 1 | yes |
| `area5-news-xss` | NEWS MODULE (XSS / SANITIZATION) | 1 | 1 | yes |
| | **TOTAL** | **6** | **6** | |

> `after/` exists only for areas whose findings are fixed and re-verified. `area3-upload-idor`
> had zero findings, so its `after/` records held defenses + one fixed non-security defect.

---

## Findings & remediation status

All findings from the 2026-09-21 run are **fixed and re-verified**.

| Severity | Area | id | Title | Fix commit | After-fix evidence |
|---|---|---|---|---|---|
| CRITICAL | `area1-login` | `vuln-0002` | Login brute-force lockout is per-session only and bypassed by discarding the session cookie | `ba4e8d1` | [`reproduce-after.log`](./area1-login/after/reproduce-after.log) |
| HIGH | `area1-login` | `vuln-0001` | NUL-byte truncation in password verification allows authentication with a NUL-suffixed password | `ba4e8d1` | [`reproduce-after.log`](./area1-login/after/reproduce-after.log) |
| MEDIUM | `area2-session-csrf` | `vuln-0001` | Session cookie issued without Secure flag on HTTPS-terminated (proxy) requests | `9f55f2a + 484bb67` | [`reproduce-after.log`](./area2-session-csrf/after/reproduce-after.log) |
| MEDIUM | `area4-pelanggaran` | `vuln-0001` | Sanction Level Not Validated Against Violation Tier (client-selectable sanksi) | `487dd93` | [`reproduce-after.log`](./area4-pelanggaran/after/reproduce-after.log) |
| MEDIUM | `area5-news-xss` | `vuln-0001` | Stored XSS on public news detail page via quote-boundary bypass of event-handler sanitizer | `7b4c39d` | [`reproduce-after.log`](./area5-news-xss/after/reproduce-after.log) |
| LOW | `area2-session-csrf` | `vuln-0002` | No absolute session lifetime and no invalidation of concurrent sessions | `9f55f2a + 484bb67` | [`reproduce-after.log`](./area2-session-csrf/after/reproduce-after.log) |

_Findings count: 6._

---
## area1-login - LOGIN / AUTHENTICATION

# Security Penetration Test Report

**Generated:** 2026-09-21 14:34:37 UTC

# Executive Summary

# Executive Summary

An authorized, focused penetration test was conducted against the **login and authentication surface** of a local DiscipLink student-discipline application (`http://172.17.112.1:8001`, PHP 8.3). Testing combined live black-box attacks with white-box tracing of the login flow (`request/handler-login.php`, `controllers/UserController.php`, `models/User.php`, `helpers/token_helper.php`, `config.php`).

**Overall risk posture: Elevated.**

The authentication input-handling and session logic is well hardened in most respects - SQL injection, CSRF, session fixation, open redirect, user enumeration, and information disclosure were all tested aggressively and **held**. However, two concrete, exploitable weaknesses were confirmed on the credential-check and anti-brute-force controls:

**Key findings**
- **Brute-force protection is ineffective (Critical).** The claimed 5-failures/15-minute lockout is stored only in the PHP session. An attacker resets it at will by discarding the session cookie, enabling unlimited password guessing against any account - including administrator accounts.
- **Password comparison is not length-exact (High).** A valid password followed by a NUL byte (`%00`) and arbitrary trailing data still authenticates, because the bcrypt verifier compares only up to the NUL. This weakens the integrity of the credential check.

**Business impact**
- Unlimited credential guessing against weakly-structured identifiers (NIM/NIDN/NIP) threatens unauthorized access to student-discipline records and, via a recovered administrator password, the full administrative surface.
- The NUL-truncation defect undermines confidence in the password control itself and should be remediated before any externally reachable deployment.

**Remediation theme**
Both issues stem from the credential/throttle logic relying on client-influenced state (the session id) and on verifier semantics rather than explicit input validation. Fixing them requires server-side, session-independent rate limiting and explicit rejection of malformed credentials.

# Methodology

# Methodology

The engagement followed the **OWASP Web Security Testing Guide (WSTG)** authentication testing areas (WSTG-ATHN-01 through -07), with severity calibrated using **CVSS v3.1**. Both **black-box** (live attack traffic) and **white-box** (source-code tracing of the login flow) techniques were applied.

**Engagement type:** Gray-box, authorized, single-area (authentication) assessment against a self-owned local lab instance.

**Scope:** `http://172.17.112.1:8001` - login/authentication only. The login entry points tested were:
- `GET /login` (login page + CSRF token issuance)
- `POST /action/login` (credential submission handler)
- `POST /action/logout` (session teardown)
- `GET /` and role dashboard redirects

**Activities performed**
- **Injection testing** of `username`, `password`, and `user_type` - error-based, boolean-blind, time-based (with baseline timing measurement), UNION, and stacked-query probes.
- **Authentication-bypass testing** - empty/whitespace passwords, array injection, type juggling, magic-hash inputs, NUL-byte handling, and Unicode normalization.
- **User enumeration** - differential comparison of a valid-but-wrong-password account versus a nonexistent account (status, redirect, body, message, and timing over repeated samples).
- **Brute-force / lockout** confirmation and defeat attempts (session rotation, `X-Forwarded-For` rotation, concurrent bursts, logout/login cycling).
- **Session testing** - fixation (session-id rotation on login), cookie flags, reuse after logout, and concurrent-session validity.
- **CSRF** - token presence, cross-session replay, and pre/post-login token reuse.
- **Open-redirect** testing on post-login navigation parameters.
- **Information-disclosure** probing of error responses.
- **Password storage/policy** inference from source.

**Constraints:** No third-party host was contacted; all traffic was directed at the provided lab target. Request/response pairs were captured as evidence for every conclusion, and defenses that held are documented as first-class negative results.

**Tools:** HTTP client (`curl`) for raw request control; source-code review for root-cause confirmation.

# Technical Analysis

# Technical Analysis

**Severity model:** exploitability (attack vector, complexity, privileges, interaction) - impact (confidentiality, integrity, availability), scored with CVSS v3.1 and justified against demonstrated behavior.

## Confirmed findings

1. **Login brute-force lockout is per-session only (Critical, CVSS 9.1, CWE-307).** The failed-attempt counter and lock expiry (`$_SESSION['__login_fails']`, `$_SESSION['__login_until']`) live exclusively in the PHP session. A client that simply omits the `PHPSESSID` cookie receives a fresh session with a zeroed counter; the same session is regenerated on every successful login. Confirmed: five failures produced the lock, while a brand-new session immediately accepted further attempts and a correct login with no lockout. `X-Forwarded-For` rotation had no effect (the throttle is not IP-scoped), and independent sessions run without any cap. The audit table records failures but nothing enforces a limit from it. (The source comment itself acknowledges the gap: "Add an IP-keyed store if deployed open-internet.")

2. **NUL-byte truncation in password verification (High, CVSS 7.4, CWE-230).** The handler passes the raw password to a bcrypt verification without rejecting NUL bytes. Because bcrypt comparison is NUL-terminated, `password123%00INJECTED` authenticates identically to `password123`, while `password123XINJECTED` is rejected. Confirmed across the `mahasiswa` and `dosen` roles, with correct-prefix controls demonstrating the truncation is specific to a NUL byte immediately following the correctly typed password. The username field is unaffected (it is compared in full via a parameterized SQL query), isolating the defect to the password verifier.

## Defenses confirmed to hold (negative results)

- **SQL injection - none.** All probes (quote, comment, `OR '1'='1`, `UNION SELECT`, mixed-case/encoded) returned `302 - /login` with no SQL error, and time-based `SLEEP(2)`/`SLEEP(5)`/stacked/subquery payloads showed no measurable delay versus the ~0.68 s baseline. Consistent with parameterized PDO queries and `ATTR_EMULATE_PREPARES=false`.
- **`user_type` manipulation - no effect.** Unrecognized values fall back to the default role sequence; the assigned role always derives from the matched account, not the requested type. A `mahasiswa` credential with `user_type=admin` logged into `/pelanggaran` and `/admin` remained denied - no privilege escalation.
- **Other bypass classes - rejected.** Empty/whitespace passwords, array injection (`username[]`/`password[]`/`user_type[]`), type juggling, and `0e`-style magic hashes all failed.
- **User enumeration - neutralized.** Valid-wrong-password and nonexistent accounts returned identical status, redirect, body length, and message; timing was equalized (0.6845 s vs 0.6861 s over repeated samples) by a dummy bcrypt cost-12 verification.
- **CSRF - enforced.** Missing, empty, wrong, and cross-session-replayed tokens all returned `419`; the token rotates on login and the pre-login token is rejected afterward.
- **Session management - sound.** The session id rotates on successful login (fixation mitigated); cookies are `HttpOnly` and `SameSite=Lax`; logout destroys the session and deletes the cookie; the old session id is rejected.
- **Open redirect - none.** `next`/`return`/`redirect`/`returnUrl` parameters were ignored; the post-login Location is a fixed, server-side role mapping.
- **Information disclosure - minimal.** No stack traces, SQL errors, or PHP warnings were emitted; sensitive paths (`/config.php`) return `403` and malformed input fails closed.
- **Password storage - strong.** bcrypt cost 12; the model rejects non-`$2` hashes, closing any plaintext fallback.

## Systemic theme

The two confirmed weaknesses share a root cause: security controls relying on client-influenced or semantically-ambiguous state (the session identifier for rate limiting; verifier NUL semantics for credential comparison) instead of explicit, server-side validation. The application's input-path hardening is otherwise consistent and effective.

## Attack-chaining assessment

The two findings were assessed for combination into a higher-impact chain. They are independent: **vuln-0002** is a rate-limit weakness that enables credential guessing, and **vuln-0001** is an input-validation weakness that requires prior knowledge of a correct password. Individually, vuln-0002 already yields the higher-impact outcome (unbounded brute force - account compromise), so it was scored accordingly and requires no chaining. Together they do not produce a stronger outcome than vuln-0002 alone: the lockout bypass does not help reach a *correct* password prefix that vuln-0001 needs, and vuln-0001 does not extend the reach of recovered credentials beyond what a directly-guessed password provides. No further plausible combination of the two confirmed findings was identified, and the remaining tested classes produced no exploitable primitives to chain with.

# Recommendations

# Recommendations

## Immediate

1. **Make brute-force protection session-independent.** Move the failed-attempt counter and lock state to a server-side store keyed on an attribute the client cannot reset - the source IP address, the attempted account identifier, or both. Enforce the 5-attempts/15-minute rule by querying recent failures from a durable store (the existing `SECURITY_AUDIT_LOG` table already captures event, actor, sid, and ip) rather than the session. Restrict lockout evaluation to a trusted client-IP source, since `X-Forwarded-For` is attacker-controllable (vuln-0002).
2. **Reject NUL bytes in credentials.** Before invoking the password verifier, reject any `username` or `password` containing a NUL byte and record an audit event. Enforce an explicit maximum password length (e.g. 72 bytes for bcrypt) so inputs cannot be silently truncated (vuln-0001).

## Short-term

3. **Add defense-in-depth to login:** a per-IP global rate limit and a progressive delay or CAPTCHA after repeated failures, to slow distributed guessing regardless of session.
4. **Add regression tests** asserting that (a) a valid password suffixed with `\0` plus data fails authentication, and (b) a fresh session does not reset the lockout counter for a locked account/IP.
5. **Alert or temporarily disable the targeted account** after a failure threshold, and surface repeated-failure patterns from the audit log.

## Medium-term

6. **Remove plaintext passwords from the development seed `.sql`** and ensure the hashing migration (`database/cli/hash-plaintext-passwords.php`) is part of every deployment path, so no plaintext rows can reach an environment (observed in source; not exploitable on the tested instance).
7. **Standardize credential-input normalization** - apply the same validation (including NUL and length checks) uniformly across username and password so handling is consistent and auditable.

## Retest & validation

Re-test the immediate items on a fresh instance: confirm that rotating `PHPSESSID` (and rotating `X-Forwarded-For`) no longer resets the lockout counter for a locked account/IP, and confirm that a correct password suffixed with `%00` plus arbitrary data is rejected while the exact password still authenticates. Verify the lockout still permits a legitimate user after the cool-down window.

### Findings index

| id | title | severity |
|---|---|---|
| `vuln-0002` | Login brute-force lockout is per-session only and bypassed by discarding the session cookie | CRITICAL |
| `vuln-0001` | NUL-byte truncation in password verification allows authentication with a NUL-suffixed password | HIGH |

### After-fix evidence

See [`./area1-login/after/README.md`](./area1-login/after/README.md) and
[`./area1-login/after/reproduce-after.log`](./area1-login/after/reproduce-after.log).

---

## area2-session-csrf - SESSION MANAGEMENT & CSRF

# Security Penetration Test Report

**Generated:** 2026-09-21 14:41:27 UTC

# Executive Summary

# Executive Summary

A focused security assessment of the session-management and CSRF controls of the **DiscipLink** web application (`http://172.17.112.1:8001`, PHP 8.3 native) was performed using white-box source review combined with live black-box testing across three roles (`mahasiswa`, `dosen`, `admin`).

**Overall risk posture: Moderate, with a well-hardened core.**

The core session and CSRF protections are strong. Session fixation is defeated (the session identifier is regenerated on login), logout destroys the session server-side, and *every* state-changing endpoint rejects requests lacking a valid CSRF token (HTTP 419). Cross-session CSRF token replay, empty/absent tokens, and method-confusion attempts all failed. Server-side authorization on protected pages was consistently enforced. These controls held under direct testing.

Two weaknesses were confirmed, both concerning session hardening rather than a bypass of authentication or CSRF:

- **Session cookie not marked `Secure` on HTTPS-terminated (proxy) requests (Medium).** When TLS is offloaded to a reverse proxy that signals the scheme via `X-Forwarded-Proto`, the application treats the connection as HTTPS (it emits HSTS) yet still issues the session cookie without the `Secure` attribute. The cookie can then be transmitted over cleartext, enabling session hijacking - including of administrative sessions.
- **No absolute session lifetime and no concurrent-session invalidation (Low).** Only a 30-minute idle timeout is enforced (reset on every request); there is no absolute cap, and one account can hold unlimited simultaneous sessions with no revocation on new login.

**Business impact.** The Medium finding, if exploited in the documented proxy deployment, could yield durable capture of an authenticated session and expose violation records, user data, and administrative functions. The Low finding extends the useful lifetime of any stolen session.

**Overarching remediation theme.** Both issues stem from the same root: the session layer does not share the application's own proxy-aware HTTPS detection, and it tracks only last-activity rather than full session lifecycle state. Aligning scheme detection and adding absolute/rotational session lifecycle controls resolves both.

**Positive note.** The CSRF implementation and session-fixation defenses are robust and consistently applied - a materially better posture than typical custom PHP applications.

# Methodology

# Methodology

**Engagement type:** Authorized, gray-box assessment of a local, self-owned lab instance, scoped strictly to **session management and CSRF**.

**Framework:** OWASP Web Security Testing Guide (WSTG) - Session Management (WSTG-SESS) and CSRF (WSTG-SESS-05) categories; findings mapped to CWE and scored with CVSS v3.1.

**Scope:**
- Target: `http://172.17.112.1:8001` (DiscipLink, PHP 8.3 native).
- Source reviewed (white-box): `helpers/token_helper.php`, `helpers/route_helper.php`, `request/handler-login.php`, `request/handler-logout.php`, all state-changing handlers under `request/`, `controllers/UserController.php`, `config.php`, `.htaccess`, `router.php`.
- Roles tested: `mahasiswa`, `dosen`, `admin`.

**Activities performed:**
1. **Session fixation** - captured the session identifier before and after login and after supplying an attacker-chosen identifier.
2. **Session lifecycle** - replayed session identifiers after logout; measured idle behaviour; assessed absolute lifetime; inspected logout handling.
3. **Cookie flags** - inspected the exact `Set-Cookie` line for `HttpOnly`, `SameSite`, `Secure`, `Path`, `Domain`, including under forwarded-proto signalling.
4. **Concurrent sessions** - authenticated one account twice and verified whether either session was invalidated.
5. **CSRF token semantics** - tested stability across pages, rotation across login, cross-session replay, cross-action replay, and empty/absent/JSON-body tokens.
6. **CSRF coverage** - enumerated all state-changing endpoints and sent POST requests without a token, recording the HTTP status for each.
7. **Session-id entropy** - sampled fresh identifiers and analysed length and alphabet.
8. **Authorization after session events** - requested protected pages without a valid session and with mismatched roles, verifying server-side enforcement.

**Constraints:** Password-verification and SQL-injection issues were explicitly out of scope (covered under a separate area) and were not re-tested. Evidence for every finding and every held defense consists of captured request/response pairs.

**Note on severity:** Ratings reflect demonstrated exploitability combined with real-world deployment context; the application's documented production host uses HTTPS behind a proxy, which is what elevates the cookie-flag inconsistency above a purely theoretical configuration note.

# Technical Analysis

# Technical Analysis

**Severity model.** Ratings follow CVSS v3.1 (Impact - Likelihood), calibrated to what was demonstrated rather than hypothetical worst cases. Two findings were filed; the remainder of the tested surface produced held defenses (negative results), which are equally important to the assessment.

## Confirmed findings

1. **Session cookie without `Secure` on HTTPS-terminated requests - Medium.**
   The session cookie parameters are set in a single location and derive `secure` solely from `$_SERVER['HTTPS']`. Elsewhere, the application correctly treats `X-Forwarded-Proto: https` (and `SERVER_PORT=443`) as HTTPS - it emits `Strict-Transport-Security` on that basis. This asymmetry means that on HTTPS-equivalent requests terminated at a proxy, the cookie is issued as `PHPSESSID=...; path=/; HttpOnly; SameSite=Lax` with no `Secure`, so it can travel over cleartext. The inconsistency was reproduced live by sending a forwarded-proto header with a non-local `Host` and observing HSTS present alongside a `Secure`-less `Set-Cookie`.

2. **No absolute session lifetime / no concurrent-session invalidation - Low.**
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

## Immediate (0-7 days)

1. **Mark the session cookie `Secure` on all HTTPS-equivalent requests.** Replace the `$_SERVER['HTTPS']`-only check with the application's existing scheme detection (accept `HTTPS`, `SERVER_PORT=443`, or `X-Forwarded-Proto: https` from trusted proxies). Set the flag in one shared helper so the cookie and HSTS logic can never diverge. Consider enabling `session.cookie_secure` at the PHP configuration layer as defence in depth.
2. **Add an absolute session lifetime.** Record a session creation timestamp at login and reject the session once an absolute ceiling (e.g., 8-12 hours) is exceeded, regardless of activity, forcing re-authentication.

## Short-term (1-4 weeks)

3. **Invalidate prior sessions on authentication and credential change.** Maintain a per-user session inventory and revoke all other sessions on each successful login and on password change. Optionally cap concurrent sessions per account.
4. **Ensure HTTP-to-HTTPS redirection and HSTS** so that no cleartext request can ever carry the session cookie.

## Medium-term (1-3 months)

5. **Harden the CSRF token model (defence in depth).** The current per-session token is adequate given server-side verification, but consider per-form or single-use tokens and explicit binding to the authenticated session state to reduce the value of any token exposure.
6. **Align session garbage collection** with the intended lifetime (`gc_maxlifetime`) and document the session lifecycle (idle vs. absolute) so future changes preserve both.

## Retest & validation

Re-test the immediate items specifically: confirm the `Set-Cookie` line carries `Secure` for proxy-signalled HTTPS requests, confirm an aged session is rejected after the absolute ceiling, and confirm a second login revokes the first session. Regression tests should assert the exact `Set-Cookie` attributes and the post-login invalidation behaviour. Re-run the full CSRF coverage matrix and the session-fixation/rotation checks to confirm no regression, as these controls are currently sound and must remain so.

### Findings index

| id | title | severity |
|---|---|---|
| `vuln-0001` | Session cookie issued without Secure flag on HTTPS-terminated (proxy) requests | MEDIUM |
| `vuln-0002` | No absolute session lifetime and no invalidation of concurrent sessions | LOW |

### After-fix evidence

See [`./area2-session-csrf/after/README.md`](./area2-session-csrf/after/README.md) and
[`./area2-session-csrf/after/reproduce-after.log`](./area2-session-csrf/after/reproduce-after.log).

---

## area3-upload-idor - UPLOAD / DOWNLOAD / IDOR / ACCESS CONTROL

# Security Penetration Test Report

**Generated:** 2026-09-21 14:50:51 UTC

# Executive Summary

# Executive Summary

An authorized gray-box penetration test of **DiscipLink** (`http://172.17.112.1:8001`) was performed, focused on **file upload, file download, and object-level authorization (IDOR/BOLA)**. The application is a PHP 8.3 native (no framework) student-discipline management system.

**Overall risk posture for the tested area: Strong - no exploitable weakness was identified.**

Every attempt to bypass upload controls, forge or replay file-download tokens, enumerate other users' objects, or escalate role privileges was blocked by server-side controls. The key defenses are: server-side MIME detection (`finfo`) with an extension allowlist, server-generated non-guessable filenames, per-session authenticated-encryption capability tokens (NaCl secretbox / AES-GCM) bound to the session identifier, subject and expiry, ownership-scoped SQL for every object operation, and server-side role + CSRF enforcement on all state-changing endpoints.

**Business impact of the tested area:** None - no unauthorized file read/write, no cross-user object access, no privilege escalation was achieved.

**Non-security observations** (documented for completeness, not vulnerabilities): one view hardcodes a download link so that all students receive the same generic template PDF (a functionality defect, not a disclosure), and an administrator navigating to a lecturer-only page receives a graceful HTTP 500 with no internal detail leaked.

# Methodology

# Methodology

**Engagement type:** Gray-box (white-box source review combined with live black-box exploitation) against a local, self-owned instance.

**Scope:** `http://172.17.112.1:8001`, restricted to file upload, file download, and object-level authorization. Source reviewed: `request/handler-upload.php`, `request/handler-download.php`, `helpers/token_helper.php`, `helpers/route_helper.php`, `controllers/PelanggaranController.php`, `controllers/NewsController.php`, `models/Pelanggaran.php`, `router.php`, `.htaccess`, and the rendering views.

**Activities:** authentication as four distinct principals (mahasiswa `2341238901`, second mahasiswa `2341238902`, dosen `1234567890`, second dosen `1234567891`, admin `ADMIN001`); upload type/traversal/overwrite/polyglot testing; token sealing tests (tamper, cross-session replay, cross-entity confusion, expiry by code analysis); direct storage-path and traversal access; and cross-user object reference manipulation across the edit/detail/confirm/delete violation flows and administrative actions.

**Reference framework:** OWASP Web Security Testing Guide (WSTG) - file upload, path traversal, IDOR, broken access control, and role-based access testing categories.

**Constraints:** The token TTL (1800 s) could not be awaited in live testing; expiry enforcement was confirmed by source analysis of the decode path. Negative (held) results were recorded alongside successful ones and are first-class evidence for the coverage matrix.

# Technical Analysis

# Technical Analysis

Testing concentrated on three attack surfaces. All were hardened. No confirmed vulnerability met the proof-of-concept threshold, so no vulnerability reports were filed for this area.

## 1. File upload
- **Type restriction.** The handler derives the type from server-side `finfo` MIME detection and ignores the client-declared `Content-Type`. Uploads of `.php`, `.phtml`, `.docx` content and PHP-in-image content were rejected with *"Tipe file tidak diizinkan."* A valid JPEG header with appended PHP passed MIME checks but was stored with the **original** extension (`.jpg`) - non-executable and served as `image/jpeg` with `nosniff`.
- **Filename handling.** The stored name is `<idDetail>_<fileType>_<24-hex>.<ext>` generated server-side; the client filename is never used for the path. Traversal payloads (`../../`, `..%2f`, `....//`), null bytes and absolute paths all landed as generated names inside `storage/uploads/` with nothing written elsewhere.
- **Overwrite.** A 12-byte random suffix makes collisions/overwrites infeasible (two same-named uploads produced two distinct files).
- **SVG / polyglot XSS.** SVG MIME is not permitted and SVG-content-as-PNG is rejected by `finfo`; news images accept only JPEG/PNG and are served as `image/jpeg` with `nosniff`. Requests for a `.php` path in the upload directory return 404.

## 2. Download / file disclosure
- **Token sealing.** Download links carry a sealed capability token. Flipping one character - 403. Replaying a token in a different session (verified dosen-mahasiswa and second-user permutations) - 403, because the payload embeds `sid = sha256(session_id)` compared with `hash_equals`. Cross-entity reuse (a notification or detail token used as a file token) - 403. Expiry is enforced in the decode routine.
- **Direct object access.** A raw filename instead of a token - 403. Direct GETs of `/storage/uploads/<file>`, `/storage/keys/app_token.key`, `/config.php` and `/.env` - 403; traversal attempts - 403. Only the intentionally public news-image directory is reachable.
- **Download headers.** Successful downloads use `Content-Disposition: attachment`, `Cache-Control: no-store`, and `X-Content-Type-Options: nosniff`, preventing inline execution and intermediary caching of token-bearing URLs.

## 3. Object-level authorization (IDOR / BOLA)
- Violation records are addressed by sealed, session-bound ID tokens; raw numeric IDs - 403. A second user replaying another user's token (edit, download, upload, confirm, delete) was rejected in every case, and the underlying SQL is additionally scoped to the authenticated `nidn`/`nim` (ownership predicate on `id_dosen` / `id_dosen_penanggung_jawab` / `id_mhs`). Cross-user deletion returned a redirect with a *"token invalid"* flash while the target record remained intact.
- Upload authorization resolves the record through an ownership-scoped query joining `MAHASISWA`/`DOSEN`; absent, foreign, or malformed `id_detail` tokens all fail closed.
- Role enforcement is server-side: mahasiswa and dosen POSTs to administrative endpoints (`/action/news`, `/action/tatib`) - 403, `/admin/*` pages redirect, and an admin cannot invoke lecturer-only violation actions.

**Systemic theme:** authorization is centralized and deny-by-default, and file/object references are sealed opaque tokens bound to the session rather than guessable sequential identifiers - which is why the IDOR and token attacks uniformly failed.

# Recommendations

# Recommendations

**Immediate (cleanup / correctness)**
1. Remove the test upload artifacts created during this assessment from the target's `storage/uploads/` and `storage/uploads/news/` directories.
2. Fix the student violation view that hardcodes the download link to the generic `SURAT PERNYATAAN TI.pdf`; render the student's own uploaded document (sealed token per file) so the feature works as intended.

**Short-term (robustness)**
3. Make the lecturer-only violation page tolerate a missing `nidn` (e.g. reject non-dosen callers with a 403 rather than an unhandled 500) so administrators and other roles get a clean, non-error response.
4. Consider adding server-side image re-encoding (re-write through GD/Imagick) for uploaded images to strip appended payload bytes, as defense-in-depth on top of the existing MIME/extension allowlist.

**Medium-term (hardening continuity)**
5. Preserve the current token design principles in future endpoints: keep tokens session-bound with subject and expiry, keep object queries ownership-scoped, and continue generating server-side filenames.
6. Ensure the `.htaccess`/`router.php` deny rules remain mirrored for every deployment target (Apache and the PHP built-in server), since the storage deny and traversal protections depend on both being present.

**Retest & validation**
7. Re-run the AREA 3 matrix after any change to upload, download, or token code to confirm the MIME allowlist, filename generation, token binding and ownership predicates still hold.

### After-fix evidence

See [`./area3-upload-idor/after/README.md`](./area3-upload-idor/after/README.md) and
[`./area3-upload-idor/after/reproduce-after.log`](./area3-upload-idor/after/reproduce-after.log).

---

## area4-pelanggaran - VIOLATION WORKFLOW (BUSINESS LOGIC / INPUT)

# Security Penetration Test Report

**Generated:** 2026-09-21 15:00:50 UTC

# Executive Summary

# Executive Summary

An authorized white-box penetration test of the DISCIPLINARY VIOLATION WORKFLOW of the DiscipLink application (local instance at `http://172.17.112.1:8001`) identified **one confirmed business-logic / data-integrity vulnerability** in how a violation record's sanction is stored.

**Overall risk posture:** Moderate (localized). The violation workflow is otherwise well hardened - authorization, input handling, injection defenses, and workflow state controls all held under targeted testing.

**Key finding**
- **Sanction level not validated against violation tier (Medium, CVSS 6.5).** When a lecturer records a violation, the sanction is accepted from a client-supplied value and stored without verifying that the sanction's tier matches the violation's tier. A lecturer can attach the most severe sanction ("Diberhentikan sebagai mahasiswa" - expulsion) to a trivial Tier V, 2-point infraction, and the student portal presents it as authoritative. The same manipulation is accepted when editing an existing record.

**Business impact**
- The disciplinary record is the authoritative artifact shown to students; a mismatched sanction can cause wrongful distress, reputational harm, and support fraudulent escalation. Because the flaw is server-side and silent, it undermines trust in the integrity of the whole violation registry.

**Notable strengths (verified, not assumed)**
- Role separation (mahasiswa/dosen/admin), ownership after DPA delegation, session-bound and entity-scoped capability tokens, parameterized SQL, output encoding at every render sink, strict output/type handling, and server-side workflow-state controls all resisted the tested attacks.

# Methodology

# Methodology

**Engagement type:** Authorized gray-box assessment against a local, self-owned instance of DiscipLink (PHP 8.3 native). Scope was limited to the violation (`pelanggaran`) lifecycle and its inputs and authorization. Testing combined static review of the mounted source repository with live request/response PoCs.

**Framework alignment:** OWASP WSTG (business-logic, authorization, input-validation, and injection test categories) and PTES.

**Areas tested**
1. Business logic - self-reporting, point manipulation, sanction-threshold enforcement, workflow/state skipping, step repetition, duplicate submission, and edit ownership after DPA (delegated adviser) changes.
2. Input validation and injection - SQL injection (error-, boolean-, time-based, UNION, stacked) in all violation fields and search/filter/pagination parameters; stored XSS in violation description and task fields as rendered to students, lecturers, and admins; mass assignment; type juggling (arrays, negatives, floats, oversized integers, NUL bytes).
3. Authorization - mahasiswa attempting lecturer-only actions, lecturer attempting admin-only actions, and cross-role token reuse on violation detail/edit/confirm/cancel.

**Constraints:** The report focuses on one confirmed, PoC-backed issue. Defenses that held are documented as first-class negative results for the remediation matrix.

**Techniques:** Session-authenticated request replay with token/parameter tampering, concurrent-submission race testing, payload reflection/escaping analysis across all render sinks, and timing analysis for injection.

# Technical Analysis

# Technical Analysis

**Severity model:** exploitability - impact. The single confirmed finding is **Medium** because it requires a low-privilege authenticated lecturer and yields record-integrity impact (no confidentiality or availability compromise).

## Confirmed finding

1. **Sanction level not validated against violation tier** (Medium, CVSS 6.5) - endpoint `POST /action/pelanggaran`. The violation tier is correctly derived server-side from the selected tata-tertib row, and points are read from the same row at render time, so neither can be forged directly. However, the sanction supplied in the `sanksi` field overrides the tier-consistent default and is never compared against the violation tier. The model performs only an existence check on the sanction row, never reading its tier. A tier-V, 2-point violation was stored and rendered with the tier-I sanction "Diberhentikan sebagai mahasiswa". Reproducible on both the create and update paths. Root cause is a missing cross-field integrity check on a trusted, server-authoritative record.

## Systemic themes

- **Server-side values are trusted where derived, but one client-supplied linkage (violation - sanction) bypasses the derivation.** The fix theme is to enforce referential consistency for every client-selectable linkage that carries semantic weight.
- **Token design is sound.** Capability tokens are authenticated-encrypted and bound to the session and to a specific entity, which blocked cross-role and cross-session reuse; this is the model the sanction linkage should also follow.

## Defenses that held (verified negative results)

- **Self-reporting / point manipulation:** students cannot create or edit violations; points and tier are always server-derived.
- **Workflow/state integrity:** violation `status` is hard-coded server-side; confirmation requires the mandated uploaded documents; edits are blocked once a record is complete; deletion removes the record and its notifications transactionally.
- **Duplicate submission (race):** concurrent identical submissions create multiple rows, which is legitimate (no de-duplication requirement) - not a vulnerability.
- **Ownership after DPA delegation:** the reporting lecturer is blocked from editing/confirming a record delegated to the DPA; the DPA sees only records they own; other lecturers receive 403.
- **SQL injection:** all violation, lookup, and search parameters are parameterized with native prepares; no error-, boolean-, time-based, UNION, or stacked injection succeeded; LIKE wildcards are escaped.
- **Stored XSS:** every violation/task field is HTML-encoded at all render sinks; no payload executed.
- **Mass assignment and type juggling:** extra and malformed fields are ignored or rejected gracefully with no server error.
- **Cross-role access:** students and admins are blocked from lecturer-only actions; lecturer-only search/lookup endpoints return 403 to other roles; tokens cannot be reused across roles or sessions.

## Minor observations (not filed)

- The edit form permits changing the target `nim`, so a lecturer can reassign a violation (with its points and sanction) to a different student; this is an intended correction capability but warrants an audit trail.
- The lecturer violation page returns HTTP 500 when accessed by an admin session (view assumes lecturer identity); a robustness issue with no sensitive data exposure.

## Attack-chaining assessment

Chaining opportunities were considered. The confirmed sanction-mismatch issue does not combine with any other confirmed weakness to escalate privilege or reach a different asset; the token-binding and role controls that would be required to pivot held under test. No valid multi-finding chain was demonstrated.

# Recommendations

# Recommendations

## Immediate
1. **Enforce sanction-violation tier consistency.** Derive the sanction server-side from the violation tier and do not accept an arbitrary client-selected sanction. If selection among valid sanctions is required, constrain the choice to sanctions whose tier equals the violation's tier, and reject any mismatch on both the create and update paths.
2. **Validate the linkage in the data layer.** Select the sanction together with its tier and compare it to the tata-tertib tier before insert/update; fail closed on mismatch.

## Short-term
3. **Add a database-level integrity guarantee** (constraint or trigger) so a stored violation's sanction always references a sanction of the matching tier, independent of application code.
4. **Apply the fix uniformly** to every write path that sets `sanksi` (create, update, and any bulk/import routine).

## Medium-term
5. **Introduce an audit trail for student reassignment** on violation edits (record who changed the target student and from/to which student), since this is an intended but currently unaudited capability.
6. **Harden role-specific views** so that an admin or other non-lecturer session accessing a lecturer page degrades gracefully (redirect/403) instead of raising a server error.
7. **Add regression tests** covering: sanction-tier mismatch rejection on create/update, client-supplied `tingkat`/`poin`/`status` being ignored, and ownership enforcement after DPA delegation.

## Retest & validation
Re-test the primary finding first: after remediation, replay the sanction-mismatch request on both create and update paths and confirm the server rejects the mismatched sanction. Then re-run the negative-result matrix (injection, XSS, mass assignment, type juggling, cross-role token reuse) to confirm no regression in the controls that held.

### Findings index

| id | title | severity |
|---|---|---|
| `vuln-0001` | Sanction Level Not Validated Against Violation Tier (client-selectable sanksi) | MEDIUM |

### After-fix evidence

See [`./area4-pelanggaran/after/README.md`](./area4-pelanggaran/after/README.md) and
[`./area4-pelanggaran/after/reproduce-after.log`](./area4-pelanggaran/after/reproduce-after.log).

---

## area5-news-xss - NEWS MODULE (XSS / SANITIZATION)

# Security Penetration Test Report

**Generated:** 2026-09-21 15:11:34 UTC

# Executive Summary

# Executive Summary

A focused security assessment of the **News/Announcement surface** of the DiscipLink application (PHP 8.3, local instance at `http://172.17.112.1:8001`) was performed. This area covers news authoring (admin), storage, and rendering to public, unauthenticated visitors.

**Overall risk posture:** Moderate. The News module's authorization and request-forgery defenses are strong, but its HTML content sanitizer is bypassable, resulting in a persistent script-injection issue on a public page.

**Key finding**
- **Stored cross-site scripting (XSS)** on the public news detail page. Article body content is filtered with `strip_tags()` plus a regular expression intended to remove inline event handlers. Because allowed tags keep their attributes and the regex only matches handlers preceded by whitespace or `/`, an author can inject a live handler by closing a preceding attribute with a quote (`<div title="x"onmouseover="alert(document.domain)">`). The payload is stored and emitted unescaped, executing in the browser of any visitor - including unauthenticated guests and administrators.

**Business impact**
- An attacker with an admin account can turn any article into a persistent script-execution vector seen by all visitors (students, lecturers, staff).
- Consequences include theft of session/CSRF material rendered in the page, execution of arbitrary JavaScript in the portal origin, content defacement, and drive-by actions on behalf of viewers.

**Notable defenses that held (verified negative results)**
- CSRF protection fails closed (missing/wrong/empty/foreign token - HTTP 419).
- Role authorization is enforced server-side (student/lecturer - 403; unauthenticated - 401).
- No SQL injection in news lookups; no server-side URL fetching or open redirect.
- Script/link/image tags and style attributes are stripped; JSON/JS embeds are safely escaped.

**Overarching remediation theme:** the tag/regex sanitizer is fundamentally unreliable and must be replaced by an allow-list HTML sanitizer applied authoritatively on render, backed by a strict Content Security Policy.

# Methodology

# Methodology

**Engagement type:** Gray-box assessment of a local, self-owned instance, with full access to the application source repository (white-box tracing).

**Scope:** The News/Announcement module and its supporting components - `controllers/NewsController.php`, `request/handler-news.php`, `models/News.php`, `views/admin/news-admin.php`, `views/admin/tambah-berita.php`, `views/admin/edit-berita.php`, `views/public/berita-detail.php`, `helpers/token_helper.php`, and `js/news-form-editor.js`. Target: `http://172.17.112.1:8001`.

**Testing approach (aligned to OWASP WSTG):**
- **Source review** of the sanitizer, authorization gates, CSRF helpers, and rendering path to identify candidate sinks.
- **Dynamic black-box testing** via crafted HTTP requests: authenticated as admin (create/edit/delete), as student and lecturer (authorization), and as an unauthenticated guest (public rendering and CSRF).
- **Browser-assisted verification** using a headless Chromium session to confirm whether candidate payloads produce live handlers and actually execute (dialog capture and DOM inspection).
- **At-rest vs at-render comparison** to distinguish sanitized-but-stored from stored-and-emitted-raw.

**Test categories:** stored XSS (title, body, slug, meta), SQL injection (slug/id/search/sort), CSRF (missing, wrong, replayed, cross-session), role authorization, cross-admin object access, draft reachability, and URL/SSRF/open-redirect handling in image and URL fields.

**Constraints:** Testing was limited to the authorized local lab host. Negative results were recorded as first-class evidence.

# Technical Analysis

# Technical Analysis

## Severity model
Severity reflects demonstrated exploitability and impact, scored with CVSS v3.1. One finding was confirmed; the remainder of the tested surface returned documented negative results.

## Confirmed findings

1. **Stored XSS on the public news detail page** (Medium, CVSS 5.4 - `AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N`). Two layers share the same defective pattern:
 - **Store path** (`NewsController::sanitizeNewsContent`): `strip_tags($html, '<div><p>...<blockquote>')` preserves attributes on allowed tags, then `preg_replace('/[\s\/]on[a-z]+\s*=\s*(...)/i', ...)` removes event handlers **only when preceded by whitespace or `/`**.
 - **Render path** (`views/public/berita-detail.php`): re-applies the same `strip_tags` + regex, then emits the result **raw** (`$formattedContent` is not re-escaped when the content contains HTML).

   Root cause: the handler-closing quote in `title="x"onmouseover="alert(1)"` is not whitespace or `/`, so the regex does not match, and the browser parses the tail as a second valid attribute. Payloads are stored raw and delivered raw. Verified variants: `onmouseover`, `onfocus` + `autofocus` (auto-fires), `onanimationstart`, `ontoggle`, `onerror`; single- and double-quote boundaries.

## Verified negative results (defenses that held)

- **Other XSS vectors (A):** `<script>` tags fully stripped (inner text rendered as inert text); `<img>`, `<svg>`, `<iframe>`, `<a>` tags removed; `style=` removed; HTML-entity-encoded `&#60;script&#62;` remains inert text; a normally space-prefixed ` onmouseover=` is removed. Titles, slugs, the admin list (escaped `data-*` metadata), and the edit-page textarea are all safely escaped.
- **SQL injection (B):** the public slug path extracts only a trailing `-(\d+)` and casts to integer; no error/boolean/time-based behavior observed. News search, filter, and sort are client-side only. `getNewsById` uses a prepared integer-bound statement.
- **CSRF (C):** missing, wrong, empty, and cross-session-replayed tokens all return HTTP 419 (fail-closed) via `hash_equals` against the session token.
- **Authorization (C):** student and lecturer POSTs to `/action/news` return HTTP 403; unauthenticated and forged-cookie requests return HTTP 401. Cross-admin edit/delete with another admin's ID token returns HTTP 403 (tokens are bound to the session ID). No draft/unpublished status exists in the schema, so unpublished-article exposure is not applicable.
- **Output handling (D):** no raw JSON/JS embed of news content; only `defer` external scripts are present, and the editor bootstrap value is `htmlspecialchars`-escaped.
- **URL/SSRF/open redirect (E):** the image field is upload-only (server-side `finfo` MIME check, no server-side fetch); no user-controlled `Location` header or URL-following feature exists in the News module.

## Systemic themes
- **Blacklist-based HTML filtering** (tag list + regex attribute removal) is the common weakness; it is bypassable and duplicated across two files.
- By contrast, the authorization, CSRF, and database layers apply consistent, fail-closed controls - the risk in this module is concentrated in output encoding.

# Recommendations

# Recommendations

**Immediate**
1. Replace the `strip_tags()` + regex sanitizer with a maintained allow-list HTML sanitizer (e.g. HTML Purifier) configured with an explicit tag and attribute allow-list, no `on*` handlers, and no `style`.
2. Make sanitization authoritative at render time: either sanitize stored content with the library before output, or escape all output by default and re-introduce markup only through the sanitizer. Apply the same fix in `NewsController` and `berita-detail.php`.

**Short-term**
3. Deploy a strict Content Security Policy and remove `'unsafe-inline'` from `script-src` (adopt per-response nonces for legitimate inline scripts) so injected inline handlers cannot execute even if they reach the DOM.
4. Add regression tests for the quote-boundary and autofocus/on-handler vectors and for the confirmed negative controls (script/tag stripping, entity encoding).

**Medium-term**
5. Consolidate all news HTML handling into a single shared sanitization function to prevent divergent copies drifting apart.
6. Periodically review other rich-text entry points for the same tag/regex pattern.

**Retest & validation**
7. After remediation, re-run the stored-XSS PoC (`<div title="x"onmouseover=...>` and `<p title="a"onfocus=... autofocus>`) against the public detail page and confirm the handler is neutered both at rest and at render, and that the CSP blocks inline execution. Re-confirm the CSRF (419) and authorization (403/401) controls to ensure the fix introduces no regressions.

### Findings index

| id | title | severity |
|---|---|---|
| `vuln-0001` | Stored XSS on public news detail page via quote-boundary bypass of event-handler sanitizer | MEDIUM |

### After-fix evidence

See [`./area5-news-xss/after/README.md`](./area5-news-xss/after/README.md) and
[`./area5-news-xss/after/reproduce-after.log`](./area5-news-xss/after/reproduce-after.log).

---
