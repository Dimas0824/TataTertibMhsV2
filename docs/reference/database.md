# Reference: Database & CLI Artisan

Project ini menyediakan command mirip artisan untuk migrasi dan seed database.

---

## Persiapan Koneksi

Pilih salah satu:

1. Buat `.env` dari `.env.example` lalu isi `DB_DSN`, `DB_USER`, `DB_PASS`.
2. Atau tetap gunakan `config.php` existing yang berisi global `$connect` (PDO).
3. `migrate:fresh` saat ini didukung untuk driver `mysql` dan `sqlsrv`.

---

## CLI Artisan Commands

| Command | Deskripsi |
|---------|-----------|
| `php artisan list` | Daftar command tersedia |
| `php artisan help [command]` | Bantuan untuk command tertentu |
| `php artisan migrate` | Jalankan migrasi |
| `php artisan migrate --seed` | Jalankan migrasi + seed |
| `php artisan migrate:fresh` | Drop semua tabel + migrate |
| `php artisan migrate:fresh --seed --force` | Drop + migrate + seed (production) |
| `php artisan migrate --path=database/migrations` | Migrate dari path spesifik |
| `php artisan db:seed` | Seed saja |
| `php artisan db:seed --file=20260225_000001_data_dummy.sql` | Seed file tertentu |
| `php artisan db:seed --path=database/seeders --force` | Seed dari folder |
| `php artisan serve` | Start dev server |
| `php artisan serve --host=127.0.0.1 --port=8000` | Start dengan host/port custom |
| `php artisan serve --hot` | Hot-reload via BrowserSync (port 8001) |

---

## Menjalankan Project

### Development Server

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

### Hot Reload

```bash
php artisan serve --hot
```

Hot reload membutuhkan Node.js (`npx`) karena menggunakan BrowserSync.

---

## Aturan Migrasi & Seed

### Struktur Folder

```
database/
├── migrations/    ← definisi schema
└── seeders/      ← data contoh
```

### Format Nama File

Wajib: `YYYYMMDD_HHMMSS_name.sql`

Contoh:
```
database/migrations/20260225_000001_initial_schema.sql
database/seeders/20260225_000001_data_dummy.sql
```

### Tracking

File yang sudah dijalankan dicatat di:
- `schema_migrations`
- `schema_seeds`

### Drift Detection

Jika checksum file berubah setelah pernah dijalankan, command akan gagal. Ini mencegah perubahan migrasi yang sudah applied.

---

## Keamanan Password Seeder

Saat `php artisan db:seed` dijalankan:

1. Nilai kolom `password` pada statement `INSERT ... VALUES ...` otomatis di-hash menggunakan `bcrypt` cost `12`
2. File seed boleh menyimpan password dummy plaintext untuk kemudahan maintenance
3. Data di database tetap hash bcrypt
4. Jika nilai password di seed sudah berupa hash bcrypt valid, tidak di-hash ulang

---

## Production Guard

Jika `APP_ENV=production` di `.env`:
- Command `migrate` dan `db:seed` akan ditolak
- Tambahkan `--force` untuk memaksa

---

## Baseline Files

| File | Deskripsi |
|------|-----------|
| `database/migrations/20260225_000001_initial_schema.sql` | Baseline schema |
| `database/seeders/20260225_000001_data_dummy.sql` | Baseline data contoh |

Baseline sudah disanitasi dari SQL legacy agar aman sebagai migrasi terkontrol.

---

## Membuat Migrasi Baru

1. Buat file di `database/migrations/` dengan format `YYYYMMDD_HHMMSS_nama_migration.sql`
2. File harus memiliki header tracking:

```sql
INSERT INTO schema_migrations (filename) VALUES ('20260708_120000_add_field_to_tabel.sql')
ON DUPLICATE KEY UPDATE executed_at = CURRENT_TIMESTAMP;

ALTER TABLE NAMA_TABEL
    ADD COLUMN nama_kolom VARCHAR(255) NULL AFTER kolom_existing;
```

3. Jalankan dengan `php artisan migrate --force`

---

## Struktur Tabel Utama

### TATA_TERTIB

| Kolom | Tipe | Deskripsi |
|-------|------|-----------|
| id_tata_tertib | INT PK | Auto increment |
| deskripsi | TEXT | Isi aturan |
| tingkat | ENUM('I','II','III') | Tingkat pelanggaran |
| poin | INT | Poin sanksi |

### DETAIL_PELANGGARAN

| Kolom | Tipe | Deskripsi |
|-------|------|-----------|
| id_detail | INT PK | Auto increment |
| id_mahasiswa | INT FK | Mahasiswa terkait |
| id_tata_tertib | INT FK | Aturan yang dilanggar |
| id_dosen | INT FK | Dosen pelapor |
| surat | VARCHAR(255) | Path file surat pernyataan |
| pengumpulan_tgsKhusu | VARCHAR(255) | Path file tugas khusus |
| status | ENUM('proses','selesai') | Status kasus |
