# Contributing ke DiscipLink V2

Terima kasih sudah tertarik berkontribusi. Proyek ini adalah portofolio sekaligus sarana
belajar - semua bentuk kontribusi (bug report, perbaikan, ide, dokumentasi) sangat dihargai.

> *Thanks for contributing. This is a portfolio/learning project - bug reports, fixes, ideas, and
> documentation improvements are all welcome.*

## Cara Berkontribusi

### 1. Laporkan Bug

Buka **GitHub Issue** dengan:

- Judul ringkas + langkah reproduksi (perintah/URL yang dipakai).
- Expected vs actual behavior, pesan error, versi PHP/DB.
- Kalau bisa, sertakan bukti (log, screenshot, respons HTTP).

### 2. Laporkan Kerentanan Keamanan

Sama seperti bug, tapi tambahkan label **`security`** dan detail:

- Endpoint / parameter yang terpapar, langkah repro, dan dampak yang **terbukti** (bukan dugaan).
- Untuk celah yang belum ada fix dan berdampak luas, koordinasikan dulu via issue/DM
  (lihat [`SECURITY.md`](SECURITY.md)) sebelum publikasi detail.

### 3. Kirim Pull Request

1. Fork & buat branch: `fix/<ringkas>`, `feat/<ringkas>`, atau `docs/<ringkas>`.
2. Jaga perubahan **fokus** (satu PR = satu tujuan).
3. Untuk perbaikan keamanan/fungsional: tambahkan **test regression** di `tests/security/**`
   atau `tests/e2e/**` yang membuktikan perbaikan (payload PoC jadi test).
4. Pastikan lolos:

   ```bash
   php artisan migrate:fresh --seed --force   # butuh DB
   php tests/run.php                          # unit + integration + security
   ```

   (CI juga menjalankan lint + suite ini pada PHP 8.3 / MySQL 8.)
5. Jelaskan di deskripsi PR: **apa** yang diubah, **kenapa**, dan **bukti** (output/test).

## Aturan Umum

- Ikuti gaya kode yang sudah ada (PHP native, PDO prepared statements, output-encoding).
- **Jangan commit** rahasia: `.env`, `storage/keys/*`, file upload runtime. Lihat `.gitignore`.
- Hormati deny-list server (`router.php` / `.htaccess`) - jangan tambah file sensitif di docroot.
- Satu perubahan yang jelas lebih mudah di-review daripada banyak sekaligus.

## Etika Pengujian

- Uji **hanya** aplikasi milikmu / yang kamu punya izin tertulis.
- Untuk pentest lokal: arahkan ke instance sendiri (`php artisan serve` di `127.0.0.1`).
- **Jangan** mengarahkan alat uji ke server milik orang lain (mis. server kampus) tanpa izin.

## Disclaimer

Proyek ini terus berkembang dan jauh dari sempurna. Klaim keamanan di [`SECURITY.md`](SECURITY.md)
mencerminkan metode & ruang lingkup pengujian yang sudah dilakukan, **bukan** jaminan bahwa
aplikasi bebas dari seluruh kerentanan. Temuanmu membantu proyek ini belajar dan jadi lebih baik.

---

Pertanyaan? Buka issue atau hubungi **@Dimas0824**.
