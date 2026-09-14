# Case Study — DiscipLink V2

> **TL;DR** Sebuah sistem informasi tata tertib mahasiswa (PHP native, tanpa framework) yang
> dibangun dengan satu prinsip: **otorisasi dan keamanan ditegakkan di server, deny-by-default**,
> lalu dibuktikan lewat pengujian berlapis — bukan diklaim. Dokumen ini menceritakan **bagaimana
> keputusan diambil**, bukan sekadar daftar fitur.

---

## Konteks

**Masalah.** Pencatatan pelanggaran tata tertib mahasiswa umumnya masih manual (kertas/spreadsheet):
sulit dilacak, rawan hilang, dan tidak ada jejak siapa mengubah apa. Mahasiswa tidak punya
transparansi atas poin pelanggaran mereka; dosen tidak punya rekap cepat untuk konfirmasi.

**Peran saya.** Desain arsitektur, implementasi, hardening keamanan, infrastruktur pengujian, dan
dokumentasi — dari nol sampai pipeline CI hijau.

**Yang dipakai.** PHP 8.3 native · PDO/MySQL · HTML/CSS/JS (vanilla) · custom `artisan` CLI ·
test runner sendiri · GitHub Actions (lint + test + E2E Playwright).

---

## Keputusan 1 — Otorisasi objek: ID buram yang terikat sesi (bukan cek manual tiap query)

**Situasi.** Setiap pelanggaran, notifikasi, dan file punya ID yang muncul di URL/form. Model akses
naif (`WHERE id = ?` + percaya role) adalah tanah subur **IDOR**: cukup ganti angka `12` jadi `13`
untuk mengintip data orang lain.

**Pilihan yang dipertimbangkan:**

1. Cek kepemilikan manual di *setiap* query — rawan lupa di satu tempat, dan satu kelalaian = satu kebocoran.
2. **ID buram ber-enkripsi (AEAD) yang terikat `session_id` + kedaluwarsa** — objek tidak bisa
   dirujuk tanpa token sah, dan token tidak bisa dipindah antar sesi.

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

## Yang dibuktikan (bukan diklaim)

- **160/160 tes hijau** pada pipeline CI (lint + unit + integration + security regression + E2E
  Playwright chromium).
- **Suite keamanan** yang mereplay kelas payload pentest: deny matrix, IDOR, CSRF, XSS, upload —
  target `failed 0`.
- **0 vulnerability terkonfirmasi** pada pentest otomatis authenticated 3-role (terdokumentasi di
  [`docs/intern/pentest-strix/`](docs/intern/pentest-strix/)), dengan klaim yang **jujur**:
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
| CI | GitHub Actions: lint, migrate+seed, full suite, E2E |

---

*Sumber: [Dimas0824/TataTertibMhsV2](https://github.com/Dimas0824/TataTertibMhsV2) · Refactor dari
[TataTertibMhs (VarizkyNaldiba)](https://github.com/VarizkyNaldiba).*
