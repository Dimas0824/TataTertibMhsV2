# HOWTO: Resep Task Umum Developer

Panduan problem-oriented untuk menyelesaikan tugas spesifik di DiscipLink V2.
Tidak perlu membaca semua docs — cari task, ikuti langkah, selesai.

---

## Cara Baca Dokumen Ini

Format setiap entry:

```
## [TASK]
Penjelasan singkat kapan perlu ini.

### Langkah
1. ...

### File yang Berubah
- `file-ubah-1.php`
- `file-ubah-2.php`
```

---

## Menambah Halaman/Rute Baru

### Langkah

1. **Daftarkan route** di `helpers/route_helper.php`:

```php
// Di blok $pageRoutes:
'page.nama_baru' => [
    'path' => '/nama-url',
    'file' => 'views/kategori/nama-file.php',
    'title' => 'Judul Halaman',
    'roles' => ['mahasiswa', 'dosen', 'admin'],
],
```

2. **Buat view** di `views/kategori/nama-file.php`:

```php
<?php
require_once __DIR__ . '/../helpers/route_helper.php';
app_require_auth();

$pageTitle = 'Judul Halaman';
render_app_header(['title' => $pageTitle]);
// ... konten ...
render_app_footer();
```

### File yang Berubah
- `helpers/route_helper.php`
- `views/kategori/nama-file.php` (new)

---

## Menambah Endpoint Action

### Langkah

1. **Daftarkan action** di `helpers/route_helper.php`:

```php
'action.nama_action' => [
    'path' => '/action/nama',
    'file' => 'request/handler-nama.php',
],
```

2. **Buat handler** di `request/handler-nama.php`:

```php
<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../helpers/route_helper.php';
require_once __DIR__ . '/../helpers/token_helper.php';
app_verify_csrf();

// auth guard
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    exit('Unauthorized');
}

try {
    // ... logic ...
    respondJson(true, 'Berhasil.');
} catch (Exception $e) {
    error_log($e->getMessage());
    respondJson(false, 'Terjadi kesalahan.', 500);
}
```

### File yang Berubah
- `helpers/route_helper.php`
- `request/handler-nama.php` (new)

---

## CSRF Protection

Semua action POST WAJIB punya CSRF token.

### Di view (form HTML)

```php
<form method="POST" action="<?= htmlspecialchars(app_action_url('action.target'), ENT_QUOTES, 'UTF-8') ?>">
    <?= app_csrf_field() ?>
    <!-- fields ... -->
</form>
```

### Di handler

```php
require_once __DIR__ . '/../helpers/token_helper.php';
app_verify_csrf(); // akan exit(419) jika token invalid
```

### Di AJAX (JSON body)

```php
// view: simpan token di data attribute
<section data-csrf-token="<?= htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

// JS: kirim sebagai field JSON
body: JSON.stringify({ ...data, csrf_token: csrfToken })
```

### File yang Berubah
- `views/.../form.php` (tambah CSRF field)
- `request/handler-xxx.php` (tambah `app_verify_csrf()`)

---

## Upload File

### Langkah

1. **Form** harus `enctype="multipart/form-data"` + CSRF field.
2. **Handler** menerima via `$_FILES`:

```php
$file = $_FILES['nama_field'] ?? null;
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    respondJson(false, 'Upload gagal.', 422);
}

// Validasi MIME via finfo (TIDAK percaya $_FILES['type'])
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
if (!in_array($detectedMime, $allowedMimes, true)) {
    respondJson(false, 'Tipe file tidak diizinkan.', 422);
}

// Generate random filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = bin2hex(random_bytes(12)) . '.' . strtolower($ext);
$targetPath = app_path('storage/uploads') . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    respondJson(false, 'Gagal menyimpan file.', 500);
}
```

### File yang Berubah
- `views/.../xxx.php`
- `request/handler-upload-xxx.php`

---

## Menambah Kolom Database

### Langkah

1. **Buat file migrasi** di `database/migrations/`:

```sql
-- 20260708_120000_add_field_to_tabel.sql
INSERT INTO schema_migrations (filename) VALUES ('20260708_120000_add_field_to_tabel.sql')
ON DUPLICATE KEY UPDATE executed_at = CURRENT_TIMESTAMP;

ALTER TABLE NAMA_TABEL
    ADD COLUMN nama_kolom VARCHAR(255) NULL AFTER kolom_existing;
```

2. **Jalankan migrasi:**

```bash
php artisan migrate --force
```

3. **Update model** di `models/NamaModel.php`:

```php
public function getNamaKolom(): string
{
    return (string) ($this->data['nama_kolom'] ?? '');
}
```

4. **Update form view** untuk menampilkan/menerima field baru.

### File yang Berubah
- `database/migrations/YYYYMMDD_HHMMSS_nama_migration.sql`
- `models/NamaModel.php`
- `views/.../form.php`

---

## Authorization: Batasan per Role

### Hanya admin

```php
require_once __DIR__ . '/../helpers/token_helper.php';
app_require_role('admin'); // exit(403) jika bukan Admin
```

### Minimal login

```php
app_require_login(); // exit(401) jika belum login
```

### Check role dinamis

```php
if (($_SESSION['user_type'] ?? '') !== 'dosen') {
    app_redirect('views/auth/login.php');
}
```

---

## Error Handling

### Generic user-facing message (Wajib)

```php
try {
    // logic...
} catch (Exception $e) {
    error_log('Context: ' . $e->getMessage()); // log untuk developer
    set_app_flash_modal('error', 'Terjadi kesalahan. Silakan coba lagi.');
    app_redirect('views/page/gagal.php');
}
```

### Jangan pernah

```php
// SALAH: exception message ke user
set_app_flash_modal('error', 'Error: ' . $e->getMessage());

// SALAH: echo/print exception
echo $e->getMessage();
```

---

## Debugging

### Cek route yang terdaftar

```php
// Tambahkan temporarily di router.php sebelum dispatch:
var_dump(array_keys($pageRoutes));
var_dump(array_keys($actionRoutes));
```

### Cek session saat ini

```php
// Di view atau handler:
var_dump($_SESSION);
```

### Cek query yang dijalankan

```php
// Di model, sebelum execute:
error_log($stmt->queryString);
```

---

## Deploy Checklist

1. `.env` terisi dengan credential production
2. `APP_ENV=production` di `.env`
3. `storage/uploads/` dan `storage/keys/` writable oleh web server
4. `.htaccess` aktif di direktori deploy
5. `mod_rewrite` aktif
6. Semua file permission: 644, folder: 755

---

## Checklist Keamanan (Wajib)

- [ ] Action baru punya `app_verify_csrf()`?
- [ ] Form baru punya `<?= app_csrf_field() ?>`?
- [ ] Upload baru pakai `finfo_file()` bukan `$_FILES['type']`?
- [ ] Error message generic, tidak bocorkan detail internal?
- [ ] Role/authorization dicek sebelum eksekusi logic?

---

## Dokumentasi Terkait

- [reference/api.md](../reference/api.md) — Detail API dan helper
- [reference/database.md](../reference/database.md) — CLI artisan dan migrasi
- [explanation/architecture.md](../explanation/architecture.md) — Penjelasan arsitektur
