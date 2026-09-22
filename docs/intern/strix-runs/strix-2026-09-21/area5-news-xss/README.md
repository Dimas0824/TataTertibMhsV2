# Area 5 - News module / XSS (run 2026-09-21)

Indeks area. Artefak mentah ada di `before/`, bukti perbaikan ada di `after/`.

| Bagian | Isi |
| ------ | --- |
| [`before/`](before/) | Artefak mentah Strix: `findings.sarif`, `vulnerabilities/vuln-*.md`, `penetration_test_report.md`, `run.json`, `strix.log`, `.state/` |
| [`after/`](after/) | Bukti perbaikan + skrip reproduce + screenshot |

## Temuan run ini

| ID | Severity | CWE | Temuan |
| -- | ---- | --- | ------ |
| A5-vuln-0001 | MEDIUM (5.4) | CWE-79 | Stored XSS halaman publik via quote-boundary bypass sanitizer |

Regression: `tests/unit/Area5XssSuite.php`.

> Status: **HILANG** di re-scan 2026-09-22. Re-scan menemukan **temuan baru**
> (XSS via JSON-LD) - lihat
> [`../../strix-2026-09-22/area5-news-xss/`](../../strix-2026-09-22/area5-news-xss/).
