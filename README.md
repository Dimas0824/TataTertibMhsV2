# DiscipLink V2

**Sistem informasi tata tertib mahasiswa, dibangun dengan keamanan sebagai syarat utama - bukan tambahan.**

DiscipLink mengelola aturan, pelanggaran, notifikasi, dan berita kedisiplinan dalam satu
platform terpusat untuk tiga peran (Mahasiswa, Dosen, Admin). Yang membedakan proyek ini:
**setiap kontrol keamanan dirancang, diuji, dan dibuktikan** - lalu diuji ulang oleh agen
pentest otomatis setiap kali ada perubahan.

![CI](https://github.com/Dimas0824/TataTertibMhsV2/actions/workflows/ci.yml/badge.svg)
![E2E](https://github.com/Dimas0824/TataTertibMhsV2/actions/workflows/e2e.yml/badge.svg)
![PHP](https://img.shields.io/badge/PHP-8.3-777bb3?logo=php&logoColor=white)
![Security](https://img.shields.io/badge/security-audited%20%C2%B7%20pentested%20%C2%B7%20regression--tested-brightgreen)
![Tests](https://img.shields.io/badge/tests-198%2F198-brightgreen)
![License](https://img.shields.io/badge/license-MIT-blue)

---

## Mengapa Keamanan Menjadi Fokus

Ini proyek **belajar keamanan aplikasi web**. Tujuannya bukan sekadar membuat aplikasi
berjalan, tetapi membangunnya dengan **postur keamanan yang dapat dibuktikan**: kontrol otorisasi di sisi server, penanganan input yang ketat, dan jejak pengujian yang lengkap.

Tiga prinsip yang dipegang:

- **Deny-by-default.** Yang tidak eksplisit diizinkan, ditolak.
- **Fail-closed.** Kalau ragu, tolak - jangan menampilkan detail internal.
- **Satu tempat untuk satu kontrol.** Session, header, CSRF, otorisasi objek - terpusat,
  supaya tidak ada jalur yang "lupa diperiksa".

Hasilnya bukan klaim, melainkan **bukti**: 198 regression test hijau, dua ronde pentest
code-level, dan pentest otomatis berulang dengan agen AI (Strix) - semuanya tersimpan
dan dapat direproduksi.

---

## Postur Keamanan

Berikut ulasan menyeluruh mengenai pertahanan yang sudah diterapkan. Setiap klaim di sini di-back oleh regression test dan/atau bukti pentest yang bisa Anda periksa sendiri.

### 1. Autentikasi

- **bcrypt cost 12** untuk hashing password; login hanya menerima hash bcrypt.
- **Validasi kredensial ketat** - menolak input mengandung NUL byte dan yang over-long
  (mencegah truncation attack pada verifikasi password).
- **Throttle brute-force durable di sisi server**, bukan per-sesi: berbasis tabel audit
  `SECURITY_AUDIT_LOG`, dengan batas **5 kegagalan per akun** dan **15 per IP** dalam
  window 15 menit. Membuang cookie sesi tidak me-reset counter.
- **Dummy-verify anti timing-leak** - username tidak dikenal tetap melewati perhitungan
  hash, sehingga waktu respons tidak membocorkan keberadaan akun.

### 2. Session

- **`session.use_strict_mode` aktif** - session ID yang tidak pernah dibuat server ditolak
  dan diganti (mencegah session fixation).
- **`session_regenerate_id(true)`** pada perubahan privilege (login).
- **Absolute session lifetime** (12 jam) selain idle expiry, dan **invalidasi sesi
  bersamaan** saat login ke akun yang sama (tabel `USER_SESSION`).
- Cookie session **`HttpOnly` + `SameSite=Lax` + `Secure`** (Secure mengikuti sinyal
  HTTPS termasuk di belakang proxy yang TLS-nya di-terminate).

### 3. Otorisasi Objek (Anti-IDOR / BOLA)

- **Capability token terenkripsi** (NaCl/AES-GCM) untuk ID yang muncul di URL/form -
  bukan ID sekuensial. Ubah satu digit, token gagal didekripsi, permintaan ditolak.
- Token **terikat sesi** (`sid = sha256(session_id)`) dan **terikat entitas**, dengan
  `hash_equals` (tahan timing) serta expiry.
- Query otorisasi **ber-scope kepemilikan** di sisi server (`id_mhs` / `id_dosen`) - tidak
  ada pengecekan manual yang bisa terlupa di satu tempat saja.

### 4. Otorisasi Peran (RBAC)

- Enforcement **role di sisi server** pada setiap aksi dan halaman.
- Akses lintas-peran yang tidak sah -> **403** (fail-closed), bukan 500 yang membocorkan
  detail internal.

### 5. Perlindungan Injeksi

- **Prepared statement PDO secara native** (`ATTR_EMULATE_PREPARES = false`) untuk semua query - parameterized, bukan string concatenation.
- Validasi input ketat pada semua jalur yang menulis ke DB.

### 6. CSRF

- CSRF token **64-hex** wajib pada **semua** state-changing request (POST/AJAX); request tanpa token -> **419**. Diterapkan lewat helper terpusat (`app_verify_csrf()`), bukan per-form.

### 7. XSS & Output Encoding

- **Sanitizer berlapis** untuk konten berita: membuang blok `<script>`/`<style>` beserta
  isinya, event handler lintas-batas-kutip (`\bon[a-z]+`), dan URI berbahaya
  (`javascript:` / `vbscript:` / `data:`) pada `href`/`src`.
- **JSON-LD di-hex-escape** (`\uXXXX`) sehingga judul berita tidak bisa memutus blok
  `<script type="application/ld+json">`.

### 8. Upload File

- **Allowlist MIME (`finfo`) + ekstensi** di sisi server; nama file dibuat server
  (`<id>_<type>_<24-hex>.<ext>`), nama dari klien tidak pernah dipakai.
- File disajikan lewat **token**, bukan path langsung; `.htaccess` + router menolak akses
  langsung ke `storage/`.

### 9. Header Keamanan & Error Handling

- **CSP**, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`.
- **Error fail-closed**: pesan generik ke pengguna, detail hanya ke log (bukan ke response).
- **Kebocoran konfigurasi ditutup**: `expose_php = Off`, `display_errors = Off` di produksi;
  `.htaccess` menolak `.env`, `storage/keys/`, `*.key/*.sql/*.md`, dotfile, dan direktori
  aplikasi.

### 10. Audit Trail

- **`SECURITY_AUDIT_LOG` append-only** mencatat peristiwa autentikasi & keamanan penting,
  dengan viewer admin - sekaligus menjadi basis throttle durable.

---

## Bukti Pengujian Keamanan

Tiga lapis, masing-masing dapat direproduksi. Detail & standar dokumentasi ada di
**[docs/intern/SECURITY-DOC-STANDARD.md](docs/intern/SECURITY-DOC-STANDARD.md)**.

### Lapis 1 - Regression suite (otomatis, in-repo)

Red-team yang me-replay payload dari pentest dan memastikan setiap celah **tetap tertutup**.
Semua di `tests/security/**` dan `tests/unit/**` - total suite **198/198 hijau**.

| Suite | Fokus |
| ------- | ------- |
| `LoginBruteForceSuite` | NUL-suffixed ditolak, sesi baru tidak melewati lockout, scope & window throttle |
| `LoginThrottleHelperTest` | Guard NUL/over-long + throttle durable (akun/IP), fail-soft |
| `SessionLifecycleSuite` | Cookie `Secure` via proxy, absolute lifetime, revoke sesi saat login |
| `SessionFixationSuite` | `use_strict_mode` menolak session ID tak dikenal |
| `TokenSuite` | Capability token (file/ID): tamper fail-closed, entity-scoped, CSRF 64-hex |
| `HttpMatrixSuite` | Blackbox vs `php -S`: deny matrix, headers, CSRF, IDOR, upload, brute-force, XSS |
| `UploadOwnershipSuite` | Upload dokumen: regresi bug 500, otorisasi objek cross-user, guard CSRF |
| `Area3AccessSuite` | Halaman dosen-only -> 403 untuk non-dosen (bukan 500) |
| `Area4SanctionSuite` | Sanksi wajib cocok tingkat pelanggaran (tolak mismatch, tanpa baris) |
| `Area4WorkflowSuite` | Pelanggaran `selesai` (finalized) tidak dapat dihapus |
| `Area5XssSuite` | Sanitizer news membuang event handler lintas-quote & URI berbahaya |
| `Area5JsonLdSuite` | Nilai JSON-LD ter-hex-escape (tidak bisa memutus blok script) |
| `SourceScanSuite` | Guardrail statis: error disclosure, bare `session_start`, `0777`, dynamic-exec |
| `NewsHandlerSuite` / `HandlerCoverageSuite` / `PelanggaranFormSuite` | Handler action: token, role, validasi |

```bash
php tests/run.php          # unit + integration + security (butuh DB; ~3-6 mnt)
```

Suite HTTP men-boot `php -S` sendiri di port 8123-8140; server dev tidak perlu berjalan.

### Lapis 2 - Pentest code-level (audit terarah)

Audit manual yang memetakan dan menutup temuan di lapisan aplikasi **dan** konfigurasi
server: auth/session, injection, file handling, XSS, server config.
Laporan: [`docs/intern/PENTEST-REPORT-2026-09-08.md`](docs/intern/PENTEST-REPORT-2026-09-08.md).

### Lapis 3 - Pentest otomatis dengan agen AI (Strix)

Strix dijalankan **white-box per area** (target live + source di-mount), satu area per run.
Setiap run diuji ulang dengan instruksi identik untuk membuktikan perbaikan - dan menemukan
celah baru. Ringkasan status terkini:

| Metrik | Nilai |
| ------- | ----- |
| Run terakhir | **2026-09-22** (re-scan verifikasi, instruksi identik) |
| Temuan terbuka | **0** - seluruh temuan pada kode saat ini sudah diperbaiki & terverifikasi |
| Temuan terverifikasi tertutup | **10** (6 temuan 2026-09-21 + 4 temuan baru 2026-09-22) |
| Klaim terbukti false positive | 1 (`case-variant lockout`; `_ci` collation - bukan celah) |
| Regression suite | **198/198** hijau |

**Riwayat lengkap temuan (change log) tidak ditampilkan di sini** - agar README tetap
menampilkan postur keamanan *saat ini*, bukan riwayat. Riwayat dan bukti mentahnya adalah
dokumen tersendiri:

- **Indeks semua temuan lintas-run (change-log security):** [`docs/intern/VULN-LOG.md`](docs/intern/VULN-LOG.md)
  - satu baris per temuan, terbaru di atas, dengan CWE, severity, tanggal, commit fix, status terkini.
- **Laporan per-run (temuan + matriks penutupan):** [`docs/intern/strix-runs/`](docs/intern/strix-runs/README.md)
  - `strix-2026-09-21/` (temuan asli) dan `strix-2026-09-22/` (re-scan verifikasi + temuan baru).
- **Standar & aturan dokumentasi keamanan:** [`docs/intern/SECURITY-DOC-STANDARD.md`](docs/intern/SECURITY-DOC-STANDARD.md).

```bash
# contoh reproduce perbaikan area LOGIN (butuh app jalan di :8123 + DB ter-seed)
cd docs/intern/strix-runs/strix-2026-09-21/area1-login/after && bash reproduce.sh http://127.0.0.1:8123
```

### Hasil & klaim (jujur)

Setelah hardening dan tiga lapis pengujian di atas (termasuk fase **deep authenticated
3 role** dengan uji IDOR / privilege escalation / XSS / SQLi / CSRF), **tidak ditemukan
vulnerability yang dapat dieksploitasi**. Kontrol otorisasi server-side terbukti kuat.

> **Disclaimer:** hasil pentest yang bersih **bukan** jaminan aplikasi 100% aman di
> production. Pengujian tidak pernah exhaustive dan proyek ini adalah sarana **belajar**
> yang terus diperbaiki. *A clean pentest is not a guarantee of absolute security.*

**Menemukan bug atau kerentanan?** Kami menyambut kontribusi - buka **GitHub Issue**
(label `security`/`bug`) atau kirim **Pull Request**. Lihat
[CONTRIBUTING.md](CONTRIBUTING.md) dan [SECURITY.md](SECURITY.md).

---

## Fitur Aplikasi

| Role | Akses |
| ------ | ------- |
| **Mahasiswa** | Dashboard pelanggaran, poin, upload dokumen (surat/tugas), notifikasi |
| **Dosen** | Pelaporan pelanggaran, rekap & konfirmasi laporan mahasiswa |
| **Admin** | CRUD tata tertib, CRUD berita, manajemen konten |

---

## Quick Start

```bash
git clone https://github.com/Dimas0824/TataTertibMhsV2.git
cd TataTertibMhsV2
cp .env.example .env
# edit .env - sesuaikan DB_DSN, DB_USER, DB_PASS
php artisan migrate:fresh --seed --force
php artisan serve --host=127.0.0.1 --port=8000
```

Buka [http://127.0.0.1:8000](http://127.0.0.1:8000)

**Akun contoh** (lab; password plaintext otomatis di-hash bcrypt saat seed):

| Role | Username | Password |
| ------ | ---------- | --------- |
| Mahasiswa | `2341238901` | `password123` |
| Mahasiswa (2) | `2341238902` | `password456` |
| Dosen | `1234567890` | `password123` |
| Admin | `ADMIN001` | `admin123` |

---

## Ringkasan Teknis

| | |
| --- | --- |
| **Stack** | PHP 8.3 native - PDO (native prepared statements) - MySQL - HTML/CSS/JS vanilla |
| **Arsitektur** | MVC + Request Handler + Central Router - otorisasi & keamanan deny-by-default di server |
| **Auth** | Role-based (Mahasiswa, Dosen, Admin) + capability token terenkripsi untuk ID objek |
| **CLI** | Custom `artisan` untuk migrate/seed/serve - tanpa dependency Composer |
| **Testing** | Unit + Integration + Security regression + E2E (Playwright) - **198/198** hijau |
| **Coverage** | Line coverage via Xdebug (`tests/cover.php`); fungsi inti >80% |

---

## Dokumentasi

Navigasi lengkap (Diataxis: tutorial, how-to, reference, explanation):
**[docs/README.md](docs/README.md)**.

- **Standar dokumentasi keamanan (WAJIB):** [docs/intern/SECURITY-DOC-STANDARD.md](docs/intern/SECURITY-DOC-STANDARD.md)
- **Indeks semua temuan keamanan lintas-run:** [docs/intern/VULN-LOG.md](docs/intern/VULN-LOG.md)
- **Bukti pentest Strix (per tanggal):** [docs/intern/strix-runs/](docs/intern/strix-runs/README.md)
- **Audit & pentest code-level:** [docs/intern/](docs/intern/README.md)
- **Case study (proses & keputusan desain):** [CASE_STUDY.md](CASE_STUDY.md)
- **Kebijakan keamanan & pelaporan kerentanan:** [SECURITY.md](SECURITY.md)
- **Panduan kontribusi:** [CONTRIBUTING.md](CONTRIBUTING.md)
- **Panduan testing:** [tests/README.md](tests/README.md)

---

## Catatan Keamanan (Deploy)

- `php artisan db:seed` otomatis mem-hash password plaintext di seed - bcrypt (cost 12).
  Login **hanya** menerima hash bcrypt; untuk import `.sql` lama, jalankan
  `php database/cli/hash-plaintext-passwords.php` sekali.
- Deploy di docroot: `.htaccess` menolak `.env`, `storage/keys/`, `*.key/*.sql/*.md/dotfile`,
  dan direktori aplikasi (`controllers/`, `models/`, `helpers/`, `database/`, `docs/`,
  `tests/`). `router.php` menerapkan guard yang sama untuk `php artisan serve`.
- PHP `php.ini` produksi: `expose_php = Off`, `display_errors = Off`. `APP_DEBUG=true` di
  `.env` **hanya** untuk dev lokal.
- Semua request dinamis lewat `router.php` (session cookie HttpOnly/SameSite=Lax/Secure-on-HTTPS,
  idle expiry 30 menit, security headers CSP/XFO/nosniff).
- Laporan & status hardening: [docs/intern/PENTEST-REPORT-2026-09-08.md](docs/intern/PENTEST-REPORT-2026-09-08.md).

---

## Sumber

Refactor dari: [TataTertibMhs (VarizkyNaldiba)](https://github.com/VarizkyNaldiba)
UI/UX Design: [Figma](https://www.figma.com/design/yRxgSGu5uvuoKQznRxPCNg/UI%2FUX-Sistem-Tatib)

---

## Lisensi

[MIT](LICENSE) - 2026 Dimas0824
