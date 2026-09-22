# DiscipLink - Strix Automated Pentest (Run 2026-09-22)

> Dokumen ini mengikuti [Standar Dokumentasi Security Testing](../../SECURITY-DOC-STANDARD.md).
> Bentuk folder dijelaskan di [`../strix-runs/README.md`](../README.md).

## Metadata

| Field | Value |
| --- | --- |
| Target | DiscipLink / TataTertibMhsV2 - instance lab lokal `http://172.17.112.1:8001` |
| Mode | white-box (`--mount` source tree + live target) |
| Tool | Strix v1.4.1 (sandbox image: `ghcr.io/usestrix/strix-sandbox:1.2.0`) |
| Model | `dailyDriver` (self-hosted gateway) |
| Guardrails | one-area-per-run, `STRIX_REASONING_EFFORT=low`, `--max-turns 120`, `sleep 30` antar run |
| Baseline run | [`../strix-2026-09-21/`](../strix-2026-09-21/) - MD5 instruksi **identik** (diverifikasi) |
| Status | SELESAI (CLOSED) - 4 temuan baru diperbaiki; 1 klaim terbukti false positive |

## Executive Summary

Run verifikasi terhadap kode yang **sudah diperbaiki** dari run 2026-09-21.
Hasilnya: **keenam temuan lama HILANG** (perbaikan terbukti efektif) sekaligus
**5 temuan baru** di permukaan yang sama - 4 **valid** (sudah diperbaiki) dan
1 **false positive**. Tidak ada temuan lama yang regresi.

## Scope & Methodology

Instruksi + parameter **identik** dengan run 2026-09-21 (MD5 lima berkas instruksi
dicocokkan) agar perbandingan before/after sah. Lima area yang sama diuji ulang,
satu area per run, white-box.

| Area | Scope yang diuji |
| --- | --- |
| Login / Authentication | SQLi, auth bypass, enumeration, brute-force, session, CSRF, open redirect |
| Session & CSRF | session fixation, cookie flags, lifecycle, cakupan CSRF |
| Upload / Download / IDOR | unrestricted upload, traversal, token sealing, IDOR/BOLA, RBAC |
| Violation workflow | business logic, mass assignment, SQLi, stored XSS, otorisasi |
| News module (XSS) | stored XSS, bypass sanitizer, SQLi, CSRF, otorisasi, output encoding |

**Di luar scope:** sama seperti run baseline - infrastruktur, DoS/beban, rekayasa sosial.

## Run Summary

| # | Area | Scope tested | Runs | LLM reqs | Tokens | Status | Findings |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | Login / Authentication | sama dengan baseline | 1 | - | - | completed | 1 klaim (false positive) |
| 2 | Session & CSRF | sama dengan baseline | 1 | - | - | completed | 1 MEDIUM (baru) |
| 3 | Upload / Download / IDOR | sama dengan baseline | 1 | - | - | completed | 1 MEDIUM (baru) |
| 4 | Violation workflow | sama dengan baseline | 1 | - | - | completed | 1 HIGH (baru) |
| 5 | News module (XSS) | sama dengan baseline | 1 | - | - | completed | 1 MEDIUM (baru) |
| | **TOTAL** | | **5** | - | - | 5/5 completed | **4 valid (baru) + 1 false positive** |

> Angka LLM reqs/tokens re-scan tidak dicatat di artefak yang diarsipkan (hanya
> `run.json` per area). Kolom dibiarkan `-` daripada diisi tebakan.

## Findings Matrix

Severity sesuai laporan Strix. Kolom `Status` memakai kosakata baku
(`New` / `Confirmed-fixed` / `False-positive` / `Carried-over`).

| ID | Area | Severity | CVSS | CWE | Finding | Endpoint | Status | Evidence |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| A1-vuln-0001 (2026-09-22) | Login | CRITICAL (klaim) | - | CWE-307 | Lockout bisa dilewati via identifier akun berbeda huruf besar/kecil | `POST /action/login` | False-positive | [analisis](verification-analysis/README.md) |
| A2-vuln-0001 (2026-09-22) | Session | MEDIUM | 4.2 | CWE-384 | `session.use_strict_mode` nonaktif - session ID tak dikenal dari klien diterima (session fixation) | siklus hidup sesi | New | [after](area2-session-csrf/after/README.md) |
| A3-vuln-0001 (2026-09-22) | Upload/IDOR | MEDIUM | 4.3 | CWE-284 | Halaman mahasiswa tanpa role guard - role admin kena 500, bukan fail-closed | halaman mahasiswa | New | [after](area3-upload-idor/after/README.md) |
| A4-vuln-0001 (2026-09-22) | Pelanggaran | HIGH | 7.1 | CWE-863 | Pelanggaran berstatus `selesai` (finalized) masih bisa dihapus | `POST /action/pelanggaran` (hapus) | New | [after](area4-pelanggaran/after/README.md) |
| A5-vuln-0001 (2026-09-22) | News | MEDIUM | 5.4 | CWE-79 | Judul berita keluar dari blok JSON-LD - XSS | `GET /berita?slug=-` | New | [after](area5-news-xss/after/README.md) |

## Coverage / Negative-Result Matrix

| Area | Attack class | Outcome |
|---|---|---|
| Login | brute-force / lockout bypass (varian huruf besar-kecil) | **Blocked** - throttle & lookup keduanya case-insensitive (lihat 4 lapis bukti di bawah) |
| Session | session fixation via ID tak dikenal | **Bypass** - `use_strict_mode` menerima hanya ID yang dibuat server (temuan baru) |
| Upload/IDOR | akses halaman lintas-role | **Bypass** - role tak berhak kena 500, bukan 403 (temuan baru) |
| Pelanggaran | hapus record finalized | **Bypass** - record `selesai` terhapus (temuan baru) |
| News | XSS via jalur selain body artikel (JSON-LD) | **Bypass** - judul keluar dari blok script (temuan baru) |

_Area yang tidak menghasilkan temuan valid pada run ini: Login (hanya false positive)._

## Remediation & Verification

Karena ini run re-scan, dua hal dipisahkan: (a) penutupan temuan run sebelumnya,
dan (b) temuan **baru** run ini (masuk Findings Matrix dengan Status `New`).

**(a) Penutupan temuan run 2026-09-21:**

| ID | Temuan run sebelumnya | Fix | Commit | Hasil re-scan |
| --- | --- | --- | --- | --- |
| A1-vuln-0001 | NUL-byte truncation (CWE-230) | tolak NUL sebelum hashing | `ba4e8d1` | HILANG |
| A1-vuln-0002 | Lockout per-sesi (CWE-307) | throttle durable | `ba4e8d1` | HILANG |
| A2-vuln-0001 | Cookie tanpa `Secure` (CWE-614) | set `Secure` + `use_strict_mode` | `9f55f2a`, `a56baca` | HILANG |
| A2-vuln-0002 | Tanpa absolute lifetime (CWE-613) | `APP_SESSION_ABSOLUTE_TTL` | `484bb67` | HILANG |
| A4-vuln-0001 | Tier sanksi tak divalidasi (CWE-20) | validasi tier | `487dd93` | HILANG |
| A5-vuln-0001 | Stored XSS batas kutip (CWE-79) | sanitizer sadar-kutip | `7b4c39d` | HILANG |

**(b) Perbaikan temuan baru run ini:** temuan baru (Status `New`) sudah terdaftar
di [Findings Matrix](#findings-matrix) beserta commit fix-nya; tidak diulang di sini
agar tidak ada tabel kembar. Ringkasannya: `A2-vuln-0001 (CWE-384)` -> `a56baca`,
`A3-vuln-0001 (CWE-284)` -> `d4aec0e`, `A4-vuln-0001 (CWE-863)` -> `579a4c6`,
`A5-vuln-0001 (CWE-79)` -> `1181dec`.

### Analisis: mengapa temuan case-variant lockout adalah false positive

Re-scan (area 1) melaporkan CRITICAL _"Login lockout can be bypassed via
case-varied account identifiers"_ - klaim bahwa throttle case-sensitive sedangkan
lookup akun case-insensitive, sehingga varian huruf besar/kecil mendapat budget
terpisah. **Klaim ini keliru.** Bukti (4 lapis, lihat
[`verification-analysis/prove_case_variant_not_bypass.py`](verification-analysis/prove_case_variant_not_bypass.py)):

1. Kolom `SECURITY_AUDIT_LOG.actor_id` bercollation `utf8mb4_unicode_ci` -> perbandingan **case-insensitive**.
2. `WHERE actor_id='admin001'` **cocok** dengan baris `'ADMIN001'`.
3. Unit: 5 gagal `ADMIN001` -> varian `admin001/Admin001/aDmIn001` semua `locked=TRUE`.
4. End-to-end HTTP (skenario Strix sendiri): semua varian **terkunci** (time 0.04-0.06s = short-circuit throttle).

Strix menyimpulkan dari **pembacaan kode** dan mengasumsikan `=` case-sensitive;
MySQL membandingkan mengikuti **collation kolom** (`_ci`). Strix juga mengakui PoC
end-to-end-nya gagal (kena IP cap). Detail lengkap:
[`verification-analysis/README.md`](verification-analysis/README.md).

## Raw Artifacts

```
strix-2026-09-22/
  README.md                    dokumen ini
  instructions/                instruksi area yang dijalankan (identik dgn run 21-09)
  verification-analysis/       analisis + skrip bukti false positive
  area1-login/
    README.md                  indeks area
    before/                    findings.sarif, vulnerabilities/, run.json, strix.log, .state/
    after/README.md            bukti perbaikan temuan BARU area ini (bila ada)
  area2-session-csrf/          (before/ + after/README.md)
  area3-upload-idor/
  area4-pelanggaran/
  area5-news-xss/
```

Setiap `areaN/before/` berisi artefak verbatim run re-scan (SARIF 2.1.0 tool=Strix,
PoC di `vulnerabilities/`, laporan, log, database percakapan agent).

## Limitations & Honesty Note

- `after/` di folder ini memuat perbaikan **temuan baru 2026-09-22** (4 valid;
  area 1 tanpa temuan valid). Perbaikan dikunci **regression test**, tetapi
  **belum diverifikasi re-scan Strix ketiga** - atribut ini dinyatakan jujur di
  tiap `areaN/after/README.md`.
- Run `completed` dengan 0 temuan valid (area 1) bukan bukti ketiadaan kerentanan.
- Semua pengujian **authorized**, hanya terhadap instance lokal milik sendiri.
- Artefak dimasukkan **verbatim** (apa adanya), bukan rangkuman tangan.

> **Lihat juga run sebelumnya:** baseline di
> [`../strix-2026-09-21/`](../strix-2026-09-21/) - 6 temuan asli yang dibuktikan
> tertutup di sini.
