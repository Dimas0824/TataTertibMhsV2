# Investigasi: `/action/upload` mengembalikan HTTP 500

**Tanggal:** 2026-09-14
**Ditemukan oleh:** Deep pentest Strix (authenticated, mode `deep`, 3 role) — run `192-168-1-15-8001_fe19`
**Status:** RESOLVED — akar masalah dikonfirmasi & diperbaiki (`request/handler-upload.php`)
**Severity:** Medium — bug fungsional (blocker workflow), **bukan** temuan keamanan
**Klasifikasi:** Functional / Backend — bukan vulnerability

---

## Ringkasan

Endpoint upload dokumen pendukung pelanggaran (`POST /action/upload`) mengembalikan
**HTTP 500 `Internal Server Error`** untuk **user yang sah** (login sebagai mahasiswa pemilik
record, CSRF token valid, `id_detail` token valid, file PNG valid). Ini memblokir alur inti:
mahasiswa tidak bisa mengunggah surat pernyataan / tugas khusus.

Deep pentest Strix melaporkannya sebagai `needs_follow_up` (bukan vulnerability terkonfirmasi)
karena endpoint tidak pernah mencapai jalur sukses → keputusan otorisasi objek tidak bisa
diobservasi. Pentester menandai ini sebagai item retest #1 — **yang sejak itu sudah ditutup**
(lihat "Bukti Verifikasi" & "Sisa Pekerjaan" di bawah).

---

## Cara Reproduksi

**Prasyarat**

- Server dev berjalan: `php artisan serve --host=0.0.0.0 --port=8001` (atau host/port lain).
- DB ter-seed (akun demo). Lihat README akun contoh.
- Mahasiswa target punya minimal 1 record `DETAIL_PELANGGARAN` yang dirender di `/pelanggaran`
  (halaman harus menampilkan form upload + `id_detail` token).

**Langkah**

1. `GET /login` → ambil `csrf_token` (input hidden, 64-hex).
2. `POST /action/login` dengan `csrf_token`, `user_type=nim`, `username=2341238901`,
   `password=password123` → 302 ke `/pelanggaran`.
3. `GET /pelanggaran` → ambil `csrf_token` baru + satu nilai `id_detail`
   (token terenkripsi berpola `s1.`/`o1.`, panjang ~311 char).
4. `POST /action/upload` (multipart) dengan:
   - `csrf_token` (dari langkah 3)
   - `id_detail` (token dari langkah 3)
   - `suratPernyataan=@file.png;type=image/png` (file PNG valid, < 2 MB)

**Hasil aktual (terverifikasi 2026-09-14):**

```json
{"success":false,"message":"Internal Server Error"}
```

`HTTP:500`

**Contoh perintah (curl, setelah cookie sesi & token didapat):**

```bash
curl -s -S -w "\nHTTP:%{http_code}" -b "<session-cookie>" \
  -X POST \
  -F "csrf_token=<64-hex>" \
  -F "id_detail=<s1.…token…>" \
  -F "suratPernyataan=@file.png;type=image/png" \
  http://127.0.0.1:8001/action/upload
# → {"success":false,"message":"Internal Server Error"}
#     HTTP:500
```

---

## Konteks / Apa yang Sudah Dicek

| Aspek | Hasil | Kesimpulan |
| --- | --- | --- |
| Skema DB `DETAIL_PELANGGARAN` | Punya kolom `surat`, `pengumpulan_tgsKhusus` (yang di-UPDATE handler) | Bukan masalah kolom hilang |
| Direktori `storage/uploads` | Ada & writable (`Modify` untuk Authenticated Users) | Bukan masalah permission |
| `finfo_open` / `finfo_file` | Tersedia (MIME detection path aktif) | Bukan masalah ekstensi |
| PHP `file_uploads`, `upload_max_filesize`, `post_max_size` | `On`, 200M, 200M | Bukan masalah limit |
| CSRF (`app_verify_csrf`) | Token valid diterima (tidak 419) | CSRF lolos; masalah di hilir |
| `id_detail` token | Valid & ter-resolve (tidak 422) | Resolusi token lolos |
| Ownership check | Query di handler parameterized + cek role/owner | Parameterized, tetapi **query-nya gagal dieksekusi** (lihat akar masalah) |
| Response 500 | JSON generic | Dari exception handler global `router.php` |

**Penyebab 500 generik:** `router.php` memasang `set_error_handler()` yang mengubah **setiap**
warning/notice/error menjadi `ErrorException`, lalu `set_exception_handler()` merender
`{"success":false,"message":"Internal Server Error"}` untuk path `/action/*` (JSON).
Artinya ada **Throwable/warning tersembunyi** di jalur upload yang tidak terlihat karena
`display_errors=0` (fail-closed setelah hardening).

---

## Akar Masalah (TERKONFIRMASI)

**`PDOException: SQLSTATE[HY093]: Invalid parameter number`** pada query SELECT ownership di
`request/handler-upload.php`.

**Sebab:** query memakai **nama placeholder yang sama lebih dari sekali**:

```sql
WHERE dp.id_detail = :idDetail
  AND (
       (:role = 'mahasiswa' AND m.nim = :nim)
    OR (:role = 'dosen' AND (pelapor.nidn = :nidn OR penanggung.nidn = :nidn))
  )
```

`:role` dipakai 2× dan `:nidn` dipakai 2×. Karena `config.php` men-set
`PDO::ATTR_EMULATE_PREPARES = false` (native prepares), MySQL native prepared statements
**melarang** nama parameter yang sama muncul lebih dari sekali → `execute()` melempar
`HY093`. Exception ini di-intercept `router.php` → dirender sebagai HTTP 500 generic.

> Ini adalah hipotesis #3 yang tercantum di versi awal laporan, kini terkonfirmasi
> (`EMULATE_PREPARES=false` + placeholder berulang → native prepare menolak).

## Perbaikan

Setiap kemunculan placeholder diberi **nama unik** di `request/handler-upload.php`:

```sql
WHERE dp.id_detail = :idDetail
  AND (
       (:roleMahasiswa = 'mahasiswa' AND m.nim = :nim)
    OR (:roleDosen = 'dosen' AND (pelapor.nidn = :nidn OR penanggung.nidn = :nidnPenanggung))
  )
```

…dengan binding yang sesuai:

```php
$detailStmt->bindValue(':idDetail', $idDetail, PDO::PARAM_INT);
$detailStmt->bindValue(':roleMahasiswa', $role, PDO::PARAM_STR);
$detailStmt->bindValue(':roleDosen', $role, PDO::PARAM_STR);
$detailStmt->bindValue(':nim', (string) ($sessionData['nim'] ?? ''), PDO::PARAM_STR);
$detailStmt->bindValue(':nidn', (string) ($sessionData['nidn'] ?? ''), PDO::PARAM_STR);
$detailStmt->bindValue(':nidnPenanggung', (string) ($sessionData['nidn'] ?? ''), PDO::PARAM_STR);
```

**Catatan retest:** setelah fix, ulangi langkah reproduksi di atas → harus `200`
`{"success":true,"message":"File berhasil diunggah."}`. Lalu **verifikasi otorisasi objek**:
mahasiswa lain mencoba upload dengan `id_detail` token milik mahasiswa pertama → harus ditolak
(404 "Data pelanggaran tidak ditemukan"). **Keduanya sudah dijalankan** sebagai regresi di
`tests/security/UploadOwnershipSuite.php` (lihat bagian "Sisa Pekerjaan" di bawah).

## Bukti Verifikasi (2026-09-14)

Terhadap server dev `http://127.0.0.1:8001` (DB `disciplink`, MySQL 9.7, `EMULATE_PREPARES=false`):

| Tahap | Sebelum fix | Sesudah fix |
| --- | --- | --- |
| `POST /action/upload` (mahasiswa pemilik, PNG valid) | `500` · `{"success":false,"message":"Internal Server Error"}` | `200` · `{"success":true,"message":"File berhasil diunggah."}` |

Akar masalah dikonfirmasi via isolasi query:

```
EMULATE_PREPARES=0
DRIVER=mysql
SERVER=9.7.0
DUPLICATE-PLACEHOLDER: [PDOException] SQLSTATE[HY093]: Invalid parameter number
```

**Scan repo:** pencarian statis seluruh `prepare()` di repo → **tidak ada** query lain dengan
nama placeholder berulang. Bug ini terisolasi hanya di `request/handler-upload.php`.

## Sisa Pekerjaan

- [x] **Retest otorisasi objek** (cross-user upload) — **SELESAI**: `tests/security/UploadOwnershipSuite.php`
      mencakup owner sukses (mahasiswa & dosen), cross-user ditolak, anon/CSRF-less tidak sampai jalur tulis.
- [x] **Regresi otomatis** — **SELESAI**: `tests/security/UploadOwnershipSuite.php` terdaftar di
      `tests/run.php`. Bukti runtime: `php tests/run.php` → **160/160 PASS, 0 failed** (2026-09-14).

**Status akhir: CLOSED** — bug fungsional diperbaiki, gap otorisasi objek ditutup, regresi terpasang.

---

## Hipotesis Awal (dipertahankan sebagai jejak investigasi)

1. **`move_uploaded_file()` gagal** → tidak terbukti (error muncul sebelum tahap move).
2. **`UPDATE … SET $updateColumn = :filePath`** dengan `$updateColumn` tak ter-set → tidak terbukti.
3. **`PDO::ATTR_EMULATE_PREPARES = false` + placeholder berulang / bind tak cocok** →  **TERKONFIRMASI** (ini akarnya).
4. Warning minor yang di-throw oleh error handler global → tidak terbukti sebagai akar.

---

## Cara Akar Masalah Ditemukan (metode, untuk referensi)

Investigasi selesai; langkah berikut dipertahankan agar tekniknya bisa dipakai ulang saat
debug error generic serupa (500 tanpa pesan karena `display_errors=0`):

1. **Jalankan server debug** di port terpisah dengan override env (`.env` **tidak** diubah):

   ```powershell
   # PowerShell, dari root repo
   $env:APP_DEBUG = "true"
   & "D:\Tools\FlyEnv-Data\env\php\php.exe" -S 127.0.0.1:8002 -t "D:\MiniProject\TataTertibMhsV2" router.php
   ```

   Dengan `APP_DEBUG=true`, `config.php` menyalakan `display_errors` → pesan exception muncul.

2. **Reproduksi request asli** (login → ambil token → multipart upload) ke port debug.
3. **Isolasi query** di handler lewat harness CLI kecil (`require config.php`) untuk melihat
   `PDOException` yang sebenarnya — ini yang mengungkap `SQLSTATE[HY093]`.

Alternatif lebih cepat: pasang `try/catch (Throwable)` sementara di sekitar `execute()` untuk
mencatat `$e->getMessage()` (tanpa mengubah `.env`).

---

## Dampak

- **Fungsional:** mahasiswa tidak dapat mengunggah surat pernyataan / tugas khusus → alur
  kedisiplinan inti terblokir. Ini blocker workflow, bukan hanya kosmetik.
- **Keamanan:** **tidak ada** temuan keamanan yang terbukti pada endpoint ini. Otorisasi objek
  kini **sudah diverifikasi** (setelah 500 diperbaiki): `tests/security/UploadOwnershipSuite.php`
  membuktikan pemilik (mahasiswa/dosen) bisa upload, sedangkan cross-user ditolak dan anon/CSRF-less
  tidak mencapai jalur tulis. Desain otorisasi (query parameterized + cek `role` + cek `nim`/`nidn`
  pemilik) terkonfirmasi benar.

---

## Rujukan

- Handler: `request/handler-upload.php`
- Routing: `helpers/route_helper.php` (`action.upload` → `/action/upload`)
- Error handling global: `router.php` (`set_error_handler` / `set_exception_handler`, baris ~49–90)
- Laporan pentest Strix (deep): `F:\SecurityLab\scans-deep\strix_runs\192-168-1-15-8001_fe19\`
  (luar repo; artefak scan)
