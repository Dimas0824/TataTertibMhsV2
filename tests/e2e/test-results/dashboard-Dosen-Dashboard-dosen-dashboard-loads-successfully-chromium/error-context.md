# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: dashboard.spec.js >> Dosen Dashboard >> dosen dashboard loads successfully
- Location: tests\dashboard.spec.js:60:3

# Error details

```
TimeoutError: page.waitForURL: Timeout 10000ms exceeded.
=========================== logs ===========================
waiting for navigation to "**/pelanggaran/dosen**" until "load"
============================================================
```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - complementary "Navigasi utama" [ref=e2]:
    - link "DiscipLink Home" [ref=e3] [cursor=pointer]:
      - /url: /
      - img "DiscipLink logo" [ref=e4]
      - text: DiscipLink
    - button "Pin sidebar" [ref=e5]:
      - generic [ref=e6]: 
    - list [ref=e7]:
      - listitem [ref=e8]:
        - link "Home" [ref=e9] [cursor=pointer]:
          - /url: /
          - generic [ref=e11]: 
          - text: Home
      - listitem [ref=e12]:
        - link "Tata Tertib" [ref=e13] [cursor=pointer]:
          - /url: /tatib
          - generic [ref=e15]: 
          - text: Tata Tertib
      - listitem [ref=e16]:
        - link "Pelanggaran" [ref=e17] [cursor=pointer]:
          - /url: /pelanggaran
          - generic [ref=e19]: 
          - text: Pelanggaran
  - generic [ref=e20]:
    - banner [ref=e21]:
      - heading "Login" [level=1] [ref=e23]
    - main [ref=e24]:
      - generic [ref=e25]:
        - text: DiscipLink Access
        - heading [level=2] [ref=e26]: Kelola tata tertib kampus dengan lebih rapi.
        - paragraph [ref=e27]: Masuk untuk melihat data pelanggaran, notifikasi, dan riwayat pelaporan dalam satu dashboard.
        - list [ref=e28]:
          - listitem [ref=e29]:
            - generic [ref=e30]: 
            - text: Login aman untuk setiap user
          - listitem [ref=e31]:
            - generic [ref=e32]: 
            - text: Data pelanggaran terstruktur
      - generic [ref=e34]:
        - heading "Selamat Datang" [level=3] [ref=e35]
        - paragraph [ref=e36]: Masuk ke akun DiscipLink kamu
        - text: Username
        - generic [ref=e37]:
          - generic [ref=e38]: 
          - textbox "Username" [ref=e39]:
            - /placeholder: Masukkan Username
        - text: Kata Sandi
        - generic [ref=e40]:
          - generic [ref=e41]: 
          - textbox "Kata Sandi" [ref=e42]:
            - /placeholder: Masukkan Kata Sandi
        - button "Masuk" [ref=e43]
    - alertdialog [ref=e45]:
      - banner [ref=e46]:
        - text: Informasi
        - button [ref=e47]:
          - generic [ref=e48]: 
      - generic [ref=e49]:
        - generic [ref=e50]: i
        - heading [level=2] [ref=e51]: Informasi
      - contentinfo [ref=e52]:
        - button [ref=e53]: Tutup
```

# Test source

```ts
  1   | // @ts-check
  2   | const { test, expect } = require('@playwright/test');
  3   | 
  4   | /**
  5   |  * Helper: login as specific role
  6   |  */
  7   | async function loginAs(page, role, username, password) {
  8   |   await page.goto('/login');
  9   |   
  10  |   // Set user_type based on role
  11  |   const userTypeMap = {
  12  |     mahasiswa: 'nim',
  13  |     dosen: 'nidn',
  14  |     admin: 'NIP',
  15  |   };
  16  |   
  17  |   await page.evaluate((type) => {
  18  |     document.querySelector('input[name="user_type"]').value = type;
  19  |   }, userTypeMap[role]);
  20  |   
  21  |   await page.fill('input[name="username"]', username);
  22  |   await page.fill('input[name="password"]', password);
  23  |   await page.click('button[type="submit"]');
  24  | }
  25  | 
  26  | test.describe('Mahasiswa Dashboard', () => {
  27  |   test.beforeEach(async ({ page }) => {
  28  |     await loginAs(page, 'mahasiswa', '2341238901', 'password123');
  29  |     await page.waitForURL('**/pelanggaran**', { timeout: 10000 });
  30  |   });
  31  | 
  32  |   test('dashboard loads successfully', async ({ page }) => {
  33  |     await expect(page.locator('body')).toBeVisible();
  34  |   });
  35  | 
  36  |   test('shows violation list or empty state', async ({ page }) => {
  37  |     // Should show table or empty state message
  38  |     const hasTable = await page.locator('table').isVisible().catch(() => false);
  39  |     const hasEmptyState = await page.locator('text=tidak ada').isVisible().catch(() => false);
  40  |     assertTrue(hasTable || hasEmptyState || true, 'Should show violation data or empty state');
  41  |   });
  42  | 
  43  |   test('shows notification page', async ({ page }) => {
  44  |     await page.goto('/notifikasi');
  45  |     await expect(page.locator('body')).toBeVisible();
  46  |   });
  47  | 
  48  |   test('can view tatib list', async ({ page }) => {
  49  |     await page.goto('/tatib');
  50  |     await expect(page.locator('body')).toBeVisible();
  51  |   });
  52  | });
  53  | 
  54  | test.describe('Dosen Dashboard', () => {
  55  |   test.beforeEach(async ({ page }) => {
  56  |     await loginAs(page, 'dosen', '1234567890', 'password123');
> 57  |     await page.waitForURL('**/pelanggaran/dosen**', { timeout: 10000 });
      |                ^ TimeoutError: page.waitForURL: Timeout 10000ms exceeded.
  58  |   });
  59  | 
  60  |   test('dosen dashboard loads successfully', async ({ page }) => {
  61  |     await expect(page.locator('body')).toBeVisible();
  62  |   });
  63  | 
  64  |   test('shows laporan list or empty state', async ({ page }) => {
  65  |     // Should show table or empty state
  66  |     await expect(page.locator('body')).toBeVisible();
  67  |   });
  68  | 
  69  |   test('can access pelaporan page', async ({ page }) => {
  70  |     await page.goto('/pelaporan');
  71  |     await expect(page.locator('body')).toBeVisible();
  72  |   });
  73  | });
  74  | 
  75  | test.describe('Admin Dashboard', () => {
  76  |   test.beforeEach(async ({ page }) => {
  77  |     await loginAs(page, 'admin', 'ADMIN001', 'admin123');
  78  |     await page.waitForURL('**/admin**', { timeout: 10000 });
  79  |   });
  80  | 
  81  |   test('admin dashboard loads successfully', async ({ page }) => {
  82  |     await expect(page.locator('body')).toBeVisible();
  83  |   });
  84  | 
  85  |   test('can access tatib management', async ({ page }) => {
  86  |     await page.goto('/admin/tatib');
  87  |     await expect(page.locator('body')).toBeVisible();
  88  |   });
  89  | 
  90  |   test('can access news management', async ({ page }) => {
  91  |     await page.goto('/admin/news');
  92  |     await expect(page.locator('body')).toBeVisible();
  93  |   });
  94  | 
  95  |   test('can access tambah berita page', async ({ page }) => {
  96  |     await page.goto('/admin/news/tambah');
  97  |     await expect(page.locator('body')).toBeVisible();
  98  |   });
  99  | });
  100 | 
  101 | function assertTrue(condition, message) {
  102 |   if (!condition) {
  103 |     throw new Error(message || 'Assertion failed');
  104 |   }
  105 | }
  106 | 
```