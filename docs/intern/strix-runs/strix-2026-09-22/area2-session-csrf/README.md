# Area 2 - Session & CSRF (run 2026-09-22)

| Bagian | Isi |
| ------ | --- |
| [`before/`](before/) | Artefak mentah Strix re-scan: `findings.sarif`, `vulnerabilities/vuln-*.md`, `penetration_test_report.md`, `run.json`, `strix.log`, `.state/` |
| [`after/`](after/) | Bukti perbaikan temuan BARU area ini + skrip/commit |

## Temuan run ini

| ID | Severity | CWE | Temuan | Status |
| --- | -------- | --- | ------ | ------ |
| A2-vuln-0001 (2026-09-22) | MEDIUM (4.2) | CWE-384 | `session.use_strict_mode` nonaktif - session ID tak dikenal dari klien diterima (session fixation) | New |

Fix: `a56baca` (aktifkan `use_strict_mode`). Regression: `tests/security/SessionFixationSuite.php`.

> 2 temuan LAMA run 2026-09-21 (cookie tanpa `Secure`, tanpa absolute lifetime)
> **HILANG** di re-scan ini. Perbaikan temuan baru **belum** diverifikasi re-scan
> ketiga - lihat [`after/README.md`](after/README.md).
