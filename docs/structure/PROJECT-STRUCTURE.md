# Project Structure (DiscipLink V2)

Dokumen ini menjelaskan struktur folder proyek setelah perapihan.

## Struktur Tingkat Atas

```text
.
├── controllers/              # Business flow orchestration
├── models/                   # Data access / query layer
├── request/                  # HTTP action handlers
├── views/                    # UI templates (public, admin, auth, components)
├── helpers/                  # Routing, token, SEO, path, error helpers
├── database/
│   ├── cli/                  # Console command engine (artisan backend)
│   ├── migrations/           # SQL migration files (tracked)
│   ├── seeders/              # SQL seed files (tracked)
│   └── legacy/               # Arsip SQL lama (non-active)
├── css/                      # Stylesheets per halaman/komponen
├── js/                       # Frontend scripts + minified pair
├── img/                      # Static image assets / favicon
├── document/                 # Uploaded documents (runtime)
├── storage/                  # Runtime private storage (keys)
├── docs/                     # Dokumentasi internal + screenshot
│   └── structure/            # Dokumentasi struktur folder & indeks file
├── index.php                 # Public homepage entry
├── router.php                # Central router untuk page/action routes
├── artisan                   # CLI runner (migrate/seed/serve)
└── config.php                # DB connection bootstrap
```

## Prinsip Organisasi

- `database/migrations` dan `database/seeders` adalah sumber resmi perubahan schema/data bootstrap.
- File SQL lama dipindahkan ke `database/legacy` agar tidak bercampur dengan migration pipeline aktif.
- Dokumentasi per folder disediakan melalui `README.md` lokal untuk mempercepat onboarding.
- Dokumentasi indeks file tersedia di `docs/structure/FILE-INDEX.md`.
