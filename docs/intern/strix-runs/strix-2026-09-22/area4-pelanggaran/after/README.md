# Area 4 - After fix (verification evidence)

| | |
| --- | --- |
| Findings | A4-vuln-0001 (2026-09-22) - CWE-863 - HIGH (CVSS 7.1) |
| Fix commit | `579a4c6` |
| Regression test | `tests/unit/Area4WorkflowSuite.php`, `tests/unit/Area4SanctionSuite.php` |
| Reproduce | - (dikunci regression test; belum ada skrip re-scan) |

## Before vs After

| Skenario | BEFORE (rentan) | AFTER (terlindung) |
| --- | --- | --- |
| Hapus pelanggaran berstatus `selesai` (finalized) | **berhasil dihapus** (integritas record hilang) | **ditolak** |
| Hapus pelanggaran berstatus non-final | berhasil | berhasil (tidak berubah) |

## Cara reproduksi (after)

Belum ada skrip mandiri; perilaku dikunci oleh regression test:

```bash
php tests/run.php     # menjalankan Area4WorkflowSuite + Area4SanctionSuite
```

Perbaikan kode: `models/Pelanggaran.php` - hapus ditolak bila status `selesai`.

## Status verifikasi

Dikunci oleh **regression test** (suite hijau penuh). **Belum** diverifikasi
re-scan Strix ketiga - lihat catatan di [`../../README.md`](../../README.md).
Temuan LAMA 2026-09-21 (sanksi tier) **HILANG** di re-scan ini.
