# Security Penetration Test Report

**Generated:** 2026-09-21 15:00:50 UTC

# Executive Summary

# Executive Summary

An authorized white-box penetration test of the DISCIPLINARY VIOLATION WORKFLOW of the DiscipLink application (local instance at `http://172.17.112.1:8001`) identified **one confirmed business-logic / data-integrity vulnerability** in how a violation record's sanction is stored.

**Overall risk posture:** Moderate (localized). The violation workflow is otherwise well hardened — authorization, input handling, injection defenses, and workflow state controls all held under targeted testing.

**Key finding**
- **Sanction level not validated against violation tier (Medium, CVSS 6.5).** When a lecturer records a violation, the sanction is accepted from a client-supplied value and stored without verifying that the sanction's tier matches the violation's tier. A lecturer can attach the most severe sanction ("Diberhentikan sebagai mahasiswa" — expulsion) to a trivial Tier V, 2-point infraction, and the student portal presents it as authoritative. The same manipulation is accepted when editing an existing record.

**Business impact**
- The disciplinary record is the authoritative artifact shown to students; a mismatched sanction can cause wrongful distress, reputational harm, and support fraudulent escalation. Because the flaw is server-side and silent, it undermines trust in the integrity of the whole violation registry.

**Notable strengths (verified, not assumed)**
- Role separation (mahasiswa/dosen/admin), ownership after DPA delegation, session-bound and entity-scoped capability tokens, parameterized SQL, output encoding at every render sink, strict output/type handling, and server-side workflow-state controls all resisted the tested attacks.

# Methodology

# Methodology

**Engagement type:** Authorized gray-box assessment against a local, self-owned instance of DiscipLink (PHP 8.3 native). Scope was limited to the violation (`pelanggaran`) lifecycle and its inputs and authorization. Testing combined static review of the mounted source repository with live request/response PoCs.

**Framework alignment:** OWASP WSTG (business-logic, authorization, input-validation, and injection test categories) and PTES.

**Areas tested**
1. Business logic — self-reporting, point manipulation, sanction-threshold enforcement, workflow/state skipping, step repetition, duplicate submission, and edit ownership after DPA (delegated adviser) changes.
2. Input validation and injection — SQL injection (error-, boolean-, time-based, UNION, stacked) in all violation fields and search/filter/pagination parameters; stored XSS in violation description and task fields as rendered to students, lecturers, and admins; mass assignment; type juggling (arrays, negatives, floats, oversized integers, NUL bytes).
3. Authorization — mahasiswa attempting lecturer-only actions, lecturer attempting admin-only actions, and cross-role token reuse on violation detail/edit/confirm/cancel.

**Constraints:** The report focuses on one confirmed, PoC-backed issue. Defenses that held are documented as first-class negative results for the remediation matrix.

**Techniques:** Session-authenticated request replay with token/parameter tampering, concurrent-submission race testing, payload reflection/escaping analysis across all render sinks, and timing analysis for injection.

# Technical Analysis

# Technical Analysis

**Severity model:** exploitability × impact. The single confirmed finding is **Medium** because it requires a low-privilege authenticated lecturer and yields record-integrity impact (no confidentiality or availability compromise).

## Confirmed finding

1. **Sanction level not validated against violation tier** (Medium, CVSS 6.5) — endpoint `POST /action/pelanggaran`. The violation tier is correctly derived server-side from the selected tata-tertib row, and points are read from the same row at render time, so neither can be forged directly. However, the sanction supplied in the `sanksi` field overrides the tier-consistent default and is never compared against the violation tier. The model performs only an existence check on the sanction row, never reading its tier. A tier-V, 2-point violation was stored and rendered with the tier-I sanction "Diberhentikan sebagai mahasiswa". Reproducible on both the create and update paths. Root cause is a missing cross-field integrity check on a trusted, server-authoritative record.

## Systemic themes

- **Server-side values are trusted where derived, but one client-supplied linkage (violation → sanction) bypasses the derivation.** The fix theme is to enforce referential consistency for every client-selectable linkage that carries semantic weight.
- **Token design is sound.** Capability tokens are authenticated-encrypted and bound to the session and to a specific entity, which blocked cross-role and cross-session reuse; this is the model the sanction linkage should also follow.

## Defenses that held (verified negative results)

- **Self-reporting / point manipulation:** students cannot create or edit violations; points and tier are always server-derived.
- **Workflow/state integrity:** violation `status` is hard-coded server-side; confirmation requires the mandated uploaded documents; edits are blocked once a record is complete; deletion removes the record and its notifications transactionally.
- **Duplicate submission (race):** concurrent identical submissions create multiple rows, which is legitimate (no de-duplication requirement) — not a vulnerability.
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
1. **Enforce sanction–violation tier consistency.** Derive the sanction server-side from the violation tier and do not accept an arbitrary client-selected sanction. If selection among valid sanctions is required, constrain the choice to sanctions whose tier equals the violation's tier, and reject any mismatch on both the create and update paths.
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

