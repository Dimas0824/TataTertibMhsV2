# Security Policy

DiscipLink V2 adalah proyek portofolio non-komersial yang dikembangkan secara aktif - dan
sebagian sebagai sarana belajar. Dokumen ini menjelaskan posture keamanan, cara melaporkan
temuan, dan cara berkontribusi.

> *DiscipLink V2 is a non-commercial portfolio/learning project. This document describes its
> security posture, how to report findings, and how to contribute.*

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| `main` / aktif  | Ya |
| lama dari itu   | Tidak              |

## Posture Keamanan (Klaim yang Jujur)

Aplikasi ini telah melalui **hardening** dan **beberapa gelombang pengujian keamanan**:

- **Hardening (2026-09)** - bcrypt + throttle login (5 gagal/15 menit, kini **berbasis server per akun+IP** - tahan ganti sesi) + validasi kredensial (tolak NUL/over-long) + dummy-verify anti
  timing-leak, session regeneration saat privilege change, capability token terenkripsi terikat
  sesi untuk ID & file (anti-IDOR), CSRF pada semua state-changing request, security headers
  (CSP/XFO/nosniff/Referrer-Policy), error fail-closed, output-encoding & sanitizer.
- **Audit code-level (2026-09-08)** - auth/session, injection, file handling, XSS, server config
  (lihat `docs/intern/PENTEST-REPORT-2026-09-08.md`).
- **Pentest otomatis dengan agen AI Strix (2026-09-14)** - dua fase (lihat `docs/intern/pentest-strix/`):
 - **Quick (blackbox)**: enumerasi endpoint, header analysis, SQLi (error/boolean/timing),
    reflected XSS, open-redirect, CSRF, auth gating.
 - **Deep (authenticated, 3 role mahasiswa/dosen/admin)**: broken access control & IDOR /
    privilege escalation, object-level authorization pada endpoint ber-token, stored XSS,
    CSRF pada semua POST ber-state, upload handling - fokus OWASP Top 10:2021 A01.

**Hasil:** dari pentest tersebut, **tidak ditemukan vulnerability yang dapat dieksploitasi**.
Kontrol otorisasi server-side terbukti kuat (0 vulnerability pada fase deep).

### Disclaimer

> **Klaim di atas TIDAK berarti web ini 100% aman di production.**
> Pengujian tidak pernah exhaustive - bisa ada jalur, endpoint, atau kondisi yang belum tercakup.
> Proyek ini dikembangkan sebagai sarana **belajar**; pengembangnya terus belajar dan memperbaiki.
> *A clean pentest result is not a guarantee of absolute security - it reflects the methods and
> scope tested. This is a learning project under active improvement.*

## Melaporkan Kerentanan / Bug

Kami menyambut laporan dari siapa pun - auditor, pengguna, atau kontributor.

**Untuk temuan keamanan maupun bug fungsional, kamu boleh membuka:**

- **GitHub Issue** - label `security` (kerentanan) atau `bug` (fungsional). Sertakan:
  langkah reproduksi, versi PHP/DB, endpoint/parameter, dan dampak yang teramati.
- **Pull Request** - kalau kamu sudah punya perbaikan. Untuk temuan keamanan yang sensitif
  (belum ada fix), mohon koordinasikan lewat issue/DM dulu agar tidak mengekspos celah sebelum
  diperbaiki.

**Kontak privat (opsional, untuk isu sensitif):** Email / DM GitHub **@Dimas0824**, subjek
`[security] <ringkasan>`.

Respon awal target ≤ 72 jam. Perbaikan keamanan didahului **test regression** di `tests/security/`
(yang me-replay payload pelaporan sebagai bukti celah tertutup permanen), lalu lolos CI.

## Space yang Sudah Diaudit

Audit code-level 2026-09-08 + pentest otomatis 2026-09-14; detail internal di `docs/intern/`.
Temuan berlabel medium/ke atas ditutup dan distabilkan lewat regression suite + CI.
Temuan fungsional terbuka (mis. `/action/upload` 500) didokumentasikan di
`docs/intern/UPLOAD-500-INVESTIGATION.md`.

## Hardening Default yang Perlu Diketahui Auditor Baru

1. Semua request dinamis lewat `router.php` - header, session flags, idle-expiry di sana.
2. ID & nama objek di URL = token terenkripsi terikat sesi (`helpers/token_helper.php`), bukan integer - jangan berasumsi bisa enumerasi.
3. Password seed di file SQL boleh plaintext demi maintenance, tetapi `artisan db:seed` **selalu** meng-hash bcrypt sebelum masuk DB - jangan mengimpor `.sql` seed langsung ke MySQL (pakai `database/cli/hash-plaintext-passwords.php` bila terlanjur).
4. `.htaccess` + `router.php` menerapkan deny-list yang sama (Apache & dev server); menambahkan file sensitif di docroot akan tetap 403, tapi itu safety net terakhir, bukan izin.
