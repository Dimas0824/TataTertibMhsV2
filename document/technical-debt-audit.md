# Technical Debt Audit (2026-02-25)

## Critical
1. Konfigurasi DB belum versioned karena `config.php` tidak di-repo.
2. `Models/Tatib.php` update query salah (syntax dan variabel undefined).
3. `Request/Handler_News.php` memakai `$this->connect` pada script prosedural.
4. Status pelanggaran tidak konsisten antara layer app dan procedure.
5. SQL schema legacy mengandung perintah destruktif di file yang sama.

## High
1. Password masih plaintext di proses autentikasi.
2. Output view belum konsisten menggunakan escaping.
3. Upload belum validasi MIME/size yang kuat.
4. JS pelaporan mengakses elemen yang tidak selalu ada.
5. Path asset pada `index.php` tidak tepat.

## Medium
1. Coupling tinggi lewat global `$connect` lintas layer.
2. Belum ada automated test regresi.
3. Query/business rule tersebar lintas controller/model/view.

## Implemented in this iteration
- Menambahkan CLI `artisan` untuk `migrate` dan `db:seed`.
- Menyiapkan baseline migration dan baseline seeder versioned.
- Menambahkan `.env.example` dan dokumentasi command.
- Memperbaiki beberapa issue critical yang langsung berdampak ke flow utama.

## Backlog lanjutan
1. Migrasi password ke `password_hash()/password_verify()`.
2. Tambahkan CSRF token pada seluruh form mutasi.
3. Hardening upload file (MIME sniffing + whitelist extension + size limit backend).
4. Refactor dependency injection untuk mengurangi global state.
5. Tambahkan test minimal untuk login, pelaporan, migrate, dan seed.
