# Request Handlers

Entrypoint HTTP untuk action route (`/action/*`). Handler melakukan validasi awal request sebelum memanggil controller.

## File

- `handler-login.php`: proses login (POST) dan validasi kredensial.
- `handler-logout.php`: proses logout dan pembersihan sesi.
- `handler-pelanggaran.php`: proses aksi pelaporan/pelanggaran (create/update/read helper endpoints).
- `handler-notifikasi.php`: endpoint aksi notifikasi (mark read, mark all read, dll).
- `handler-news.php`: endpoint aksi CRUD berita admin.
- `handler-tatib.php`: endpoint aksi CRUD tata tertib/sanksi.
- `handler-upload.php`: endpoint upload dokumen pendukung pelanggaran.
- `handler-download.php`: endpoint download dokumen dengan validasi akses.
