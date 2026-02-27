# DiscipLink V2

Sistem informasi tata tertib mahasiswa untuk membantu proses pembinaan disiplin secara lebih rapi, jelas, dan terdokumentasi.

## Apa Itu DiscipLink?

DiscipLink adalah aplikasi web yang dipakai untuk:

- melihat aturan tata tertib kampus,
- mencatat pelanggaran mahasiswa,
- memantau status tindak lanjut,
- mengelola notifikasi,
- mengelola berita informasi kedisiplinan.

Aplikasi ini digunakan oleh 3 jenis pengguna:

1. `Mahasiswa`
2. `Dosen`
3. `Admin`

## Manfaat Utama

- Informasi aturan dan sanksi jadi lebih mudah diakses.
- Proses pelaporan pelanggaran lebih cepat dan terstruktur.
- Status kasus bisa dipantau langsung oleh pihak terkait.
- Berita/pengumuman kedisiplinan bisa dikelola dari satu tempat.

## Fitur Berdasarkan Pengguna

| Pengguna | Fitur Utama |
| --- | --- |
| Mahasiswa | Melihat status pelanggaran, poin, notifikasi, serta upload dokumen yang diminta. |
| Dosen | Membuat pelaporan pelanggaran dan memantau rekap laporan mahasiswa. |
| Admin | Mengelola tata tertib, mengelola berita, dan memelihara konten informasi. |

## Cara Menjalankan Aplikasi (Singkat)

Jika kamu ingin menjalankan aplikasi ini di komputer lokal:

1. Clone repository lalu masuk ke folder proyek.

```bash
git clone <url-repository-anda>
cd TataTertibMhsV2
```

1. Buat file `.env` dari contoh.

```bash
cp .env.example .env
```

1. Sesuaikan koneksi database di file `.env`.

```dotenv
DB_DSN="mysql:host=127.0.0.1;port=3306;dbname=DiscipLink;charset=utf8mb4"
DB_USER="root"
DB_PASS=""
```

1. Jalankan migrasi dan isi data contoh.

```bash
php artisan migrate:fresh --seed --force
```

1. Jalankan server lokal.

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

1. Buka aplikasi di browser:
   <http://127.0.0.1:8000>

## Akun Contoh (Data Seeder)

| Role | Username | Password |
| --- | --- | --- |
| Mahasiswa | `2341238901` | `password123` |
| Dosen | `1234567890` | `password123` |
| Admin | `ADMIN001` | `admin123` |

## App Gallery

### Public / Umum

| Halaman | Preview | Deskripsi |
| --- | --- | --- |
| Homepage | ![Homepage](docs/img/homepage.png) | Landing page DiscipLink untuk pengguna umum. |
| Login | ![Login](docs/img/login.png) | Halaman autentikasi multi-role. |
| List Tata Tertib | ![List Tata Tertib](docs/img/listTatib.png) | Halaman daftar aturan tata tertib yang dapat diakses umum. |

### Mahasiswa

| Halaman | Preview | Deskripsi |
| --- | --- | --- |
| Dashboard Pelanggaran Mahasiswa | ![Pelanggaran Mahasiswa](docs/img/pelanggaranMahasiswa.png) | Monitoring riwayat pelanggaran, poin, status, dan upload berkas. |
| Notifikasi Mahasiswa | ![Notifikasi Mahasiswa](docs/img/notifikasi.png) | Inbox notifikasi aktivitas dan update status pelanggaran. |

### Dosen

| Halaman | Preview | Deskripsi |
| --- | --- | --- |
| Rekap Pelanggaran Dosen | ![Pelaporan Dosen](docs/img/pelaporanDosen.png) | Tabel rekap pelanggaran mahasiswa untuk ditinjau dosen. |
| Form Pelaporan Dosen | ![Form Pelaporan](docs/img/formPelaporan.png) | Form pelaporan pelanggaran baru oleh dosen. |

### Admin

| Halaman | Preview | Deskripsi |
| --- | --- | --- |
| Manajemen Tata Tertib | ![Tatib Admin](docs/img/tatibAdmin.png) | CRUD data aturan, tingkat, dan poin pelanggaran. |
| Manajemen News (List/CRUD) | ![CRUD News Admin](docs/img/CRUDNewsAdmin.png) | Daftar berita admin dengan aksi edit dan hapus. |
| Tambah Berita (Rich Editor) | ![Tambah Berita Admin](docs/img/buatBeritaAdmin.png) | Form tambah berita dengan editor konten terformat. |

### Branding & Asset Logo

| Aset | Preview | Keterangan |
| --- | --- | --- |
| Logo penuh DiscipLink + slogan | ![Logo Penuh DiscipLink](img/ga%20logo%20aja.png) | Logo utama DiscipLink (versi lengkap). |
| Logo icon DiscipLink | ![Logo Icon DiscipLink](img/logo%20aja.png) | Ikon turunan dari logo DiscipLink. |
| Hero background | ![Hero Background](img/GRAHA-POLINEMA1-slider-01.webp) | Latar hero halaman public (gedung Graha Polinema). |
| App icon set (Polinema) | ![Polinema Icon](img/apple-touch-icon.png) | `apple-touch-icon.png`, `favicon.ico`, `favicon.svg`, `favicon-96x96.png`, `web-app-manifest-192x192.png`, dan `web-app-manifest-512x512.png` menggunakan logo Polinema. |

## Dokumentasi Lanjutan

Untuk pembaca non-teknis, README ini sudah cukup jelas.

Jika Anda developer dan butuh detail teknis, buka:

- Arsitektur teknis: [`README-ARCHITECTURE.md`](./README-ARCHITECTURE.md)
- Database tooling: [`Database/README.md`](./Database/README.md)
- Referensi UI/UX: <https://www.figma.com/design/yRxgSGu5uvuoKQznRxPCNg/UI%2FUX-Sistem-Tatib?node-id=10-572&node-type=frame&t=FUBlBYXBfDiK1yST-0>

## Sumber Proyek

Refactor dari proyek awal:
<https://github.com/VarizkyNaldiba/TataTertibMhs>

## Lisensi

MIT License
