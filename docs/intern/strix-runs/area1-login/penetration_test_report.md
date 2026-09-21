# Security Penetration Test Report

**Generated:** 2026-09-21 14:34:37 UTC

# Executive Summary

# Executive Summary

An authorized, focused penetration test was conducted against the **login and authentication surface** of a local DiscipLink student-discipline application (`http://172.17.112.1:8001`, PHP 8.3). Testing combined live black-box attacks with white-box tracing of the login flow (`request/handler-login.php`, `controllers/UserController.php`, `models/User.php`, `helpers/token_helper.php`, `config.php`).

**Overall risk posture: Elevated.**

The authentication input-handling and session logic is well hardened in most respects — SQL injection, CSRF, session fixation, open redirect, user enumeration, and information disclosure were all tested aggressively and **held**. However, two concrete, exploitable weaknesses were confirmed on the credential-check and anti-brute-force controls:

**Key findings**
- **Brute-force protection is ineffective (Critical).** The claimed 5-failures/15-minute lockout is stored only in the PHP session. An attacker resets it at will by discarding the session cookie, enabling unlimited password guessing against any account — including administrator accounts.
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

**Scope:** `http://172.17.112.1:8001` — login/authentication only. The login entry points tested were:
- `GET /login` (login page + CSRF token issuance)
- `POST /action/login` (credential submission handler)
- `POST /action/logout` (session teardown)
- `GET /` and role dashboard redirects

**Activities performed**
- **Injection testing** of `username`, `password`, and `user_type` — error-based, boolean-blind, time-based (with baseline timing measurement), UNION, and stacked-query probes.
- **Authentication-bypass testing** — empty/whitespace passwords, array injection, type juggling, magic-hash inputs, NUL-byte handling, and Unicode normalization.
- **User enumeration** — differential comparison of a valid-but-wrong-password account versus a nonexistent account (status, redirect, body, message, and timing over repeated samples).
- **Brute-force / lockout** confirmation and defeat attempts (session rotation, `X-Forwarded-For` rotation, concurrent bursts, logout/login cycling).
- **Session testing** — fixation (session-id rotation on login), cookie flags, reuse after logout, and concurrent-session validity.
- **CSRF** — token presence, cross-session replay, and pre/post-login token reuse.
- **Open-redirect** testing on post-login navigation parameters.
- **Information-disclosure** probing of error responses.
- **Password storage/policy** inference from source.

**Constraints:** No third-party host was contacted; all traffic was directed at the provided lab target. Request/response pairs were captured as evidence for every conclusion, and defenses that held are documented as first-class negative results.

**Tools:** HTTP client (`curl`) for raw request control; source-code review for root-cause confirmation.

# Technical Analysis

# Technical Analysis

**Severity model:** exploitability (attack vector, complexity, privileges, interaction) × impact (confidentiality, integrity, availability), scored with CVSS v3.1 and justified against demonstrated behavior.

## Confirmed findings

1. **Login brute-force lockout is per-session only (Critical, CVSS 9.1, CWE-307).** The failed-attempt counter and lock expiry (`$_SESSION['__login_fails']`, `$_SESSION['__login_until']`) live exclusively in the PHP session. A client that simply omits the `PHPSESSID` cookie receives a fresh session with a zeroed counter; the same session is regenerated on every successful login. Confirmed: five failures produced the lock, while a brand-new session immediately accepted further attempts and a correct login with no lockout. `X-Forwarded-For` rotation had no effect (the throttle is not IP-scoped), and independent sessions run without any cap. The audit table records failures but nothing enforces a limit from it. (The source comment itself acknowledges the gap: "Add an IP-keyed store if deployed open-internet.")

2. **NUL-byte truncation in password verification (High, CVSS 7.4, CWE-230).** The handler passes the raw password to a bcrypt verification without rejecting NUL bytes. Because bcrypt comparison is NUL-terminated, `password123%00INJECTED` authenticates identically to `password123`, while `password123XINJECTED` is rejected. Confirmed across the `mahasiswa` and `dosen` roles, with correct-prefix controls demonstrating the truncation is specific to a NUL byte immediately following the correctly typed password. The username field is unaffected (it is compared in full via a parameterized SQL query), isolating the defect to the password verifier.

## Defenses confirmed to hold (negative results)

- **SQL injection — none.** All probes (quote, comment, `OR '1'='1`, `UNION SELECT`, mixed-case/encoded) returned `302 → /login` with no SQL error, and time-based `SLEEP(2)`/`SLEEP(5)`/stacked/subquery payloads showed no measurable delay versus the ~0.68 s baseline. Consistent with parameterized PDO queries and `ATTR_EMULATE_PREPARES=false`.
- **`user_type` manipulation — no effect.** Unrecognized values fall back to the default role sequence; the assigned role always derives from the matched account, not the requested type. A `mahasiswa` credential with `user_type=admin` logged into `/pelanggaran` and `/admin` remained denied — no privilege escalation.
- **Other bypass classes — rejected.** Empty/whitespace passwords, array injection (`username[]`/`password[]`/`user_type[]`), type juggling, and `0e`-style magic hashes all failed.
- **User enumeration — neutralized.** Valid-wrong-password and nonexistent accounts returned identical status, redirect, body length, and message; timing was equalized (0.6845 s vs 0.6861 s over repeated samples) by a dummy bcrypt cost-12 verification.
- **CSRF — enforced.** Missing, empty, wrong, and cross-session-replayed tokens all returned `419`; the token rotates on login and the pre-login token is rejected afterward.
- **Session management — sound.** The session id rotates on successful login (fixation mitigated); cookies are `HttpOnly` and `SameSite=Lax`; logout destroys the session and deletes the cookie; the old session id is rejected.
- **Open redirect — none.** `next`/`return`/`redirect`/`returnUrl` parameters were ignored; the post-login Location is a fixed, server-side role mapping.
- **Information disclosure — minimal.** No stack traces, SQL errors, or PHP warnings were emitted; sensitive paths (`/config.php`) return `403` and malformed input fails closed.
- **Password storage — strong.** bcrypt cost 12; the model rejects non-`$2` hashes, closing any plaintext fallback.

## Systemic theme

The two confirmed weaknesses share a root cause: security controls relying on client-influenced or semantically-ambiguous state (the session identifier for rate limiting; verifier NUL semantics for credential comparison) instead of explicit, server-side validation. The application's input-path hardening is otherwise consistent and effective.

## Attack-chaining assessment

The two findings were assessed for combination into a higher-impact chain. They are independent: **vuln-0002** is a rate-limit weakness that enables credential guessing, and **vuln-0001** is an input-validation weakness that requires prior knowledge of a correct password. Individually, vuln-0002 already yields the higher-impact outcome (unbounded brute force → account compromise), so it was scored accordingly and requires no chaining. Together they do not produce a stronger outcome than vuln-0002 alone: the lockout bypass does not help reach a *correct* password prefix that vuln-0001 needs, and vuln-0001 does not extend the reach of recovered credentials beyond what a directly-guessed password provides. No further plausible combination of the two confirmed findings was identified, and the remaining tested classes produced no exploitable primitives to chain with.

# Recommendations

# Recommendations

## Immediate

1. **Make brute-force protection session-independent.** Move the failed-attempt counter and lock state to a server-side store keyed on an attribute the client cannot reset — the source IP address, the attempted account identifier, or both. Enforce the 5-attempts/15-minute rule by querying recent failures from a durable store (the existing `SECURITY_AUDIT_LOG` table already captures event, actor, sid, and ip) rather than the session. Restrict lockout evaluation to a trusted client-IP source, since `X-Forwarded-For` is attacker-controllable (vuln-0002).
2. **Reject NUL bytes in credentials.** Before invoking the password verifier, reject any `username` or `password` containing a NUL byte and record an audit event. Enforce an explicit maximum password length (e.g. 72 bytes for bcrypt) so inputs cannot be silently truncated (vuln-0001).

## Short-term

3. **Add defense-in-depth to login:** a per-IP global rate limit and a progressive delay or CAPTCHA after repeated failures, to slow distributed guessing regardless of session.
4. **Add regression tests** asserting that (a) a valid password suffixed with `\0` plus data fails authentication, and (b) a fresh session does not reset the lockout counter for a locked account/IP.
5. **Alert or temporarily disable the targeted account** after a failure threshold, and surface repeated-failure patterns from the audit log.

## Medium-term

6. **Remove plaintext passwords from the development seed `.sql`** and ensure the hashing migration (`database/cli/hash-plaintext-passwords.php`) is part of every deployment path, so no plaintext rows can reach an environment (observed in source; not exploitable on the tested instance).
7. **Standardize credential-input normalization** — apply the same validation (including NUL and length checks) uniformly across username and password so handling is consistent and auditable.

## Retest & validation

Re-test the immediate items on a fresh instance: confirm that rotating `PHPSESSID` (and rotating `X-Forwarded-For`) no longer resets the lockout counter for a locked account/IP, and confirm that a correct password suffixed with `%00` plus arbitrary data is rejected while the exact password still authenticates. Verify the lockout still permits a legitimate user after the cool-down window.

