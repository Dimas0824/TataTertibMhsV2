# DiscipLink V2

Sistem informasi tata tertib mahasiswa - mengelola aturan, pelanggaran, notifikasi, dan berita kedisiplinan dalam satu platform terpusat.

![CI](https://github.com/Dimas0824/TataTertibMhsV2/actions/workflows/ci.yml/badge.svg)
![E2E](https://github.com/Dimas0824/TataTertibMhsV2/actions/workflows/e2e.yml/badge.svg)
![PHP](https://img.shields.io/badge/PHP-8.3-777bb3?logo=php&logoColor=white)
![Security](https://img.shields.io/badge/security-audited%20%C2%B7%20regression%20tested-brightgreen)
![Tests](https://img.shields.io/badge/tests-191%2F191-brightgreen)
![License](https://img.shields.io/badge/license-MIT-blue)

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

- **Case study (proses & keputusan desain):** [CASE_STUDY.md](CASE_STUDY.md)
- **Keamanan & hasil audit/pentest:** [docs/intern/](docs/intern/README.md)
- **Kebijakan keamanan & pelaporan kerentanan:** [SECURITY.md](SECURITY.md)
- **Panduan kontribusi:** [CONTRIBUTING.md](CONTRIBUTING.md)
- **Panduan testing (cara jalan, struktur, coverage):** [tests/README.md](tests/README.md)
- **Bug tracking (historis):** [docs/intern/BUG_REPORT.md](docs/intern/BUG_REPORT.md)

---

## Ringkasan Teknis

| | |
| --- | --- |
| **Stack** | PHP native - PDO - MySQL |
| **Arsitektur** | MVC + Request Handler + Central Router |
| **Auth** | Role-based (Mahasiswa, Dosen, Admin) |
| **CLI** | Custom `artisan` untuk migrate/seed/serve |
| **Testing** | Unit + Integration + Security regression + E2E (Playwright) - **191/191** hijau |
| **Coverage** | Line coverage via Xdebug (`tests/cover.php`); fungsi inti >80% |

---

## Security Testing

Proyek ini menjalani dua lapis pengujian keamanan:

**1. Regression suite (otomatis, in-repo)** - `tests/security/**`
Red-team yang me-replay payload dari pentest code-level dan memastikan setiap celah tetap tertutup:

| Suite | Fokus |
| ------- | ------- |
| `TokenSuite` | Capability token (file/ID): tamper - fail-closed, entity-scoped, CSRF 64-hex, edge-case token |
| `SourceScanSuite` | Guardrail statis: error disclosure, bare `session_start`, `0777`, dynamic-exec, deny rules |
| `UploadOwnershipSuite` | Upload dokumen: regresi bug 500 (placeholder PDO), otorisasi objek cross-user, guard CSRF |
| `HandlerCoverageSuite` | Handler action: notifikasi, tatib (admin), pelanggaran (lookup/confirm/delete) |
| `NewsHandlerSuite` | Handler berita: store/update/delete + validasi token & role |
| `PelanggaranFormSuite` | Form `/pelaporan`: store/update laporan dosen + validasi token tatib |
| `HttpMatrixSuite` | Blackbox vs `php -S`: deny matrix, headers, CSRF, IDOR, upload, brute-force, XSS pipeline |
| `LoginThrottleHelperTest` | Unit: guard NUL/over-long + throttle durable (akun/IP), fail-soft |
| `LoginBruteForceSuite` | HTTP: NUL-suffixed ditolak, sesi baru tidak melewati lockout, scope & window |
| `SessionLifecycleSuite` | HTTP+white-box: cookie `Secure` via proxy, absolute lifetime, revoke sesi saat login |
| `Area3AccessSuite` | HTTP: halaman dosen-only -> 403 untuk non-dosen (bukan 500) |
| `Area4SanctionSuite` | Unit: sanksi wajib cocok tingkat pelanggaran (tolak mismatch, tanpa baris) |
| `Area5XssSuite` | Unit: sanitizer news membuang event handler lintas-quote & URI berbahaya |

```bash
php tests/run.php          # unit + integration + security (butuh DB; ~3-6 mnt)
```

**Menjalankan test dengan DB lokal:** salin `tests/.env.testing.example` - `tests/.env.testing`
(atau biarkan kosong - fallback ke `.env` root). Suite HTTP men-boot `php -S` sendiri di
port 8123-8140, jadi server dev tidak perlu berjalan.

**2. Pentest (audit terarah)** - lihat [`docs/intern/PENTEST-REPORT-2026-09-08.md`](docs/intern/PENTEST-REPORT-2026-09-08.md)
Audit code-level yang memetakan & menutup temuan (auth/session, injection, file handling, XSS, server config).

**3. Pentest otomatis dengan agen AI (Strix)** - lihat [`docs/intern/pentest-strix/`](docs/intern/pentest-strix/)
Dijalankan dalam dua fase: **quick** (blackbox, menemukan robots.txt MEDIUM) dan **deep**
(authenticated 3 role - 0 vulnerability terkonfirmasi, otorisasi server-side terbukti kuat).

### Showcase: pentest Strix per-area + remediasi (berjalan, per tanggal)

> **Proyek ini masih terus dikembangkan.** Setiap perubahan akan terus diuji
> melalui **Strix pentesting**, celah yang ditemukan terus diperbaiki, lalu
> **diverifikasi ulang** dengan re-scan. Seluruh bukti mentah (SARIF, PoC,
> laporan, log, database percakapan agent) - dan catatan sebelum/sesudah -
> tersimpan di **[`docs/intern/strix-runs/`](docs/intern/strix-runs/)**.

Strix dijalankan **white-box per area** (target live + source di-mount), satu area per run,
`reasoning=low`, RPM-safe. Setiap run disimpan **per tanggal**, dengan `before/` (temuan) dan
`after/` (bukti fix + skrip reproduce).

| Tanggal | Jenis | Hasil |
| ------- | ----- | ----- |
| **2026-09-21** | Run pertama (5 area) | 6 temuan: 1 CRITICAL, 1 HIGH, 3 MEDIUM, 1 LOW |
| **2026-09-22** | Re-scan verifikasi (instruksi identik) | **6 temuan 21-09 HILANG** (fix terbukti) + 5 temuan baru (1 false positive + 4 valid, sudah difix) |

**Temuan run pertama (2026-09-21) - semua FIXED:**

| # | Area | Temuan | Severity | Status |
| --- | ---- | ------ | -------- | ------ |
| 1 | LOGIN | NUL-byte truncation pada verifikasi password (`password123%00junk` tembus) | HIGH (CVSS 7.4) | FIXED |
| 1 | LOGIN | Lockout brute-force per-sesi - buang cookie = reset counter | CRITICAL (CVSS 9.1) | FIXED |
| 2 | SESSION/CSRF | Cookie session tanpa `Secure` saat TLS di-terminate proxy | MEDIUM (CVSS 5.9) | FIXED |
| 2 | SESSION/CSRF | Tidak ada absolute lifetime + tidak ada invalidasi sesi bersamaan | LOW (CVSS 3.7) | FIXED |
| 3 | UPLOAD/IDOR | **0 temuan** - upload/token/IDOR/RBAC semua ditahan | - | documented |
| 4 | PELANGGARAN | Sanksi tidak divalidasi terhadap tingkat pelanggaran (client-selectable) | MEDIUM (CVSS 6.5) | FIXED |
| 5 | NEWS | Stored XSS halaman publik via quote-boundary bypass sanitizer | MEDIUM (CVSS 5.4) | FIXED |

**Re-scan 2026-09-22 membuktikan** bahwa seluruh 6 temuan di atas **sudah tidak ada** pada kode
saat ini - sekaligus menemukan **4 celah baru** yang juga sudah diperbaiki:

> **Di mana membaca apa?**
> - Bukti bahwa temuan **2026-09-21 sudah SELESAI** (matriks penutupan per temuan) ->
>   [`strix-2026-09-21/README.md`](docs/intern/strix-runs/strix-2026-09-21/README.md) (section *Verifikasi penutupan*).
> - **Celah keamanan BARU** yang ditemukan re-scan 2026-09-22 -> [`strix-2026-09-22/README.md`](docs/intern/strix-runs/strix-2026-09-22/README.md) + [`strix-2026-09-22/areaN/README.md`](docs/intern/strix-runs/strix-2026-09-22/).

| # | Area | Temuan baru (2026-09-22) | Severity | Status |
| --- | ---- | ------------------------ | -------- | ------ |
| 2 | SESSION/CSRF | `session.use_strict_mode` nonaktif (session fixation) | MEDIUM (CVSS 4.2) | FIXED |
| 3 | UPLOAD/IDOR | Halaman mahasiswa tanpa role guard -> HTTP 500 untuk admin | MEDIUM (CVSS 4.3) | FIXED |
| 4 | PELANGGARAN | Pelanggaran berstatus `selesai` masih bisa dihapus | HIGH (CVSS 7.1) | FIXED |
| 5 | NEWS | XSS via judul berita keluar dari blok JSON-LD | MEDIUM (CVSS 5.4) | FIXED |

*(Satu temuan re-scan lain - "case-variant lockout" - terbukti **false positive**: throttle &
lookup keduanya case-insensitive. Analisis + skrip buktinya ada di
[`strix-2026-09-22/verification-analysis/`](docs/intern/strix-runs/strix-2026-09-22/verification-analysis/).)*

**Cara memeriksa bukti:**

```bash
# contoh reproduce perbaikan area LOGIN (butuh app jalan di :8123 + DB ter-seed)
cd docs/intern/strix-runs/strix-2026-09-21/area1-login/after && bash reproduce.sh http://127.0.0.1:8123
```

Struktur & konvensi folder: **[`docs/intern/strix-runs/README.md`](docs/intern/strix-runs/README.md)** 
dokumen itu memuat **kontrak struktur + aturan dokumentasi** (dipatuhi setiap run, tiap
tanggal: `areaN/before/` = temuan mentah, `areaN/after/` = bukti fix, plus `instructions/`
dan `README.md` di tiap level) supaya dokumentasi tetap **seragam dan persisten** antar run.

Setiap temuan punya **regression test** yang mengunci perbaikannya (`LoginThrottleHelperTest`,
`LoginBruteForceSuite`, `SessionLifecycleSuite`, `SessionFixationSuite`, `Area3AccessSuite`,
`Area4SanctionSuite`, `Area4WorkflowSuite`, `Area5XssSuite`, `Area5JsonLdSuite`) - total suite
kini **198/198 hijau**.



**Kontrol yang aktif (terverifikasi):** bcrypt + throttle login (5 gagal/15 mnt) + dummy-verify anti
timing-leak - session regeneration saat privilege change - file/ID token terenkripsi terikat sesi (IDOR) -
CSRF pada semua state-changing request (419 tanpa token) - CSP + `X-Frame-Options: DENY` + `nosniff` +
`Referrer-Policy` - error fail-closed - JSON embed hex-escaped (anti `</script>` breakout).

**Hasil & klaim (jujur):** setelah hardening dan pentest di atas (termasuk fase **deep authenticated
3 role** dengan uji IDOR / privilege escalation / XSS / SQLi / CSRF), **tidak ditemukan vulnerability
yang dapat dieksploitasi**. Kontrol otorisasi server-side terbukti kuat.

> **Disclaimer:** hasil pentest yang bersih **bukan** jaminan aplikasi 100% aman di production.
> Pengujian tidak pernah exhaustive dan proyek ini adalah sarana **belajar** yang terus diperbaiki.
> *A clean pentest is not a guarantee of absolute security.*

**Menemukan bug atau kerentanan?** Kami menyambut kontribusi - buka **GitHub Issue** (label
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

- `php artisan db:seed` otomatis mem-hash password plaintext di seed - bcrypt (cost 12). Login **hanya** menerima hash bcrypt; untuk import `.sql` lama secara manual, jalankan `php database/cli/hash-plaintext-passwords.php` sekali.
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

[MIT](LICENSE) - 2026 Dimas0824
