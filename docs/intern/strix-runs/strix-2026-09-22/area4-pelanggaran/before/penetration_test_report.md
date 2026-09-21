# Security Penetration Test Report

**Generated:** 2026-09-21 23:15:28 UTC

# Executive Summary

# Executive Summary

An authorized white-box assessment of the **violation (pelanggaran) lifecycle** of the DiscipLink application was conducted against the local instance at `http://172.17.112.1:8001`. The review covered business logic, input validation/injection, and authorization across the full violation workflow (create, delegate-to-DPA, upload, confirm, edit, delete) and its supporting inputs.

**Overall risk posture:** Moderate. The workflow is generally well-defended — the vast majority of attempted manipulations were correctly rejected — but one workflow-state enforcement gap allows destruction of finalized disciplinary records.

**Key finding**

- **Missing workflow-state check allows deletion of finalized violation records** (High, CVSS 7.1, `CWE-863`). A lecturer can permanently delete a violation record whose status is `selesai` (finalized), even though the UI disables the delete control for finalized records and the server refuses to *edit* them. This permits destruction of adjudicated disciplinary history and its sanction record.

**Defenses confirmed to hold (no finding)**

- Point/score manipulation and self-reporting by students are impossible; points are always derived server-side.
- Forged `poin`/`tingkat`/`status`/`sanksi`/`id_mhs`/`id_dosen` fields are ignored (no mass assignment); cross-tier sanctions are rejected.
- SQL injection across all violation fields, lookups, and search is not possible (native prepared statements with LIKE escaping).
- Stored XSS in violation description/task fields is not possible (output encoding at rest and at render).
- Role boundaries hold: students cannot perform lecturer actions; lecturers cannot reach admin actions.
- Session-bound AEAD identifiers prevent cross-role/cross-session token reuse.

**Business impact:** The single confirmed issue allows an authenticated lecturer to erase a completed, adjudicated sanction record for a student — undermining the integrity and auditability of the disciplinary process. The remaining control environment is robust, so remediation can be focused and low-effort.

# Methodology

# Methodology

**Engagement type:** Authorized white-box assessment of a local, self-owned instance, combining static source review with dynamic testing against the running application.

**Scope:** The violation (pelanggaran) lifecycle — `controllers/PelanggaranController.php`, `models/Pelanggaran.php`, `models/Sanksi.php`, `request/handler-pelanggaran.php`, `views/pelanggaran/*.php`, `helpers/token_helper.php`, `helpers/route_helper.php`, and the schema migrations.

**Framework:** Aligned to OWASP WSTG categories (business logic, authorization, input validation, injection). Attack classes exercised:

- **Business logic:** point/sanction manipulation, sanction-threshold bypass, workflow/state skipping, duplicate submission (concurrency), edit ownership after DPA delegation.
- **Input validation / injection:** SQL injection (error-, boolean-, time-based, UNION, stacked), stored XSS, mass assignment, type juggling (arrays, negatives, huge integers, floats).
- **Authorization:** role escalation (student→lecturer, lecturer→admin), cross-role/cross-session token reuse, and object-ownership enforcement on edit/confirm/delete.

**Test accounts:** lecturer `1234567890`, students `2341238901`–`2341238904`, admin `ADMIN001`.

**Approach:** Behaviour was verified by driving the full workflow end-to-end (create → student upload → confirm → edit/delete) and by intercepting and replaying requests with tampered parameters. Each attack class is reported with either a working proof of concept or a documented negative result (payload tried and why it failed). Two consecutive reproductions were obtained for the single confirmed finding.

# Technical Analysis

# Technical Analysis

**Severity model:** Exploitability × impact. The confirmed finding is rated High (CVSS 7.1) because it is trivially reproducible by any authenticated lecturer on their own reports and causes irreversible integrity loss of finalized records. All other tested classes are rated Informational (no exploitable issue).

## Confirmed finding

1. **Missing workflow-state check on violation deletion** (High, `CWE-863`) — `POST /action/pelanggaran?action=delete`.
   `hapusDetailPelanggaranByDosen()` enforces reporter ownership but applies no `status` predicate, so a record in the `selesai` state is deleted. The sibling edit path and the UI both enforce the finalized-state guard, proving the state is meant to be frozen. Verified end-to-end: the same record the server refuses to edit (`"Data tidak dapat diedit..."`) is deleted (`"Laporan berhasil dihapus."`), with the row count decreasing (52 → 51) across two consecutive trials. The associated notifications are cascade-deleted as well.

## Systemic themes

- **Workflow state is enforced inconsistently.** The finalized-state guard is applied in the edit path and UI, but omitted in the delete path. This is the root cause of the single finding. The fix is localized.
- **Server-side derivation is otherwise strong.** Sanction level, points, and tier are always recomputed server-side from `TATA_TERTIB`/`SANKSI`; client-supplied authority-bearing fields are ignored. This neutralized all point-/sanction-manipulation and mass-assignment attempts.
- **Consistent parameterization and output encoding.** No SQL injection or XSS sink was found in the violation surface.
- **Opaque, session-bound identifiers.** Entity IDs are transported as AEAD, session-bound tokens, which defeated cross-role and cross-session replay attempts and prevented object-ID tampering.

## Areas tested with no issue (summary)

- Point manipulation / student self-report — blocked.
- Sanction tier forging, raw/bogus sanction tokens, cross-tier sanctions — blocked.
- Confirm without required documents; confirm of a finalized record; uploads of the wrong document type — blocked.
- Concurrent duplicate creates — no atomicity/privilege defect (informational data-quality note only).
- DPA delegation ownership (pelapor vs. penanggung vs. uninvolved lecturer) — correctly enforced.
- SQL injection in `q`, `nim`, filters — no injection.
- Stored XSS in description/task fields — encoded at rest and render.
- Mass assignment, type juggling (arrays/negatives/huge/floats) — no bypass.
- Role boundaries (student→lecturer, lecturer→admin) and cross-role token reuse — blocked.

# Recommendations

# Recommendations

## Immediate

1. **Enforce the finalized-state guard on deletion.** In `models/Pelanggaran.php::hapusDetailPelanggaranByDosen()` (and mirror in the `request/handler-pelanggaran.php` delete branch), load `status`/`status_tugas` and refuse deletion when the record is finalized (`selesai`/`done`, or task finalized), returning an error before any `DELETE` executes. Reuse the exact predicate already applied by the edit path so the two paths cannot diverge again.

2. **Prefer record preservation over destruction for finalized cases.** Replace hard deletion of finalized records with a soft-delete / explicit "void" state that retains the original row, its sanction, and its audit history. Restrict any hard delete to active, unreviewed records.

## Short-term

3. **Centralize workflow-state transitions.** Move the per-state transition rules (which role may act, from which state, for which operation) into one reusable guard/state-machine used by create, edit, confirm, upload, and delete, rather than re-implementing the check per endpoint.

4. **Add regression tests.** Assert that delete is rejected for finalized records and accepted only for active ones, and that edit/confirm/delete all enforce identical ownership and state rules.

## Medium-term

5. **Preserve an append-only audit trail for violation state changes.** Log every create/edit/confirm/delete with actor, timestamp, and before/after state so that any administrative correction remains reconstructable even if a record is removed.

## Retest & validation

6. Re-test the delete endpoint after the fix to confirm a `selesai` record cannot be removed, while active-record deletion by the reporter still functions. Re-run the negative-result matrix (point/sanction forging, SQLi payloads, XSS payloads, role-boundary and token-reuse attempts) to confirm no regressions.

