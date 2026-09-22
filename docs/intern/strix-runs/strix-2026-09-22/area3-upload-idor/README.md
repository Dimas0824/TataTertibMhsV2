# Area 3 - Upload / Download / IDOR (run 2026-09-22)

| Bagian | Isi |
| ------ | --- |
| [`before/`](before/) | Artefak mentah Strix re-scan: `findings.sarif`, `vulnerabilities/vuln-*.md`, `penetration_test_report.md`, `run.json`, `strix.log`, `.state/` |
| [`after/`](after/) | Bukti perbaikan temuan BARU area ini + skrip/commit |

## Temuan run ini

| ID | Severity | CWE | Temuan | Status |
| --- | -------- | --- | ------ | ------ |
| A3-vuln-0001 (2026-09-22) | MEDIUM (4.3) | CWE-284 | Halaman mahasiswa tanpa role guard - role admin kena 500, bukan fail-closed | New |

Fix: `d4aec0e` (403, bukan 500, untuk non-mahasiswa). Regression: `tests/security/Area3AccessSuite.php`.

> Area ini 0 temuan di run 2026-09-21; re-scan menemukan **1 temuan baru**.
> Perbaikan **belum** diverifikasi re-scan ketiga - lihat [`after/README.md`](after/README.md).
