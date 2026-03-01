# JavaScript Assets

Script frontend berbasis vanilla JS. File `*.min.js` adalah versi minified untuk produksi.

## Core / Shared

- `layout-nav.js`: interaksi navigasi/layout shell.
- `script.js`: utilitas script umum legacy.
- `universal-table-filter.js`: logika filter/search tabel reusable.
- `mobile-violation-cards.js`: transformasi tabel ke card pada mobile untuk data pelanggaran.

## Auth & Public

- `login.js`: validasi/interaksi UI login.
- `homepage.js`: interaksi homepage (slider, card, event UI).
- `news-detail.js`: interaksi detail berita.

## Admin

- `news-admin.js`: aksi list berita admin (hapus, filter, dsb).
- `news-form-editor.js`: editor konten berita di form admin.
- `admin-tatib.js`: aksi CRUD/UX halaman tatib admin.
- `admin-confirm-modal.js`: perilaku modal konfirmasi admin.

## Pelanggaran / Notifikasi

- `pelanggaran-dashboard.js`: interaksi dashboard pelanggaran.
- `pelaporan-form.js`: logika form pelaporan/edit pelaporan.
- `notifikasi.js`: interaksi halaman notifikasi.
- `pelaporan-cancel-modal.js`: kontrol modal pembatalan pelaporan.
- `app-modal.js`: kontrol modal feedback global lintas halaman.

## Minified Build

- `*.min.js`: pasangan minified dari script utama (`login`, `homepage`, `layout-nav`, `script`, `news-admin`, `pelanggaran-dashboard`, `pelaporan-form`, `notifikasi`, `app-modal`, `admin-tatib`, `pelaporan-cancel-modal`).
