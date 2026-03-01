# Database CLI Guide

Project ini menyediakan command mirip artisan untuk migrasi dan seed database.

## Persiapan Koneksi

Pilih salah satu:

1. Buat `.env` dari `.env.example` lalu isi `DB_DSN`, `DB_USER`, `DB_PASS`.
2. Atau tetap gunakan `config.php` existing yang berisi global `$connect` (PDO).
3. `migrate:fresh` saat ini didukung untuk driver `mysql` dan `sqlsrv`.

## Command

- `php artisan list`
- `php artisan help`
- `php artisan migrate`
- `php artisan migrate --seed`
- `php artisan migrate:fresh`
- `php artisan migrate:fresh --seed --force`
- `php artisan migrate --path=database/migrations`
- `php artisan db:seed`
- `php artisan db:seed --file=20260225_000001_data_dummy.sql`
- `php artisan db:seed --path=database/seeders --force`
- `php artisan serve`
- `php artisan serve --host=127.0.0.1 --port=8000`
- `php artisan serve --hot` (proxy hot-reload di port `8001` default)

## Menjalankan Project

- Start biasa: `php artisan serve`
- Start hot-reload: `php artisan serve --hot`
- Opsi hot-reload membutuhkan Node.js (`npx`) karena menggunakan BrowserSync.

## Aturan Migrasi & Seed

- Folder migrasi: `database/migrations`
- Folder seed: `database/seeders`
- Format nama file wajib: `YYYYMMDD_HHMMSS_name.sql`
- File yang sudah dijalankan dicatat di:
  - `schema_migrations`
  - `schema_seeds`
- Jika checksum file berubah setelah pernah dijalankan, command akan gagal (drift detection).

### Keamanan Password Seeder

- Saat `php artisan db:seed` dijalankan, nilai kolom `password` pada statement `INSERT ... VALUES ...` akan otomatis di-hash menggunakan `bcrypt` cost `12` sebelum dieksekusi ke database.
- Artinya file seed boleh menyimpan nilai password dummy plaintext untuk kemudahan maintenance, namun data yang tersimpan di DB tetap hash bcrypt.
- Jika nilai password di seed sudah berupa hash bcrypt valid, nilainya tidak di-hash ulang.

## Guard Production

Jika `APP_ENV=production`, command `migrate` dan `db:seed` akan ditolak kecuali memakai `--force`.

## Catatan Baseline

- Baseline schema: `database/migrations/20260225_000001_initial_schema.sql`
- Baseline seed: `database/seeders/20260225_000001_data_dummy.sql`
- Kedua baseline disanitasi dari SQL legacy agar aman dipakai sebagai migrasi terkontrol.
