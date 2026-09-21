# After — perbaikan temuan 2026-09-22 (Area 1: Login)

`before/` berisi artefak mentah re-scan. `after/` berisi bukti perbaikan
**temuan baru** yang muncul di re-scan 2026-09-22 untuk area ini.

## Temuan baru (2026-09-22)

| ID | Severity | Klasifikasi | Status |
| -- | -------- | ----------- | ------ |
| case-variant lockout | CRITICAL | **FALSE POSITIVE** | tidak perlu fix (lihat [`../../verification-analysis/`](../../verification-analysis/)) |

Area 1 re-scan **tidak menghasilkan temuan valid** — satu-satunya laporan
(case-variant lockout) dibuktikan false positive: throttle dan lookup akun
keduanya **case-insensitive** (collation `utf8mb4_unicode_ci` pada
`SECURITY_AUDIT_LOG.actor_id`), sehingga varian huruf besar/kecil berbagi budget
throttle yang sama.

## Bukti

- Analisis + skrip: [`../../verification-analysis/prove_case_variant_not_bypass.py`](../../verification-analysis/prove_case_variant_not_bypass.py)
- Uraian: [`../../verification-analysis/README.md`](../../verification-analysis/README.md)

## Status verifikasi

Tidak ada `after/` berbasis re-scan untuk area ini karena **tidak ada temuan valid
yang perlu ditutup**. Enam temuan run 2026-09-21 (termasuk kedua temuan login)
sudah terbukti HILANG di `before/` re-scan ini — lihat
[`../../../strix-2026-09-21/README.md`](../../../strix-2026-09-21/README.md).
