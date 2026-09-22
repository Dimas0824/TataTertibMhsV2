# Testing Infrastructure Setup - Summary

> **Dokumen historis (2026-07-04).** Angka di bawah menggambarkan kondisi **saat infrastructure
> pertama di-setup** - dan pada saat itu **login belum diperbaiki**, sehingga 14/21 E2E gagal.
> **Kondisi terkini (2026-09):** login & semua bug terkait sudah FIXED/VERIFIED; E2E chromium
> **21/21 hijau** (job CI blocking), ditambah suite regresi keamanan. Untuk status terkini lihat
> [BUG_REPORT.md](./BUG_REPORT.md) (bagian "Kondisi Terkini") dan
> [`pentest-strix/`](./pentest-strix/README.md).

## Overview

Testing infrastructure telah berhasil di-setup untuk project DiscipLink V2 (PHP native dengan custom artisan).

## What Was Created

### 1. Unit & Integration Tests (PHP Native Test Runner)

**Location:** `tests/`

**Files:**

- `TestRunner.php` - Custom test framework (no PHPUnit dependency)
- `bootstrap.php` - Test bootstrap & autoloader
- `TestCase.php` - Base test case class
- `run.php` - Test entry point
- `unit/HelpersTest.php` - 12 tests untuk helper functions
- `unit/ModelsTest.php` - 9 tests untuk model classes
- `integration/DatabaseTest.php` - Database connectivity tests

**Run Command:**

```bash
php tests/run.php
```

**Results (2026-07-04 - historis):**

- 21/21 unit tests PASS
-  All helper functions validated
-  All model classes validated
-  Database tests skipped (requires DB setup)

> **Kemudian (2026-09):** suite diperluas dengan **regresi keamanan** (`tests/security/**`)
> dan **unit/behavior** (model & controller) - total suite kini **160 test, semua PASS**.
> Mencakup unit + integration (DB) + security + HTTP handler coverage. Line coverage diukur
> dengan Xdebug (`tests/cover.php`); fungsi inti >80%. Lihat [`../../tests/README.md`](../../tests/README.md).

---

### 2. E2E Tests (Playwright)

**Location:** `tests/e2e/`

**Files:**

- `package.json` - Playwright dependencies
- `playwright.config.js` - Playwright configuration
- `tests/public.spec.js` - Public pages & authentication tests
- `tests/dashboard.spec.js` - Role-based dashboard tests

**Run Commands:**

```bash
cd tests/e2e
npm install
npx playwright install chromium
npx playwright test --project=chromium
```

**Results (2026-07-04 - historis):**

- 7/21 tests PASS
- X 14/21 tests FAIL (login issues)
-  Screenshots & videos captured for failures

> **Kemudian (2026-09):** setelah login di-hardening, E2E chromium **21/21 hijau** dan job e2e di CI
> dipromosikan menjadi **blocking**.

**Passing Tests (era Juli 2026):**

- Homepage loads
- Login page loads
- Tatib page loads
- 404 page handling
- Invalid credentials handling
- Empty credentials validation
- Unauthenticated access redirect

**Failing Tests:**

- All authentication tests (mahasiswa/dosen/admin login)
- All dashboard tests (require successful login)

---

### 3. Bug Documentation

**Location:** `BUG_REPORT.md`

**Bugs Documented (semuanya kini FIXED/VERIFIED - lihat `BUG_REPORT.md`):**

| ID | Severity | Category | Status | Description |
| ---- | ---------- | ---------- | -------- | ------------- |
| BUG-001 | Critical | Backend/Database | FIXED | Login tidak berfungsi - diperbaiki (bcrypt seed + throttle) |
| BUG-002 | High | UI/UX | FIXED | Form login menggunakan hidden input untuk user type |
| BUG-003 | High | UI/UX/Frontend | FIXED | Error message tidak muncul saat login gagal |
| BUG-004 | Medium | Testing | FIXED | Database test skipped - no test database configured |

---

## Test Credentials

| Role | Username | Password | user_type Value |
| ------ | ---------- | ---------- | ----------------- |
| Mahasiswa | `2341238901` | `password123` | `nim` |
| Dosen | `1234567890` | `password123` | `nidn` |
| Admin | `ADMIN001` | `admin123` | `NIP` |

---

## How to Use

### Running Unit Tests

```bash
# From project root
php tests/run.php
```

### Running E2E Tests

```bash
# 1. Start server
php artisan serve --host=127.0.0.1 --port=8080

# 2. Run tests (in another terminal)
cd tests/e2e
npx playwright test --project=chromium

# 3. View report
npx playwright show-report
```

### Adding New Tests

**Unit Test:**

```php
// In tests/unit/YourTest.php
$runner->addTest('Test description', function() {
    // Arrange
    $input = 'test';
    
    // Act
    $result = some_function($input);
    
    // Assert
    assertEquals('expected', $result);
});
```

**E2E Test:**

```javascript
// In tests/e2e/tests/your-test.spec.js
const { test, expect } = require('@playwright/test');

test('test description', async ({ page }) => {
    await page.goto('/some-page');
    await expect(page.locator('.element')).toBeVisible();
});
```

---

## Next Steps

> **Update 2026-09:** Prioritas 1 & 2 di bawah **sudah selesai** (lihat [`BUG_REPORT.md`](./BUG_REPORT.md)
> bagian "Kondisi Terkini"). Prioritas 3 (ekspansi coverage) sebagian sudah dikerjakan via suite
> keamanan. Sisa ide: audit-trail test assertions, CSP nonce (Phase #2), dan throttle berbasis IP.

### Priority 1: Fix Critical Bugs - SELESAI (2026-09)

1. **BUG-001** - Login functionality fixed (bcrypt + throttle + session regen)
2. **BUG-002** - Role selection UI fixed
3. **BUG-003** - Error message display fixed (generic, anti-enumeration)

### Priority 2: Setup Test Database - SELESAI (2026-09)

1. Database `disciplink_test` dibuat 
2. Migration & seed dijalankan 
3. Kredensial test DB terpisah dari production 
4. Integration tests berjalan (tidak lagi di-skip) 

### Priority 3: Expand Test Coverage

1. Add more unit tests for controllers
2. Add integration tests for API endpoints
3. Add E2E tests for CRUD operations
4. Add performance tests

---

## Architecture Notes

### Test Framework Choice

**Why Custom Test Runner (not PHPUnit)?**

- No composer dependency
- Works with PHP native project
- Simple and lightweight
- Easy to understand and extend

**Why Playwright?**

- Cross-browser testing
- Auto-wait and retry
- Screenshot & video capture
- Good for complex user flows

---

## File Structure

```
tests/
  run.php                    # Entry point for PHP tests
  TestRunner.php             # Custom test framework
  bootstrap.php              # Test bootstrap
  TestCase.php               # Base test case
  README.md                  # Testing documentation
  unit/
    HelpersTest.php        # Helper function tests
    ModelsTest.php         # Model class tests
  integration/
    DatabaseTest.php       # Database tests
  security/                  # Red-team regression suites
    SecurityClient.php     # `php -S` harness + curl client
    TokenSuite.php         # Capability token tests
    SourceScanSuite.php    # Static guardrails
    HttpMatrixSuite.php    # Blackbox HTTP matrix
    UploadOwnershipSuite.php
  e2e/
      package.json           # Playwright deps
      playwright.config.js   # Playwright config
      tests/
          public.spec.js     # Public page tests
          dashboard.spec.js  # Dashboard tests
```

Bug tracking & ringkasan testing tinggal di `docs/intern/` (bukan lagi root repo).

---

## Commands Reference

| Command | Description |
| --------- | ------------- |
| `php tests/run.php` | Run all unit & integration tests |
| `cd tests/e2e && npx playwright test` | Run E2E tests |
| `npx playwright test --headed` | Run E2E tests with browser visible |
| `npx playwright test --debug` | Run E2E tests in debug mode |
| `npx playwright show-report` | View HTML test report |

---

## Success Metrics

**Kondisi 2026-07-04 (historis):**

- Catatan: Unit Tests: 21/21 passing (100%)
- X E2E Tests: 7/21 passing (33%) - blocked by login bugs
-  Bug Documentation: 4 bugs documented with reproduction steps
-  Test Infrastructure: Fully operational

**Kondisi terkini (2026-09):**

-  Unit + Integration: 21/21 (+ integration via `disciplink_test`)
-  **Security regression suite**: `tests/security/**` (39 test red-team) - target `failed 0`
-  **E2E Tests: 21/21 passing** (chromium, job CI blocking)
-  CI: lint + migrate/seed + full suite (PHP 8.3 / MySQL 8)
-  Semua bug (BUG-001..004) FIXED/VERIFIED

---

*Generated: 2026-07-04 - Terakhir disinkronkan: 2026-09-14*
