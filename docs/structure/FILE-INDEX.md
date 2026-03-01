# File Index (Simple Docs)

Ringkasan fungsi file utama proyek. Fokus pada file source code dan file konfigurasi.

## Root Files

- `index.php`: entrypoint halaman publik utama.
- `router.php`: central request router untuk page/action/API style response.
- `artisan`: command runner untuk migrasi, seeder, dan local serve.
- `config.php`: bootstrap koneksi PDO dari `.env`/environment.
- `.htaccess`: rewrite rule, error document, dan cache header static assets.
- `README.md`: dokumentasi umum pengguna/developer.
- `README-ARCHITECTURE.md`: dokumentasi arsitektur teknis.
- `robots.txt`: aturan crawl engine.
- `sitemap.xml`: daftar URL publik untuk indexing.

## Backend - Controllers

- `controllers/UserController.php`: autentikasi dan sesi user.
- `controllers/NewsController.php`: use-case berita.
- `controllers/PelanggaranController.php`: use-case pelanggaran + notifikasi.
- `controllers/TatibController.php`: use-case tata tertib.

## Backend - Models

- `models/Users.php`: query user dan role.
- `models/News.php`: query data berita.
- `models/Pelanggaran.php`: query pelanggaran + detail.
- `models/Tatib.php`: query aturan tata tertib.
- `models/Sanksi.php`: query sanksi dan relasi.

## Backend - Request Handlers

- `requests/handler-login.php`: endpoint login.
- `requests/handler-logout.php`: endpoint logout.
- `requests/handler-pelanggaran.php`: endpoint aksi pelanggaran/pelaporan.
- `requests/handler-notifikasi.php`: endpoint aksi notifikasi.
- `requests/handler-news.php`: endpoint aksi news.
- `requests/handler-tatib.php`: endpoint aksi tatib.
- `requests/handler-upload.php`: endpoint upload dokumen.
- `requests/handler-download.php`: endpoint download dokumen.

## Backend - Helpers

- `helpers/path_helper.php`: helper path absolut, URL, redirect.
- `helpers/route_helper.php`: registry route + dispatch.
- `helpers/token_helper.php`: token issue/verify untuk route & ID.
- `helpers/seo_helper.php`: canonical/meta/JSON-LD/security header SEO.
- `helpers/flash_modal.php`: helper feedback modal berbasis flash state.
- `helpers/error_page_helper.php`: helper render error page.

## Views

- `views/public/homepage.php`: halaman beranda publik.
- `views/public/berita-detail.php`: detail berita publik.
- `views/auth/login.php`: halaman login.
- `views/tatib/list-tatib.php`: list tatib publik.
- `views/tatib/list-tatib-admin.php`: tatib management admin.
- `views/pelanggaran/pelanggaran-page.php`: dashboard pelanggaran mahasiswa.
- `views/pelanggaran/pelanggaran-dosen.php`: dashboard pelanggaran dosen.
- `views/pelanggaran/pelaporan.php`: form pelaporan.
- `views/pelanggaran/edit-pelaporan.php`: form edit pelaporan.
- `views/pelanggaran/notifikasi.php`: halaman notifikasi.
- `views/admin/home-admin.php`: dashboard admin.
- `views/admin/news-admin.php`: list berita admin.
- `views/admin/tambah-berita.php`: form tambah berita.
- `views/admin/edit-berita.php`: form edit berita.
- `views/partials/app-shell.php`: layout shell bersama.
- `views/components/modals/app-feedback-modal.php`: modal feedback.
- `views/components/modals/admin-confirm-modal.php`: modal konfirmasi admin.
- `views/components/modals/pelaporan-cancel-modal.php`: modal batal pelaporan.
- `views/components/notifications/notification-center.php`: komponen notifikasi.
- `views/components/tables/universal-filterable-table.php`: komponen tabel reusable.
- `views/errors/error-page.php`: template error terpadu.

## Frontend Assets - CSS

- `css/global.css`: style global.
- `css/homepage.css`: style homepage.
- `css/login.css`: style login.
- `css/list-tatib.css`: style list tatib publik.
- `css/tatib-admin.css`: style tatib admin.
- `css/pelanggaran-page.css`: style pelanggaran page.
- `css/pelaporan.css`: style form pelaporan.
- `css/notifikasi.css`: style notifikasi.
- `css/news-admin.css`: style news admin list.
- `css/news-form.css`: style news form.
- `css/news-detail.css`: style detail berita.
- `css/modal.css`: style modal legacy.
- `css/app-modal.css`: style modal global.
- `css/admin-confirm-modal.css`: style modal konfirmasi.
- `css/pelaporan-cancel-modal.css`: style modal batal.
- `css/error-page.css`: style halaman error.
- `css/not-found.css`: style not found variant.

## Frontend Assets - JavaScript

- `js/layout-nav.js`: interaksi nav/layout.
- `js/script.js`: utilitas global legacy.
- `js/universal-table-filter.js`: filter tabel reusable.
- `js/mobile-violation-cards.js`: mode card mobile untuk tabel.
- `js/login.js`: script login.
- `js/homepage.js`: script homepage.
- `js/news-detail.js`: script detail berita.
- `js/script-news.js`: script berita admin.
- `js/news-form-editor.js`: script editor berita.
- `js/admin-tatib.js`: script tatib admin.
- `js/admin-confirm-modal.js`: script modal konfirmasi.
- `js/script-pelanggaran.js`: script dashboard pelanggaran.
- `js/script-pelaporan.js`: script form pelaporan.
- `js/notifikasi.js`: script notifikasi.
- `js/pelaporan-cancel-modal.js`: script modal batal.
- `js/app-modal.js`: script modal global.
- `js/*.min.js`: pasangan minified untuk file JS utama.

## Database

- `database/cli/ConnectionResolver.php`: resolver koneksi DB untuk CLI.
- `database/cli/SqlRunner.php`: executor SQL statement/file.
- `database/cli/MigrationService.php`: migrasi + tracking checksum.
- `database/cli/SeederService.php`: seeding + transform password.
- `database/cli/ConsoleKernel.php`: parser command/options artisan.
- `database/migrations/20260225_000001_initial_schema.sql`: baseline schema.
- `database/migrations/20260227_220157_add_published_at_to_news.sql`: alter kolom published_at.
- `database/migrations/20260301_230000_add_dpa_delegation_to_detail_pelanggaran.sql`: tambah kolom delegasi DPA.
- `database/seeders/20260225_000001_data_dummy.sql`: baseline data dummy.
- `database/legacy/database_disciplink_legacy.sql`: dump legacy full.
- `database/legacy/data_dummy_legacy.sql`: dump dummy legacy.

## Error & Runtime

- `errors/303.php`: fallback 303.
- `errors/400.php`: fallback 400.
- `errors/403.php`: fallback 403.
- `errors/404.php`: fallback 404.
- `errors/500.php`: fallback 500.
- `errors/503.php`: fallback 503.
- `storage/keys/*`: key/token runtime aplikasi.
- `document/news/*`: file upload gambar berita runtime.

## Visual / Documentation Assets

- `img/*`: logo, favicon, dan gambar statis aplikasi.
- `docs/img/*`: screenshot UI dan diagram arsitektur untuk dokumentasi.
