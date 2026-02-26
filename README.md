# DiscipLink V2 - Sistem Informasi Tata Tertib

## Deskripsi

DiscipLink adalah sistem informasi berbasis web untuk mengelola tata tertib, pelanggaran, sanksi, berita, dan notifikasi di lingkungan POLINEMA (JTI).

Versi ini adalah **DiscipLink V2** dengan perbaikan struktur aplikasi, keamanan autentikasi, UX operasional, dan fondasi deployment lokal.

## Asal Proyek dan Konteks Akademik

Repository dan sistem ini merupakan **hasil refactor, perbaikan, dan upgrade** dari DiscipLink versi sebelumnya:

- Sumber awal: <https://github.com/VarizkyNaldiba/TataTertibMhs>

Pengembangan DiscipLink berangkat dari **Project Based Learning (PBL) PHP Native semester 3**, lalu dilanjutkan pada versi ini untuk peningkatan kualitas arsitektur, maintainability, dan alur operasional aplikasi.

## Apa Yang Berbeda di V2

Berikut pembeda utama DiscipLink V2 dibanding versi sebelumnya:

- **Arsitektur lebih rapih (MVC + handler request):** pemisahan `Models`, `Controllers`, `Request`, dan `views` lebih konsisten.
- **Reusable app shell:** sidebar dan header dipusatkan di `views/partials/app-shell.php` agar tampilan lintas halaman konsisten.
- **Role-based flow lebih jelas:** pemisahan alur mahasiswa, dosen, dan admin untuk akses halaman dan data.
- **Feedback operasi universal:** semua aksi utama (login, CRUD berita, CRUD tatib, pelaporan, upload berkas) memakai modal sukses/gagal yang seragam.
- **Autentikasi password lebih aman:** dukungan hash password bcrypt dengan fallback legacy + rehash otomatis saat login.
- **Pelaporan dosen lebih efisien:** lookup mahasiswa by NIM secara real-time pada form pelaporan.
- **Validasi upload lebih ketat:** validasi ukuran file, ekstensi, MIME type, dan respons JSON untuk integrasi frontend.
- **Database tooling built-in:** tersedia CLI `artisan` untuk migrate/seed/serve.

## Fitur Utama

- Manajemen data tata tertib dan sanksi.
- Pelaporan pelanggaran oleh dosen.
- Monitoring pelanggaran oleh mahasiswa dan dosen.
- Manajemen berita untuk admin.
- Notifikasi berdasarkan peran.
- Upload dokumen pendukung pelanggaran.

## Quick Start

1. Clone repository:

```bash
git clone https://github.com/VarizkyNaldiba/TataTertibMhs.git
cd TataTertibMhsV2
```

1. Siapkan environment:

- Salin `.env.example` menjadi `.env`.
- Sesuaikan kredensial database.

1. Jalankan migration + seed (opsional tapi direkomendasikan):

```bash
php artisan migrate --fresh --force
php artisan db:seed
```

1. Jalankan aplikasi:

```bash
php artisan serve
```

## Database CLI (Artisan-like)

Command yang tersedia:

```bash
php artisan list
php artisan migrate
php artisan db:seed
php artisan serve
```

Opsi penting:

- `php artisan migrate --fresh --force`
- `php artisan migrate:fresh --seed`
- `php artisan db:seed --file=20260225_000001_data_dummy.sql`
- `php artisan serve --host=127.0.0.1 --port=8000`
- `php artisan serve --hot` (butuh Node.js/npx, default hot-server port `8001`)

Dokumentasi database: [`Database/README.md`](./Database/README.md)

## App Gallery

- ![Login](docs/img/login.png)
- `document/gallery/04-pelaporan-dosen.png`
- `document/gallery/05-pelanggaran-mahasiswa.png`

## Arsitektur DiscipLink V2

Dokumen arsitektur dipisah agar lebih detail:

- [`README-ARCHITECTURE.md`](./README-ARCHITECTURE.md)

## Referensi UI/UX

- Figma: <https://www.figma.com/design/yRxgSGu5uvuoKQznRxPCNg/UI%2FUX-Sistem-Tatib?node-id=10-572&node-type=frame&t=FUBlBYXBfDiK1yST-0>

## Aturan Kolaborasi

1. Setiap mahasiswa mengerjakan fitur di branch masing-masing.
2. Jangan push langsung ke `main` tanpa pull request.
3. Lakukan review sebelum merge.
4. Uji fitur sebelum push.
5. Perbarui dokumentasi setiap ada perubahan.
6. Gunakan kanal komunikasi tim yang sudah disepakati.
