# AREA 3 — FILE UPLOAD, DOWNLOAD & IDOR / ACCESS CONTROL (offensive, focused)

Authorized penetration test against a LOCAL, SELF-OWNED instance of DiscipLink
(PHP 8.3 native). Target: http://172.17.112.1:8001
Also mounted: the source repo (whitebox). Trace:
  request/handler-upload.php, request/handler-download.php,
  helpers/token_helper.php (file/ID token sealing), controllers/PelanggaranController.php,
  models/Pelanggaran.php, storage/uploads/, router.php (deny + token routes).

## CONTEXT (already found in AREA 1-2 — do NOT re-report)
- vuln-0001 HIGH: NUL-byte password truncation.
- vuln-0002 CRITICAL: per-session login lockout bypass.
- Area 2: cookie missing Secure; no absolute session lifetime.
AREA 3 is about FILE HANDLING AND OBJECT-LEVEL AUTHORIZATION.

## SCOPE: UPLOAD / DOWNLOAD / IDOR / BROKEN ACCESS CONTROL.

## Attack classes to attempt (PoC or documented negative result)

### A. File upload
1. Unrestricted file type — upload .php, .phtml, .php5, .phar, double extension
   (x.php.png), mismatched Content-Type vs extension, image with appended PHP.
   Did it land in a web-served path? Can it be executed?
2. Path traversal in the stored filename — `../`, `..%2f`, absolute paths,
   `....//`, null-byte in name. Where does the file actually land?
3. Upload size / overwrite — overwrite another user's file by reusing a name.
4. Polyglot / SVG with script — stored XSS via uploaded SVG served inline.

### B. Download / file disclosure
5. Token sealing — are download links protected by a session-bound token?
   Tamper the token (flip bytes), replay a valid token from session A in
   session B, and use an expired token. Must fail closed.
6. IDOR on download — request another user's file by guessing/altering the
   identifier or by using a raw filename instead of a token.
7. Direct path access — try to GET /storage/uploads/<file> directly and via
   `..%2f` traversal; confirm the .htaccess/router deny actually holds.

### C. Object-level authorization (IDOR / BOLA)
8. Enumerate object references in the pelanggaran (violation) flow: edit,
   detail, cancel, delete. As mahasiswa A, attempt to read/edit/cancel
   mahasiswa B's violation by manipulating the id/token.
9. As `dosen`, attempt to act on records that are not theirs.
10. As `mahasiswa`, attempt any `/admin/*` action (news, tatib, users) directly
    with a crafted POST — confirm server-side role enforcement, not just UI.

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