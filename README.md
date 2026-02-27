# DiscipLink V2 - Sistem Informasi Tata Tertib

## Deskripsi
DiscipLink adalah sistem informasi berbasis web untuk mengelola tata tertib, pelanggaran, sanksi, berita, notifikasi, dan dokumen pendukung di lingkungan POLINEMA (JTI).

Versi ini adalah **DiscipLink V2** yang berfokus pada peningkatan arsitektur, keamanan request, UX operasional, dan kesiapan deployment lokal.

## Asal Proyek dan Konteks Akademik
Repository ini merupakan hasil **refactor, perbaikan, dan upgrade** dari versi sebelumnya:

- Sumber awal: <https://github.com/VarizkyNaldiba/TataTertibMhs>

Pengembangan DiscipLink berangkat dari **Project Based Learning (PBL) PHP Native semester 3**, lalu dilanjutkan pada V2 untuk peningkatan maintainability dan quality standard.

## Ringkasan Upgrade V2
Berikut fitur upgrade utama yang sudah diimplementasikan:

- **Routing terpusat dan lebih readable**: URL sudah memakai route path jelas seperti `/pelanggaran`, `/admin/news`, `/action/download`.
- **Masking hanya pada parameter ID**: ID sensitif (`id_news`, `id_detail`, dll) ditokenkan, sementara path URL tetap terbaca.
- **Kompatibilitas backward**: URL lama berbasis `/p/<token>` dan `/a/<token>` masih didukung.
- **Session hardening**: idle timeout sesi 30 menit dengan invalidasi otomatis.
- **Token security layer**: token terenkripsi, terikat ke session, dan memakai TTL.
- **Proteksi direct access**: akses langsung ke folder `views/` dan `Request/` diblokir dari router.
- **Handler khusus logout dan download**: alur logout dan download file dipisah ke endpoint terdedikasi.
- **Validasi download file**: whitelist ekstensi + sanitasi nama file + MIME detection.
- **Validasi upload lebih ketat**: validasi ukuran, MIME, ekstensi, dan respons JSON yang konsisten.
- **Auth upgrade**: dukungan bcrypt, fallback legacy plaintext, dan rehash otomatis saat login sukses.
- **UX feedback seragam**: flash modal global untuk menampilkan status sukses/gagal lintas halaman.
- **Role-based flow yang jelas**: pemisahan alur mahasiswa, dosen, admin termasuk menu sidebar.
- **SEO & technical hardening**: canonical host redirect, HSTS (HTTPS), meta SEO helper, JSON-LD, `robots.txt`, `sitemap.xml`.
- **Asset delivery improvement**: helper otomatis memilih file `.min.js` jika tersedia.
- **Database tooling built-in**: CLI `artisan` untuk migrate, seed, dan serve.

## Contoh Format URL Baru
Contoh hasil URL setelah upgrade routing:

- Halaman umum: `/pelanggaran`
- Halaman edit admin: `/admin/news/edit?id_news=<token>`
- Halaman edit pelaporan: `/pelaporan/edit?id_detail=<token>`
- Endpoint download: `/action/download?file=SURAT%20PERNYATAAN%20TI.pdf`

Catatan: hanya parameter ID yang dimasking token, bukan seluruh URL.

## Fitur Utama Aplikasi
- Manajemen data tata tertib dan sanksi.
- Pelaporan pelanggaran oleh dosen.
- Monitoring pelanggaran oleh mahasiswa dan dosen.
- Manajemen berita untuk admin.
- Notifikasi berbasis role.
- Upload dokumen pendukung pelanggaran.

## Quick Start
1. Clone repository.

```bash
git clone https://github.com/VarizkyNaldiba/TataTertibMhs.git
cd TataTertibMhsV2
```

2. Siapkan environment.
- Salin `.env.example` menjadi `.env`.
- Sesuaikan kredensial database.

3. Jalankan migration + seed (opsional tapi direkomendasikan).

```bash
php artisan migrate --fresh --force
php artisan db:seed
```

4. Jalankan aplikasi.

```bash
php artisan serve
```

## Database CLI (Artisan-like)
Command yang tersedia:

```bash
php artisan list
php artisan migrate
php artisan migrate:fresh
php artisan db:seed
php artisan serve
```

Opsi yang sering dipakai:

- `php artisan migrate --fresh --force`
- `php artisan migrate:fresh --seed`
- `php artisan db:seed --file=20260225_000001_data_dummy.sql`
- `php artisan serve --host=127.0.0.1 --port=8000`
- `php artisan serve --hot` (butuh Node.js + npx)

Dokumentasi database: [`Database/README.md`](./Database/README.md)

## Struktur Proyek (Ringkas)
```text
.
├── Controllers/
├── Models/
├── Request/
├── views/
├── helpers/
├── css/
├── js/
├── Database/
└── document/
```

## App Gallery
- ![Login](docs/img/login.png)
- `document/gallery/04-pelaporan-dosen.png`
- `document/gallery/05-pelanggaran-mahasiswa.png`

## Dokumentasi Tambahan
- Arsitektur: [`README-ARCHITECTURE.md`](./README-ARCHITECTURE.md)
- Referensi UI/UX (Figma): <https://www.figma.com/design/yRxgSGu5uvuoKQznRxPCNg/UI%2FUX-Sistem-Tatib?node-id=10-572&node-type=frame&t=FUBlBYXBfDiK1yST-0>

## Aturan Kolaborasi
1. Setiap mahasiswa mengerjakan fitur di branch masing-masing.
2. Jangan push langsung ke `main` tanpa pull request.
3. Lakukan review sebelum merge.
4. Uji fitur sebelum push.
5. Perbarui dokumentasi setiap ada perubahan.
6. Gunakan kanal komunikasi tim yang sudah disepakati.
