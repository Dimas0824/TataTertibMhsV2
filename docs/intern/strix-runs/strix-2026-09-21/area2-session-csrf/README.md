# Area 2 - Session & CSRF (run 2026-09-21)

Indeks area. Artefak mentah ada di `before/`, bukti perbaikan ada di `after/`.

| Bagian | Isi |
| ------ | --- |
| [`before/`](before/) | Artefak mentah Strix: `findings.sarif`, `vulnerabilities/vuln-*.md`, `penetration_test_report.md`, `run.json`, `strix.log`, `.state/` |
| [`after/`](after/) | Bukti perbaikan + skrip reproduce + screenshot |

## Temuan run ini

| ID | Severity | CWE | Temuan | Status |
| --- | -------- | --- | ------ | ------ |
| A2-vuln-0001 | MEDIUM (5.9) | CWE-614 | Cookie session tanpa `Secure` saat TLS di-terminate proxy | Confirmed-fixed |
| A2-vuln-0002 | LOW (3.7) | CWE-613 | Tidak ada absolute lifetime / invalidasi sesi bersamaan | Confirmed-fixed |

Regression: `tests/security/SessionLifecycleSuite.php`.

> Status: **HILANG** di re-scan 2026-09-22 - lihat
> [`../../strix-2026-09-22/`](../../strix-2026-09-22/).
