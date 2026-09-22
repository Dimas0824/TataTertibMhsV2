# After - perbaikan temuan 2026-09-22 (Area 2: Session / CSRF)

`before/` berisi artefak mentah re-scan. `after/` berisi bukti perbaikan
**temuan baru** yang muncul di re-scan 2026-09-22 untuk area ini.

## Temuan baru (2026-09-22)

| ID | Severity | CWE | Temuan | Fix |
| -- | -------- | --- | ------ | --- |
| A2-rerun-0001 | MEDIUM (CVSS 4.2) | CWE-384 | `session.use_strict_mode` nonaktif - session ID tak dikenal dari klien diterima tanpa regenerasi (session fixation) | commit `a56baca` |

## Perbaikan

- `helpers/token_helper.php` - `app_session_start_if_needed()` kini menyalakan
  `session.use_strict_mode=1` sebelum `session_start()`, sehingga PHP menolak
  session ID yang tidak pernah dibuat server (diganti yang baru).

## Bukti

- Komit: `a56baca` - fix(security): enable session.use_strict_mode to replace unknown PHPSESSID (CWE-384)
- Regression test: `tests/security/SessionFixationSuite.php` (terdaftar di `tests/run.php`)

## Status verifikasi

Perbaikan dikunci oleh **regression test** (suite hijau penuh). **Belum** ada
re-scan Strix ketiga untuk area ini - lihat catatan di
[`../../README.md`](../../README.md).
