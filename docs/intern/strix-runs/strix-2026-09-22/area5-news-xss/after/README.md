# After - perbaikan temuan 2026-09-22 (Area 5: News / XSS)

`before/` berisi artefak mentah re-scan. `after/` berisi bukti perbaikan
**temuan baru** yang muncul di re-scan 2026-09-22 untuk area ini.

## Temuan baru (2026-09-22)

| ID | Severity | CWE | Temuan | Fix |
| -- | -------- | --- | ------ | --- |
| A5-rerun-0001 | MEDIUM (CVSS 5.4) | CWE-79 | Judul berita keluar dari blok `<script type="application/ld+json">` (JSON-LD) -> XSS | commit `1181dec` |

## Perbaikan

- `helpers/seo_helper.php` - nilai yang di-embed ke JSON-LD kini **hex-escape**
  (`\uXXXX`) sehingga karakter `</script>` / kutip tidak bisa memutus blok.

## Bukti

- Komit: `1181dec` - fix(security): hex-escape JSON-LD values (CWE-79)
- Regression test: `tests/unit/Area5JsonLdSuite.php`, `tests/unit/Area5XssSuite.php`

## Status verifikasi

Perbaikan dikunci oleh **regression test** (suite hijau penuh). **Belum** ada
re-scan Strix ketiga untuk area ini - lihat catatan di
[`../../README.md`](../../README.md).
