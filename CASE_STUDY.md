# Case Study - DiscipLink V2

**Sistem informasi tata tertib mahasiswa, dibangun di atas satu prinsip: otorisasi dan keamanan ditegakkan di server, deny-by-default - lalu dibuktikan lewat pengujian berlapis, bukan sekadar diklaim.**

Dokumen ini menceritakan *bagaimana keputusan diambil*, bukan sekadar daftar fitur.

---

## Konteks

**Masalah.**
Pencatatan pelanggaran tata tertib mahasiswa umumnya masih manual (kertas/spreadsheet): sulit dilacak, rawan hilang, dan tidak ada jejak siapa mengubah apa. Mahasiswa tidak punya transparansi atas poin pelanggaran mereka; dosen tidak punya rekap cepat untuk konfirmasi.

**Peran saya.**
Desain arsitektur, implementasi, hardening keamanan, infrastruktur pengujian, dan dokumentasi - dari nol sampai pipeline CI hijau.

**Stack.**
PHP 8.3 native - PDO/MySQL - HTML/CSS/JS (vanilla) - custom `artisan` CLI - test runner sendiri - GitHub Actions (lint + test + E2E Playwright)

---

## Keputusan 1 - ID Buram Terikat Sesi, Bukan Cek Manual di Tiap Query

### Situasi

Setiap pelanggaran, notifikasi, dan file punya ID yang muncul di URL/form. Model akses naif (`WHERE id = ?` + percaya role) adalah tanah subur **IDOR** - cukup ganti angka `12` jadi `13` untuk mengintip data orang lain.

### Opsi yang dipertimbangkan

| # | Pendekatan | Masalah |
|---|---|---|
| 1 | Cek kepemilikan manual di *setiap* query | Rawan lupa di satu tempat - satu kelalaian, satu kebocoran |
| 2 | **ID buram ber-enkripsi (AEAD), terikat `session_id` + kedaluwarsa** | Objek tidak bisa dirujuk tanpa token sah, dan token tidak bisa dipindah antar sesi |

### Keputusan: Opsi 2

Setiap referensi objek melewati `app_id_token()` / `app_id_resolve()`, dengan:

- Enkripsi AEAD (sodium secretbox, fallback AES-256-GCM)
- Binding ke hash sesi
- TTL (masa berlaku token)

**Efeknya:**

- Ganti `id` sembarangan -> token gagal didekripsi -> ditolak. Bukan "query yang lupa dicek".
- Replay lintas sesi/role -> ditolak secara kriptografis.
- Token file (`?file=`) juga berupa token, bukan path -> path traversal jadi tidak relevan.

> **Yang saya pelajari:** membuat *jalur yang salah* jadi tidak mungkin dilakukan itu lebih kuat daripada mengandalkan ingatan untuk memeriksa di setiap tempat. Ini trade-off yang sadar - sedikit lebih rumit di sisi token, tapi menghilangkan seluruh kelas kerentanan sekaligus.

---

## Keputusan 2 - Membongkar HTTP 500 di Endpoint Upload

### Gejala

`POST /action/upload` mengembalikan `500 Internal Server Error` untuk user **yang sah** - memblokir alur inti (mahasiswa tak bisa unggah surat/tugas). Semua endpoint lain normal (200). Pesan error generik karena production mematikan `display_errors` (fail-closed by design).

### Proses diagnosis

1. **Reproduksi** alur asli: login -> ambil CSRF + token `id_detail` -> kirim multipart PNG valid -> dapat 500. Konsisten, bukan flaky.
2. **Isolasi.** Jalankan query otorisasi handler di harness CLI dengan `display_errors=1` -> muncul akar sebenarnya:

   ```
   PDOException: SQLSTATE[HY093] Invalid parameter number
   ```

3. **Akar masalah.** Query memakai nama placeholder yang sama dua kali (`:role` ×2, `:nidn` ×2). Karena aplikasi memakai `PDO::ATTR_EMULATE_PREPARES = false` (native prepare), MySQL **menolak** placeholder duplikat - di mode emulasi lama ini lolos, sekarang tidak.
4. **Fix.** Tiap kemunculan diberi nama unik: `:roleMahasiswa`, `:roleDosen`, `:nidnPenanggung`.
5. **Verifikasi.** 500 -> 200 di live. Ditambah regresi (`UploadOwnershipSuite`) yang membuktikan alur sukses milik pemilik *dan* penolakan cross-user, agar bug tidak kembali.

### Kenapa ini bukan sekadar "tambal bug"

Handler-nya dibungkus `try/catch` dan mengembalikan pesan generik - jadi bug-nya *tersembunyi*. Yang menyelesaikannya bukan `try/catch` tambahan, tapi menemukan sebab sebenarnya lewat reproduksi terkendali. Itulah beda antara menambal gejala dan mengobati penyakit.

> Detail lengkap: [`docs/intern/UPLOAD-500-INVESTIGATION.md`](docs/intern/UPLOAD-500-INVESTIGATION.md)

---

## Keputusan 3 - Target Coverage Itu "Kualitas", Bukan Angka

Saat ditantang menaikkan line coverage ke 85%, saya mengukur dulu dengan **Xdebug** (line coverage asli, menggabungkan proses CLI + server HTTP).

**Hasil:** 76.3% keseluruhan, dengan fungsi-fungsi inti sudah **di atas 80%** (auth, token, otorisasi objek, upload, model pelanggaran - bahkan `TatibController` mencapai 100%).

**Keputusan sadar untuk berhenti:** sisa baris yang belum ter-*cover* mayoritas adalah *catch-block* defensif dan cabang error yang hanya bisa dipicu dengan memaksa kegagalan buatan. Mengejarnya sampai 85% berarti menulis tes yang menguji *detail implementasi*, bukan *perilaku yang bermakna*.

Menulis tes semacam itu pantas ditinggalkan bila nilai tambahnya nol. Yang penting adalah kelas perilaku kritis (otorisasi, token, upload, sanitasi) sudah punya regresi - dan itu sudah tercapai.

> Filosofi ini terdokumentasi di [`tests/README.md`](tests/README.md) dan [`README.md`](README.md)

---

## Hardening dari Sisi Kode

Selain dua keputusan desain di atas, ada satu gelombang hardening terarah - audit code-level dengan 3 auditor paralel - yang menutup temuan di lapisan aplikasi **dan** konfigurasi server. Semua diperbaiki di kode, bukan sekadar di dokumentasi.

| Kelas | Sebelum | Sesudah (di kode) |
| --- | --- | --- |
| **Secret exposure** | `.env` & `storage/keys/app_token.key` bisa diunduh via HTTP | `.htaccess` + guard `router.php`, deny fail-closed; guardrail statis menolak regresi |
| **Autentikasi** | Seed password plaintext; enumerasi user via timing | bcrypt (cost 12) wajib; throttle 5 gagal/15 menit + dummy-hash verify (anti timing-leak) |
| **Session** | Cookie anonim mewarisi default `php.ini` pada entry path non-router | `session_start()` mentah diganti `app_session_start_if_needed()` (HttpOnly / SameSite / Secure) |
| **CSRF** | Dua jalur POST state-changing tanpa verifikasi | `app_verify_csrf()` di seluruh jalur; token dirotasi saat login |
| **XSS** | Teks database mentah tampil di `<option>` / atribut view | Escaping `htmlspecialchars(ENT_QUOTES)` seragam; flash JSON dengan `JSON_HEX_TAG` + `JSON_HEX_AMP` |
| **Header** | Hanya HSTS kondisional | CSP + `X-Frame-Options: DENY` + `nosniff` + `Referrer-Policy`, disatukan dalam satu fungsi |
| **File upload** | MIME fail-open (percaya `Content-Type` klien), `mkdir 0777` | Fail-closed MIME check (`finfo`), mode `0755`, nama file acak |
| **IDOR download** | `?file=` param mentah (proteksi hanya nama tak tertebak) | `file` = token AEAD terikat sesi; nama mentah ditolak 403 di router |
| **Server config** | Directory listing aktif; `README` / `docs` / `database/*.sql` ikut tersaji | `Options -Indexes` + deny-list; deployment hanya berkas publik |

**Pola yang konsisten** di semua perbaikan ini:

- **Fail-closed** - kalau ragu, tolak.
- **Deny-by-default** - yang tidak eksplisit diizinkan, ditolak.
- **Satu tempat untuk satu kontrol** (session, headers, CSRF helper) - supaya tidak ada jalur yang "lupa".

> Rincian per temuan: [`docs/intern/PENTEST-REPORT-2026-09-08.md`](docs/intern/PENTEST-REPORT-2026-09-08.md) dan [`docs/intern/SECURITY_AUDIT.md`](docs/intern/SECURITY_AUDIT.md)

---

## Lab Pentest Terisolasi - Strix Agent di Dalam Docker

Untuk menguji aplikasi dari **sudut pandang penyerang** tanpa risiko menyentuh environment kerja, saya menjalankan agent pentester otomatis ([Strix](https://github.com/usestrix/strix)) di dalam **container Docker yang terisolasi (sandbox)**.

Bagian yang penting untuk digarisbawahi: **agent-nya yang disandbox, bukan aplikasinya.**

### Kenapa disandbox

Agent pentest secara desain melakukan hal-hal ofensif (kirim payload XSS, probe SQLi, enumerasi route). Menjalankannya di sandbox memberi dua jaminan:

- **Isolasi** - aktivitas ofensif tidak bisa menyentuh berkas/sistem di luar scope pengujian.
- **Reproduksibilitas** - lingkungan bersih dan tidak bergantung pada state host, sehingga hasil konsisten.

### Alur yang dijalankan

1. Container Docker terisolasi dibangun sebagai lingkungan lab (agent + runtime terkurung).
2. Aplikasi diletakkan sebagai target yang boleh diuji (authorized - aplikasi sendiri, localhost).
3. Strix dijalankan dalam dua fase:
 - **Quick** - blackbox tanpa login (permukaan publik).
 - **Deep** - authenticated 3 role (mahasiswa / dosen / admin), fokus pada Broken Access Control, IDOR, dan privilege escalation.
4. Artefak hasil (SARIF, coverage ledger, report) disalin keluar container ke [`docs/intern/pentest-strix/`](docs/intern/pentest-strix/) sebagai bukti run Strix yang verbatim.

### Hasil (bukti, bukan klaim)

| Fase | Cakupan | Hasil |
| --- | --- | --- |
| Quick | 6 surface, blackbox | 1 temuan MEDIUM (robots.txt) - *sudah diremediasi* |
| Deep | 8 surface, authenticated 3 role | **0 vulnerability terkonfirmasi**; 1 bug fungsional (upload 500) |

Setiap temuan diremediasi lalu diverifikasi ulang, dan kelas payload-nya dijadikan regresi otomatis (`tests/security/`) supaya tidak kembali muncul. Hasil pentest tidak berhenti sebagai laporan PDF yang dibaca sekali - ia berubah menjadi tes yang terus menjaga.

> Bukti run: [`docs/intern/pentest-strix/`](docs/intern/pentest-strix/) - ringkasan & status remediasi: [`docs/intern/pentest-strix/README.md`](docs/intern/pentest-strix/README.md)

---

## Yang Dibuktikan (Bukan Diklaim)

- **160/160 tes hijau** pada pipeline CI (lint + unit + integration + security regression + E2E Playwright chromium).
- **Suite keamanan** yang mereplay kelas payload pentest - deny matrix, IDOR, CSRF, XSS, upload - dengan target `failed: 0`.
- **0 vulnerability terkonfirmasi** pada pentest otomatis Strix, dijalankan di container Docker terisolasi, authenticated 3-role. Artefak tersimpan di [`docs/intern/pentest-strix/`](docs/intern/pentest-strix/), dengan klaim yang jujur: *"clean pentest bukan jaminan aman 100%."*
- **Bug yang hanya muncul di CI** (case-sensitivity nama tabel `NEWS` antara Windows dev dan Linux runner) ditemukan dan diperbaiki - contoh nyata mengapa environment-aware testing itu penting.

---

## Refleksi: Apa yang Saya Lakukan Berbeda

1. **Membuat kesalahan jadi tidak mungkin terjadi**, bukan mengandalkan ingatan (desain token).
2. **Mencari akar, bukan gejala** (reproduksi terkendali untuk membongkar 500 generik).
3. **Tahu kapan harus berhenti** (coverage: kualitas, bukan angka).
4. **Menjaga kebersihan artefak** - commit atomik (Conventional Commits), dokumentasi terstruktur (Diátaxis: tutorial / how-to / reference / explanation), dan jejak teknis ditaruh di `docs/intern/` alih-alih mengotori landing page repo.
5. **Kejujuran teknis** - membedakan "temuan keamanan" dari "bug fungsional", dan menulis disclaimer yang tidak berlebihan.

---

## Teknologi & Infrastruktur

| Lapisan | Pilihan |
| --- | --- |
| Bahasa & runtime | PHP 8.3, tanpa framework |
| Data | MySQL / MariaDB via PDO (prepared statements, native prepares) |
| Routing | Registry-based central router (`router.php`) |
| Auth | Session-based + ID token AEAD terikat sesi (anti-IDOR) |
| CLI | `artisan` kustom (migrate/seed/serve) - zero Composer dependency |
| Pengujian | Test runner kustom + harness `php -S` + Playwright E2E |
| Coverage | Xdebug (line coverage, CLI + server digabung) |
| Pentest (lab) | Strix agent di container Docker terisolasi; hasil disalin ke `docs/intern/pentest-strix/` |
| CI | GitHub Actions: lint, migrate+seed, full suite, E2E |

---

*Sumber: [Dimas0824/TataTertibMhsV2](https://github.com/Dimas0824/TataTertibMhsV2) - Refactor dari [TataTertibMhs (VarizkyNaldiba)](https://github.com/VarizkyNaldiba)*
