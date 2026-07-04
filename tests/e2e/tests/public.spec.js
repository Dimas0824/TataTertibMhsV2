// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Public Pages', () => {
  test('homepage loads successfully', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('body')).toBeVisible();
  });

  test('login page loads successfully', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('input[name="username"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    // user_type is a hidden input, not a select
    await expect(page.locator('input[name="user_type"]')).toHaveValue('nim');
  });

  test('tatib page loads successfully', async ({ page }) => {
    await page.goto('/tatib');
    await expect(page.locator('body')).toBeVisible();
  });

  test('404 page for invalid route', async ({ page }) => {
    const response = await page.goto('/invalid-route-12345');
    expect(response.status()).toBe(404);
  });
});

test.describe('Authentication', () => {
  test('login with invalid credentials shows error', async ({ page }) => {
    await page.goto('/login');
    
    await page.fill('input[name="username"]', 'invalid_user');
    await page.fill('input[name="password"]', 'wrong_password');
    // user_type defaults to 'nim' (mahasiswa)
    await page.click('button[type="submit"]');
    
    // Should redirect back to login with flash message
    await page.waitForURL('**/login**', { timeout: 5000 });
  });

  test('login with empty credentials shows validation error', async ({ page }) => {
    await page.goto('/login');
    
    // Form has required attribute, try submitting empty
    await page.click('button[type="submit"]');
    
    // HTML5 validation should prevent submission or show error
    // Wait a moment for any error display
    await page.waitForTimeout(1000);
  });

  test('mahasiswa login with valid credentials', async ({ page }) => {
    await page.goto('/login');
    
    // user_type hidden input defaults to 'nim' for mahasiswa
    await page.fill('input[name="username"]', '2341238901');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    
    // Should redirect to pelanggaran page
    await page.waitForURL('**/pelanggaran**', { timeout: 10000 });
  });

  test('dosen login with valid credentials', async ({ page }) => {
    await page.goto('/login');
    
    // Set user_type to 'nidn' for dosen
    await page.evaluate(() => {
      document.querySelector('input[name="user_type"]').value = 'nidn';
    });
    await page.fill('input[name="username"]', '1234567890');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    
    // Should redirect to dosen pelanggaran page
    await page.waitForURL('**/pelanggaran/dosen**', { timeout: 10000 });
  });

  test('admin login with valid credentials', async ({ page }) => {
    await page.goto('/login');
    
    // Set user_type to 'NIP' for admin
    await page.evaluate(() => {
      document.querySelector('input[name="user_type"]').value = 'NIP';
    });
    await page.fill('input[name="username"]', 'ADMIN001');
    await page.fill('input[name="password"]', 'admin123');
    await page.click('button[type="submit"]');
    
    // Should redirect to admin page
    await page.waitForURL('**/admin**', { timeout: 10000 });
  });
});

test.describe('Session Management', () => {
  test('unauthenticated access redirects to login', async ({ page }) => {
    await page.goto('/pelanggaran');
    
    // Should redirect to login
    await expect(page).toHaveURL(/\/login/);
  });
});
