# Arsitektur & Keputusan Desain

Dokumen ini menjelaskan mengapa DiscipLink V2 dirancang seperti ini — keputusan arsitektur, trade-off, dan filosofi di balik pilihan teknis. Bukan tutorial atau referensi; ini untuk memahami "kenapa" di balik sistem.

---

## Kenapa PHP Native Tanpa Framework?

### Pertimbangan Awal

Project ini berevolusi dari codebase PHP legacy yang sudah ada. Menggunakan framework seperti Laravel akan memerlukan rewrite total, yang tidak feasible untuk project yang sudah berjalan.

### Keputusan: PHP Native + MVC Pattern

Kami memilih PHP native dengan pattern MVC terstruktur karena:

- **Minimal dependencies** — hanya butuh PHP 8.1+ dan MySQL. Tidak ada Composer autoload, tidak ada framework overhead.
- **Full control** — setiap baris kode bisa ditrace tanpa membaca dokumentasi framework.
- **Lightweight deployment** — bisa jalan di shared hosting minimal.
- **Learning curve rendah** — developer baru cukup tahu PHP standar, tidak perlu belajar idioms framework.

### Trade-off yang Diterima

- **Boilerplate lebih banyak** — autentikasi, routing, dan validasi ditulis manual.
- **Tidak ada built-in protection** — CSRF, SQL injection prevention harus implemented sendiri.
- **Maintenance lebih manual** — tidak ada framework upgrade path, tapi juga tidak ada breaking changes dari framework.

---

## Arsitektur Request Lifecycle

### Central Router Pattern

```
Browser Request
      │
      ▼
router.php (Single Entry Point)
      │
      ├─► Page Route ──► views/{kategori}/{file}.php
      │
      └─► Action Route ──► request/handler-{nama}.php
                               │
                               ├─► Auth Guard
                               ├─► CSRF Verify
                               ├─► Controller::method()
                               ├─► Model::query()
                               └─► respondJson() / app_redirect()
```

### Kenapa Single Entry Point?

Semua request masuk lewat `index.php` → `router.php` agar:

1. **Konsistensi security** — auth dan security headers diterapkan di satu tempat.
2. **URL abstraction** — route mapping terpusat di `route_helper.php`.
3. **Easier middleware** — auth guard, CSRF verify, dan error handling konsisten.

### Route Registry: Kenapa Array-Based?

Kami pakai array PHP sebagai registry route, bukan regex pattern matching:

```php
'page.slug' => [
    'path' => '/url-path',
    'file' => 'views/kategori/file.php',
    'title' => 'Judul Halaman',
    'roles' => ['mahasiswa', 'dosen', 'admin'],
],
```

**Keuntungan:**
- Declarative, mudah dibaca dan di-audit.
- Tidak ada regex complexity.
- Role-based access control langsung di registry.

**Kekurangan:**
- Tidak mendukung wildcard/parameterized routes secara natural.
- Semua parameter harus di-encode sebagai encrypted token ID.

---

## Encrypted Token ID: Solusi untuk IDOR

### Masalah

Meng暴露 numerik ID di URL/browser memungkinkan IDOR (Insecure Direct Object Reference):

```
GET /action/pelanggaran?detail=42
```

User bisa mengganti `42` ke `43` dan mengakses record orang lain.

### Solusi: HMAC-Signed Token

Setiap ID yang dikirim ke browser dienkripsi:

```php
app_id_token('detail_pelanggaran', 42)
// → "eyJ..." (base64url encoded + HMAC signature)
```

Token berisi:
- Tabel origin (`detail_pelanggaran`)
- ID numerik (`42`)
- Signature dengan secret key (`storage/keys/app_token.key`)

**Security property:**
- User tidak bisa menebak ID record lain.
- Token tidak bisa di-forge tanpa secret key.
- Server bisa verifikasi token valid dan berasal dari tabel yang diharapkan.

### Kapan Pakai Token ID?

- Semua ID record yang muncul di URL, form, atau JSON body.
- Tidak untuk ID yang tidak pernah exposed ke client (foreign key internal).

---

## Session-Based Authentication

### Kenapa Bukan JWT?

Project ini target audience adalah user di kampus — aplikasi diakses dari browser, bukan API mobile. JWT menawarkan stateless, tapi:

- JWT yang di-stored di localStorage rentan XSS.
- JWT yang di-stored di HttpOnly cookie memiliki trade-off serupa dengan session.
- PHP native session sudah handle semua ini dengan baik.

### Session Structure

```php
$_SESSION['username']      // string — identifier user
$_SESSION['user_type']     // string — 'mahasiswa'|'dosen'|'admin'
$_SESSION['user_data']     // array  — data user sesuai role
$_SESSION['csrf_token']    // string — CSRF request token
```

Data user di-`user_data` di-fetch per-request dari database, tidak di-cache lama di session.

### Session Security

| Pengaturan | Alasan |
|---|---|
| `session_regenerate_id(true)` setelah login | Mencegah session fixation |
| `HttpOnly` cookie | Mencegah JavaScript access (XSS) |
| `SameSite=Lax` | CSRF mitigation tanpa breaking navigation |
| `Secure` (HTTPS only) | Cookie tidak dikirim over HTTP |

---

## CSRF Protection: Kenapa Manual?

### Keputusan Desain

Kami mengimplement CSRF protection secara manual, bukan pakai library, karena:

1. **Transparansi penuh** — developer harus memahami setiap protection.
2. **Minimal footprint** — tidak ada dependency untuk security critical code.
3. **Control penuh** — bisa customize behavior tanpa reverse-engineering library.

### Token Lifecycle

```
1. Session Start: Generate token
   $_SESSION['csrf_token'] = bin2hex(random_bytes(32))

2. Form Render: Include hidden field
   <?= app_csrf_field() ?>
   → <input type="hidden" name="csrf_token" value="abc123...">

3. POST Request: Verify token
   app_verify_csrf()
   → if (!hash_equals($_SESSION['csrf_token'], $input)) exit(419)

4. Success Response: Token tetap sama (one token per session)
```

**Catatan:** Token tidak di-regenerate per request untuk menghindari race condition saat user membuka multiple tabs.

---

## File Upload Security

### Three-Layer Validation

**Layer 1: MIME Detection (Server-side)**
```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
```
Client MIME (`$_FILES['type']`) TIDAK dipercaya — bisa di-spoof.

**Layer 2: Extension Allowlist**
```php
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
```
Ekstension dicek setelah MIME detection.

**Layer 3: Random Filename**
```php
$filename = bin2hex(random_bytes(12)) . '.' . $extension;
```
Filename asli tidak dipakai — mencegah path traversal dan filename collision.

### Storage Outside Web Root

Upload disimpan di `storage/uploads/` (outside `public/`). File hanya bisa diakses melalui `handler-download.php` yang menerapkan:
- Ownership check
- MIME verification
- Authorization guard

---

## Authorization Model

### Role-Based Access Control (RBAC)

Tiga role dengan akses berbeda:

| Kapabilitas | Mahasiswa | Dosen | Admin |
|---|---|---|---|
| Lihat pelanggaran sendiri | Ya | - | Ya |
| Buat pelaporan | Tidak | Ya | Ya |
| Upload dokumen pelanggaran | Ya | Ya | Tidak |
| Konfirmasi penyelesaian | Tidak | Ya | Ya |
| CRUD tata tertib | Tidak | Tidak | Ya |
| CRUD berita | Tidak | Tidak | Ya |

### Authorization di Action Handler

Setiap action handler memeriksa authorization di awal:

```php
// Minimal: harus login
app_require_login();

// Spesifik: harus role tertentu
app_require_role('admin');

// Ownership: harus punya akses ke record
$record = Model::findWithOwnership($id, $_SESSION);
if (!$record) { /* 403 */ }
```

### Kenapa Authorization di Handler, Bukan Middleware?

PHP native tanpa framework = tidak ada middleware pipeline. Kami pilih check di handler untuk visibility langsung dan simplicity.

Trade-off: setiap handler harus copy-paste authorization check. Ini accepted karena:
- Authorization logic sederhana (3 role + optional ownership).
- Easy to audit — semua logic ada di satu file.

---

## Error Handling Strategy

### Two Environment Modes

**.env: `APP_ENV=local`**
- Errors ditampilkan di browser (development)
- Stack trace tersedia

**.env: `APP_ENV=production`**
- Errors logged ke server log
- User melihat generic message

### Generic User Messages

```php
// BENAR
set_app_flash_modal('error', 'Terjadi kesalahan. Silakan coba lagi.');

// SALAH — bocorkan detail internal
set_app_flash_modal('error', 'Error: ' . $e->getMessage());
```

**Prinsip:** User tidak pernah melihat:
- SQL query atau database error
- File path atau struktur aplikasi
- Exception stack trace
- Variable values atau session state

Semua detail di-log ke `error_log` untuk developer debugging.

---

## Database Design Philosophy

### Single Connection Pool

Aplikasi menggunakan single PDO connection (`$connect`) yang di-include di setiap file yang butuh database. Tidak ada connection pooling atau persistent connection.

**Alasan:**
- Sederhana, predictable.
- Cocok untuk aplikasi dengan user count terbatas (kampus).
- Tidak perlu manage connection lifecycle.

### Prepared Statements Mandatory

Tidak ada query langsung dengan string interpolation:

```php
// WAJIB
$stmt = $pdo->prepare("SELECT * FROM table WHERE col = :val");
$stmt->bindValue(':val', $value, PDO::PARAM_STR);

// DILARANG
$stmt = $pdo->query("SELECT * FROM table WHERE col = '$value'");
```

Ini bukan cuma soal SQL injection — ini adalah Kultur: semua query harus traceable dan reviewable.

---

## Why Not Modern Stack?

Pertanyaan yang sering muncul: "Kenapa tidak pakai Laravel/React/Vue?"

### Jawaban Singkat

Kebutuhan project tidak memerlukan complexity tersebut. Setiap technology choice punya cost:

| Technology | Cost |
|---|---|
| Laravel | PHP 8.2+, Composer, deployment complexity, learning curve |
| React SPA | JavaScript bundler, API layer, state management, CORS |
| Vue SPA | Sama dengan React |
| PostgreSQL | Hosting complexity, migration effort |

DiscipLink V2 adalah internal campus tool dengan:
- ~500 concurrent users max
- Simple CRUD operations
- No real-time requirements
- Team kecil dengan limited resources

Pilihan PHP native + vanilla JS adalah sweet spot: cukup powerful untuk requirements, cukup simple untuk maintenance.

---

## Future Considerations

### Yang Mungkin Berubah

1. **API Layer** — Jika ada mobile app atau integrasi pihak ketiga, REST/GraphQL API layer bisa ditambahkan.
2. **Caching** — Untuk query yang frequent, Redis bisa ditambahkan sebagai cache layer.
3. **Real-time notification** — WebSocket atau Server-Sent Events jika polling tidak cukup.

### Yang Kemungkinan Tidak Berubah

1. **PHP native** — Tidak ada plan migrasi ke framework.
2. **Vanilla JS** — SPA migration tidak di-scheduled.
3. **MySQL** — Tidak ada plan migrasi ke PostgreSQL atau NoSQL.

---

## Dokumentasi Terkait

- [reference/api.md](../reference/api.md) — Detail teknis API dan file
- [howto/recipes.md](../howto/recipes.md) — Recipe untuk task spesifik
- [tutorial/getting-started.md](../tutorial/getting-started.md) — Panduan setup untuk newcomer
- [explanation/security.md](./security.md) — Kebijakan keamanan
