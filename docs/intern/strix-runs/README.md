# Strix Pentest Runs — DiscipLink

Riwayat pentest otomatis [Strix](https://github.com/usestrix/strix) terhadap **DiscipLink**,
diorganisir **per tanggal run**. Tujuannya: setiap temuan bisa dilacak → diperbaiki →
**diverifikasi ulang** oleh run berikutnya.

| Tanggal | Jenis | Isi | Hasil |
| ------- | ----- | --- | ----- |
| [**2026-09-21**](strix-2026-09-21/) | Run pertama (5 area, white-box) | `areaN/before/` (temuan) + `areaN/after/` (bukti fix + reproduce) | 6 temuan: 1 CRITICAL, 1 HIGH, 3 MEDIUM, 1 LOW |
| [**2026-09-22**](strix-2026-09-22/) | Re-scan verifikasi (instruksi identik) | `areaN/` (artefak re-scan) + `verification-analysis/` | **6 temuan 21-09 HILANG** → fix terbukti; 5 temuan baru (1 false positive + 4 valid, sudah difix) |

## Alur

```
2026-09-21  run pertama  ──►  6 temuan  ──►  fix (commit)  ──►  test regression
                                                                    │
2026-09-22  re-scan  ◄──────────────────────────────────────────────┘
            │
            ├─ 6 temuan lama HILANG  →  fix terbukti
            └─ 5 temuan baru         →  1 false positive + 4 valid (sudah difix)
```

## Konvensi folder

```
strix-runs/
├── strix-2026-09-21/            # run pertama
│   ├── areaN/before/            # artefak Strix (temuan)
│   ├── areaN/after/             # bukti fix: reproduce.sh, reproduce-after.log, screenshot
│   ├── 00-CONSOLIDATED-REPORT.md
│   ├── COMBINED.sarif
│   └── instructions/
└── strix-2026-09-22/            # re-scan verifikasi
    ├── areaN/                   # artefak re-scan (termasuk temuan baru)
    ├── verification-analysis/   # analisis + bukti false positive
    └── README.md
```

Setiap `areaN/` menyimpan artefak **verbatim** dari Strix: `findings.sarif` (SARIF 2.1.0,
`tool=Strix`), `vulnerabilities/*.md` (PoC), `penetration_test_report.md`, `vulnerabilities.csv/json`,
`run.json`, `strix.log`, dan `.state/` (database percakapan agent).

## Catatan

- Semua pengujian **authorized** terhadap instance lokal milik sendiri (`http://172.17.112.1:8001`).
- Artefak dimasukkan **apa adanya** (verbatim) — bukan rangkuman tangan.
- Perbaikan tiap temuan punya **regression test** di `tests/security/**` dan `tests/unit/**`.
