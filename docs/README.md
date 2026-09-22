# Dokumentasi DiscipLink V2

Selamat datang. Dokumentasi ini mengikuti [Diataxis Framework](https://diataxis.fr/) yang memisahkan dokumentasi berdasarkan tujuan pembaca.

---

## Temukan Dokumentasi yang Kamu Butuh

### Saya developer baru di project ini

**Mulai dari:**

1. [tutorial/getting-started.md](./tutorial/getting-started.md) - Setup dari nol dan eksekusi alur dasar

**Setelah paham dasar:**

- [reference/api.md](./reference/api.md) - Indeks teknis API dan file

---

### Saya perlu menyelesaikan task spesifik

**Mulai dari:**

1. [howto/recipes.md](./howto/recipes.md) - Cari task yang mirip, ikuti resepnya

**Contoh task yang tersedia:**

- Menambah halaman/rute baru
- Menambah endpoint action
- Upload file dengan aman
- Menambah kolom database
- Setup deployment

---

### Saya ingin memahami keputusan arsitektur

**Baca:**

- [explanation/architecture.md](./explanation/architecture.md) - Penjelasan mendalam:
 - Kenapa PHP native tanpa framework
 - Kenapa encrypted token ID untuk IDOR prevention
 - Kenapa session-based auth
 - Decision log lainnya

---

### Saya perlu tahu kebijakan keamanan

**Baca:**

- [explanation/security.md](./explanation/security.md) - Filosofi keamanan, ancaman yang dicegah, dan mitigasi

---

### Saya ingin melihat hasil pengujian keamanan (audit/pentest)

**Baca folder `intern/`** (laporan internal - bukti, bukan tutorial):

- [intern/README.md](./intern/README.md) - Index semua laporan internal
- [intern/PENTEST-REPORT-2026-09-08.md](./intern/PENTEST-REPORT-2026-09-08.md) - Audit code-level (auth, injection, file handling, XSS, server config)
- [intern/pentest-strix/](./intern/pentest-strix/README.md) - Pentest otomatis agen AI (quick + deep, authenticated 3 role)
- [intern/SECURITY_AUDIT.md](./intern/SECURITY_AUDIT.md) - Audit baseline PHP security
- [intern/UPLOAD-500-INVESTIGATION.md](./intern/UPLOAD-500-INVESTIGATION.md) - Investigasi bug fungsional upload (root cause + fix)
- [intern/BUG_REPORT.md](./intern/BUG_REPORT.md) - Bug tracking (historis, semua sudah FIXED/VERIFIED)
- [intern/TESTING-SUMMARY.md](./intern/TESTING-SUMMARY.md) - Ringkasan infrastruktur testing

**Kebijakan keamanan & cara melaporkan kerentanan:** [`../SECURITY.md`](../SECURITY.md)

---

### Saya butuh referensi teknis cepat

**Langsung ke:**

- [reference/api.md](./reference/api.md) - Tabel controller, model, helper, route registry
- [reference/database.md](./reference/database.md) - CLI commands, migrasi

---

## Struktur Dokumentasi

```
docs/
  README.md              <- Navigasi (anda di sini)
  tutorial/
    getting-started.md  <- Tutorial: setup & belajar
  howto/
    recipes.md          <- How-to: resep task
  reference/
    api.md              <- Reference: API & file
    database.md         <- Reference: CLI & database
  explanation/
    architecture.md     <- Explanation: keputusan arsitektur
    security.md         <- Explanation: keamanan
  intern/
      README.md                      <- Index laporan internal
      PENTEST-REPORT-2026-09-08.md   <- Audit code-level
      pentest-strix/                 <- Pentest otomatis (Strix)
      SECURITY_AUDIT.md              <- Audit baseline
      UPLOAD-500-INVESTIGATION.md    <- Investigasi bug upload
      BUG_REPORT.md                  <- Bug tracking (historis)
      TESTING-SUMMARY.md             <- Ringkasan testing
```

---

## Quick Reference

| Jenis | Dokumen | Gunakan ketika... |
| ------- | --------- | ------------------- |
| **Tutorial** | [tutorial/getting-started.md](./tutorial/getting-started.md) | Pertama kali setup project |
| **How-to** | [howto/recipes.md](./howto/recipes.md) | Perlu tambah fitur/fix bug |
| **Reference** | [reference/api.md](./reference/api.md) | Butuh detail API/helper |
| **Reference** | [reference/database.md](./reference/database.md) | Setup database, CLI |
| **Explanation** | [explanation/security.md](./explanation/security.md) | Pahami keputusan keamanan |
| **Explanation** | [explanation/architecture.md](./explanation/architecture.md) | Pahami filosofi arsitektur |
| **Internal** | [intern/](./intern/pentest-strix/README.md) | Lihat hasil audit/pentest & bug tracking |

---

## Diataxis Framework

Dokumentasi ini dipisah berdasarkan tujuan pembaca:

| Jenis | Fokus | Contoh |
| ------- | ------- | -------- |
| **Tutorial** | Belajar langkah demi langkah | "Ikuti panduan ini untuk setup project" |
| **How-to** | Solve problem spesifik | "Cara menambah halaman baru" |
| **Reference** | Detail teknis, lookup | "Daftar API endpoint" |
| **Explanation** | Pahami konsep | "Kenapa kita pakai PHP native" |
