# Reference: API & File Index

Dokumen ini adalah referensi teknis untuk developer yang sudah familiar dengan DiscipLink V2.
Bukan tutorial — langsung ke fakta.

---

## Stack & Runtime

| Komponen | Nilai |
|---|---|
| Bahasa | PHP 8.1+ (native, tanpa framework) |
| Database | MySQL/MariaDB via PDO |
| Frontend | HTML5, CSS3, Vanilla JS (ES6+) |
| Routing | Custom central router (router.php) |
| Auth | PHP native sessions + encrypted token IDs |
| CLI | Custom artisan (migrate, seed, serve) |

---

## Arsitektur Request

```
router.php (dispatch berdasarkan PATH_INFO)
  ├─► PAGE ROUTE → views/{kategori}/{file}.php (langsung render)
  └─► ACTION ROUTE → request/handler-{nama}.php
                        ├─► app_require_login() / app_require_role()
                        ├─► Controller::method()
                        ├─► Model::query()
                        └─► respondJson() / app_redirect()
```

---

## Routing Registry

Lokasi: `helpers/route_helper.php`

### Page Routes

Format:

```php
'page.slug' => [
    'path' => '/url-path',
    'file' => 'views/kategori/file.php',
    'title' => 'Judul Halaman',
    'roles' => ['mahasiswa', 'dosen', 'admin'],  // opsional, empty = guest
],
```

### Action Routes

Format:

```php
'action.slug' => [
    'path' => '/action/nama',
    'file' => 'request/handler-nama.php',
],
```

### Helper URL

```php
app_page_url('page.slug')                   // → string URL
app_action_url('action.slug')              // → string URL
app_id_token('nama_tabel', (int) $id)      // → encrypted token string
app_id_resolve((string) $token, 'nama_tabel') // → int|null
```

---

## Controllers

| File | Responsibility |
|---|---|
| `UserController.php` | Login, logout, session, role redirect |
| `PelanggaranController.php` | CRUD pelanggaran, konfirmasi, delete |
| `TatibController.php` | CRUD tata tertib |
| `NewsController.php` | CRUD berita, upload gambar |

### Auth Methods (UserController)

```php
UserController::login(string $username, string $password, string $userType): array
// Return: ['status' => 'success'|'error', 'message' => string, 'role' => string]

UserController::logout(): void
// Destroys session, clears cookies, redirects
```

---

## Models

| File | Tabel | Key Methods |
|---|---|---|
| `User.php` | `MAHASISWA`, `DOSEN`, `ADMIN` | `findByUsername()`, `findById()` |
| `Pelanggaran.php` | `DETAIL_PELANGGARAN`, `PELANGGARAN` | `findByMahasiswa()`, `create()`, `update()` |
| `Tatib.php` | `TATA_TERTIB` | `all()`, `find()`, `create()`, `update()`, `delete()` |
| `News.php` | `BERITA` | `all()`, `find()`, `create()`, `update()`, `delete()` |
| `Sanksi.php` | `SANKSI` | `findByPelanggaran()` |

### Query Pattern

```php
// Prepared statement (WAJIB untuk semua input user)
$stmt = $this->connect->prepare("SELECT * FROM TABLE WHERE col = :val LIMIT 1");
$stmt->bindValue(':val', $value, PDO::PARAM_STR);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
```

---

## Helpers

| Helper | Fungsi Utama |
|---|---|
| `route_helper.php` | Registry route, dispatch, URL generation |
| `path_helper.php` | `app_path()`, `app_asset_url()`, `app_redirect()` |
| `token_helper.php` | `app_id_token()`, `app_id_resolve()`, CSRF helpers |
| `seo_helper.php` | Canonical, meta tags, JSON-LD, security headers |
| `flash_modal.php` | Flash state untuk feedback modal |
| `error_page_helper.php` | Render error pages (400, 403, 404, 500) |

### CSRF Helpers

```php
app_csrf_token()   // string — get or generate token
app_csrf_field()   // string HTML — hidden input field
app_verify_csrf()  // void — verify POST/JSON token, exit(419) if invalid
```

### Token ID Helpers

```php
// Enkripsi ID untuk mencegah IDOR
app_id_token('detail_pelanggaran', 42)
// → "eyJ..." (base64url encoded + HMAC)

// Dekripsi kembali ke integer
app_id_resolve("eyJ...", 'detail_pelanggaran')
// → 42 atau null
```

---

## Request Handlers

| Handler | Auth Required | Method |
|---|---|---|
| `handler-login.php` | Guest | CSRF verify |
| `handler-logout.php` | Login | Session destroy |
| `handler-pelanggaran.php` | Login + CSRF | CRUD, lookup, confirm, delete |
| `handler-notifikasi.php` | Login + CSRF | Mark read, mark all read |
| `handler-news.php` | Admin + CSRF | CRUD berita |
| `handler-tatib.php` | Admin + CSRF | CRUD tata tertib |
| `handler-upload.php` | Login + CSRF + Ownership | Upload surat/tugas |
| `handler-download.php` | Login | Serve file (with MIME check) |

---

## Session Structure

```php
$_SESSION['username']      // string — identifier user
$_SESSION['user_type']     // string — 'mahasiswa'|'dosen'|'admin'
$_SESSION['user_data']     // array  — data user sesuai role
$_SESSION['csrf_token']    // string — CSRF request token
```

Session cookie flags: `HttpOnly`, `SameSite=Lax`, `Secure` (HTTPS).

---

## Response Patterns

### JSON (AJAX)

```php
function respondJson(bool $success, string $message, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}
```

### Redirect

```php
app_redirect('views/page/tujuan.php');
// atau
app_redirect_page('page.slug');
```

### Flash Feedback

```php
set_app_flash_modal('success', 'Data berhasil disimpan.');
// → tersimpan di session, ditampilkan di modal saat page berikutnya render
```

---

## File Upload Path

```
storage/
├── keys/
│   └── app_token.key          ← generated on first run, gitignored
└── uploads/
    ├── [student NIM]_[type]_[random].pdf
    └── news/
        └── [random].jpg|png
```

---

## Database Schema (Key Tables)

### TATA_TERTIB

| Kolom | Tipe | Deskripsi |
|---|---|---|
| id_tata_tertib | INT PK | Auto increment |
| deskripsi | TEXT | Isi aturan |
| tingkat | ENUM('I','II','III') | Tingkat pelanggaran |
| poin | INT | Poin sanksi |

### DETAIL_PELANGGARAN

| Kolom | Tipe | Deskripsi |
|---|---|---|
| id_detail | INT PK | Auto increment |
| id_mahasiswa | INT FK | Mahasiswa terkait |
| id_tata_tertib | INT FK | Aturan yang dilanggar |
| id_dosen | INT FK | Dosen pelapor |
| surat | VARCHAR(255) | Path file surat pernyataan |
| pengumpulan_tgsKhusus | VARCHAR(255) | Path file tugas khusus |
| status | ENUM('proses','selesai') | Status kasus |

---

## Environment Variables

| Variable | Required | Default | Deskripsi |
|---|---|---|---|
| `APP_ENV` | Yes | `local` | `local` atau `production` |
| `APP_BASE_PATH` | No | `auto` | URL prefix path |
| `DB_DSN` | Yes | — | PDO DSN string |
| `DB_USER` | Yes | — | Database username |
| `DB_PASS` | Yes | — | Database password |
| `APP_CANONICAL_URL` | No | — | Canonical base URL |

---

## Error Codes

| HTTP Code | Usage |
|---|---|
| 200 | Success |
| 400 | Bad request / validation error |
| 401 | Not authenticated |
| 403 | Forbidden (role mismatch) |
| 404 | Resource not found |
| 419 | CSRF token invalid |
| 422 | Business logic error (e.g. file type rejected) |
| 500 | Internal server error |

---

## Minified Assets

JavaScript files yang punya pasangan `.min.js` adalah versi production (uglify). Jangan edit file `.min.js` secara langsung — edit sumber `.js`, lalu minify.

| Source | Minified |
|---|---|
| `js/homepage.js` | `js/homepage.min.js` |
| `js/login.js` | `js/login.min.js` |
| `js/pelaporan-form.js` | `js/pelaporan-form.min.js` |
| `js/notifikasi.js` | `js/notifikasi.min.js` |
| `js/pelanggaran-dashboard.js` | `js/pelanggaran-dashboard.min.js` |

---

## CLI Artisan Commands

```bash
php artisan list                    # daftar command
php artisan help [command]          # bantuan command
php artisan migrate                # jalankan migrasi
php artisan migrate:fresh          # drop semua tabel + migrate
php artisan migrate:fresh --seed   # + isi data contoh
php artisan db:seed                # seed saja
php artisan serve [--host] [--port] # start dev server
php artisan serve --hot            # hot-reload via BrowserSync
```

Untuk detail lengkap CLI artisan dan database migration, lihat [database.md](./database.md).
