# Security Penetration Test Report

**Generated:** 2026-09-21 15:11:34 UTC

# Executive Summary

# Executive Summary

A focused security assessment of the **News/Announcement surface** of the DiscipLink application (PHP 8.3, local instance at `http://172.17.112.1:8001`) was performed. This area covers news authoring (admin), storage, and rendering to public, unauthenticated visitors.

**Overall risk posture:** Moderate. The News module's authorization and request-forgery defenses are strong, but its HTML content sanitizer is bypassable, resulting in a persistent script-injection issue on a public page.

**Key finding**
- **Stored cross-site scripting (XSS)** on the public news detail page. Article body content is filtered with `strip_tags()` plus a regular expression intended to remove inline event handlers. Because allowed tags keep their attributes and the regex only matches handlers preceded by whitespace or `/`, an author can inject a live handler by closing a preceding attribute with a quote (`<div title="x"onmouseover="alert(document.domain)">`). The payload is stored and emitted unescaped, executing in the browser of any visitor — including unauthenticated guests and administrators.

**Business impact**
- An attacker with an admin account can turn any article into a persistent script-execution vector seen by all visitors (students, lecturers, staff).
- Consequences include theft of session/CSRF material rendered in the page, execution of arbitrary JavaScript in the portal origin, content defacement, and drive-by actions on behalf of viewers.

**Notable defenses that held (verified negative results)**
- CSRF protection fails closed (missing/wrong/empty/foreign token → HTTP 419).
- Role authorization is enforced server-side (student/lecturer → 403; unauthenticated → 401).
- No SQL injection in news lookups; no server-side URL fetching or open redirect.
- Script/link/image tags and style attributes are stripped; JSON/JS embeds are safely escaped.

**Overarching remediation theme:** the tag/regex sanitizer is fundamentally unreliable and must be replaced by an allow-list HTML sanitizer applied authoritatively on render, backed by a strict Content Security Policy.

# Methodology

# Methodology

**Engagement type:** Gray-box assessment of a local, self-owned instance, with full access to the application source repository (white-box tracing).

**Scope:** The News/Announcement module and its supporting components — `controllers/NewsController.php`, `request/handler-news.php`, `models/News.php`, `views/admin/news-admin.php`, `views/admin/tambah-berita.php`, `views/admin/edit-berita.php`, `views/public/berita-detail.php`, `helpers/token_helper.php`, and `js/news-form-editor.js`. Target: `http://172.17.112.1:8001`.

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

1. **Stored XSS on the public news detail page** (Medium, CVSS 5.4 — `AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N`). Two layers share the same defective pattern:
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
- By contrast, the authorization, CSRF, and database layers apply consistent, fail-closed controls — the risk in this module is concentrated in output encoding.

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

