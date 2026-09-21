# Strix Re-Scan — 2026-09-22 (verification of the 2026-09-21 fixes)

Run **kedua** Strix, dijalankan terhadap kode yang **sudah diperbaiki** dari temuan
run pertama (2026-09-21). Instruksi + parameter **identik** dengan run pertama
(MD5 instruksi diverifikasi sama) supaya perbandingan before/after sah.

**Tujuan:** membuktikan secara independen bahwa temuan 2026-09-21 sudah tertutup,
dan menemukan celah baru yang muncul setelah perbaikan.

> **Halaman ini = daftar celah keamanan BARU hasil re-scan.** Kalau kamu
> datang untuk memeriksa *"apakah temuan 2026-09-21 sudah selesai?"* - jawabannya
> ada di [`../strix-2026-09-21/README.md`](../strix-2026-09-21/README.md)
> (section *Verifikasi penutupan*). Kalau kamu datang untuk *"apa celah baru
> setelah fix?"* - kamu ada di halaman yang tepat (tabel di bawah + `areaN/README.md`).

> Folder run pertama: [`../strix-2026-09-21/`](../strix-2026-09-21/)

---

## Ringkasan hasil

| Area | Temuan 2026-09-21 | Status di re-scan | Temuan BARU 2026-09-22 | Klasifikasi | Fix |
| ---- | ----------------- | ----------------- | ---------------------- | ----------- | --- |
| 1 LOGIN | CRITICAL (lockout per-sesi) + HIGH (NUL-byte) | ✅ **HILANG** | case-variant lockout (CRITICAL) | ❌ false positive | — |
| 2 SESSION/CSRF | MEDIUM (cookie Secure) + LOW (lifetime) | ✅ **HILANG** | use_strict_mode (MEDIUM) | ✅ valid | `a56baca` |
| 3 UPLOAD/IDOR | (0 temuan) | — | missing role guard → 500 (MEDIUM) | ✅ valid | `d4aec0e` |
| 4 PELANGGARAN | MEDIUM (sanksi tier) | ✅ **HILANG** | delete finalized violation (HIGH) | ✅ valid | `579a4c6` |
| 5 NEWS | MEDIUM (stored XSS body) | ✅ **HILANG** | XSS via JSON-LD title (MEDIUM) | ✅ valid | `1181dec` |

**Kesimpulan:**
- **Seluruh 5 temuan 2026-09-21 HILANG** → perbaikan (fix) terbukti efektif.
- Re-scan menemukan **5 temuan baru**: 1 false positive + 4 valid.
- **4 temuan valid sudah diperbaiki** (commit di tabel atas).

---

## Analisis: mengapa satu temuan adalah false positive

Re-scan (area 1) melaporkan CRITICAL *"Login lockout can be bypassed via
case-varied account identifiers"* — yaitu klaim bahwa throttle case-sensitive
sedangkan lookup akun case-insensitive, sehingga varian huruf besar/kecil
mendapat budget terpisah.

**Klaim ini keliru.** Bukti (4 lapis, lihat [`verification-analysis/prove_case_variant_not_bypass.py`](verification-analysis/prove_case_variant_not_bypass.py)):

1. Kolom `SECURITY_AUDIT_LOG.actor_id` bercollation `utf8mb4_unicode_ci` → perbandingan **case-insensitive**.
2. `WHERE actor_id='admin001'` **cocok** dengan baris `'ADMIN001'`.
3. Unit: 5 gagal `ADMIN001` → varian `admin001/Admin001/aDmIn001` semua `locked=TRUE`.
4. End-to-end HTTP (skenario Strix sendiri): semua varian **terkunci** (time 0.04–0.06s = short-circuit throttle).

Strix menyimpulkan dari **pembacaan kode** dan mengasumsikan `=` case-sensitive;
MySQL membandingkan mengikuti **collation kolom** (`_ci`). Strix juga mengakui
PoC end-to-end-nya gagal (kena IP cap).

Detail lengkap: [`verification-analysis/README.md`](verification-analysis/README.md)

---

## Struktur folder

Mengikuti [kontrak struktur & dokumentasi](../README.md#struktur-wajib--sama-untuk-setiap-tanggal):

```
strix-2026-09-22/
├── README.md                    (dokumen ini)
├── instructions/                instruksi area yang dijalankan (identik dgn run 21-09)
├── verification-analysis/       analisis + skrip bukti false positive
├── area1-login/
│   ├── README.md                indeks area
│   ├── before/                  findings.sarif, vulnerabilities/, run.json, strix.log, .state/
│   └── after/README.md          bukti perbaikan temuan BARU area ini (bila ada)
├── area2-session-csrf/          (before/ + after/README.md)
├── area3-upload-idor/
├── area4-pelanggaran/
└── area5-news-xss/
```

Setiap `areaN/before/` berisi artefak verbatim run re-scan (SARIF 2.1.0 tool=Strix,
PoC di `vulnerabilities/`, laporan, log, database percakapan agent).

## Catatan

- `after/` di sini memuat perbaikan **temuan BARU 2026-09-22** (4 valid, sudah
  difix; area 1 tidak punya temuan valid). Perbaikan ini dikunci **regression test**,
  tetapi **belum diverifikasi re-scan Strix ketiga** - atribut ini dinyatakan jujur
  di tiap `areaN/after/README.md`.
- Instruksi scan: [`instructions/`](instructions/) (sama dengan
  [`../strix-2026-09-21/instructions/`](../strix-2026-09-21/instructions/)).
