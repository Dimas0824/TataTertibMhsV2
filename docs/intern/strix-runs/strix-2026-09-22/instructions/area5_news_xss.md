# AREA 5 — NEWS MODULE: XSS, SANITIZATION, CSRF & AUTHORIZATION

Authorized penetration test against a LOCAL, SELF-OWNED instance of DiscipLink
(PHP 8.3 native). Target: http://172.17.112.1:8001
Also mounted: the source repo (whitebox). Trace:
  controllers/NewsController.php, request/handler-news.php, models/News.php,
  views/admin/news-admin.php, views/admin/tambah-berita.php, views/admin/edit-berita.php,
  views/public/berita-detail.php, helpers/token_helper.php, js/news-form-editor.js.

## CONTEXT (already found in AREA 1-4 — do NOT re-report)
- AREA 1: NUL-byte password truncation (HIGH); per-session lockout bypass (CRITICAL).
- AREA 2: cookie without Secure; no absolute session lifetime.
- AREA 3: upload/download/IDOR hardened; no findings.
- AREA 4: sanction tier not validated against violation tier (MEDIUM).
AREA 5 is the NEWS/ANNOUNCEMENT surface (rich text -> public rendering).

## SCOPE: NEWS CRUD, its HTML sanitizer, and its authorization.

## Attack classes to attempt (PoC or documented negative result)

### A. Stored XSS (primary target)
1. Inject script payloads into news fields (title, body, slug, meta/SEO fields)
   as admin, then view the PUBLIC page and the admin list. Payloads to try:
   <script>alert(1)</script>, <img src=x onerror=alert(1)>,
   <svg/onload=alert(1)>, <p/onmouseover=alert(1)>, <iframe srcdoc=...>,
   javascript: URIs in <a href>, <a href="data:text/html,...">,
   mutation-XSS style <noscript><p title="</noscript><img src=x onerror=...">,
   and nested/mixed-case/entity-encoded variants (&#60;script&#62;,
   <ScRiPt>, <scr<script>ipt>).
2. Verify the sanitizer BOTH at rest (what is stored in DB) and at render
   (what the public page emits). A payload stored raw but escaped at render is
   acceptable; stored raw AND emitted raw is a finding.
3. Try to break out of the rich-text context (attribute injection, style=
   expression, unclosed tags) and of any JSON embed used by the editor.

### B. Injection
4. SQL injection in news lookups: slug, id, category/tag, search, pagination,
   sort. error-based, boolean, time-based, UNION, stacked.
5. Second-order: store a payload via create, then trigger it on edit/view.

### C. Authorization & workflow
6. CSRF on news create/edit/delete — POST without a token, replay a token,
   cross-session replay. Must fail closed.
7. As mahasiswa/dosen: attempt to POST to /action/news (create/edit/delete).
   Must be denied server-side (403), not merely hidden in the UI.
8. As admin: attempt to edit/delete another admin's article; check ownership
   expectations. Test whether drafts/unpublished articles are reachable
   publicly by guessing the slug/id.
9. Open redirect / SSRF via any URL or image field in news (e.g. "fetch from
   URL" or an image src the server follows).

### D. Output handling
10. Check whether news content is emitted inside a JSON/JS block
    (editor bootstrap) and whether that embed is safely escaped
    (it must be hex/unicode escaped, not raw).

## Rules of engagement
- Authorized local lab; attack ONLY the given target.
- Attach exact request + response for every finding.
- Record defenses that HELD (payload tried, why it failed) — negative results
  are first-class evidence for the README matrix.
- Severity justified by exploitability (Impact + Likelihood + CVSS).

## Known test accounts
- mahasiswa: 2341238901 / password123
- dosen: 1234567890 / password123
- admin: ADMIN001 / admin123