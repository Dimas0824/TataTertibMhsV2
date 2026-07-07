# Dokumentasi DiscipLink V2

Selamat datang. Dokumentasi ini mengikuti [Diataxis Framework](https://diataxis.fr/) yang memisahkan dokumentasi berdasarkan tujuan pembaca.

---

## Temukan Dokumentasi yang Kamu Butuh

### Saya developer baru di project ini

**Mulai dari:**
1. [tutorial/getting-started.md](./tutorial/getting-started.md) — Setup dari nol dan eksekusi alur dasar

**Setelah paham dasar:**
- [reference/api.md](./reference/api.md) — Indeks teknis API dan file

---

### Saya perlu menyelesaikan task spesifik

**Mulai dari:**
1. [howto/recipes.md](./howto/recipes.md) — Cari task yang mirip, ikuti resepnya

**Contoh task yang tersedia:**
- Menambah halaman/rute baru
- Menambah endpoint action
- Upload file dengan aman
- Menambah kolom database
- Setup deployment

---

### Saya ingin memahami keputusan arsitektur

**Baca:**
- [explanation/architecture.md](./explanation/architecture.md) — Penjelasan mendalam:
  - Kenapa PHP native tanpa framework
  - Kenapa encrypted token ID untuk IDOR prevention
  - Kenapa session-based auth
  - Decision log lainnya

---

### Saya perlu tahu kebijakan keamanan

**Baca:**
- [explanation/security.md](./explanation/security.md) — Filosofi keamanan, ancaman yang dicegah, dan mitigasi

---

### Saya butuh referensi teknis cepat

**Langsung ke:**
- [reference/api.md](./reference/api.md) — Tabel controller, model, helper, route registry
- [reference/database.md](./reference/database.md) — CLI commands, migrasi

---

## Struktur Dokumentasi

```
docs/
├── README.md              ← Navigasi (anda di sini)
├── tutorial/
│   └── getting-started.md  ← Tutorial: setup & belajar
├── howto/
│   └── recipes.md          ← How-to: resep task
├── reference/
│   ├── api.md              ← Reference: API & file
│   └── database.md         ← Reference: CLI & database
└── explanation/
    ├── architecture.md     ← Explanation: keputusan arsitektur
    └── security.md         ← Explanation: keamanan
```

---

## Quick Reference

| Jenis | Dokumen | Gunakan ketika... |
|-------|---------|-------------------|
| **Tutorial** | [tutorial/getting-started.md](./tutorial/getting-started.md) | Pertama kali setup project |
| **How-to** | [howto/recipes.md](./howto/recipes.md) | Perlu tambah fitur/fix bug |
| **Reference** | [reference/api.md](./reference/api.md) | Butuh detail API/helper |
| **Reference** | [reference/database.md](./reference/database.md) | Setup database, CLI |
| **Explanation** | [explanation/security.md](./explanation/security.md) | Pahami keputusan keamanan |
| **Explanation** | [explanation/architecture.md](./explanation/architecture.md) | Pahami filosofi arsitektur |

---

## Diataxis Framework

Dokumentasi ini dipisah berdasarkan tujuan pembaca:

| Jenis | Fokus | Contoh |
|-------|-------|--------|
| **Tutorial** | Belajar langkah demi langkah | "Ikuti panduan ini untuk setup project" |
| **How-to** | Solve problem spesifik | "Cara menambah halaman baru" |
| **Reference** | Detail teknis, lookup | "Daftar API endpoint" |
| **Explanation** | Pahami konsep | "Kenapa kita pakai PHP native" |
