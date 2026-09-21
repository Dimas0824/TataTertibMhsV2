# Security Penetration Test Report

**Generated:** 2026-09-21 23:24:18 UTC

# Executive Summary

# Executive Summary

An authorized gray-box assessment of the **News / Announcement module** of the DiscipLink application (`http://172.17.112.1:8001`) was performed. The module is admin-managed and renders rich text to public pages, making it a high-value target for stored content attacks.

**Overall risk posture:** Moderate — the defense-in-depth controls around the news module are generally strong, but one confirmed stored cross-site scripting issue exists in a public rendering path.

**Key finding**
- **Stored XSS via the article title.** A news article title is embedded without escaping into a JSON-LD metadata block on the public article page. A crafted title can break out of that block and inject JavaScript that executes for every visitor of the affected article. Reported as a **Medium** severity issue (CVSS 5.4). An attacker requires an admin account to publish, but the payload then fires for all readers with no further interaction.

**Controls that held (verified negative results)**
- The article **body** sanitizer resisted a broad payload matrix (script tags, event handlers, dangerous URI schemes, entity-encoded/mixed-case/nested variants, and attribute-breakout attempts).
- **SQL injection** in the article lookup path was not possible (strict integer parsing plus bound parameters).
- **CSRF** protection on create/update/delete rejected missing, empty, incorrect, and cross-session tokens.
- **Server-side role enforcement** denied non-admin users on the news action endpoint (`403`), and the admin listing is scoped to the signed-in administrator.

**Business impact**
The confirmed issue allows script execution in the browsers of students, lecturers, guests, and administrators viewing an affected article — enabling session/credential theft, content defacement, or redirection to attacker-controlled pages. Remediation is low-effort (a single output-encoding fix), and the systemic theme — JSON embedded in HTML requires hex escaping of tag delimiters — is straightforward to standardize across the codebase.

# Methodology

# Methodology

This engagement assessed **AREA 5 — the News / Announcement module** of the DiscipLink application, a PHP 8.3 native web application.

**Engagement type:** Gray-box assessment against a local, self-owned instance (`http://172.17.112.1:8001`), with full read access to the application source for white-box tracing.

**Scope:** News CRUD operations, the HTML sanitizer applied to news content, and the authorization/CSRF controls governing the news surface. The public rendering of news articles was in scope as the sink for stored input.

**Frameworks:** Testing followed the **OWASP Web Security Testing Guide (WSTG)** and **OWASP Top 10 (2021)**, with emphasis on:
- WSTG-CLNT / stored cross-site scripting (A03:2021 Injection),
- Output-encoding contexts (HTML body, HTML attribute, and inside `<script>` / JSON-LD),
- SQL injection in lookups (A03:2021),
- CSRF and server-side authorization for state-changing operations (A01:2021 Broken Access Control).

**Activities performed:**
1. Source review of the news controller, request handler, model, admin and public views, token/CSRF helper, and the client-side rich-text editor.
2. A structured stored-XSS payload matrix (50+ payloads) covering `<script>`, event-handler attributes (`onerror`, `onload`, `onmouseover`, `ontoggle`, etc.), `javascript:`/`data:` URIs, entity-encoded, mixed-case, nested/concatenated tags, and attribute-breakout variants.
3. Verification of each payload in **two contexts**: the value persisted server-side (surfaced via the edit view) and the byte-for-byte rendering of the public article page.
4. Dynamic browser verification (headless Chromium) of any payload that survived server-side filtering.
5. Injection testing of the article lookup path (slug/id), plus CSRF (absent/empty/wrong/replayed/cross-session tokens) and server-side role enforcement on the news action endpoint.
6. Review of output-context handling for embedded JSON/JavaScript (the editor bootstrap and the SEO/JSON-LD block).

**Constraints:** Testing was limited to the provided target host and the listed test accounts (`mahasiswa`, `dosen`, `admin`); no denial-of-service or destructive actions against shared data beyond creating/altering test articles used as PoCs.

# Technical Analysis

# Technical Analysis

**Severity model:** Aligned to CVSS v3.1 (attack vector, complexity, privileges, interaction, scope, and CIA impact). One confirmed vulnerability was identified; the remaining attack classes produced negative results (the controls held).

## Confirmed finding

**1. Stored Cross-Site Scripting via the news title embedded in the JSON-LD Article schema (Medium, CVSS 5.4).**
The news title is embedded unescaped into a `<script type="application/ld+json">` block on the public article page. The JSON is serialized without hex-escaping of `<`, `>`, `&`, `'`, `"`, so a title containing `</script>` terminates the script element early and the trailing markup is parsed as HTML. A payload title `</script><svg onload=alert(document.domain)>` results in a live handler executing for any visitor of the article. Because the deployed Content-Security-Policy includes `script-src 'unsafe-inline'` (no nonce/hash), the injected inline handler is not blocked. The value is stored raw and emitted raw in this single output context; all other output contexts (page `<title>`, heading, and meta/OpenGraph tags) are correctly HTML-encoded. Verified both at the HTTP level (the literal `</script>` appears inside the JSON-LD `headline`) and in a live browser (the injected `<svg>` element with a live `onload` handler is present in the DOM and its JavaScript executes).

## Attack classes tested — controls that held (negative results)

**A. Stored XSS (content body):** The body-content sanitizer, applied both on write and again as a render-time defense, neutralized the full payload matrix: `<script>`, `<img onerror>`, `<svg onload>`, `<iframe srcdoc>`, `javascript:` and `data:` URIs in `href`/`src`, HTML-entity-encoded and mixed-case tags, nested/concatenated tags (`<scr<script>ipt>`), and attribute-breakout variants including handlers attached without a preceding space (`<div title="x"onmouseover=...>`), handlers separated by `/` or tab/newline, and `style=`/`expression()` constructs. Whole `<script>`/`<style>` blocks are removed with their contents, and a submission that reduces to empty content is rejected outright. Event-handler and URI-scheme filtering is robust.

**B. SQL Injection:** The article lookup derives its key from the slug via a strict trailing-integer pattern and an explicit integer cast, and all queries use bound prepared-statement parameters. Error-based, boolean, time-based, and stacked-query probes produced only `404` responses with no database error disclosure and no timing delay — not injectable.

**C. Authorization & CSRF:** State-changing news operations (create/update/delete) require a valid session-bound CSRF token: absent, empty, or incorrect tokens are rejected (`419`), and a token issued to a different session is rejected (`401`). Replay of a valid same-session token is accepted, which is expected for a per-session token design. Server-side role enforcement holds: `mahasiswa` and `dosen` submitting to the news action endpoint receive `403`, and the admin news pages redirect such users to their own pages; the admin listing shows only the signed-in administrator's own articles.

**D. Output handling — JSON/JavaScript embedding:** This is the location of the confirmed finding above. The other embedded-JavaScript surfaces reviewed (the client-side rich-text editor initialization and the analytics block) did not introduce an additional independent issue.

## Notes / hardening observations (not filed as vulnerabilities)

- **Draft reachability:** The news table has no status/draft/published column — all rows are immediately public, so "unpublished draft" access does not apply.
- **Object-level authorization (defense-in-depth):** The update/delete paths do not verify that the acting administrator owns the target article. In practice this is gated by session-bound, authenticated-encryption id-tokens that are only issued for the administrator's own rows, so cross-administrator editing was not reachable; noted as a hardening item rather than a confirmed exploit.
- **Open redirect / SSRF:** The news image field is upload-only and the server never fetches a user-supplied URL; canonical/redirect URLs are constructed from relative paths, so no open-redirect or SSRF condition was found.

## Systemic theme

Output encoding is applied correctly for HTML text and attribute contexts but was missed for the `<script>`-embedded JSON context — a reminder that JSON-in-HTML is not a safe sink without hex-escaping of tag delimiters.

# Recommendations

# Recommendations

## Immediate
1. **Fix the JSON-LD output encoding.** Serialize every JSON-LD block with hex escaping enabled (`JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`) in addition to the existing flags, applied centrally so all schema blocks are covered. This prevents any user-controlled string (article `headline`, `description`, `author`) from closing the enclosing `<script>` element.
2. **Re-publish / review existing articles** for titles or metadata containing `</script>` or angle brackets, and correct any found, since already-stored values remain exploitable until the rendering fix is deployed.
3. **Retest** the confirmed finding after remediation: create an article whose title contains `</script><svg onload=...>` and confirm the rendered page no longer contains a literal `</script>` inside any `application/ld+json` block and that no handler executes in the browser.

## Short-term
4. **Treat the news title as untrusted output data:** HTML-encode it at storage time as defense in depth, and optionally reject titles containing `<`/`>` unless legitimately required.
5. **Strengthen the Content-Security-Policy** by removing `'unsafe-inline'` from `script-src` and adopting nonces or hashes, so any residual inline-handler injection cannot execute.
6. **Add explicit object-level authorization checks** to the news update/delete paths so that an administrator can only modify articles they own, independent of token possession.

## Medium-term
7. **Standardize output-encoding guidance** across the codebase: maintain a context-specific escaping matrix (HTML text, HTML attribute, URL, JavaScript, and JSON-in-script) and apply the corresponding encoder at each sink; add linting/review checks for `json_encode` used inside `<script>` tags without hex flags.
8. **Introduce regression tests** for the news rendering pipeline that assert no user-controlled value can break out of a `<script>` context, complementing existing sanitizer tests.

## Retest & validation
After fixes are deployed, re-run the stored-XSS payload matrix (both storage-time and render-time), the CSRF and role-enforcement checks, and the injection probes to confirm the controls continue to hold and that the JSON-LD context is now safe.

