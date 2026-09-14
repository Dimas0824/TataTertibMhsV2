# DiscipLink V2

Sistem informasi tata tertib mahasiswa — mengelola aturan, pelanggaran, notifikasi, dan berita kedisiplinan dalam satu platform terpusat.

![CI](https://github.com/Dimas0824/TataTertibMhsV2/actions/workflows/ci.yml/badge.svg)
![E2E](https://github.com/Dimas0824/TataTertibMhsV2/actions/workflows/e2e.yml/badge.svg)
![PHP](https://img.shields.io/badge/PHP-8.3-777bb3?logo=php&logoColor=white)
![Security](https://img.shields.io/badge/security-audited%20%C2%B7%20regression%20tested-brightgreen)
![Tests](https://img.shields.io/badge/tests-160%2F160-brightgreen)
![License](https://img.shields.io/badge/license-MIT-blue)

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
| ------ | ---------- | --------- |
| Mahasiswa | `2341238901` | `password123` |
| Mahasiswa (2) | `2341238902` | `password456` |
| Dosen | `1234567890` | `password123` |
| Admin | `ADMIN001` | `admin123` |

---

## Fitur per Role

| Role | Akses |
| ------ | ------- |
| **Mahasiswa** | Dashboard pelanggaran, poin, upload dokumen (surat/tugas), notifikasi |
| **Dosen** | Pelaporan pelanggaran, rekap & konfirmasi laporan mahasiswa |
| **Admin** | CRUD tata tertib, CRUD berita, manajemen konten |

---

## Dokumentasi

Lihat **[docs/README.md](docs/README.md)** untuk navigasi lengkap (Diataxis: tutorial, how-to, reference, explanation).

- **Keamanan & hasil audit/pentest:** [docs/intern/](docs/intern/README.md)
- **Kebijakan keamanan & pelaporan kerentanan:** [SECURITY.md](SECURITY.md)
- **Panduan kontribusi:** [CONTRIBUTING.md](CONTRIBUTING.md)
- **Panduan testing (cara jalan, struktur, coverage):** [tests/README.md](tests/README.md)
- **Bug tracking (historis):** [docs/intern/BUG_REPORT.md](docs/intern/BUG_REPORT.md)

---

## Ringkasan Teknis

| | |
| --- | --- |
| **Stack** | PHP native · PDO · MySQL |
| **Arsitektur** | MVC + Request Handler + Central Router |
| **Auth** | Role-based (Mahasiswa, Dosen, Admin) |
| **CLI** | Custom `artisan` untuk migrate/seed/serve |
| **Testing** | Unit + Integration + Security regression + E2E (Playwright) — **160/160** hijau |
| **Coverage** | Line coverage via Xdebug (`tests/cover.php`); fungsi inti >80% |

---

## Security Testing

Proyek ini menjalani dua lapis pengujian keamanan:

**1. Regression suite (otomatis, in-repo)** — `tests/security/**`
Red-team yang me-replay payload dari pentest code-level dan memastikan setiap celah tetap tertutup:

| Suite | Fokus |
| ------- | ------- |
| `TokenSuite` | Capability token (file/ID): tamper → fail-closed, entity-scoped, CSRF 64-hex, edge-case token |
| `SourceScanSuite` | Guardrail statis: error disclosure, bare `session_start`, `0777`, dynamic-exec, deny rules |
| `UploadOwnershipSuite` | Upload dokumen: regresi bug 500 (placeholder PDO), otorisasi objek cross-user, guard CSRF |
| `HandlerCoverageSuite` | Handler action: notifikasi, tatib (admin), pelanggaran (lookup/confirm/delete) |
| `NewsHandlerSuite` | Handler berita: store/update/delete + validasi token & role |
| `PelanggaranFormSuite` | Form `/pelaporan`: store/update laporan dosen + validasi token tatib |
| `HttpMatrixSuite` | Blackbox vs `php -S`: deny matrix, headers, CSRF, IDOR, upload, brute-force, XSS pipeline |

```bash
php tests/run.php          # unit + integration + security (butuh DB; ~3-6 mnt)
```

**Menjalankan test dengan DB lokal:** salin `tests/.env.testing.example` → `tests/.env.testing`
(atau biarkan kosong → fallback ke `.env` root). Suite HTTP men-boot `php -S` sendiri di
port 8123-8140, jadi server dev tidak perlu berjalan.

**2. Pentest (audit terarah)** — lihat [`docs/intern/PENTEST-REPORT-2026-09-08.md`](docs/intern/PENTEST-REPORT-2026-09-08.md)
Audit code-level yang memetakan & menutup temuan (auth/session, injection, file handling, XSS, server config).

**3. Pentest otomatis dengan agen AI (Strix)** — lihat [`docs/intern/pentest-strix/`](docs/intern/pentest-strix/)
Dijalankan dalam dua fase: **quick** (blackbox, menemukan robots.txt MEDIUM) dan **deep**
(authenticated 3 role — 0 vulnerability terkonfirmasi, otorisasi server-side terbukti kuat).

**Kontrol yang aktif (terverifikasi):** bcrypt + throttle login (5 gagal/15 mnt) + dummy-verify anti
timing-leak · session regeneration saat privilege change · file/ID token terenkripsi terikat sesi (IDOR) ·
CSRF pada semua state-changing request (419 tanpa token) · CSP + `X-Frame-Options: DENY` + `nosniff` +
`Referrer-Policy` · error fail-closed · JSON embed hex-escaped (anti `</script>` breakout).

**Hasil & klaim (jujur):** setelah hardening dan pentest di atas (termasuk fase **deep authenticated
3 role** dengan uji IDOR / privilege escalation / XSS / SQLi / CSRF), **tidak ditemukan vulnerability
yang dapat dieksploitasi**. Kontrol otorisasi server-side terbukti kuat.

> ⚠️ **Disclaimer:** hasil pentest yang bersih **bukan** jaminan aplikasi 100% aman di production.
> Pengujian tidak pernah exhaustive dan proyek ini adalah sarana **belajar** yang terus diperbaiki.
> *A clean pentest is not a guarantee of absolute security.*

**Menemukan bug atau kerentanan?** Kami menyambut kontribusi — buka **GitHub Issue** (label
`security`/`bug`) atau kirim **Pull Request**. Lihat [CONTRIBUTING.md](CONTRIBUTING.md) dan
[SECURITY.md](SECURITY.md).

---

## Coverage

Line coverage diukur dengan Xdebug (CLI + HTTP server digabung):

```bash
XDEBUG_MODE=coverage php tests/cover.php    # butuh Xdebug terpasang (lihat tests/README.md)
```

**Prinsip: kualitas, bukan angka.** Fungsi-fungsi inti (auth, capability token, otorisasi objek,
upload, model pelanggaran) sudah **>80%** dan teruji lewat regresi yang bermakna. Baris yang
sengaja dibiarkan belum ter-cover umumnya adalah cabang error defensif / catch-block yang hanya
bisa dipicu dengan memaksa kegagalan buatan.

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

[MIT](LICENSE) © 2026 Dimas0824
