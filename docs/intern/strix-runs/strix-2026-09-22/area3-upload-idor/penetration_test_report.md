# Security Penetration Test Report

**Generated:** 2026-09-21 23:05:22 UTC

# Executive Summary

# Executive Summary

A focused assessment of the **file-handling and object-level authorization** surface of the DiscipLink application (PHP 8.3) was performed, covering file upload, file download, direct file access, and cross-user object access within the violation (pelanggaran) workflow.

**Overall risk posture:** Low. The file-handling and access-control design is mature and, in almost every case, defended correctly — an AEAD-sealed, session-bound capability-token layer governs file and object access, and file storage is confined to a non-servable, non-executable directory. One defect was confirmed.

**Key finding**
- **Missing role guard on the student violation page (Medium, CVSS 4.3).** An authenticated administrator visiting `/pelanggaran` receives an unhandled `HTTP 500` instead of a controlled `403`. The page only redirects the `dosen` role and then dereferences student-only session fields that an administrator account does not possess. The equivalent lecturer page is guarded and correctly returns `403`, so the student page is an oversight rather than a design choice.

**Business impact**
- Limited: no customer data is exposed, no records are modified, and no stack traces or internal paths leak. The defect is an availability and consistency issue — a page fails with a generic error page rather than denying cleanly — and it requires an authenticated administrator account.

**Positive security posture**
- Malicious uploads (PHP/polyglot/traversal/mismatched types) are rejected or neutralized; arbitrary download and direct path access are refused; cross-user object manipulation (IDOR/BOLA) on the violation, notification, and admin flows is denied. These outcomes are documented in the report matrix as evidence of effective controls.

# Methodology

# Methodology

**Engagement type:** Gray-box (source-assisted) external test against a self-owned local instance at `http://172.17.112.1:8001`, with the application source available for tracing.

**Framework alignment:** Testing was organized around the OWASP Web Security Testing Guide (WSTG) and OWASP Top 10 categories relevant to this scope — unrestricted file upload, path traversal, insecure direct object references (IDOR), and broken access control.

**Scope:** File upload (`/action/upload`), file download (`/action/download`), the sealed token helper (file/ID capability tokens), direct path access to storage, and object-level authorization across the violation (pelanggaran) flow — edit, detail, confirm, cancel, delete — plus role enforcement on `/admin/*` actions.

**Activities performed**
1. Source review of `request/handler-upload.php`, `request/handler-download.php`, `helpers/token_helper.php`, `helpers/route_helper.php`, `controllers/PelanggaranController.php`, `models/Pelanggaran.php`, `router.php`, the `.htaccess` rules, and the role guards in each role-scoped view.
2. Live upload testing across file types, double extensions, MIME/extension mismatches, image-with-appended-code polyglots, SVG, and traversal/null-byte filenames.
3. Live download testing: token tamper, cross-session replay, unauthenticated access, raw-filename substitution, path traversal, and entity-type confusion.
4. Direct-path and traversal probes against sensitive directories and configuration.
5. Multi-account object-authorization testing (two students, two lecturers, one administrator) exercising cross-user tokens and raw identifiers on the violation, notification, and admin action handlers.
6. Role-boundary mapping across every role-scoped page and action handler.

**Accounts used:** a `mahasiswa` account, a second `mahasiswa` account, two `dosen` accounts (one with 40 records, one with 6), and an `admin` account. All testing was confined to the provided target.

# Technical Analysis

# Technical Analysis

**Severity model:** exploitability × impact, with CVSS v3.1 base vectors and least-privilege calibration. Requires-position and required-interaction prerequisites are reflected honestly rather than assumed ideal.

## Confirmed finding

**1. Missing role guard causes unhandled HTTP 500 for administrators — Medium (CVSS 4.3)**
`views/pelanggaran/pelanggaran-page.php` performs only a login check and a `dosen`-role redirect, then reads student-only session fields (`$userData['angkatan']`, `$userData['nim']`). An `admin` session's `user_data` row contains neither key, so the undefined-array-key warning is promoted by the router's error handler to an uncaught exception and rendered as a generic `500`. The sibling route `/pelanggaran/dosen` returns `403` for administrators, confirming the intended fail-closed behaviour. Impact is bounded to availability and access-control consistency; no data is exposed (display_errors disabled, generic error page). Fixed by adding a `mahasiswa`-only fail-closed guard.

## Defenses that held (documented negative results)

**File upload** — Extension allowlist plus `finfo` MIME allowlist, both fail-closed. `.php/.phtml/.phar/.php5`, `double.php.png`, real-image-bytes-named-`.php`, and `.svg` are all rejected (`422`). A real image with appended PHP is accepted only as a non-executable `.png` stored in a route-denied directory. Traversal and null-byte filenames are discarded because the stored name is server-generated (`{id}_{type}_{24-hex}.{ext}`).

**Download and token sealing** — Tokens are AEAD-sealed (sodium secretbox / AES-256-GCM), entity-bound, session-bound (`sid = sha256(session_id)`), and time-bound (`exp`). Single-byte tampering, cross-session replay, unauthenticated use, raw-filename substitution, path traversal, and entity-type confusion all fail closed (`403`). The download handler does not verify ownership, but the only way to obtain a `file=` token is server-side rendering of a record the session already owns, and stored filenames are random — so the gap is unreachable.

**Direct path access** — `/storage/uploads/*`, the token key, `/config.php`, `/.env`, and source directories, including all traversal variants, return `403` via both `.htaccess` and the router's sensitive-path filter.

**Object-level authorization** — Cross-user `id_detail` tokens, raw numeric identifiers, and the legacy `/a/<token>` route are all rejected across edit, confirm, delete, and upload. Students cannot browse or act on other students' or lecturers' records, cannot self-edit or self-approve violations, and cannot reach `/admin/*` pages or trigger `/action/news` and `/action/tatib` (server-side `403`). Notification read-marking is bound to the owning student/lecturer.

## Systemic themes
- Authorization is enforced by a consistent, deny-by-default capability-token layer, which neutralizes most object-reference attacks.
- Role enforcement is applied server-side in handlers, not merely in the UI.
- The one weakness is an inconsistency: the per-view role-check pattern is applied thoroughly for most views but was omitted on the student violation page.

## Attack chaining
No chainable primitive exists in this area. The token layer admits no user-controlled input into the sealing routine, so tokens cannot be forged, re-bound, or replayed; uploads cannot produce executable or guessable artifacts; and the download ownership gap is unreachable without a valid server-issued capability. The confirmed finding is a standalone robustness/availability defect that exposes no data to chain.

# Recommendations

# Recommendations

## Immediate
1. **Add a fail-closed role guard to the student violation page.** In `views/pelanggaran/pelanggaran-page.php`, immediately after the existing `dosen` redirect, deny any session whose role is not `mahasiswa` with a controlled `403` (reusing the existing `app_abort_forbidden()` helper) instead of falling through to student-only field access. This converts the unhandled `500` into a deliberate denial and aligns the page with the lecturer page.

## Short-term
2. **Centralize the role check.** Replace the per-view ad-hoc role conditionals with a single shared guard (for example `app_require_role('mahasiswa')` / `app_require_role('admin')`) invoked at the top of every role-scoped view and action handler, so an unexpected role always yields a deliberate denial rather than an unhandled exception. This removes the class of bug where one page is guarded and its sibling is not.
3. **Guard the remaining lecturer-only page consistently.** The lecturer reporting form (`/pelaporan`) is reachable by administrator and lecturer sessions; ensure the page itself enforces the intended role rather than relying solely on the action handler.

## Medium-term
4. **Add a regression test for every role-scoped page** asserting the expected `403`/redirect for each non-permitted role, extending the existing access-control suite to cover the student page (which currently only covers the lecturer page).
5. **Add an ownership assertion to the download handler** as defense-in-depth: verify that the resolved filename belongs to a record associated with the requesting session's identity, so the control does not depend solely on the unguessability of stored filenames.
6. **Keep the upload/finfo and sealed-token controls in place** — they performed correctly under active testing — and retain the deny-by-default storage routing.

## Retest and validation
Re-test the administrator request to `/pelanggaran` after remediation to confirm it returns `403` (not `500`), and re-run the cross-role page matrix to confirm every role-scoped page denies non-permitted roles consistently.

