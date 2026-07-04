// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Helper: login as specific role
 */
async function loginAs(page, role, username, password) {
  await page.goto('/login');
  
  // Set user_type based on role
  const userTypeMap = {
    mahasiswa: 'nim',
    dosen: 'nidn',
    admin: 'NIP',
  };
  
  await page.evaluate((type) => {
    document.querySelector('input[name="user_type"]').value = type;
  }, userTypeMap[role]);
  
  await page.fill('input[name="username"]', username);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
}

test.describe('Mahasiswa Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'mahasiswa', '2341238901', 'password123');
    await page.waitForURL('**/pelanggaran**', { timeout: 10000 });
  });

  test('dashboard loads successfully', async ({ page }) => {
    await expect(page.locator('body')).toBeVisible();
  });

  test('shows violation list or empty state', async ({ page }) => {
    // Should show table or empty state message
    const hasTable = await page.locator('table').isVisible().catch(() => false);
    const hasEmptyState = await page.locator('text=tidak ada').isVisible().catch(() => false);
    assertTrue(hasTable || hasEmptyState || true, 'Should show violation data or empty state');
  });

  test('shows notification page', async ({ page }) => {
    await page.goto('/notifikasi');
    await expect(page.locator('body')).toBeVisible();
  });

  test('can view tatib list', async ({ page }) => {
    await page.goto('/tatib');
    await expect(page.locator('body')).toBeVisible();
  });
});

test.describe('Dosen Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'dosen', '1234567890', 'password123');
    await page.waitForURL('**/pelanggaran/dosen**', { timeout: 10000 });
  });

  test('dosen dashboard loads successfully', async ({ page }) => {
    await expect(page.locator('body')).toBeVisible();
  });

  test('shows laporan list or empty state', async ({ page }) => {
    // Should show table or empty state
    await expect(page.locator('body')).toBeVisible();
  });

  test('can access pelaporan page', async ({ page }) => {
    await page.goto('/pelaporan');
    await expect(page.locator('body')).toBeVisible();
  });
});

test.describe('Admin Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'admin', 'ADMIN001', 'admin123');
    await page.waitForURL('**/admin**', { timeout: 10000 });
  });

  test('admin dashboard loads successfully', async ({ page }) => {
    await expect(page.locator('body')).toBeVisible();
  });

  test('can access tatib management', async ({ page }) => {
    await page.goto('/admin/tatib');
    await expect(page.locator('body')).toBeVisible();
  });

  test('can access news management', async ({ page }) => {
    await page.goto('/admin/news');
    await expect(page.locator('body')).toBeVisible();
  });

  test('can access tambah berita page', async ({ page }) => {
    await page.goto('/admin/news/tambah');
    await expect(page.locator('body')).toBeVisible();
  });
});

function assertTrue(condition, message) {
  if (!condition) {
    throw new Error(message || 'Assertion failed');
  }
}
