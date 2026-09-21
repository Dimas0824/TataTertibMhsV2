# AREA 4 — VIOLATION WORKFLOW: BUSINESS LOGIC, INPUT VALIDATION & AUTHORIZATION

Authorized penetration test against a LOCAL, SELF-OWNED instance of DiscipLink
(PHP 8.3 native). Target: http://172.17.112.1:8001
Also mounted: the source repo (whitebox). Trace:
  controllers/PelanggaranController.php, models/Pelanggaran.php, models/Sanksi.php,
  request/handler-pelanggaran.php, views/pelanggaran/*.php,
  helpers/token_helper.php (authz + tokens), database/migrations/*.sql.

## CONTEXT (already found in AREA 1-3 — do NOT re-report)
- AREA 1: NUL-byte password truncation (HIGH); per-session lockout bypass (CRITICAL).
- AREA 2: cookie without Secure; no absolute session lifetime.
- AREA 3: upload/download/IDOR — all hardened; no findings.
AREA 4 moves to the VIOLATION RECORD WORKFLOW itself.

## SCOPE: the violation (pelanggaran) lifecycle + its inputs + its authorization.

## Attack classes to attempt (PoC or documented negative result)

### A. Business logic
1. Point manipulation — can a mahasiswa self-report, or cause points to be
   added/removed for a violation they should not control?
2. Sanksi (sanction) threshold — is the sanction level always recomputed
   server-side from the stored points, or can a client-supplied level/points
   value be persisted? Try sending forged `tingkat`/`poin`/`sanksi` fields.
3. Workflow/state skipping — can a report be edited/confirmed/cancelled from an
   invalid state, or can a step be repeated to duplicate a record?
4. Duplicate submission — submit the same violation twice quickly (race) and
   see if two records / double points result.
5. Ownership of edits — after DPA delegation changes, can the wrong lecturer
   still edit/confirm a record?

### B. Input validation / injection
6. SQL injection in violation fields (description, id lookups, filter/search
   params, `tingkat` filter, pagination, sort keys). error-based, boolean,
   time-based, UNION, stacked.
7. Stored XSS via the violation description / task fields as seen by dosen and
   admin (the sanitizer must hold at rest AND at render).
8. Mass assignment — inject extra POST fields (e.g. `id_mhs`, `id_dosen`,
   `poin`, `status`) and see whether they are honoured.
9. Type juggling — send arrays where scalars are expected
   (`id_detail[]=`, `poin[]=`), negative points, huge integers, floats.

### C. Authorization
10. As mahasiswa: attempt dosen-only actions (confirm, delegate DPA, edit
    another student's report).
11. As dosen: attempt admin-only actions.
12. Cross-role token reuse on violation detail/edit/confirm/cancel.

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