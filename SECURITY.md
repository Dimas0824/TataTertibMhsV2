# Security Policy

DiscipLink V2 adalah proyek portofolio non-komersial yang dikembangkan secara aktif.

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| `main` / aktif  | ✅ |
| lama dari itu   | ❌                 |

## Melaporkan Kerentanan

Jangan buka issue publik untuk masalah keamanan.

- Email / DM GitHub: **@Dimas0824**
- Subjek: `[security] <ringkasan>`
- Sertakan: langkah reproduksi, versi PHP/DB, dan dampaknya.

Respon awal target ≤ 72 jam. Fix akan didahului test regression di `tests/security/`
(yang me-replay payload pelaporan sebagai bukti hole tertutup permanen).

## Space yang Sudah Diaudit

Audit code-level dilakukan pada 2026-09-08 (auth/session/CSRF, injection & file
handling, XSS, server config; lihat `docs/intern/` untuk detail internal). Temuan
berlabel medium/ke atas ditutup + distabilkan lewat regression suite dan CI.

## Hardening Default yang Perlu Diketahui Auditor Baru

1. Semua request dinamis lewat `router.php` — header, session flags, idle-expiry di sana.
2. ID & nama objek di URL = token terenkripsi terikat sesi (`helpers/token_helper.php`), bukan integer — jangan berasumsi bisa enumerasi.
3. Password seed di file SQL boleh plaintext demi maintenance, tetapi `artisan db:seed` **selalu** meng-hash bcrypt sebelum masuk DB — jangan mengimpor `.sql` seed langsung ke MySQL (pakai `database/cli/hash-plaintext-passwords.php` bila terlanjur).
4. `.htaccess` + `router.php` menerapkan deny-list yang sama (Apache & dev server); menambahkan file sensitif di docroot akan tetap 403, tapi itu safety net terakhir, bukan izin.
