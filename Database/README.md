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
- `php artisan migrate --path=Database/migrations`
- `php artisan db:seed`
- `php artisan db:seed --file=20260225_000001_data_dummy.sql`
- `php artisan db:seed --path=Database/seeders --force`

## Aturan Migrasi & Seed
- Folder migrasi: `Database/migrations`
- Folder seed: `Database/seeders`
- Format nama file wajib: `YYYYMMDD_HHMMSS_name.sql`
- File yang sudah dijalankan dicatat di:
  - `schema_migrations`
  - `schema_seeds`
- Jika checksum file berubah setelah pernah dijalankan, command akan gagal (drift detection).

## Guard Production
Jika `APP_ENV=production`, command `migrate` dan `db:seed` akan ditolak kecuali memakai `--force`.

## Catatan Baseline
- Baseline schema: `Database/migrations/20260225_000001_initial_schema.sql`
- Baseline seed: `Database/seeders/20260225_000001_data_dummy.sql`
- Kedua baseline disanitasi dari SQL legacy agar aman dipakai sebagai migrasi terkontrol.
