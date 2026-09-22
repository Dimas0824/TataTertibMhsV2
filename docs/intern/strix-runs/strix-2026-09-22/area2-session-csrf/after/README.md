# Area 2 - After fix (verification evidence)

| | |
| --- | --- |
| Findings | A2-vuln-0001 (2026-09-22) - CWE-384 - MEDIUM (CVSS 4.2) |
| Fix commit | `a56baca` |
| Regression test | `tests/security/SessionFixationSuite.php` |
| Reproduce | - (dikunci regression test; belum ada skrip re-scan) |

## Before vs After

| Skenario | BEFORE (rentan) | AFTER (terlindung) |
| --- | --- | --- |
| Klien mengirim `PHPSESSID` yang tidak pernah dibuat server | diterima apa adanya (session fixation) | **ditolak** - `session.use_strict_mode=1` mengganti dengan ID baru |

## Cara reproduksi (after)

Belum ada skrip mandiri; perilaku dikunci oleh regression test:

```bash
php tests/run.php     # menjalankan SessionFixationSuite
```

Perbaikan kode: `helpers/token_helper.php` - `app_session_start_if_needed()`
menyalakan `session.use_strict_mode=1` sebelum `session_start()`.

## Status verifikasi

Dikunci oleh **regression test** (suite hijau penuh). **Belum** diverifikasi
re-scan Strix ketiga - lihat catatan di [`../../README.md`](../../README.md).
Dua temuan LAMA 2026-09-21 (cookie tanpa `Secure`, tanpa absolute lifetime)
**HILANG** di re-scan ini.
