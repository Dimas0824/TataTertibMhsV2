# VULN-LOG - DiscipLink

> Indeks lintas-run semua temuan security. Satu baris = satu temuan.
> **Ini bukan pengganti README per-run** - untuk detail evidence, PoC, dan
> narasi lengkap, ikuti link di kolom `Detail` menuju folder run yang
> bersangkutan. Struktur & definisi kolom mengikuti
> [`SECURITY-DOC-STANDARD.md`](SECURITY-DOC-STANDARD.md).
>
> Diurutkan dari yang terbaru ke terlama. Update file ini adalah bagian dari
> *definition of done* setiap run pentest - lihat checklist di standar.

| ID | CWE | Area | Severity | Ditemukan | Diperbaiki | Commit Fix | Status Saat Ini | Detail |
|---|---|---|---|---|---|---|---|---|
| A2-vuln-0001 (2026-09-22) | CWE-384 | Session/CSRF | MEDIUM | 2026-09-22 | 2026-09-22 | `a56baca` | Confirmed-fixed | [vuln](strix-runs/strix-2026-09-22/area2-session-csrf/) |
| A3-vuln-0001 (2026-09-22) | CWE-284 | Upload/IDOR | MEDIUM | 2026-09-22 | 2026-09-22 | `d4aec0e` | Confirmed-fixed | [vuln](strix-runs/strix-2026-09-22/area3-upload-idor/) |
| A4-vuln-0001 (2026-09-22) | CWE-863 | Pelanggaran | HIGH | 2026-09-22 | 2026-09-22 | `579a4c6` | Confirmed-fixed | [vuln](strix-runs/strix-2026-09-22/area4-pelanggaran/) |
| A5-vuln-0001 (2026-09-22) | CWE-79 | News | MEDIUM | 2026-09-22 | 2026-09-22 | `1181dec` | Confirmed-fixed | [vuln](strix-runs/strix-2026-09-22/area5-news-xss/) |
| A1-vuln-0001 (2026-09-22) | CWE-307 | Login | CRITICAL (klaim) | 2026-09-22 | - | - | False-positive | [analisis](strix-runs/strix-2026-09-22/verification-analysis/) |
| A1-vuln-0002 (2026-09-21) | CWE-307 | Login | CRITICAL | 2026-09-21 | 2026-09-22 | `ba4e8d1` | Confirmed-fixed | [vuln](strix-runs/strix-2026-09-21/area1-login/before/vulnerabilities/vuln-0002.md) |
| A1-vuln-0001 (2026-09-21) | CWE-230 | Login | HIGH | 2026-09-21 | 2026-09-22 | `ba4e8d1` | Confirmed-fixed | [vuln](strix-runs/strix-2026-09-21/area1-login/before/vulnerabilities/vuln-0001.md) |
| A2-vuln-0001 (2026-09-21) | CWE-614 | Session | MEDIUM | 2026-09-21 | 2026-09-22 | `9f55f2a` | Confirmed-fixed | [vuln](strix-runs/strix-2026-09-21/area2-session-csrf/before/vulnerabilities/vuln-0001.md) |
| A2-vuln-0002 (2026-09-21) | CWE-613 | Session | LOW | 2026-09-21 | 2026-09-22 | `484bb67` | Confirmed-fixed | [vuln](strix-runs/strix-2026-09-21/area2-session-csrf/before/vulnerabilities/vuln-0002.md) |
| A4-vuln-0001 (2026-09-21) | CWE-20 | Pelanggaran | MEDIUM | 2026-09-21 | 2026-09-22 | `487dd93` | Confirmed-fixed | [vuln](strix-runs/strix-2026-09-21/area4-pelanggaran/before/vulnerabilities/vuln-0001.md) |
| A5-vuln-0001 (2026-09-21) | CWE-79 | News | MEDIUM | 2026-09-21 | 2026-09-22 | `7b4c39d` | Confirmed-fixed | [vuln](strix-runs/strix-2026-09-21/area5-news-xss/before/vulnerabilities/vuln-0001.md) |

---

## Catatan pengisian

- **Tabrakan ID antar-run.** Penomoran Strix mulai dari `vuln-0001` di tiap run,
  sehingga ID bisa sama di dua tanggal berbeda. ID yang bentrok diberi suffix
  `(tanggal)` - mis. `A1-vuln-0001 (2026-09-21)` dan `A1-vuln-0001 (2026-09-22)`
  adalah dua temuan berbeda. Konvensi ini didokumentasikan di
  [`SECURITY-DOC-STANDARD.md`](SECURITY-DOC-STANDARD.md) bagian 4.
- **Kolom `Severity`** memakai severity Strix apa adanya. Untuk entri false
  positive, ditulis `CRITICAL (klaim)` untuk menandai bahwa itu klaim Strix yang
  terbukti salah, bukan severity riil.
- **Kolom `Commit Fix`** diisi dari penelusuran `git log` pada tanggal terkait
  (temuan run 2026-09-21 diperbaiki dengan prefix `fix(auth)`/`fix(authz)`).
  Konvensi `fix(security): ... [CWE-XXX]` berlaku mulai run 2026-09-22, sesuai
  catatan historis di standar bagian 5.
- Baris false-positive **tidak dihapus** dari log ini - justru penting
  didokumentasikan supaya orang lain tidak menginvestigasi ulang klaim yang
  sama tanpa membaca analisisnya terlebih dahulu.
