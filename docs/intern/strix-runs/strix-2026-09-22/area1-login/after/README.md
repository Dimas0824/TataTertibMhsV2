# Area 1 - After fix (verification evidence)

| | |
| --- | --- |
| Findings | A1-vuln-0001 (2026-09-22) - CWE-307 - CRITICAL (klaim) - **FALSE POSITIVE** |
| Fix commit | - (tidak perlu fix; klaim terbukti salah) |
| Regression test | - (tidak ada kode yang diubah) |
| Reproduce | [`../../verification-analysis/prove_case_variant_not_bypass.py`](../../verification-analysis/prove_case_variant_not_bypass.py) |

## Before vs After

Tidak ada perubahan kode untuk area ini; tabel di bawah menunjukkan **verifikasi
klaim**, bukan perbaikan.

| Skenario | Klaim Strix (BEFORE) | Terverifikasi (AFTER) |
| --- | --- | --- |
| 5 gagal login `ADMIN001`, lalu varian `admin001` | seharusnya lock terpisah (bypass) | **terkunci** - throttle & lookup keduanya case-insensitive |
| `WHERE actor_id='admin001'` vs baris `'ADMIN001'` | dianggap tidak cocok | **cocok** (collation `utf8mb4_unicode_ci`) |

## Cara reproduksi (after)

```bash
python3 ../../verification-analysis/prove_case_variant_not_bypass.py
```

## Status verifikasi

**False positive**. Bukti 4 lapis (collation kolom, kecocokan query, unit test,
end-to-end HTTP) ada di [`../../verification-analysis/README.md`](../../verification-analysis/README.md).
Tidak ada perbaikan yang perlu diverifikasi ulang. Dua temuan LAMA 2026-09-21
(NUL-byte, lockout per-sesi) **HILANG** di re-scan ini.
