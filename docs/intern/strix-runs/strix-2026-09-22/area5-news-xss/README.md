# Area 5 - News module / XSS (run 2026-09-22)

| Bagian | Isi |
| ------ | --- |
| [`before/`](before/) | Artefak mentah Strix re-scan: `findings.sarif`, `vulnerabilities/vuln-*.md`, `penetration_test_report.md`, `run.json`, `strix.log`, `.state/` |
| [`after/`](after/) | Bukti perbaikan temuan BARU area ini + skrip/commit |

## Temuan run ini

| ID | Severity | CWE | Temuan | Status |
| --- | -------- | --- | ------ | ------ |
| A5-vuln-0001 (2026-09-22) | MEDIUM (5.4) | CWE-79 | Judul berita keluar dari blok JSON-LD (`application/ld+json`) - XSS | New |

Fix: `1181dec` (hex-escape nilai JSON-LD). Regression: `tests/unit/Area5JsonLdSuite.php`.

> 1 temuan LAMA run 2026-09-21 (stored XSS body) **HILANG** di re-scan ini.
> Perbaikan temuan baru **belum** diverifikasi re-scan ketiga - lihat [`after/README.md`](after/README.md).
