# DiscipLink V2

Sistem informasi tata tertib mahasiswa — mengelola aturan, pelanggaran, notifikasi, dan berita kedisiplinan dalam satu platform terpusat.

---

## Quick Start

```bash
git clone https://github.com/Dimas0824/TataTertibMhsV2.git
cd TataTertibMhsV2
cp .env.example .env
# edit .env → sesuaikan DB_DSN, DB_USER, DB_PASS
php artisan migrate:fresh --seed --force
php artisan serve --host=127.0.0.1 --port=8000
```

Buka [http://127.0.0.1:8000](http://127.0.0.1:8000)

---

## Akun Contoh

| Role | Username | Password |
|------|----------|---------|
| Mahasiswa | `2341238901` | `password123` |
| Dosen | `1234567890` | `password123` |
| Admin | `ADMIN001` | `admin123` |

---

## Fitur per Role

| Role | Akses |
|------|-------|
| **Mahasiswa** | Dashboard pelanggaran, poin, upload dokumen (surat/tugas), notifikasi |
| **Dosen** | Pelaporan pelanggaran, rekap & konfirmasi laporan mahasiswa |
| **Admin** | CRUD tata tertib, CRUD berita, manajemen konten |

---

## Dokumentasi

Lihat **[docs/README.md](docs/README.md)** untuk navigasi lengkap.

---

## Ringkasan Teknis

| | |
|---|---|
| **Stack** | PHP native · PDO · MySQL |
| **Arsitektur** | MVC + Request Handler + Central Router |
| **Auth** | Role-based (Mahasiswa, Dosen, Admin) |
| **CLI** | Custom `artisan` untuk migrate/seed/serve |

---

## Sumber

Refactor dari: [TataTertibMhs (VarizkyNaldiba)](https://github.com/VarizkyNaldiba)
UI/UX Design: [Figma](https://www.figma.com/design/yRxgSGu5uvuoKQznRxPCNg/UI%2FUX-Sistem-Tatib)

---

## Lisensi

MIT
