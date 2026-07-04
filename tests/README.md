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
├── run.php                 # Entry point untuk PHP tests
├── TestRunner.php          # Custom test framework
├── bootstrap.php           # Test bootstrap & autoloader
├── TestCase.php            # Base test case class
├── phpunit.xml.dist        # PHPUnit config (untuk masa depan)
├── unit/
│   ├── HelpersTest.php     # Test helper functions
│   └── ModelsTest.php      # Test model classes
├── integration/
│   └── DatabaseTest.php    # Test database connectivity
└── e2e/
    ├── package.json        # Playwright dependencies
    ├── playwright.config.js # Playwright configuration
    └── tests/
        ├── public.spec.js  # Test public pages & auth
        └── dashboard.spec.js # Test role-based dashboards
```

## Test Credentials

| Role | Username | Password |
|------|----------|----------|
| Mahasiswa | `2341238901` | `password123` |
| Dosen | `1234567890` | `password123` |
| Admin | `ADMIN001` | `admin123` |

## Bug Tracking

Lihat [`BUG_REPORT.md`](../BUG_REPORT.md) untuk dokumentasi bug dan tracking perbaikan.

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

Integration tests memerlukan database terpisah:

```sql
CREATE DATABASE DiscipLink_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Lalu jalankan migration:

```bash
php artisan migrate:fresh --seed --force
```

## CI/CD Integration

Untuk menjalankan tests di CI:

```bash
# Unit tests
php tests/run.php

# E2E tests
cd tests/e2e && npm ci && npx playwright install --with-deps && npm test
```
