# Area 4 - Pelanggaran / Violation workflow (run 2026-09-22)

| Bagian | Isi |
| ------ | --- |
| [`before/`](before/) | Artefak mentah Strix re-scan: `findings.sarif`, `vulnerabilities/vuln-*.md`, `penetration_test_report.md`, `run.json`, `strix.log`, `.state/` |
| [`after/`](after/) | Bukti perbaikan temuan BARU area ini + skrip/commit |

## Temuan run ini

| ID | Severity | CWE | Temuan | Status |
| --- | -------- | --- | ------ | ------ |
| A4-vuln-0001 (2026-09-22) | HIGH (7.1) | CWE-863 | Pelanggaran berstatus `selesai` (finalized) masih bisa dihapus | New |

Fix: `579a4c6` (tolak hapus bila status `selesai`). Regression: `tests/unit/Area4WorkflowSuite.php`.

> 1 temuan LAMA run 2026-09-21 (sanksi tier) **HILANG** di re-scan ini.
> Perbaikan temuan baru **belum** diverifikasi re-scan ketiga - lihat [`after/README.md`](after/README.md).
