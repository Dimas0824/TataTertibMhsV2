# Area 3 - After fix (verification evidence)

| | |
| --- | --- |
| Findings | A3-vuln-0001 (2026-09-22) - CWE-284 - MEDIUM (CVSS 4.3) |
| Fix commit | `d4aec0e` |
| Regression test | `tests/security/Area3AccessSuite.php` |
| Reproduce | - (dikunci regression test; belum ada skrip re-scan) |

## Before vs After

| Skenario | BEFORE (rentan) | AFTER (terlindung) |
| --- | --- | --- |
| Admin membuka halaman mahasiswa | **HTTP 500** (bukan fail-closed, bocor detail internal) | **HTTP 403** |
| Mahasiswa membuka halaman mahasiswa | 200 | 200 (tidak berubah) |

## Cara reproduksi (after)

Belum ada skrip mandiri; perilaku dikunci oleh regression test:

```bash
php tests/run.php     # menjalankan Area3AccessSuite
```

Perbaikan kode: `controllers/UserController.php` - role guard fail-closed
(403) menggantikan 500.

## Status verifikasi

Dikunci oleh **regression test** (suite hijau penuh). **Belum** diverifikasi
re-scan Strix ketiga - lihat catatan di [`../../README.md`](../../README.md).
