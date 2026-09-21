# area4-pelanggaran — AFTER fix (verification evidence)

Bukti bahwa temuan AREA 4 (validasi tingkat sanksi) **sudah diperbaiki dan terbukti tertutup**.

| | |
|---|---|
| **Finding** | `vuln-0001` CWE-20 MEDIUM (CVSS 6.5) |
| **Fix commit** | `487dd93` |
| **Regression test** | `tests/unit/Area4SanctionSuite.php` (2 test) |
| **Reproduce** | [`reproduce.sh`](reproduce.sh) · output: [`reproduce-after.log`](reproduce-after.log) |

---

## Before vs After

`before/` = artefak Strix asli (kondisi rentan). `after/` = hasil reproduksi ulang setelah perbaikan.

| Skenario | BEFORE (rentan) | AFTER (terbukti aman) |
|---|---|---|
| Dosen menyimpan pelanggaran dengan sanksi beda tingkat (mis. sanksi Tier I untuk pelanggaran Tier V) | **diterima**, sanksi tersimpan ❌ | **ditolak** (`success=false`), tidak ada baris ditulis ✅ |
| Dosen menyimpan pelanggaran dengan sanksi tingkat yang cocok | diterima | diterima ✅ (tidak berubah) |

Angka BEFORE diambil dari `before/vulnerabilities/vuln-0001.md`. Angka AFTER dihasilkan skrip di folder ini.

---

## Cara reproduksi (after)

```bash
# butuh DB ter-seed & dapat dijangkau (config.php). Dari root repo:
REPRO_ROOT=/path/ke/repo bash reproduce.sh     # harapan: "RESULT: 2 passed, 0 failed"
```

> Skenario: ambil satu `SANKSI` yang tingkatnya BEDA dari `TATA_TERTIB`, panggil
> `simpanDetailPelanggaran(...)` dengan sanksi itu, lalu pastikan ditolak dan tidak ada
> baris baru di `DETAIL_PELANGGARAN`.

---

## Kenapa hasilnya sah

- **Mismatch ditolak** dan **tidak ada baris ditulis** (`before=23 after=23`) → celah CWE-20 tertutup.
- **Regression test** (`Area4SanctionSuite`, 2/2) mengunci perilaku ini; suite penuh 186/186
  (termasuk `PelanggaranFormSuite` yang menyimpan sanksi valid — tidak rusak).

Perbaikan kode (`models/Pelanggaran.php`, jalur store **dan** update):
- Query sanksi sekarang `SELECT id_sanksi, tingkat FROM SANKSI` dan **membandingkan `tingkat`**
  dengan `TATA_TERTIB.tingkat`; beda → `RuntimeException('Sanksi tidak sesuai dengan tingkat pelanggaran.')`.
- Tambah `getSanksiTingkatById(int): ?string` untuk uji & pemakaian lain.
