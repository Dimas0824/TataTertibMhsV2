# Area 1 — Login / Authentication (run 2026-09-21)

Indeks area. Artefak mentah ada di `before/`, bukti perbaikan ada di `after/`.

| Bagian | Isi |
| ------ | --- |
| [`before/`](before/) | Artefak mentah Strix: `findings.sarif`, `vulnerabilities/vuln-*.md`, `penetration_test_report.md`, `run.json`, `strix.log`, `.state/` |
| [`after/`](after/) | Bukti perbaikan + skrip reproduce + screenshot |

## Temuan run ini

| ID | Severity | CWE | Temuan |
| -- | -------- | --- | ------ |
| A1-vuln-0002 | CRITICAL (9.1) | CWE-307 | Lockout brute-force per-sesi — buang cookie = reset counter |
| A1-vuln-0001 | HIGH (7.4) | CWE-230 | NUL-byte truncation pada verifikasi password |

Fix: `ba4e8d1` (fix) · `6e3f3b1` (regression test) · `ffdaa26` (docs).
Regression: `tests/security/LoginBruteForceSuite.php`.

> Status: **HILANG** di re-scan 2026-09-22 — lihat
> [`../../strix-2026-09-22/`](../../strix-2026-09-22/).
