# Views

Layer tampilan aplikasi (HTML/PHP template). File dikelompokkan berdasarkan area pengguna.

## Admin

- `admin/home-admin.php`: dashboard admin.
- `admin/news-admin.php`: halaman list/kelola berita.
- `admin/tambah-berita.php`: form tambah berita.
- `admin/edit-berita.php`: form edit berita.

## Auth

- `auth/login.php`: halaman login multi-role.

## Public

- `public/homepage.php`: landing page publik.
- `public/berita-detail.php`: halaman detail berita publik.

## Tatib

- `tatib/list-tatib.php`: list tata tertib untuk user umum.
- `tatib/list-tatib-admin.php`: manajemen tata tertib untuk admin.

## Pelanggaran

- `pelanggaran/pelanggaran-page.php`: dashboard pelanggaran mahasiswa.
- `pelanggaran/pelanggaran-dosen.php`: dashboard pelanggaran dosen.
- `pelanggaran/pelaporan.php`: form pelaporan pelanggaran.
- `pelanggaran/edit-pelaporan.php`: form edit pelaporan.
- `pelanggaran/notifikasi.php`: halaman notifikasi pengguna.

## Shared Components

- `partials/app-shell.php`: shell layout (header/sidebar/footer) yang dipakai halaman internal.
- `errors/error-page.php`: template error page terstandar.
- `components/modals/app-feedback-modal.php`: modal feedback global.
- `components/modals/admin-confirm-modal.php`: modal konfirmasi aksi admin.
- `components/modals/pelaporan-cancel-modal.php`: modal batal pelaporan.
- `components/notifications/notification-center.php`: komponen center notifikasi.
- `components/tables/universal-filterable-table.php`: komponen tabel dengan filter/search universal.
