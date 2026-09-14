# Bug Report & Tracking - DiscipLink V2

Dokumen ini berisi daftar bug yang ditemukan melalui automated testing dan exploratory QA.

> **Status per 2026-09-14:** Seluruh 4 bug di bawah (era 2026-07-04) **sudah ditutup dan diverifikasi**
> lewat gelombang hardening keamanan 2026-09 (bcrypt + throttle, session regeneration, file-token IDOR,
> CSRF, error-disclosure, XSS). Dokumen ini dipertahankan sebagai **jejak historis** (portfolio):
> apa yang ditemukan, bagaimana direproduksi, dan kapan diverifikasi ulang.
> Bukti terkini: [`PENTEST-REPORT-2026-09-08.md`](./PENTEST-REPORT-2026-09-08.md) dan
> [`pentest-strix/`](./pentest-strix/README.md).

## Cara Menggunakan Dokumen Ini

1. **Status Bug**: `OPEN` | `IN_PROGRESS` | `FIXED` | `WONTFIX` | `VERIFIED`
2. **Severity**: `Critical` | `High` | `Medium` | `Low`
3. **Category**: `Functional` | `Security` | `Performance` | `UI/UX` | `Data` | `Backend` | `Frontend`
4. Setiap bug punya langkah reproduksi yang jelas dan expected vs actual behavior

---

## Ringkasan

| Severity | Total | Open | Fixed/Verified |
|----------|-------|------|----------------|
| Critical | 1     | 0    | 1              |
| High     | 2     | 0    | 2              |
| Medium   | 1     | 0    | 1              |
| Low      | 0     | 0    | 0              |
| **Total**| **4** | **0**| **4**          |

*Ditemukan: 2026-07-04 · Diremediasi & diverifikasi: 2026-09 (hardening `fix/security-hardening`).*
*Terakhir diupdate: 2026-09-14.*

---

## Daftar Bug

### BUG-001: Login Tidak Berfungsi - Database Connection Error

- **Severity**: Critical
- **Category**: Backend / Database
- **Status**: FIXED / VERIFIED (2026-09)
- **Ditemukan oleh**: E2E Test (Playwright)
- **Tanggal**: 2026-07-04
- **Resolved**: 2026-09 — akar masalah bukan "login rusak", melainkan (a) environment test tanpa DB dan
  (b) seed password masih plaintext. Hardening menyelesaikan keduanya: `artisan db:seed` kini selalu
  bcrypt (cost 12), login hanya menerima hash bcrypt, plus throttle 5 gagal/15 menit + dummy-verify.
  Diverifikasi live: login mahasiswa/dosen/admin → 302 ke dashboard role masing-masing.
- **File Terkait**:
  - `request/handler-login.php`
  - `controllers/UserController.php`
  - `models/User.php`
  - `config.php`

**Deskripsi:**
Login tidak berfungsi untuk semua role (mahasiswa, dosen, admin). Setelah submit form login, page tidak redirect ke dashboard yang sesuai. Kemungkinan besar karena database connection error atau session tidak ter-set dengan benar.

**Langkah Reproduksi:**

1. Buka <http://127.0.0.1:8080/login>
2. Masukkan username: `2341238901` (mahasiswa)
3. Masukkan password: `password123`
4. Klik tombol "Masuk"

**Expected Behavior:**
Setelah login berhasil, user harus di-redirect ke `/pelanggaran` untuk mahasiswa.

**Actual Behavior:**
Page tetap di `/login` atau redirect kembali ke `/login` tanpa error message yang jelas.

**Error Log:**

```
Test timeout of 10000ms exceeded.
waiting for navigation to "**/pelanggaran**" until "load"
```

**Root Cause Hypothesis:**

1. Database connection tidak tersedia di environment testing
2. Session tidak ter-set dengan benar setelah login
3. Flash message tidak ditampilkan dengan benar

**Fix:**

- [x] Diperbaiki (2026-09)
- Seed password di-hash bcrypt sebelum masuk DB (`artisan db:seed`); untuk `.sql` lama tersedia `database/cli/hash-plaintext-passwords.php`
- Login menerima hanya hash bcrypt + throttle 5×/15 mnt (per sesi) + dummy-verify anti timing-leak
- Diverifikasi: 3 role login sukses (302 → dashboard role)

---

### BUG-002: Form Login Menggunakan Hidden Input untuk User Type

- **Severity**: High
- **Category**: UI/UX / Functional
- **Status**: FIXED / VERIFIED (2026-09)
- **Ditemukan oleh**: E2E Test (Playwright)
- **Tanggal**: 2026-07-04
- **Resolved**: 2026-09 — form login kini menyediakan cara memilih role (bukan lagi manual
  mengubah hidden input via dev tools). Diverifikasi di E2E suite (21/21 chromium green) dan
  di security suite `HttpMatrixSuite` (login ketiga role via `user_type` yang benar).
- **File Terkait**: `views/auth/login.php`

**Deskripsi:**
Form login menggunakan `<input type="hidden" name="user_type" value="nim">` untuk menentukan role user. Tidak ada UI untuk memilih role (mahasiswa/dosen/admin). User harus manual change hidden input value via JavaScript atau browser dev tools.

**Langkah Reproduksi:**

1. Buka halaman login
2. Inspect element
3. Lihat input hidden `user_type` dengan value default `nim`

**Expected Behavior:**
Harus ada dropdown atau radio button untuk memilih role sebelum login, ATAU sistem harus auto-detect role berdasarkan username format.

**Actual Behavior:**
Hidden input dengan value `nim` (mahasiswa) sebagai default. Dosen dan admin tidak bisa login tanpa manual change value ini.

**Impact:**

- Dosen tidak bisa login (harus manual change ke `nidn`)
- Admin tidak bisa login (harus manual change ke `NIP`)
- UX buruk untuk testing dan demo

**Fix:**

- [x] Diperbaiki (2026-09)
- Role selector tersedia di form login (mahasiswa/dosen/admin) — tidak lagi perlu mengubah hidden input manual
- Diverifikasi lewat E2E Playwright (21/21) + `tests/security/HttpMatrixSuite.php`

---

### BUG-003: Error Message Tidak Muncul Saat Login Gagal

- **Severity**: High
- **Category**: UI/UX / Frontend
- **Status**: FIXED / VERIFIED (2026-09)
- **Ditemukan oleh**: E2E Test (Playwright)
- **Tanggal**: 2026-07-04
- **Resolved**: 2026-09 — kegagalan login sekarang menampilkan pesan generik
  (`Invalid username or password`) yang seragam untuk "user tidak ada" dan "password salah"
  (mencegah user-enumeration). Diverifikasi di `HttpMatrixSuite`.
- **File Terkait**:
  - `request/handler-login.php`
  - `views/auth/login.php`
  - `helpers/flash_modal.php`

**Deskripsi:**
Saat login dengan kredensial invalid, tidak ada error message yang ditampilkan di halaman login. User tidak tahu apakah login gagal karena username salah, password salah, atau error lain.

**Langkah Reproduksi:**

1. Buka halaman login
2. Masukkan username: `invalid_user`
3. Masukkan password: `wrong_password`
4. Klik "Masuk"

**Expected Behavior:**
Error message muncul: "Username atau password salah" atau sejenisnya.

**Actual Behavior:**
Page redirect kembali ke `/login` tanpa error message.

**Fix:**

- [x] Diperbaiki (2026-09)
- Flash message generik dirender konsisten di halaman login; pesan seragam (anti user-enumeration)
- Diverifikasi di `tests/security/HttpMatrixSuite.php`

---

### BUG-004: Database Test Skipped - No Test Database Configured

- **Severity**: Medium
- **Category**: Testing / Infrastructure
- **Status**: FIXED / VERIFIED (2026-09)
- **Ditemukan oleh**: Unit Test (PHP TestRunner)
- **Tanggal**: 2026-07-04
- **Resolved**: 2026-09 — DB test terpisah `disciplink_test` (15 tabel, seeded) dipakai untuk
  integration & security HTTP-matrix suite; `.env` produksi tidak disentuh (dipakai swap sementara
  - restore terverifikasi hash). CI (`ci.yml`) menjalankan migrate/seed + full suite di PHP 8.3/MySQL 8.
- **File Terkait**: `tests/bootstrap.php`, `tests/integration/DatabaseTest.php`

**Deskripsi:**
Integration tests untuk database di-skip karena tidak ada database `DiscipLink_test` yang tersedia. Test runner tidak bisa connect ke database dengan kredensial default.

**Langkah Reproduksi:**

1. Jalankan `php tests/run.php`
2. Lihat output: "Warning: Cannot connect to test database"

**Expected Behavior:**
Integration tests berjalan dan memvalidasi database schema.

**Actual Behavior:**
Integration tests di-skip, hanya unit tests yang berjalan.

**Fix:**

- [x] Diperbaiki (2026-09)
- DB test terpisah `disciplink_test` dibuat; integration test berjalan
- CI `ci.yml`: lint + migrate/seed + full test suite (unit + integration + security) di PHP 8.3/MySQL 8
- Diverifikasi: `disciplink_test` seeded (15 tabel) dan suite mengaksesnya tanpa menyentuh DB utama

---

## Catatan Testing

### Test Suite yang Sudah Dijalankan

> **Angka di bawah adalah kondisi 2026-07-04 (saat bug ditemukan).** Lihat tabel berikutnya untuk kondisi terkini.

| Test Suite | Tanggal | Hasil | Catatan |
| ----------- | --------- | ------- | --------- |
| Unit Tests (Helpers) | 2026-07-04 | 12/12 PASS | Semua helper functions bekerja |
| Unit Tests (Models) | 2026-07-04 | 9/9 PASS | Semua model classes valid |
| Integration Tests (DB) | 2026-07-04 | ⏭️ SKIPPED | Database belum tersedia |
| E2E Tests (Playwright) | 2026-07-04 | X 7/21 PASS | 14 tests gagal, mostly login issues |

### Kondisi Terkini (2026-09)

| Test Suite | Hasil | Catatan |
| ----------- | ------- | --------- |
| Unit Tests (Helpers + Models) | 21/21 PASS | Stabil |
| Security Regression (token/scan/http-matrix) | 39 test | Suite red-team `tests/security/**`; replay payload pentest, target `failed 0` |
| Integration Tests (DB) | berjalan | DB terpisah `disciplink_test` |
| E2E Tests (Playwright, chromium) | 21/21 PASS | Job e2e di CI dipromosikan jadi **blocking** (`c011b5e`) |

**Catatan lokal (Windows):** saat menjalankan `php tests/run.php` di mesin Windows, sebagian test
HTTP-matrix bisa gagal dengan `curl: (3) URL rejected` — ini artefak escaping argumen `curl.exe`
di mesin lokal, **bukan** regresi aplikasi (CI Linux hijau). Jalur bersih: jalankan via CI atau
pastikan `curl` di PATH sesuai platform.

### E2E Test Results Detail (2026-07-04 — historis)

**Passing Tests (7):**

-  Homepage loads successfully
-  Login page loads successfully
-  Tatib page loads successfully
-  404 page for invalid route
-  Login with invalid credentials shows error (redirects back)
-  Login with empty credentials shows validation error
-  Unauthenticated access redirects to login

**Failing Tests (14):**

- X All authentication tests (mahasiswa/dosen/admin login)
- X All dashboard tests (require successful login)

**Root Cause (saat itu):** BUG-001 (Login tidak berfungsi) dan BUG-002 (Hidden input user_type) — keduanya kini **FIXED/VERIFIED**.

---

## Known Issues (Non-Bug)

- ~~Database test skipped karena koneksi DB belum dikonfigurasi~~ → **beres**: `disciplink_test` + CI.
- Integration tests memerlukan database `disciplink_test` yang terpisah dari production (tersedia).
- Playwright tests memerlukan server berjalan; di CI port dikelola `e2e.yml` (default lokal kini `8000`).
- **2026-09-14:** `/action/upload` sempat HTTP 500 (bug fungsional, bukan security) → sudah diperbaiki;
  detail di [`UPLOAD-500-INVESTIGATION.md`](./UPLOAD-500-INVESTIGATION.md). Bukan bagian dari 4 bug era 2026-07 di atas.

---

## Changelog

| Tanggal | Perubahan |
|---------|-----------|
| 2026-07-04 | Initial bug tracking setup. 21 unit tests passing. 7/21 E2E tests passing. 4 bugs documented. |
| 2026-09-14 | **Konsolidasi status**: BUG-001..004 ditutup & diverifikasi lewat hardening 2026-09 (bcrypt+throttle, session regen, file-token IDOR, CSRF, error-disclosure, XSS). Tabel ringkasan, hasil test, dan changelog disinkronkan ke kondisi terkini. |
