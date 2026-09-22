# Area 1 - Login / Authentication (run 2026-09-22)

| Bagian | Isi |
| ------ | --- |
| [`before/`](before/) | Artefak mentah Strix re-scan: `findings.sarif`, `vulnerabilities/vuln-*.md`, `penetration_test_report.md`, `run.json`, `strix.log`, `.state/` |
| [`after/`](after/) | Bukti perbaikan temuan BARU area ini (area 1: tidak ada temuan valid) |

## Temuan run ini

| ID | Severity | CWE | Temuan | Status |
| --- | -------- | --- | ------ | ------ |
| A1-vuln-0001 (2026-09-22) | CRITICAL (klaim) | CWE-307 | Login lockout bisa dilewati via identifier akun berbeda huruf besar/kecil | False-positive |

Temuan tunggal area ini **false positive** - throttle & lookup keduanya
case-insensitive (collation `utf8mb4_unicode_ci`). Bukti:
[`../../verification-analysis/`](../verification-analysis/).

> 2 temuan LAMA run 2026-09-21 (NUL-byte, lockout per-sesi) **HILANG** di re-scan ini.
