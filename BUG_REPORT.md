# Bug Report & Tracking - DiscipLink V2

Dokumen ini berisi daftar bug yang ditemukan melalui automated testing dan exploratory QA.

## Cara Menggunakan Dokumen Ini

1. **Status Bug**: `OPEN` | `IN_PROGRESS` | `FIXED` | `WONTFIX` | `VERIFIED`
2. **Severity**: `Critical` | `High` | `Medium` | `Low`
3. **Category**: `Functional` | `Security` | `Performance` | `UI/UX` | `Data` | `Backend` | `Frontend`
4. Setiap bug punya langkah reproduksi yang jelas dan expected vs actual behavior

---

## Ringkasan

| Severity | Total | Open | Fixed |
|----------|-------|------|-------|
| Critical | 1     | 1    | 0     |
| High     | 2     | 2    | 0     |
| Medium   | 1     | 1    | 0     |
| Low      | 0     | 0    | 0     |
| **Total**| **4** | **4**| **0** |

*Terakhir diupdate: 2026-07-04*

---

## Daftar Bug

### BUG-001: Login Tidak Berfungsi - Database Connection Error

- **Severity**: Critical
- **Category**: Backend / Database
- **Status**: OPEN
- **Ditemukan oleh**: E2E Test (Playwright)
- **Tanggal**: 2026-07-04
- **File Terkait**: 
  - `request/handler-login.php`
  - `controllers/UserController.php`
  - `models/User.php`
  - `config.php`

**Deskripsi:**
Login tidak berfungsi untuk semua role (mahasiswa, dosen, admin). Setelah submit form login, page tidak redirect ke dashboard yang sesuai. Kemungkinan besar karena database connection error atau session tidak ter-set dengan benar.

**Langkah Reproduksi:**
1. Buka http://127.0.0.1:8080/login
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
- [ ] Belum diperbaiki
- Perlu verifikasi database connection di `config.php`
- Perlu cek session handling di `handler-login.php`

---

### BUG-002: Form Login Menggunakan Hidden Input untuk User Type

- **Severity**: High
- **Category**: UI/UX / Functional
- **Status**: OPEN
- **Ditemukan oleh**: E2E Test (Playwright)
- **Tanggal**: 2026-07-04
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
- [ ] Belum diperbaiki
- Opsi 1: Tambahkan dropdown untuk pilih role
- Opsi 2: Auto-detect role berdasarkan username pattern (NIM = angka 10 digit, NIDN = 10 digit, NIP = alphanumeric)

---

### BUG-003: Error Message Tidak Muncul Saat Login Gagal

- **Severity**: High
- **Category**: UI/UX / Frontend
- **Status**: OPEN
- **Ditemukan oleh**: E2E Test (Playwright)
- **Tanggal**: 2026-07-04
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
- [ ] Belum diperbaiki
- Perlu cek flash message rendering di `login.php`
- Pastikan `set_app_flash_modal()` dipanggil dengan benar di `handler-login.php`

---

### BUG-004: Database Test Skipped - No Test Database Configured

- **Severity**: Medium
- **Category**: Testing / Infrastructure
- **Status**: OPEN
- **Ditemukan oleh**: Unit Test (PHP TestRunner)
- **Tanggal**: 2026-07-04
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
- [ ] Belum diperbaiki
- Buat database `DiscipLink_test`
- Update `.env.testing` dengan kredensial database yang benar
- Atau gunakan SQLite in-memory untuk testing

---

## Catatan Testing

### Test Suite yang Sudah Dijalankan

| Test Suite | Tanggal | Hasil | Catatan |
|-----------|---------|-------|---------|
| Unit Tests (Helpers) | 2026-07-04 | ✅ 12/12 PASS | Semua helper functions bekerja |
| Unit Tests (Models) | 2026-07-04 | ✅ 9/9 PASS | Semua model classes valid |
| Integration Tests (DB) | 2026-07-04 | ⏭️ SKIPPED | Database belum tersedia |
| E2E Tests (Playwright) | 2026-07-04 | ❌ 7/21 PASS | 14 tests gagal, mostly login issues |

### E2E Test Results Detail

**Passing Tests (7):**
- ✅ Homepage loads successfully
- ✅ Login page loads successfully
- ✅ Tatib page loads successfully
- ✅ 404 page for invalid route
- ✅ Login with invalid credentials shows error (redirects back)
- ✅ Login with empty credentials shows validation error
- ✅ Unauthenticated access redirects to login

**Failing Tests (14):**
- ❌ All authentication tests (mahasiswa/dosen/admin login)
- ❌ All dashboard tests (require successful login)

**Root Cause:** BUG-001 (Login tidak berfungsi) dan BUG-002 (Hidden input user_type)

---

## Known Issues (Non-Bug)

- Database test skipped karena koneksi DB belum dikonfigurasi di environment testing
- Integration tests memerlukan database `DiscipLink_test` yang terpisah dari production
- Playwright tests memerlukan server running di port 8080

---

## Changelog

| Tanggal | Perubahan |
|---------|-----------|
| 2026-07-04 | Initial bug tracking setup. 21 unit tests passing. 7/21 E2E tests passing. 4 bugs documented. |
