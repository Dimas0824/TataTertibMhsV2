# Area 4 — Pelanggaran / Violation workflow (run 2026-09-21)

Indeks area. Artefak mentah ada di `before/`, bukti perbaikan ada di `after/`.

| Bagian | Isi |
| ------ | --- |
| [`before/`](before/) | Artefak mentah Strix: `findings.sarif`, `vulnerabilities/vuln-*.md`, `penetration_test_report.md`, `run.json`, `strix.log`, `.state/` |
| [`after/`](after/) | Bukti perbaikan + skrip reproduce + screenshot |

## Temuan run ini

| ID | Severity | CWE | Temuan |
| -- | -------- | --- | ------ |
| A4-vuln-0001 | MEDIUM (6.5) | CWE-20 | Sanksi tidak divalidasi terhadap tingkat pelanggaran (client-selectable) |

Regression: `tests/unit/Area4SanctionSuite.php`.

> Status: **HILANG** di re-scan 2026-09-22. Re-scan menemukan **temuan baru**
> (hapus pelanggaran finalized) — lihat
> [`../../strix-2026-09-22/area4-pelanggaran/`](../../strix-2026-09-22/area4-pelanggaran/).
