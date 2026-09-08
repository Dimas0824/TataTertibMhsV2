# DiscipLink V2

Sistem informasi tata tertib mahasiswa — mengelola aturan, pelanggaran, notifikasi, dan berita kedisiplinan dalam satu platform terpusat.

![CI](https://github.com/Dimas0824/TataTertibMhsV2/actions/workflows/ci.yml/badge.svg)
![PHP](https://img.shields.io/badge/PHP-8.3-777bb3?logo=php&logoColor=white)
![Security](https://img.shields.io/badge/security-audited%20%C2%B7%20regression%20tested-brightgreen)

---

## Quick Start

```bash
git clone https://github.com/Dimas0824/TataTertibMhsV2.git
cd TataTertibMhsV2
cp .env.example .env
# edit .env → sesuaikan DB_DSN, DB_USER, DB_PASS
php artisan migrate:fresh --seed --force
php artisan serve --host=127.0.0.1 --port=8000
```

Buka [http://127.0.0.1:8000](http://127.0.0.1:8000)

---

## Akun Contoh

| Role | Username | Password |
|------|----------|---------|
| Mahasiswa | `2341238901` | `password123` |
| Dosen | `1234567890` | `password123` |
| Admin | `ADMIN001` | `admin123` |

---

## Fitur per Role

| Role | Akses |
|------|-------|
| **Mahasiswa** | Dashboard pelanggaran, poin, upload dokumen (surat/tugas), notifikasi |
| **Dosen** | Pelaporan pelanggaran, rekap & konfirmasi laporan mahasiswa |
| **Admin** | CRUD tata tertib, CRUD berita, manajemen konten |

---

## Dokumentasi

Lihat **[docs/README.md](docs/README.md)** untuk navigasi lengkap.

---

## Ringkasan Teknis

| | |
|---|---|
| **Stack** | PHP native · PDO · MySQL |
| **Arsitektur** | MVC + Request Handler + Central Router |
| **Auth** | Role-based (Mahasiswa, Dosen, Admin) |
| **CLI** | Custom `artisan` untuk migrate/seed/serve |

---

## Catatan Keamanan (Deploy)

- `php artisan db:seed` otomatis mem-hash password plaintext di seed → bcrypt (cost 12). Login **hanya** menerima hash bcrypt; untuk import `.sql` lama secara manual, jalankan `php database/cli/hash-plaintext-passwords.php` sekali.
- Deploy di docroot: `.htaccess` menolak `.env`, `storage/keys/`, `*.key/*.sql/*.md/dotfile`, directory app (`controllers/`, `models/`, `helpers/`, `database/`, `docs/`, `tests/`), dan `php -l` friendly pass-through. `router.php` menerapkan guard yang sama untuk `php artisan serve`.
- PHP `php.ini` produksi: `expose_php = Off`, `display_errors = Off` (app sudah set fail-closed; `APP_DEBUG=true` di `.env` hanya untuk dev lokal).
- Semua request dinamis lewat `router.php` (session cookie HttpOnly/SameSite=Lax/Secure-on-HTTPS, 30-min idle expiry, security headers CSP/XFO/nosniff).
- Laporan audit & status hardening: `docs/intern/PENTEST-REPORT-2026-09-08.md`.

---

## Sumber

Refactor dari: [TataTertibMhs (VarizkyNaldiba)](https://github.com/VarizkyNaldiba)
UI/UX Design: [Figma](https://www.figma.com/design/yRxgSGu5uvuoKQznRxPCNg/UI%2FUX-Sistem-Tatib)

---

## Lisensi

MIT
