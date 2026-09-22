# Testing Documentation - DiscipLink V2

## Overview

DiscipLink V2 menggunakan 3 layer testing:

1. **Unit Tests** - Test individual functions dan classes (PHP native test runner)
2. **Integration Tests** - Test database queries dan data integrity
3. **E2E Tests** - Test user flows menggunakan Playwright browser automation

## Quick Start

### Menjalankan Unit & Integration Tests

```bash
# Dari root project
php tests/run.php
```

### Menjalankan E2E Tests

```bash
# 1. Install Playwright dependencies
cd tests/e2e
npm install
npx playwright install

# 2. Jalankan tests
npm test

# 3. Lihat report
npm run report
```

### Menjalankan Server untuk Manual Testing

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

## Struktur Test

```
tests/
  run.php                 # Entry point untuk PHP tests
  TestRunner.php          # Custom test framework
  bootstrap.php           # Test bootstrap & autoloader
  TestCase.php            # Base test case class
  phpunit.xml.dist        # PHPUnit config (untuk masa depan)
  unit/
    HelpersTest.php     # Test helper functions
    ModelsTest.php      # Test model classes
    PelanggaranModelTest.php      # Model behaviour: simpan/update/konfirmasi/hapus, search, notif (DB-backed)
    PelanggaranControllerTest.php # Controller pass-through + mark-notif validation
    NewsControllerTest.php        # News slug helpers + CRUD + sanitizer
    ModelCrudTest.php             # News & Tatib model CRUD
    SeoHelperTest.php             # SEO helpers (origin/canonical/json-ld/meta tags)
    ControllerCoverageTest.php    # Tatib/User controller wrappers
  integration/
    DatabaseTest.php    # Test database connectivity
  security/
    SecurityClient.php      # Harness: boot `php -S` + curl blackbox client
    SourceScanSuite.php     # Static scan (deny rules, echo-leak, dsb.)
    TokenSuite.php          # Unit: file/id token, CSRF, sanitizer, token edge cases
    UploadOwnershipSuite.php # Regresi upload: owner-success, cross-user, CSRF-less
    HandlerCoverageSuite.php # HTTP: handler-notifikasi + handler-tatib + handler-pelanggaran actions
    NewsHandlerSuite.php    # HTTP: handler-news store/update/delete branches
    PelanggaranFormSuite.php # HTTP: /pelaporan form POST (store/update)
    HttpMatrixSuite.php     # Blackbox red-team matrix (auth, IDOR, XSS, upload)
  cover.php               # Coverage runner (Xdebug): tests/cover.php
  cov_router.php          # Router wrapper used by cover.php for server-side coverage
  e2e/
      package.json        # Playwright dependencies
      playwright.config.js # Playwright configuration
      tests/
          public.spec.js  # Test public pages & auth
          dashboard.spec.js # Test role-based dashboards
```

## Coverage (Xdebug)

`tests/cover.php` mengukur **line coverage** memakai Xdebug (CLI + HTTP server digabung).
Xdebug tidak di-bundle dengan project; arahkan `PHP_INI_SCAN_DIR` ke folder berisi
`xdebug.ini` (`zend_extension=...` + `xdebug.mode=coverage`):

```bash
XDEBUG_MODE=coverage php tests/cover.php
```

Prinsipnya **kualitas, bukan angka**: fungsi-fungsi inti (auth, token, otorisasi,
upload, model pelanggaran) sudah >80% dan teruji lewat regresi bermakna. Baris yang
sengaja dibiarkan belum ter-cover umumnya adalah cabang error defensif / catch-block
yang sulit dipicu tanpa memaksa kegagalan buatan.

## Menjalankan Suite dengan DB Lokal

Suite `security/*` butuh database ter-seed. Urutan sumber kredensial: **`.env.testing`**
(jika ada) -> **`.env`** (root project). Jadi secara default test memakai DB yang sama
dengan aplikasi - cukup pastikan `.env` valid dan DB ter-seed:

```bash
php artisan migrate:fresh --seed --force
php tests/run.php
```

Kalau ingin DB test terpisah, salin `tests/.env.testing.example` -> `tests/.env.testing`
dan sesuaikan (tanpa mengubah `.env` aplikasi).

`HttpMatrixSuite` + `UploadOwnershipSuite` + `HandlerCoverageSuite` men-boot `php -S` mereka
sendiri di port 8123-8140, jadi tidak perlu server dev berjalan.

## Test Credentials

| Role | Username | Password |
| ------ | ---------- | ---------- |
| Mahasiswa | `2341238901` | `password123` |
| Mahasiswa (2) | `2341238902` | `password456` |
| Dosen | `1234567890` | `password123` |
| Admin | `ADMIN001` | `admin123` |

## Bug Tracking

Lihat [`docs/intern/BUG_REPORT.md`](../docs/intern/BUG_REPORT.md) untuk dokumentasi bug dan tracking perbaikan.

## Menambah Test Baru

### Unit Test

1. Buat file baru di `tests/unit/` atau `tests/integration/`
2. Daftarkan di `tests/run.php`
3. Gunakan assertion functions dari `TestRunner.php`

```php
$runner->addTest('Test description', function() {
    // Arrange
    $input = 'test';
    
    // Act
    $result = some_function($input);
    
    // Assert
    assertEquals('expected', $result);
});
```

### E2E Test

1. Buat file `.spec.js` baru di `tests/e2e/tests/`
2. Gunakan Playwright API

```javascript
const { test, expect } = require('@playwright/test');

test('test description', async ({ page }) => {
    await page.goto('/some-page');
    await expect(page.locator('.element')).toBeVisible();
});
```

## Database Testing

Suite memakai kredensial DB dari (berurutan): **`.env.testing`** -> **`.env`** (root project) ->
default lokal. Jadi secara default test memakai DB yang sama dengan aplikasi.

Untuk isolasi penuh, siapkan DB test terpisah:

```sql
CREATE DATABASE disciplink_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Arahkan test ke DB itu dengan membuat `tests/.env.testing` (lihat
[`tests/.env.testing.example`](./.env.testing.example)) - atau cukup salin `.env` project
dan ganti `dbname`. Lalu jalankan migration/seed ke DB tersebut:

```bash
php artisan migrate:fresh --seed --force
```

> **Catatan:** beberapa test HTTP/XSS memerlukan tabel nyata (mis. `news`). Kalau DB yang
> dipakai test belum di-migrate, test tersebut gagal dengan `Table '...news' doesn't exist` - 
> itu gejala DB belum di-seed, bukan bug kode.

## Troubleshooting

**`Access denied for user 'root'@'<ip>'`** - test dijalankan dari lingkungan yang berbeda
dengan MySQL (mis. shell di dalam WSL/container). MySQL di host tidak ter-reach lewat `127.0.0.1`
dari dalam WSL/container. Solusi: jalankan test dari host, atau set `DB_DSN` di `.env`/env var
ke host yang benar (`host.docker.internal` untuk container, atau IP host untuk WSL).

**`Warning: Cannot connect to test database`** (lama) - sudah ditangani: bootstrap kini
fallback ke `.env` bila `.env.testing` tidak ada.

**Semua test HTTP gagal serentak (`got 0`)** - biasanya ada proses `php -S` zombie menempati
port **8123 - 8140**. Cek & bersihkan:

```powershell
Get-NetTCPConnection -LocalPort 8123,8124 -State Listen
```

## CI/CD Integration

Untuk menjalankan tests di CI:

```bash
# Unit tests
php tests/run.php

# E2E tests
cd tests/e2e && npm ci && npx playwright install --with-deps && npm test
```
