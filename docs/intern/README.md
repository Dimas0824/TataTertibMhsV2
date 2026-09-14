# Dokumentasi Internal (`docs/intern/`)

Kumpulan laporan audit, pentest, dan bug tracking untuk DiscipLink V2. Ini **bukan** dokumentasi
pengguna (untuk itu lihat [`../README.md`](../README.md)); folder ini menampung bukti & status
teknis internal — berguna untuk auditor baru maupun sebagai jejak portofolio.

## Isi

| Dokumen | Jenis | Isi |
| --------- | ------- | ----- |
| [`PENTEST-REPORT-2026-09-08.md`](./PENTEST-REPORT-2026-09-08.md) | Audit code-level | Pemetaan & remediasi temuan (auth/session, injection, file handling, XSS, server config) |
| [`pentest-strix/`](./pentest-strix/README.md) | Pentest otomatis (agen AI) | Fase **quick** (blackbox) & **deep** (authenticated 3 role) + artefak SARIF — temuan **sudah diremediasi** |
| [`SECURITY_AUDIT.md`](./SECURITY_AUDIT.md) | Audit baseline | Checklist PHP Manual Security |
| [`BUG_REPORT.md`](./BUG_REPORT.md) | Bug tracking | 4 bug era 2026-07 — semua **FIXED/VERIFIED** (jejak historis) |
| [`UPLOAD-500-INVESTIGATION.md`](./UPLOAD-500-INVESTIGATION.md) | Investigasi | Root cause + fix bug `/action/upload` 500 |
| [`TESTING-SUMMARY.md`](./TESTING-SUMMARY.md) | Ringkasan testing | Infrastruktur unit/integration/security/E2E |

## Alur kronologis (ringkas)

1. **2026-07** — Automated testing menemukan 4 bug (login, role selector, error message, DB test).
2. **2026-09-08** — Audit code-level + hardening: bcrypt, throttle, session regeneration,
   file-token IDOR, CSRF, error-disclosure, XSS escapes → semua temuan ditutup + regression suite.
3. **2026-09-14** — Pentest otomatis Strix: **quick** (menemukan robots.txt MEDIUM) + **deep**
   (authenticated, 0 vulnerability), plus menemukan bug fungsional `/action/upload` 500.
   **Semua temuan (robots.txt + upload 500 + gap IDOR upload) sudah diperbaiki dan diverifikasi**
   (`php tests/run.php` → 160/160 PASS; line coverage inti >80%).

## Kebijakan keamanan

Lihat [`../../SECURITY.md`](../../SECURITY.md) untuk cara melaporkan kerentanan.
