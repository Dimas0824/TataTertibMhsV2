# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: public.spec.js >> Authentication >> admin login with valid credentials
- Location: tests\public.spec.js:80:3

# Error details

```
TimeoutError: page.waitForURL: Timeout 10000ms exceeded.
=========================== logs ===========================
waiting for navigation to "**/admin**" until "load"
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
  4   | test.describe('Public Pages', () => {
  5   |   test('homepage loads successfully', async ({ page }) => {
  6   |     await page.goto('/');
  7   |     await expect(page.locator('body')).toBeVisible();
  8   |   });
  9   | 
  10  |   test('login page loads successfully', async ({ page }) => {
  11  |     await page.goto('/login');
  12  |     await expect(page.locator('input[name="username"]')).toBeVisible();
  13  |     await expect(page.locator('input[name="password"]')).toBeVisible();
  14  |     // user_type is a hidden input, not a select
  15  |     await expect(page.locator('input[name="user_type"]')).toHaveValue('nim');
  16  |   });
  17  | 
  18  |   test('tatib page loads successfully', async ({ page }) => {
  19  |     await page.goto('/tatib');
  20  |     await expect(page.locator('body')).toBeVisible();
  21  |   });
  22  | 
  23  |   test('404 page for invalid route', async ({ page }) => {
  24  |     const response = await page.goto('/invalid-route-12345');
  25  |     expect(response.status()).toBe(404);
  26  |   });
  27  | });
  28  | 
  29  | test.describe('Authentication', () => {
  30  |   test('login with invalid credentials shows error', async ({ page }) => {
  31  |     await page.goto('/login');
  32  |     
  33  |     await page.fill('input[name="username"]', 'invalid_user');
  34  |     await page.fill('input[name="password"]', 'wrong_password');
  35  |     // user_type defaults to 'nim' (mahasiswa)
  36  |     await page.click('button[type="submit"]');
  37  |     
  38  |     // Should redirect back to login with flash message
  39  |     await page.waitForURL('**/login**', { timeout: 5000 });
  40  |   });
  41  | 
  42  |   test('login with empty credentials shows validation error', async ({ page }) => {
  43  |     await page.goto('/login');
  44  |     
  45  |     // Form has required attribute, try submitting empty
  46  |     await page.click('button[type="submit"]');
  47  |     
  48  |     // HTML5 validation should prevent submission or show error
  49  |     // Wait a moment for any error display
  50  |     await page.waitForTimeout(1000);
  51  |   });
  52  | 
  53  |   test('mahasiswa login with valid credentials', async ({ page }) => {
  54  |     await page.goto('/login');
  55  |     
  56  |     // user_type hidden input defaults to 'nim' for mahasiswa
  57  |     await page.fill('input[name="username"]', '2341238901');
  58  |     await page.fill('input[name="password"]', 'password123');
  59  |     await page.click('button[type="submit"]');
  60  |     
  61  |     // Should redirect to pelanggaran page
  62  |     await page.waitForURL('**/pelanggaran**', { timeout: 10000 });
  63  |   });
  64  | 
  65  |   test('dosen login with valid credentials', async ({ page }) => {
  66  |     await page.goto('/login');
  67  |     
  68  |     // Set user_type to 'nidn' for dosen
  69  |     await page.evaluate(() => {
  70  |       document.querySelector('input[name="user_type"]').value = 'nidn';
  71  |     });
  72  |     await page.fill('input[name="username"]', '1234567890');
  73  |     await page.fill('input[name="password"]', 'password123');
  74  |     await page.click('button[type="submit"]');
  75  |     
  76  |     // Should redirect to dosen pelanggaran page
  77  |     await page.waitForURL('**/pelanggaran/dosen**', { timeout: 10000 });
  78  |   });
  79  | 
  80  |   test('admin login with valid credentials', async ({ page }) => {
  81  |     await page.goto('/login');
  82  |     
  83  |     // Set user_type to 'NIP' for admin
  84  |     await page.evaluate(() => {
  85  |       document.querySelector('input[name="user_type"]').value = 'NIP';
  86  |     });
  87  |     await page.fill('input[name="username"]', 'ADMIN001');
  88  |     await page.fill('input[name="password"]', 'admin123');
  89  |     await page.click('button[type="submit"]');
  90  |     
  91  |     // Should redirect to admin page
> 92  |     await page.waitForURL('**/admin**', { timeout: 10000 });
      |                ^ TimeoutError: page.waitForURL: Timeout 10000ms exceeded.
  93  |   });
  94  | });
  95  | 
  96  | test.describe('Session Management', () => {
  97  |   test('unauthenticated access redirects to login', async ({ page }) => {
  98  |     await page.goto('/pelanggaran');
  99  |     
  100 |     // Should redirect to login
  101 |     await expect(page).toHaveURL(/\/login/);
  102 |   });
  103 | });
  104 | 
```