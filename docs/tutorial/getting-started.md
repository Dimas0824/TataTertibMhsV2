# Tutorial: Setup & Memulai Pengembangan

Panduan ini membawa kamu dari nol sampai bisa menjalankan dan memahami alur dasar aplikasi.

**Target:** developer baru yang ingin memahami atau mengembangkan DiscipLink V2.
**Prasyarat:** PHP 8.1+, MySQL/MariaDB, Git.

---

## Apa yang Akan Kamu Pelajari

- [ ] Menjalankan aplikasi dari awal
- [ ] Memahami alur request dari browser sampai database
- [ ] Menambah satu field di form sampai tersimpan di DB

---

## Langkah 1: Clone & Install

```bash
git clone https://github.com/Dimas0824/TataTertibMhsV2.git
cd TataTertibMhsV2
```

Pastikan PHP dan MySQL tersedia:

```bash
php --version    # minimal 8.1
mysql --version
```

---

## Langkah 2: Setup Database

### Buat database

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS DiscipLink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Buat file environment

```bash
cp .env.example .env
```

Edit `.env`:

```dotenv
DB_DSN="mysql:host=127.0.0.1;port=3306;dbname=DiscipLink;charset=utf8mb4"
DB_USER="root"
DB_PASS="your_password_here"
```

> **Mengapa `.env`?** Koneksi database disimpan di `.env`, bukan hardcoded. Ini agar credential tidak masuk repository saat dipush.

### Generate app key (token)

Token untuk enkripsi route-ID dibuat otomatis saat pertama kali aplikasi diakses. Pastikan folder writable:

```bash
chmod 755 storage/keys
```

---

## Langkah 3: Jalankan Migrasi

```bash
php artisan migrate --force
```

Untuk mengisi data contoh:

```bash
php artisan migrate:fresh --seed --force
```

Ini membuat semua tabel dan mengisi data role, mahasiswa, dosen, dan admin contoh.

---

## Langkah 4: Jalankan Server

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Buka [http://127.0.0.1:8000](http://127.0.0.1:8000) di browser.

---

## Langkah 5: Login & Eksplorasi

Gunakan akun contoh:

| Role | Username | Password |
|------|----------|---------|
| Mahasiswa | `2341238901` | `password123` |
| Dosen | `1234567890` | `password123` |
| Admin | `ADMIN001` | `admin123` |

Coba alur ini:
1. Login sebagai **Admin** — buat satu berita baru
2. Login sebagai **Dosen** — buat satu pelaporan pelanggaran
3. Login sebagai **Mahasiswa** — cek notifikasi dan upload dokumen

---

## Memahami Alur Request

Setiap request di DiscipLink melewati alur ini:

```
Browser
  └─► router.php          (central dispatcher)
        ├─► Page Route    → langsung render views/xxx.php
        └─► Action Route → request/handler-xxx.php
                            ├─► Controller           (business logic)
                            ├─► Model               (query DB via PDO)
                            └─► Response            (redirect HTML atau JSON)
```

**Contoh konkret — login:**
1. Form POST ke `/action/login`
2. `request/handler-login.php` menangkap
3. `UserController::login()` cek kredensial via `User::findByUsername()`
4. Session diset → redirect ke `/pelanggaran`

**Contoh konkret — upload surat:**
1. AJAX POST ke `/action/upload` dengan `FormData`
2. `request/handler-upload.php` menangkap
3. Validasi MIME via `finfo_file()`
4. `move_uploaded_file()` ke `storage/uploads/`
5. Update kolom `surat` di tabel `DETAIL_PELANGGARAN`

---

## Langkah 6: Ubah Sesuatu

Sebagai latihan, ubah teks "DiscipLink" di `helpers/seo_helper.php`:

```php
// Cari baris ini:
'app_name' => 'DiscipLink V2',

// Ganti jadi:
'app_name' => 'Sistem Tata Tertibku',
```

Refresh browser — title halaman berubah.

---

## Struktur Folder Singkat

```
controllers/     ← business logic per use-case
models/         ← query database
request/        ← HTTP action entrypoint
views/          ← UI templates
helpers/        ← routing, token, path, SEO
database/
  migrations/   ← definisi schema
  seeders/      ← data contoh
```

Penjelasan detail ada di [reference/api.md](../reference/api.md).

---

## Dokumentasi Terkait

- [reference/api.md](../reference/api.md) — Detail API dan file
- [explanation/architecture.md](../explanation/architecture.md) — Penjelasan keputusan arsitektur
- [explanation/security.md](../explanation/security.md) — Kebijakan keamanan

---

## Checklist Pemahaman

Setelah menyelesaikan tutorial ini, pastikan kamu bisa menjawab:

- [ ] Di file mana login ditangani?
- [ ] Di mana query "cari pelanggaran berdasarkan NIM" berada?
- [ ] Bagaimana cara menambahkan field baru di form pelaporan?
- [ ] Kapan harus menambah route baru di `route_helper.php`?
