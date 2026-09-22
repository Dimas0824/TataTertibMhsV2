# Area 5 - After fix (verification evidence)

| | |
| --- | --- |
| Findings | A5-vuln-0001 (2026-09-22) - CWE-79 - MEDIUM (CVSS 5.4) |
| Fix commit | `1181dec` |
| Regression test | `tests/unit/Area5JsonLdSuite.php`, `tests/unit/Area5XssSuite.php` |
| Reproduce | - (dikunci regression test; belum ada skrip re-scan) |

## Before vs After

| Skenario | BEFORE (rentan) | AFTER (terlindung) |
| --- | --- | --- |
| Judul berita berisi `</script>` (atau kutip) masuk blok `<script type="application/ld+json">` | **memutus blok** - XSS | **hex-escape** (`\uXXXX`) - blok utuh |

## Cara reproduksi (after)

Belum ada skrip mandiri; perilaku dikunci oleh regression test:

```bash
php tests/run.php     # menjalankan Area5JsonLdSuite + Area5XssSuite
```

Perbaikan kode: `helpers/seo_helper.php` - nilai JSON-LD di-hex-escape.

## Status verifikasi

Dikunci oleh **regression test** (suite hijau penuh). **Belum** diverifikasi
re-scan Strix ketiga - lihat catatan di [`../../README.md`](../../README.md).
Temuan LAMA 2026-09-21 (stored XSS body) **HILANG** di re-scan ini.
