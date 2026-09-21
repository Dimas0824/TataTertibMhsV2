# Strix Pentest Runs — DiscipLink

Riwayat pentest otomatis [Strix](https://github.com/usestrix/strix) terhadap **DiscipLink**,
diorganisir **per tanggal run**. Tujuannya: setiap temuan bisa dilacak → diperbaiki →
**diverifikasi ulang** oleh run berikutnya.

> **Untuk agent / kontributor:** dokumen ini adalah **kontrak** struktur & cara
> dokumentasi. Baca bagian [Aturan Dokumentasi (WAJIB)](#aturan-dokumentasi-wajib)
> sebelum menambah run baru. Tujuannya: setiap run - dari tanggal mana pun, dikerjakan
> agent mana pun - punya bentuk folder, penamaan, dan isi README yang **identik polanya**.

| Tanggal | Jenis | Hasil |
| ------- | ----- | ----- |
| [**2026-09-21**](strix-2026-09-21/) | Run pertama (5 area, white-box) | 6 temuan: 1 CRITICAL, 1 HIGH, 3 MEDIUM, 1 LOW |
| [**2026-09-22**](strix-2026-09-22/) | Re-scan verifikasi (instruksi identik) | **6 temuan 21-09 HILANG** → fix terbukti; 5 temuan baru (1 false positive + 4 valid, sudah difix) |

## Alur

```
2026-09-21  run pertama  ──►  6 temuan  ──►  fix (commit)  ──►  test regression
                                                                     │
2026-09-22  re-scan  ◄──────────────────────────────────────────────┘
            │
            ├─ 6 temuan lama HILANG  →  fix terbukti
            └─ 5 temuan baru         →  1 false positive + 4 valid (sudah difix)
```

---

## Struktur (WAJIB — sama untuk setiap tanggal)

Setiap run hidup di `strix-runs/strix-<YYYY-MM-DD>/` dengan bentuk **persis** berikut.
`areaN` = 5 area tetap: `area1-login`, `area2-session-csrf`, `area3-upload-idor`,
`area4-pelanggaran`, `area5-news-xss`.

```
strix-runs/
└── strix-<YYYY-MM-DD>/          # satu folder per tanggal run
    ├── README.md                # WAJIB: ringkasan run ini (lihat "Isi README")
    ├── instructions/            # WAJIB: 5 file instruksi area (areaN_<slug>.md)
    ├── areaN-<slug>/            # WAJIB: 5 area
    │   ├── README.md            # WAJIB: indeks area (temuan + pointer before/after)
    │   ├── before/              # WAJIB: artefak mentah run (verbatim dari Strix)
    │   │   ├── findings.sarif
    │   │   ├── penetration_test_report.md
    │   │   ├── vulnerabilities/  # vuln-*.md (PoC), bila ada temuan
    │   │   ├── vulnerabilities.csv
    │   │   ├── vulnerabilities.json
    │   │   ├── run.json
    │   │   ├── strix.log
    │   │   └── .state/           # agents.db, agents.json, notes.json, todos.json
    │   └── after/               # WAJIB: bukti perbaikan (lihat aturan "after")
    │       ├── README.md         # sebelum→sesudah, commit, test, cara reproduce
    │       ├── reproduce.sh      # skrip verifikasi (bila dapat diskripkan)
    │       ├── reproduce-after.log
    │       └── evidence-*.png    # screenshot bukti (bila ada)
    └── verification-analysis/    # OPSIONAL: analisis khusus (mis. bukti false positive)
```

### Kenapa `before/` + `after/` di **setiap** tanggal?

- `before/` = **apa yang Strix temukan di run ini** (mentah, verbatim).
- `after/` = **bukti bahwa temuan run ini sudah ditutup** (reproduksi/test/screenshot).

Ini berlaku **juga untuk run re-scan**: pada run re-scan, `before/` memuat artefak
re-scan, dan `after/` memuat bukti perbaikan untuk **temuan baru** yang run itu
temukan. Bila run tersebut **tidak menemukan temuan valid** (mis. hanya false
positive), `after/README.md` tetap dibuat dan menyatakan "tidak ada temuan valid
yang perlu ditutup" + menunjuk ke analisisnya.

> **Aturan emas:** tidak ada run tanpa `before/`. Tidak ada `areaN/` dengan
> artefak telanjang di root-nya. Artefak mentah **selalu** di dalam `before/`.

---

## Aturan Dokumentasi (WAJIB)

Aturan ini mengikat supaya dokumentasi **seragam & persisten** antar agent/tanggal.

### A. Penamaan & tata letak

1. Folder tanggal: `strix-YYYY-MM-DD` (tanggal run, bukan tanggal commit).
2. 5 area selalu dengan slug tetap: `area1-login`, `area2-session-csrf`,
   `area3-upload-idor`, `area4-pelanggaran`, `area5-news-xss`.
3. Artefak mentah HANYA di `areaN/before/`. Dilarang menaruh `findings.sarif`,
   `run.json`, `.state/`, dll. langsung di `areaN/`.
4. Bukti perbaikan HANYA di `areaN/after/`.
5. File instruksi area: `instructions/areaN_<slug>.md` (mis. `area1_login.md`).
   Instruksi yang dijalankan disimpan **apa adanya** di setiap run (agar perbandingan
   before/after sah), termasuk pada run re-scan.

### B. Isi README (tiga level)

1. **`strix-runs/README.md`** (dokumen ini) — indeks semua tanggal + kontrak ini.
2. **`strix-<tanggal>/README.md`** — WAJIB memuat, minimal:
   - tujuan run (pertama / re-scan verifikasi),
   - ringkasan/table hasil,
   - **matriks penutupan**: temuan run **sebelumnya** yang HILANG (dengan link ke run ini),
   - **daftar temuan BARU** run ini, masing-masing dengan klasifikasi
     (`valid` / `false positive`) dan commit fix-nya, dengan link ke `areaN/`.
3. **`areaN/README.md`** — indeks area: tabel temuan area ini (ID, severity, CWE,
   ringkasan), pointer ke `before/` & `after/`, commit fix + regression test,
   dan status (HILANG / baru).

### C. Aturan `after/`

1. `after/README.md` harus menyatakan untuk **temuan mana** bukti ini, dengan
   tabel **sebelum vs sesudah** (perilaku rentan vs aman) bila dapat diuji runtime.
2. Cantumkan **commit fix** dan **regression test** (path di `tests/**`).
3. Bila perbaikan belum diverifikasi re-scan Strix ketiga, **nyatakan jujur**
   ("belum re-scan; dikunci regression test"). Dilarang mengklaim "terbukti" bila
   hanya berbasis test.
4. Bila area tidak punya temuan valid, tetap ada `after/README.md` yang menjelaskan
   hal itu + pointer ke `verification-analysis/` (bila ada).

### D. Klasifikasi temuan (jujur)

- Setiap temuan diberi label `valid` atau `false positive`, **dengan bukti**.
- Klaim false positive wajib disertai **verifikasi setara PoC** (runtime/HTTP/DB),
  bukan sekadar pembacaan kode — simpan bukti di `verification-analysis/`.
- Severity/CWE/CVSS diambil dari `vuln-*.md` Strix, bukan dikarang.

### E. Artefak & Git

1. Artefak dimasukkan **verbatim** (apa adanya) — bukan rangkuman tangan.
2. `strix.log` diizinkan masuk lewat pengecualian `.gitignore`
   (`!docs/intern/strix-runs*/**/strix.log`). Jangan menambah pengecualian lain
   tanpa alasan — log tool umumnya tidak di-commit.
3. **Dilarang** men-commit file transient: `*.db-shm`, `*.db-wal` (sidecar SQLite)
   dan scratch lokal lain. Bersihkan sebelum commit.
4. Semua pengujian **authorized**, hanya terhadap instance lokal milik sendiri.

### F. Checklist menambah run baru

- [ ] `strix-<tanggal>/README.md` ditulis (ringkasan + matriks penutupan + temuan baru).
- [ ] `instructions/` berisi 5 instruksi yang benar-benar dijalankan.
- [ ] Tiap `areaN/before/` berisi artefak mentah lengkap; `strix.log` ada.
- [ ] Tiap `areaN/after/README.md` ada (walau tanpa temuan valid).
- [ ] Tabel di `strix-runs/README.md` (dokumen ini) ditambah baris tanggal baru.
- [ ] `git status` bersih dari `-shm`/`-wal`/scratch.
- [ ] Link antar-README diuji resolve (relatif, benar).

## Catatan

- Semua pengujian **authorized** terhadap instance lokal milik sendiri (`http://172.17.112.1:8001`).
- Artefak dimasukkan **apa adanya** (verbatim) — bukan rangkuman tangan.
- Perbaikan tiap temuan punya **regression test** di `tests/security/**` dan `tests/unit/**`.
