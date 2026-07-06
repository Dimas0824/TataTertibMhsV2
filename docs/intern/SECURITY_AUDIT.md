# Security Audit — TataTertibMhsV2

Tanggal audit: 2026-07-07
Scope: PHP security syntax baseline dari `~/.agents/skills/php-security-syntax` berdasarkan PHP Manual Security (`php.net/manual/en/security.php`) + audit aplikasi PHP native di `D:\MiniProject\TataTertibMhsV2`.

## Ringkasan

Project ini bukan Laravel; ini aplikasi PHP native dengan PDO, custom router, custom encrypted route/id token, dan session PHP.

Temuan utama:

- **Critical:** endpoint upload file bisa dipanggil user login mana pun tanpa cek role/ownership detail pelanggaran.
- **High:** endpoint admin `handler-news.php` dan `handler-tatib.php` tidak punya authorization server-side admin.
- **High:** tidak ada CSRF protection untuk POST action penting.
- **Medium:** session ID tidak diregenerasi setelah login.
- **Medium:** validasi upload gambar berita masih percaya `$_FILES['type']`.
- **Medium:** file test artifacts dan `node_modules` terlanjur tracked di git.
- **Low/Medium:** beberapa error message exception dikembalikan ke user.

## Audit baseline PHP Manual Security

Checklist yang dipakai:

- User Submitted Data: semua `$_GET`, `$_POST`, `$_FILES`, `php://input` diperlakukan sebagai untrusted.
- Filesystem Security: cek upload, download, path traversal, null byte, `readfile()`, `unlink()`.
- Database Security / SQL Injection: cek prepared statements dan dynamic SQL.
- Session Security: cek login, logout, session regeneration, cookie flags.
- Error Reporting: cek pesan internal/exception yang keluar ke user.
- Keeping Current / repository hygiene: cek artifact/dependency yang tidak seharusnya masuk git.

## Temuan Detail

### [critical] `request/handler-upload.php:39-128` — upload tidak cek role dan ownership detail pelanggaran

Endpoint upload hanya cek method POST dan `id_detail`, lalu update `DETAIL_PELANGGARAN` berdasarkan id tersebut.

Bukti:

```php
$idDetail = app_id_resolve((string) ($_POST['id_detail'] ?? ''), 'detail_pelanggaran');
...
SELECT dp.id_detail, dp.surat, dp.pengumpulan_tgsKhusus, ...
WHERE dp.id_detail = :idDetail
...
UPDATE DETAIL_PELANGGARAN SET $updateColumn = :filePath WHERE id_detail = :idDetail
```

Masalah:

- Tidak ada `isset($_SESSION['username'])`.
- Tidak ada cek `$_SESSION['user_type'] === 'mahasiswa'` atau role lain yang valid.
- Tidak ada validasi bahwa detail pelanggaran tersebut milik mahasiswa yang login atau dosen penanggung jawab.
- Token `id_detail` memang terenkripsi dan session-bound, tapi token bisa sah untuk user yang melihat halaman tertentu; server tetap harus cek ownership pada action.

Dampak:

- User login yang mendapat/memiliki token detail bisa mengupload file ke record pelanggaran yang tidak seharusnya.
- Potensi IDOR/action authorization bypass.

Fix minimal:

- Wajibkan session login.
- Tentukan rule: mahasiswa hanya boleh upload untuk `DETAIL_PELANGGARAN` miliknya; dosen hanya bila pelapor/penanggung jawab.
- Tambahkan kondisi ownership di query awal, bukan hanya setelah fetch.

Contoh arah query:

```sql
WHERE dp.id_detail = :idDetail
  AND (
    (:role = 'mahasiswa' AND m.nim = :nim)
    OR (:role = 'dosen' AND (pelapor.nidn = :nidn OR penanggung.nidn = :nidn))
  )
```

Source: PHP Manual Security → User Submitted Data, Filesystem Security, Database Security.

---

### [high] `request/handler-news.php:14-130` — endpoint admin news tidak cek session admin

Endpoint create/update/delete news langsung memproses POST tanpa memastikan user login sebagai admin.

Bukti:

```php
if (isset($_POST['store'])) { ... }
elseif (isset($_POST['update'])) { ... }
elseif (isset($_POST['delete'])) { ... }
```

Tidak ada:

```php
isset($_SESSION['username'])
$_SESSION['user_type'] === 'admin'
```

Dampak:

- Jika route `/action/news` bisa dicapai, user non-admin atau request tanpa session valid berpotensi melakukan modifikasi berita, bergantung pada token/field yang tersedia.

Fix minimal:

```php
if (!isset($_SESSION['username']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}
```

Source: PHP Manual Security → User Submitted Data, General considerations.

---

### [high] `request/handler-tatib.php:14-49` — endpoint admin tata tertib tidak cek session admin

Endpoint store/update/delete tata tertib tidak melakukan authorization server-side.

Bukti:

```php
if (isset($_POST['store']) && isset($_POST['admin']) && ...)
```

Tidak ada cek login/role sebelum action.

Dampak:

- User non-admin berpotensi membuat/mengubah/menghapus tata tertib jika bisa mengirim POST yang valid.

Fix minimal:

```php
if (!isset($_SESSION['username']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    set_app_flash_modal('error', 'Forbidden.');
    app_redirect('views/auth/login.php');
}
```

Source: PHP Manual Security → User Submitted Data, General considerations.

---

### [high] Global POST actions — belum ada CSRF protection

Tidak ditemukan mekanisme CSRF token untuk form POST penting. Search terhadap `csrf`, `nonce`, dan token form tidak menunjukkan CSRF khusus; token yang ada adalah encrypted route/id token, bukan anti-CSRF request token.

Endpoint terdampak:

- `request/handler-login.php`
- `request/handler-news.php`
- `request/handler-tatib.php`
- `request/handler-pelanggaran.php`
- `request/handler-upload.php`
- `request/handler-notifikasi.php`
- `request/handler-logout.php`

Dampak:

- Browser user yang sedang login bisa dipaksa mengirim POST dari situs lain.
- Action state-changing seperti delete/update/upload rawan CSRF.

Fix minimal:

- Generate CSRF token per session:

```php
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
```

- Tambahkan hidden input pada setiap form POST:

```php
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
```

- Validasi server-side:

```php
if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(419);
    exit('Invalid CSRF token');
}
```

Source: PHP Manual Security → User Submitted Data, Session Security.

---

### [medium] `controllers/UserController.php:55-63` — session ID tidak diregenerasi setelah login

Bukti:

```php
if ($user) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['username'] = $username;
    $_SESSION['user_type'] = $role;
    $_SESSION['user_data'] = $user;
```

Tidak ada `session_regenerate_id(true)`.

Dampak:

- Session fixation risk: session ID lama tetap dipakai setelah privilege berubah menjadi authenticated.

Fix minimal:

```php
session_regenerate_id(true);
```

Letakkan setelah `session_start()` dan sebelum set data login.

Source: PHP Manual Security → Session Security.

---

### [medium] `controllers/NewsController.php:129-132` dan `request/handler-news.php:87-90` — upload gambar percaya client MIME

Bukti:

```php
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
if (!in_array((string) ($gambar['type'] ?? ''), $allowedTypes, true)) {
```

`$_FILES['type']` dikirim oleh client dan tidak bisa dipercaya.

Dampak:

- File non-image bisa menyamar sebagai image jika server/webserver salah konfigurasi.

Fix minimal:

- Gunakan `finfo_file(FILEINFO_MIME_TYPE)`.
- Batasi extension (`jpg`, `jpeg`, `png`).
- Generate filename random, bukan mempertahankan nama asli.
- Simpan di folder non-executable atau pastikan webserver tidak execute upload.

Source: PHP Manual Security → File uploads, Filesystem Security.

---

### [medium] Git hygiene — `tests/e2e/node_modules`, Playwright report, dan test results tracked

Bukti command:

```text
git ls-files | grep -E '(^\.env$|playwright-report|test-results|\.log$|\.sql$|uploads|vendor|node_modules)'
```

Output menunjukkan banyak file tracked:

```text
tests/e2e/node_modules/...
tests/e2e/test-results/...
tests/playwright-report/...
tests/test-results/results.json
```

`.gitignore` sudah benar mengabaikan:

```gitignore
node_modules/
tests/e2e/node_modules/
tests/e2e/test-results/
tests/e2e/playwright-report/
tests/test-results/
tests/playwright-report/
.env
```

Masalahnya file-file ini sudah telanjur tracked, sehingga `.gitignore` tidak berlaku sampai di-`git rm --cached`.

Dampak:

- Repo membesar.
- Artifact test bisa mengandung screenshot/video/data sensitif.
- Supply-chain noise dari committed dependency tree.

Fix minimal:

```bash
git rm -r --cached tests/e2e/node_modules tests/e2e/test-results tests/playwright-report tests/test-results
```

Lalu commit perubahan. Jangan push otomatis.

Source: PHP Manual Security → Keeping Current, General considerations.

---

### [low/medium] Error message internal masih dikembalikan ke user di beberapa path

Contoh:

- `controllers/UserController.php:70` → `echo "Error: " . $e->getMessage();`
- `models/News.php:44` dan `models/News.php:61` → echo pesan PDO exception.
- `request/handler-news.php:127` → flash `Error: ` + exception message.
- `request/handler-pelanggaran.php:258` → flash exception message.

Dampak:

- Bisa membocorkan detail internal, query, path, atau struktur aplikasi jika exception berasal dari DB/runtime.

Fix minimal:

- `error_log($e->getMessage())` untuk log.
- User-facing message generic: `Terjadi kesalahan. Silakan coba lagi.`

Source: PHP Manual Security → Error Reporting.

---

### [positive] SQL Injection posture cukup baik

Mayoritas query memakai prepared statements dan bind parameter.

Contoh aman:

```php
$stmt = $this->connect->prepare("SELECT * FROM v_PelanggaranMahasiswa WHERE nim = ?");
$stmt->bindParam(1, $nim, PDO::PARAM_STR);
```

Dynamic SQL yang ditemukan:

- `models/User.php:33` table/column interpolated, tapi input berasal dari hardcoded method internal (`MAHASISWA/nim`, `DOSEN/nidn`, `ADMIN/NIP`), bukan request langsung.
- `request/handler-upload.php:128` column interpolated dari `$fileType`, yang dibatasi hanya `suratPernyataan`/`tugasKhusus` dan mapping internal.
- `models/News.php:91` limit interpolated dari clamped integer `max(1, min(16, (int) $limit))`.

Status: tidak saya tandai sebagai vulnerability langsung, tapi tetap jaga allowlist.

---

### [positive] Download path traversal cukup baik

`request/handler-download.php` memakai `basename()`, allowlist extension, candidate path lokal, dan `readfile()` hanya setelah `is_file()`.

Catatan tambahan:

- Masih perlu authorization ownership file, bukan hanya `isset($_SESSION['username'])`.
- Saat ini semua user login bisa download file valid jika tahu nama file.

Severity: medium jika file upload berisi dokumen sensitif mahasiswa.

Fix minimal:

- Cari `DETAIL_PELANGGARAN` berdasarkan filename lalu cek ownership/role sebelum `readfile()`.

Source: PHP Manual Security → Filesystem Security, User Submitted Data.

## Verifikasi yang sudah dijalankan

Syntax check pada file upload/download/news handler:

```text
No syntax errors detected in request/handler-news.php
No syntax errors detected in request/handler-upload.php
No syntax errors detected in request/handler-download.php
```

Project/file inspeksi:

```text
PROJECT_EXISTS=1
PHP=PHP 8.5.6 (cli)
```

## Prioritas Fix

1. Tambah authorization server-side untuk `handler-upload.php`.
2. Tambah authorization admin untuk `handler-news.php` dan `handler-tatib.php`.
3. Tambah CSRF token helper dan validasi semua POST state-changing.
4. Regenerate session ID setelah login.
5. Ganti validasi upload gambar berita ke `finfo_file()` + random filename.
6. Untrack `node_modules`, Playwright report, dan test results dari git.
7. Ubah pesan exception internal menjadi generic user-facing message.
