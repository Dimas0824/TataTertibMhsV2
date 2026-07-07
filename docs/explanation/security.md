# Keamanan di DiscipLink V2

Dokumen ini menjelaskan filosofi dan keputusan keamanan di DiscipLink V2. Untuk detail teknis implementasi, lihat referensi terkait di code.

---

## Filosofi Keamanan

Kami menganut tiga prinsip utama:

### 1. Defense in Depth

Tidak ada satu layer keamanan yang 100% aman. Maka setiap sistem memiliki multiple layer protection:

- Input validation di form (client-side)
- CSRF token verification (server-side)
- Authorization check per handler
- SQL injection prevention via prepared statements
- Session security flags

Jika satu layer gagal, layer lain tetap melindungi.

### 2. Least Privilege

Setiap component hanya memiliki akses yang diperlukan:

- Action handler hanya bisa akses data yang memang dibutuhkan
- User hanya bisa modify record miliknya (atau yang terkait)
- Admin-only endpoints diproteksi dengan role guard
- File upload tidak executable langsung

### 3. Secure by Default

Konfigurasi production berbeda dari development:

- Error details tidak ditampilkan ke user
- Session cookies hanya aktif via HTTPS
- File uploads tersimpan outside web root

---

## Ancaman yang Dicegah

### SQL Injection

**Ancamannya:** Attacker menyisipkan SQL query melalui input form.

**Contoh serangan:**
```
Username: ' OR '1'='1' --
```

**Perlindungan:** Semua query database menggunakan prepared statements dengan bind parameter. Tidak ada string interpolation langsung ke query.

```php
// AMAN: parameterized query
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = :user");
$stmt->bindValue(':user', $username, PDO::PARAM_STR);

// RAWAN: string interpolation
$stmt = $pdo->query("SELECT * FROM users WHERE username = '$username'");
```

Kami tidak menggunakan ORM atau query builder — setiap query ditulis manual. Ini berarti setiap query harus di-review secara manual untuk memastikan keamanan.

### Cross-Site Request Forgery (CSRF)

**Ancamannya:** User yang sudah login bisa dipaksa mengirim request dari situs malicious tanpa disadari.

**Contoh serangan:**
```html
<!-- Situs malicious -->
<img src="https://disciplink.id/action/delete?id=42">
```

Jika user sedang login, browser akan mengirim cookie session dan request akan dieksekusi.

**Perlindungan:** Setiap form POST memiliki CSRF token. Token dicek di server sebelum action dieksekusi.

```php
// Form: token di-generate per session
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

// Handler: verify sebelum action
app_verify_csrf(); // exit(419) jika invalid
```

**Kapan CSRF protection aktif?**
- Semua form POST
- Semua AJAX request yang mengubah state
- Tidak untuk GET request (tidak seharusnya mengubah state)

### Insecure Direct Object Reference (IDOR)

**Ancamannya:** User mengakses record orang lain dengan mengganti ID di URL.

**Contoh serangan:**
```
GET /action/pelanggaran?detail=42  (record sendiri)
GET /action/pelanggaran?detail=43  (record orang lain!)
```

**Perlindungan:** ID record tidak dikirim sebagai angka plain. Setiap ID di-enkripsi dengan HMAC signature sebelum dikirim ke browser.

```php
// View: kirim encrypted token
app_id_token('detail_pelanggaran', 42)
// → "eyJhbGciOiJ..."

// Handler: dekripsi dan verify
$id = app_id_resolve($_POST['id_detail'], 'detail_pelanggaran');
// → 42 atau null jika invalid
```

Token tidak bisa di-forge tanpa secret key di server.

### Session Hijacking & Fixation

**Ancamannya:**
- **Hijacking:** Attacker mencuri session cookie user
- **Fixation:** Attacker menetapkan session ID sebelum user login

**Perlindungan kami:**

| Serangan | Perlindungan |
|----------|--------------|
| Cookie theft via XSS | `HttpOnly` flag — JavaScript tidak bisa baca cookie |
| CSRF via cross-site | `SameSite=Lax` — cookie tidak dikirim dari domain lain |
| Session fixation | `session_regenerate_id(true)` setelah login sukses |
| Eavesdropping | `Secure` flag — cookie hanya dikirim via HTTPS |

```php
// Setelah login sukses — regenerate session ID
session_regenerate_id(true);
$_SESSION['username'] = $username;
```

### File Upload Malicious

**Ancamannya:** User mengupload file executable (PHP script, shell) yang bisa dijalankan server.

**Perlindungan berlapis:**

1. **Server-side MIME detection** — bukan percaya `$_FILES['type']` yang bisa di-spoof client
2. **Extension allowlist** — hanya `.pdf`, `.jpg`, `.png`
3. **Random filename** — filename asli tidak dipakai
4. **Storage outside web root** — file tidak bisa diakses langsung via URL
5. **Download via handler** — file hanya served setelah authorization check

```php
// MIME detection via server
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedMime = finfo_file($finfo, $file['tmp_name']);

// Extension check
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, ['pdf', 'jpg', 'png'])) { reject(); }

// Random filename
$filename = bin2hex(random_bytes(12)) . '.' . $extension;
```

### Information Disclosure

**Ancamannya:** Error message atau stack trace menampilkan detail internal aplikasi.

**Contoh buruk:**
```
Database Error: SELECT * FROM users WHERE id = 'x'
Table 'disciplink.users' doesn't exist
```

**Perlindungan:** Di production, user hanya melihat generic message. Detail error di-log ke server.

```php
// Production (.env: APP_ENV=production)
// User melihat:
"Terjadi kesalahan. Silakan coba lagi."

// Developer log:
error_log($e->getMessage()); // "PDOException: SQLSTATE[42S02]..."
```

---

## Yang Tidak Dilindungi (Known Limitations)

### Download File Authorization

Saat ini, file download (`handler-download.php`) hanya mengecek user sudah login, bukan ownership. User login bisa download file jika tahu namafilenya.

**Severity:** Medium — file upload berisi dokumen sensitif mahasiswa.

**Jika ini concern untuk deployment:** Tambahkan ownership check di handler download.

### Multiple Tab Session

CSRF token di-generate per session, bukan per request. Ini berarti:
- User bisa buka multiple tabs dengan satu token
- Jika satu tab invalidasi token, tabs lain tetap jalan

Ini acceptable untuk use case kampus dengan user single-browser.

### No Rate Limiting

Tidak ada rate limiting pada endpoint login atau action. Serangan brute-force pada login dimitigasi oleh:
- Cost factor bcrypt di password hashing
- Max login attempts dari database constraint

Untuk deployment dengan exposure tinggi, tambahkan rate limiting middleware.

---

## Responsibility Matrix

| Siapa | Tanggung Jawab |
|-------|----------------|
| **Developer** | Implementasi security yang benar di setiap handler |
| **Admin** | Tidak share credentials, monitor anomalous activity |
| **User** | Password kuat, logout setelah selesai, jangan share akun |

---

## Jika Menemukan Vulnerability

1. **Jangan disclose secara publik** sebelum fix tersedia
2. **Buat issue** dengan label `security` (issue akan di-private)
3. **Detail yang membantu:** steps to reproduce, impact assessment

---

## Dokumentasi Terkait

- [architecture.md](./architecture.md) — Keputusan arsitektur yang mendasari keamanan
- [howto/recipes.md](../howto/recipes.md) — Checklist keamanan saat menambah fitur baru
