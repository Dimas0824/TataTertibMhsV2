# After - perbaikan temuan 2026-09-22 (Area 3: Upload / Download / IDOR)

`before/` berisi artefak mentah re-scan. `after/` berisi bukti perbaikan
**temuan baru** yang muncul di re-scan 2026-09-22 untuk area ini.

## Temuan baru (2026-09-22)

| ID | Severity | CWE | Temuan | Fix |
| -- | -------- | --- | ------ | --- |
| A3-rerun-0001 | MEDIUM (CVSS 4.3) | CWE-284 | Halaman mahasiswa diakses role admin tidak punya guard -> error 500 (kebocoran detail internal / bukan fail-closed) | commit `d4aec0e` |

## Perbaikan

- `controllers/UserController.php` - ditambahkan **role guard** pada halaman
  mahasiswa; role yang tidak berhak diarahkan/denied (fail-closed), bukan 500.

## Bukti

- Komit: `d4aec0e` - fix(security): guard student page by role instead of 500
- Regression test: `tests/security/Area3AccessSuite.php`

## Status verifikasi

Perbaikan dikunci oleh **regression test** (suite hijau penuh). **Belum** ada
re-scan Strix ketiga untuk area ini - lihat catatan di
[`../../README.md`](../../README.md).
