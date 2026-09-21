# Security Penetration Test Report

**Generated:** 2026-09-21 14:50:51 UTC

# Executive Summary

# Executive Summary

An authorized gray-box penetration test of **DiscipLink** (`http://172.17.112.1:8001`) was performed, focused on **file upload, file download, and object-level authorization (IDOR/BOLA)**. The application is a PHP 8.3 native (no framework) student-discipline management system.

**Overall risk posture for the tested area: Strong — no exploitable weakness was identified.**

Every attempt to bypass upload controls, forge or replay file-download tokens, enumerate other users' objects, or escalate role privileges was blocked by server-side controls. The key defenses are: server-side MIME detection (`finfo`) with an extension allowlist, server-generated non-guessable filenames, per-session authenticated-encryption capability tokens (NaCl secretbox / AES-GCM) bound to the session identifier, subject and expiry, ownership-scoped SQL for every object operation, and server-side role + CSRF enforcement on all state-changing endpoints.

**Business impact of the tested area:** None — no unauthorized file read/write, no cross-user object access, no privilege escalation was achieved.

**Non-security observations** (documented for completeness, not vulnerabilities): one view hardcodes a download link so that all students receive the same generic template PDF (a functionality defect, not a disclosure), and an administrator navigating to a lecturer-only page receives a graceful HTTP 500 with no internal detail leaked.

# Methodology

# Methodology

**Engagement type:** Gray-box (white-box source review combined with live black-box exploitation) against a local, self-owned instance.

**Scope:** `http://172.17.112.1:8001`, restricted to file upload, file download, and object-level authorization. Source reviewed: `request/handler-upload.php`, `request/handler-download.php`, `helpers/token_helper.php`, `helpers/route_helper.php`, `controllers/PelanggaranController.php`, `controllers/NewsController.php`, `models/Pelanggaran.php`, `router.php`, `.htaccess`, and the rendering views.

**Activities:** authentication as four distinct principals (mahasiswa `2341238901`, second mahasiswa `2341238902`, dosen `1234567890`, second dosen `1234567891`, admin `ADMIN001`); upload type/traversal/overwrite/polyglot testing; token sealing tests (tamper, cross-session replay, cross-entity confusion, expiry by code analysis); direct storage-path and traversal access; and cross-user object reference manipulation across the edit/detail/confirm/delete violation flows and administrative actions.

**Reference framework:** OWASP Web Security Testing Guide (WSTG) — file upload, path traversal, IDOR, broken access control, and role-based access testing categories.

**Constraints:** The token TTL (1800 s) could not be awaited in live testing; expiry enforcement was confirmed by source analysis of the decode path. Negative (held) results were recorded alongside successful ones and are first-class evidence for the coverage matrix.

# Technical Analysis

# Technical Analysis

Testing concentrated on three attack surfaces. All were hardened. No confirmed vulnerability met the proof-of-concept threshold, so no vulnerability reports were filed for this area.

## 1. File upload
- **Type restriction.** The handler derives the type from server-side `finfo` MIME detection and ignores the client-declared `Content-Type`. Uploads of `.php`, `.phtml`, `.docx` content and PHP-in-image content were rejected with *"Tipe file tidak diizinkan."* A valid JPEG header with appended PHP passed MIME checks but was stored with the **original** extension (`.jpg`) — non-executable and served as `image/jpeg` with `nosniff`.
- **Filename handling.** The stored name is `<idDetail>_<fileType>_<24-hex>.<ext>` generated server-side; the client filename is never used for the path. Traversal payloads (`../../`, `..%2f`, `....//`), null bytes and absolute paths all landed as generated names inside `storage/uploads/` with nothing written elsewhere.
- **Overwrite.** A 12-byte random suffix makes collisions/overwrites infeasible (two same-named uploads produced two distinct files).
- **SVG / polyglot XSS.** SVG MIME is not permitted and SVG-content-as-PNG is rejected by `finfo`; news images accept only JPEG/PNG and are served as `image/jpeg` with `nosniff`. Requests for a `.php` path in the upload directory return 404.

## 2. Download / file disclosure
- **Token sealing.** Download links carry a sealed capability token. Flipping one character → 403. Replaying a token in a different session (verified dosen↔mahasiswa and second-user permutations) → 403, because the payload embeds `sid = sha256(session_id)` compared with `hash_equals`. Cross-entity reuse (a notification or detail token used as a file token) → 403. Expiry is enforced in the decode routine.
- **Direct object access.** A raw filename instead of a token → 403. Direct GETs of `/storage/uploads/<file>`, `/storage/keys/app_token.key`, `/config.php` and `/.env` → 403; traversal attempts → 403. Only the intentionally public news-image directory is reachable.
- **Download headers.** Successful downloads use `Content-Disposition: attachment`, `Cache-Control: no-store`, and `X-Content-Type-Options: nosniff`, preventing inline execution and intermediary caching of token-bearing URLs.

## 3. Object-level authorization (IDOR / BOLA)
- Violation records are addressed by sealed, session-bound ID tokens; raw numeric IDs → 403. A second user replaying another user's token (edit, download, upload, confirm, delete) was rejected in every case, and the underlying SQL is additionally scoped to the authenticated `nidn`/`nim` (ownership predicate on `id_dosen` / `id_dosen_penanggung_jawab` / `id_mhs`). Cross-user deletion returned a redirect with a *"token invalid"* flash while the target record remained intact.
- Upload authorization resolves the record through an ownership-scoped query joining `MAHASISWA`/`DOSEN`; absent, foreign, or malformed `id_detail` tokens all fail closed.
- Role enforcement is server-side: mahasiswa and dosen POSTs to administrative endpoints (`/action/news`, `/action/tatib`) → 403, `/admin/*` pages redirect, and an admin cannot invoke lecturer-only violation actions.

**Systemic theme:** authorization is centralized and deny-by-default, and file/object references are sealed opaque tokens bound to the session rather than guessable sequential identifiers — which is why the IDOR and token attacks uniformly failed.

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

