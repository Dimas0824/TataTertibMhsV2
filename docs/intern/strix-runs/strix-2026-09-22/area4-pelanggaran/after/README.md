# After - perbaikan temuan 2026-09-22 (Area 4: Pelanggaran / Violation workflow)

`before/` berisi artefak mentah re-scan. `after/` berisi bukti perbaikan
**temuan baru** yang muncul di re-scan 2026-09-22 untuk area ini.

## Temuan baru (2026-09-22)

| ID | Severity | CWE | Temuan | Fix |
| -- | -------- | --- | ------ | --- |
| A4-rerun-0001 | HIGH (CVSS 7.1) | CWE-20 | Pelanggaran berstatus `selesai` (finalized) masih bisa dihapus - integritas record hilang | commit `579a4c6` |

## Perbaikan

- `models/Pelanggaran.php` - hapus ditolak bila status pelanggaran sudah
  `selesai` (finalized); hanya record non-final yang bisa dihapus.

## Bukti

- Komit: `579a4c6` - fix(security): reject deletion of finalized violation
- Regression test: `tests/unit/Area4WorkflowSuite.php`, `tests/unit/Area4SanctionSuite.php`

## Status verifikasi

Perbaikan dikunci oleh **regression test** (suite hijau penuh). **Belum** ada
re-scan Strix ketiga untuk area ini - lihat catatan di
[`../../README.md`](../../README.md).
