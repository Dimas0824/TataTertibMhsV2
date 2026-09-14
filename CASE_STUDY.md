# Case Study — DiscipLink V2

> **TL;DR** Sebuah sistem informasi tata tertib mahasiswa (PHP native, tanpa framework) yang
> dibangun dengan satu prinsip: **otorisasi dan keamanan ditegakkan di server, deny-by-default**,
> lalu dibuktikan lewat pengujian berlapis — bukan diklaim. Dokumen ini menceritakan **bagaimana
keputusan diambil**, bukan sekadar daftar fitur.

---

## Konteks

**Masalah.** Pencatatan pelanggaran tata tertib mahasiswa umumnya masih manual (kertas/spreadsheet):
sulit dilacak, rawan hilang, dan tidak ada jejak siapa mengubah apa. Mahasiswa tidak punya transparansi atas poin pelanggaran mereka; dosen tidak punya rekap cepat untuk konfirmasi.

**Peran saya.** Desain arsitektur, implementasi, hardening keamanan, infrastruktur pengujian, dan dokumentasi — dari nol sampai pipeline CI hijau.

**Yang dipakai.** PHP 8.3 native · PDO/MySQL · HTML/CSS/JS (vanilla) · custom `artisan` CLI ·
test runner sendiri · GitHub Actions (lint + test + E2E Playwright).

---

## Keputusan 1 — Otorisasi objek: ID buram yang terikat sesi (bukan cek manual tiap query)

**Situasi.** Setiap pelanggaran, notifikasi, dan file punya ID yang muncul di URL/form. Model akses
naif (`WHERE id = ?` + percaya role) adalah tanah subur **IDOR**: cukup ganti angka `12` jadi `13`
untuk mengintip data orang lain.

**Pilihan yang dipertimbangkan:**

1. Cek kepemilikan manual di *setiap* query — rawan lupa di satu tempat, dan satu kelalaian = satu kebocoran.
2. **ID buram ber-enkripsi (AEAD) yang terikat `session_id` + kedaluwarsa** — objek tidak bisa dirujuk tanpa token sah, dan token tidak bisa dipindah antar sesi.

**Yang dipilih: #2.** Setiap referensi objek melewati `app_id_token()` / `app_id_resolve()` dengan
enkripsi AEAD (sodium secretbox, fallback AES-256-GCM), binding ke hash sesi, dan TTL. Efeknya:

- Ganti `id` sembarangan → token gagal didekripsi → ditolak, bukan "query yang lupa dicek".
- Replay lintas sesi/role → ditolak secara kriptografis.
- Token file (`?file=`) juga token, bukan path → path traversal jadi tidak relevan.

**Yang saya pelajari.** Membuat *path salah* jadi tidak mungkin itu lebih kuat daripada mengingat
untuk memeriksa di setiap tempat. Ini trade-off yang sadar: sedikit lebih rumit di sisi token,
tapi menghilangkan seluruh kelas kerentanan.

---

## Keputusan 2 — Menutup HTTP 500 di endpoint upload (dan kenapa ini penting)

**Gejala.** `POST /action/upload` mengembalikan `500 Internal Server Error` untuk user **yang sah** —
memblokir alur inti (mahasiswa tak bisa unggah surat/tugas). Semua permukaan lain 200. Error-nya
generik karena production mematikan `display_errors` (fail-closed).

**Proses diagnosis (yang justru jadi inti cerita ini):**

1. **Reproduksi** alur asli: login → ambil CSRF + token `id_detail` → kirim multipart PNG valid →
   dapat 500. Konsisten, bukan flaky.
2. **Isolasi.** Jalankan query otorisasi handler di harness CLI dengan `display_errors=1` →
   muncul akar sebenarnya: `PDOException: SQLSTATE[HY093] Invalid parameter number`.
3. **Akar masalah.** Query memakai **nama placeholder yang sama dua kali** (`:role` ×2, `:nidn` ×2).
   Karena app memakai `PDO::ATTR_EMULATE_PREPARES = false` (native prepare), MySQL **menolak**
   placeholder duplikat. Di mode emulasi dulu ini lolos — sekarang tidak.
4. **Fix.** Tiap kemunculan diberi nama unik (`:roleMahasiswa`, `:roleDosen`, `:nidnPenanggung`).
5. **Verifikasi.** 500 → 200 live. Ditambah **regresi** (`UploadOwnershipSuite`) yang membuktikan
   alur sukses pemilik *dan* penolakan cross-user, supaya tidak balik lagi.

**Kenapa ini bukan sekadar "tambal bug".** Handler-nya `try/catch` dan mengembalikan pesan generik —
jadi bug-nya *tersembunyi*. Yang menyelesaikannya bukan `try/catch` tambahan, tapi **menemukan sebab
sebenarnya lewat reproduksi terkendali**. Itulah bedanya menambal gejala vs mengobati penyakit.

> Detail lengkap: [`docs/intern/UPLOAD-500-INVESTIGATION.md`](docs/intern/UPLOAD-500-INVESTIGATION.md).

---

## Keputusan 3 — Target coverage itu "kualitas", bukan angka

Saat ditantang menaikkan line coverage ke 85%, saya mengukur dulu dengan **Xdebug** (line coverage
asli, menggabungkan proses CLI + server HTTP). Hasil: **76.3%**, dengan fungsi-fungsi inti sudah
**>80%** (auth, token, otorisasi objek, upload, model pelanggaran — bahkan `TatibController` 100%).

Lalu muncul keputusan yang **sadar untuk berhenti**: sisa baris yang belum ter-cover mayoritas
adalah **catch-block defensif** dan cabang error yang hanya bisa dipicu dengan **memaksa kegagalan
buatan**. Mengejarnya sampai 85% berarti menulis tes yang menguji *implementasi detail*, bukan
*perilaku yang bermakna*.

**Keputusan: tidak memaksakan angka.** Menulis tes pantas untuk ditinggalkan bila marginal value-nya
nol. Yang penting adalah **kelas perilaku kritis** (otorisasi, token, upload, sanitasi) punya
regresi, dan itu sudah tercapai.

> Filosofi ini terdokumentasi di [`tests/README.md`](tests/README.md) dan [`README.md`](README.md).

---

## Hardening dari sisi kode

Selain dua keputusan desain di atas, ada satu gelombang hardening terarah (audit code-level
3-auditor paralel) yang menutup temuan di lapisan aplikasi **dan** server config. Semua diperbaiki
di kode, bukan di dokumentasi:

| Kelas | Sebelum | Sesudah (di kode) |
|-------|---------|-------------------|
| **Secret exposure** | `.env` & `storage/keys/app_token.key` bisa diunduh via HTTP | `.htaccess` + guard `router.php` deny fail-closed; guardrail statis menolak regresi |
| **Autentikasi** | seed password plaintext; enumerasi user via timing | bcrypt (cost 12) wajib; throttle 5 gagal/15 mnt + dummy-hash verify (anti timing-leak) |
| **Session** | cookie anonim mewarisi default `php.ini` pada entry path non-router | `session_start()` mentah diganti `app_session_start_if_needed()` (HttpOnly/SameSite/Secure) |
| **CSRF** | dua jalur POST state-changing tanpa verifikasi | `app_verify_csrf()` di seluruh jalur; token dirotasi saat login |
| **XSS** | teks DB mentah di `<option>`/atribut view | escaping `htmlspecialchars(ENT_QUOTES)` seragam; flash JSON `JSON_HEX_TAG\|JSON_HEX_AMP` |
| **Header** | hanya HSTS kondisional | CSP + `X-Frame-Options: DENY` + `nosniff` + `Referrer-Policy` di satu fungsi |
| **File upload** | MIME fail-open (percaya `Content-Type` klien), `mkdir 0777` | fail-closed MIME (`finfo`), mode `0755`, nama file acak |
| **IDOR download** | `?file=` param mentah (proteksi hanya nama tak tertebak) | `file` = token AEAD terikat sesi; nama mentah ditolak 403 di router |
| **Server config** | directory listing aktif; `README/docs/database/*.sql` tersaji | `Options -Indexes` + deny-list; deployment hanya berkas publik |

Pola yang konsisten: **fail-closed** (kalau ragu → tolak), **deny-by-default** (yang tidak eksplisit
diizinkan → ditolak), dan **satu tempat** untuk tiap kontrol (session, headers, CSRF helper) supaya
tidak ada jalur yang "lupa".

> Rincian per temuan: [`docs/intern/PENTEST-REPORT-2026-09-08.md`](docs/intern/PENTEST-REPORT-2026-09-08.md)
> dan [`docs/intern/SECURITY_AUDIT.md`](docs/intern/SECURITY_AUDIT.md).

---

## Lab pentest terisolasi — Strix agent di dalam Docker

Untuk menguji aplikasi dari **sudut pandang penyerang** tanpa risiko menyentuh environment kerja,
saya menjalankan agent pentester otomatis ([Strix](https://github.com/usestrix/strix)) di dalam
**container Docker yang terisolasi (sandbox)** — inilah bagian yang penting: **agent yang disandbox,
bukan aplikasinya**.

**Kenapa disandbox.** Agent pentest itu secara desain melakukan hal-hal ofensif (kirim payload XSS,
probe SQLi, enumerasi route). Menjalankannya di sandbox memberi dua jaminan:
- **Isolasi** — aktivitas ofensif tidak bisa menyentuh berkas/sistem di luar scope pengujian.
- **Reproduksibilitas** — lingkungan bersih & tidak bergantung state host, jadi hasil konsisten.

**Alur yang dijalankan:**
1. Container Docker terisolasi dibangun sebagai lingkungan lab (agent + runtime terkurung).
2. Aplikasi diletakkan sebagai **target** yang boleh diuji (authorized — app sendiri, localhost).
3. Strix dijalankan dalam **dua fase**:
   - **quick** — blackbox tanpa login (permukaan publik).
   - **deep** — authenticated **3 role** (mahasiswa / dosen / admin), fokus Broken Access Control /
     IDOR / privilege escalation.
4. Artefak hasil (SARIF, coverage ledger, report) **disalin keluar** container ke
   [`docs/intern/pentest-strix/`](docs/intern/pentest-strix/) sebagai **bukti run Strix yang verbatim**.

**Hasil yang jadi bukti (bukan klaim):**

| Fase | Cakupan | Hasil |
|------|---------|-------|
| quick | 6 surface, blackbox | 1 temuan MEDIUM (robots.txt) — *sudah diremediasi* |
| deep | 8 surface, authenticated 3 role | **0 vulnerability terkonfirmasi**; 1 bug fungsional (upload 500) |

Setiap temuan diremediasi lalu **diverifikasi ulang**, dan kelas payload-nya dijadikan **regresi
otomatis** (`tests/security/`) supaya tidak balik. Jadi hasil pentest bukan cuma laporan PDF yang
dibaca sekali — ia berubah jadi tes yang menjaga.

> Bukti run: [`docs/intern/pentest-strix/`](docs/intern/pentest-strix/) ·
> ringkasan & status remediasi: [`docs/intern/pentest-strix/README.md`](docs/intern/pentest-strix/README.md).

---

## Yang dibuktikan (bukan diklaim)

- **160/160 tes hijau** pada pipeline CI (lint + unit + integration + security regression + E2E
  Playwright chromium).
- **Suite keamanan** yang mereplay kelas payload pentest: deny matrix, IDOR, CSRF, XSS, upload —
  target `failed 0`.
- **0 vulnerability terkonfirmasi** pada pentest otomatis Strix (agent AI) yang dijalankan di
  **container Docker terisolasi**, authenticated 3-role; artefak hasil tersimpan di
  [`docs/intern/pentest-strix/`](docs/intern/pentest-strix/), dengan klaim yang **jujur**:
  *"clean pentest bukan jaminan aman 100%"*.
- **Bug yang hanya muncul di CI** (case-sensitivity nama tabel `NEWS` antara Windows dev dan Linux
  runner) ditemukan & diperbaiki — contoh nyata kenapa environment-aware testing penting.

---

## Refleksi: apa yang saya lakukan berbeda

1. **Membuat kesalahan jadi tidak mungkin**, bukan mengandalkan ingatan (desain token).
2. **Mencari akar, bukan gejala** (reproduksi terkendali untuk membongkar 500 generik).
3. **Tahu kapan berhenti** (coverage: kualitas > angka).
4. **Menjaga kebersihan artefak**: commit atomik (Conventional Commits), dokumentasi terstruktur
   (Diátaxis: tutorial / how-to / reference / explanation), dan jejak teknis ditaruh di `docs/intern/`
   alih-alih mengotori landing page repo.
5. **Kejujuran teknis**: membedakan "temuan keamanan" vs "bug fungsional", dan menulis disclaimer
   yang tidak berlebihan.

---

## Teknologi & infrastruktur

| Lapisan | Pilihan |
| --------- | --------- |
| Bahasa & runtime | PHP 8.3, tanpa framework |
| Data | MySQL / MariaDB via PDO (prepared statements, native prepares) |
| Routing | Registry-based central router (`router.php`) |
| Auth | Session-based + ID token AEAD terikat sesi (anti-IDOR) |
| CLI | `artisan` kustom (migrate/seed/serve) — zero Composer dependency |
| Pengujian | Test runner kustom + harness `php -S` + Playwright E2E |
| Coverage | Xdebug (line coverage, CLI + server digabung) |
| Pentest (lab) | Strix agent di **container Docker terisolasi**; hasil disalin ke `docs/intern/pentest-strix/` |
| CI | GitHub Actions: lint, migrate+seed, full suite, E2E |

---

*Sumber: [Dimas0824/TataTertibMhsV2](https://github.com/Dimas0824/TataTertibMhsV2) · Refactor dari
[TataTertibMhs (VarizkyNaldiba)](https://github.com/VarizkyNaldiba).*
