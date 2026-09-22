# DiscipLink - Strix Automated Pentest (Run 2026-09-21)

> Dokumen ini mengikuti [Standar Dokumentasi Security Testing](../../SECURITY-DOC-STANDARD.md).
> Bentuk folder dijelaskan di [`../strix-runs/README.md`](../README.md).

## Metadata

| Field | Value |
| --- | --- |
| Target | DiscipLink / TataTertibMhsV2 (PHP 8.3 native, tanpa framework) - instance lab lokal `http://172.17.112.1:8001` |
| Mode | white-box (`--mount` source tree + live target) |
| Tool | Strix v1.4.1 (sandbox image: `ghcr.io/usestrix/strix-sandbox:1.2.0`) |
| Model | `dailyDriver` (self-hosted gateway) |
| Guardrails | one-area-per-run, `STRIX_REASONING_EFFORT=low`, `--max-turns 120`, `sleep 30` antar run |
| Baseline run | - (ini run pertama; tidak ada baseline) |
| Status | SELESAI (CLOSED) - seluruh temuan diperbaiki & terbukti hilang di re-scan 2026-09-22 |

## Executive Summary

Lima area aplikasi diuji secara terpisah. Ditemukan **6 temuan**: 1 CRITICAL,
1 HIGH, 3 MEDIUM, 1 LOW. Temuan terberat adalah lockout brute-force yang hanya
berlaku per-sesi (CRITICAL) dan NUL-byte truncation pada verifikasi password (HIGH).
Seluruh 6 temuan sudah **diperbaiki** dan **diverifikasi tertutup** oleh re-scan
2026-09-22; status run ini: **CLOSED**.

## Scope & Methodology

Run dijalankan satu area per eksekusi (lima run berurutan dengan cooldown) untuk
menjaga penggunaan di bawah batas RPM gateway LLM. Setiap area menerima target
live **dan** source tree (`--mount`) - white-box.

| Area | Scope yang diuji |
| --- | --- |
| Login / Authentication | SQLi, auth bypass, user enumeration, brute-force, session, CSRF, open redirect |
| Session & CSRF | session fixation, cookie flags, lifecycle, cakupan CSRF |
| Upload / Download / IDOR | unrestricted upload, path traversal, token sealing, IDOR/BOLA, RBAC |
| Violation workflow | business logic, mass assignment, SQLi, stored XSS, otorisasi |
| News module (XSS) | stored XSS, bypass sanitizer, SQLi, CSRF, otorisasi, output encoding |

**Di luar scope:** pengujian infrastruktur (OS/container/DB engine), serangan
DoS/beban, dan rekayasa sosial. Aplikasi diuji apa adanya sebagai instance lab
milik sendiri (authorized).

## Run Summary

| # | Area | Scope tested | Runs | LLM reqs | Tokens | Status | Findings |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | Login / Authentication | SQLi, auth bypass, enumeration, brute-force, session, CSRF, open redirect | 1 | 70 | 3.99M | completed | 1 CRITICAL, 1 HIGH |
| 2 | Session & CSRF | session fixation, cookie flags, lifecycle, CSRF coverage | 1 | 38 | 2.56M | completed | 1 MEDIUM, 1 LOW |
| 3 | Upload / Download / IDOR | unrestricted upload, traversal, token sealing, IDOR/BOLA, RBAC | 1 | 69 | 6.07M | completed | 0 (semua pertahanan bertahan) |
| 4 | Violation workflow | business logic, mass assignment, SQLi, stored XSS, authz | 1 | 89 | 8.35M | completed | 1 MEDIUM |
| 5 | News module (XSS) | stored XSS, sanitizer bypass, SQLi, CSRF, authz, output encoding | 1 | 88 | 7.25M | completed | 1 MEDIUM |
| | **TOTAL** | | **5** | **354** | **28.2M** | 5/5 completed | **1 CRITICAL - 1 HIGH - 3 MEDIUM - 1 LOW** |

## Findings Matrix

Severity sesuai laporan Strix; CVSS dan CWE diambil dari tiap `vuln-*.md`.

| ID | Area | Severity | CVSS | CWE | Finding | Endpoint | Status | Evidence |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| A1-vuln-0002 | Login | CRITICAL | 9.1 | CWE-307 | Lockout brute-force hanya per-sesi - membuang cookie sesi me-reset counter, sehingga lock 5-gagal/15-menit bisa dilewati tanpa batas | `POST /action/login` | Confirmed-fixed | [vuln](area1-login/before/vulnerabilities/vuln-0002.md) |
| A1-vuln-0001 | Login | HIGH | 7.4 | CWE-230 | NUL-byte truncation pada verifikasi password - `password123%00INJECTED` terautentikasi karena bcrypt berhenti di NUL | `POST /action/login` | Confirmed-fixed | [vuln](area1-login/before/vulnerabilities/vuln-0001.md) |
| A2-vuln-0001 | Session | MEDIUM | 5.9 | CWE-614 | Cookie sesi diterbitkan tanpa `Secure` pada permintaan yang TLS-nya di-terminate proxy | `Set-Cookie` | Confirmed-fixed | [vuln](area2-session-csrf/before/vulnerabilities/vuln-0001.md) |
| A2-vuln-0002 | Session | LOW | 3.7 | CWE-613 | Tidak ada absolute session lifetime; sesi konkuren tidak pernah diinvalidasi | siklus hidup sesi | Confirmed-fixed | [vuln](area2-session-csrf/before/vulnerabilities/vuln-0002.md) |
| A4-vuln-0001 | Pelanggaran | MEDIUM | 6.5 | CWE-20 | Tier sanksi tidak divalidasi terhadap tier pelanggaran - dosen bisa memasang sanksi terberat (Tier I) pada pelanggaran ringan Tier V; `sanksi` bisa dipilih klien | `POST /action/pelanggaran` | Confirmed-fixed | [vuln](area4-pelanggaran/before/vulnerabilities/vuln-0001.md) |
| A5-vuln-0001 | News | MEDIUM | 5.4 | CWE-79 | Stored XSS di halaman artikel publik - sanitizer event-handler dilewati via batas kutip (`<div title="x"onmouseover=alert(1)>`); terbukti eksekusi di browser headless | `GET /berita?slug=-` | Confirmed-fixed | [vuln](area5-news-xss/before/vulnerabilities/vuln-0001.md) |

## Coverage / Negative-Result Matrix

Area 3 tidak menghasilkan temuan, tetapi bukti kelas serangan yang **dicoba dan
bertahan** adalah bukti keamanan kelas satu. Direproduksi dari laporan run tersebut:

| Area | Attack class | Outcome |
|---|---|---|
| Upload / IDOR | Unrestricted file type (`.php`, `.phtml`, PHP-in-image, Content-Type mismatch) | **Blocked** - allowlist MIME `finfo` + ekstensi di sisi server; "Tipe file tidak diizinkan" |
| Upload / IDOR | Path traversal pada nama file (`../`, `..%2f`, `....//`, null byte, absolute path) | **Blocked** - nama dibuat server `<id>_<type>_<24-hex>.<ext>`; semua tersimpan di dalam `storage/uploads/` |
| Upload / IDOR | Overwrite / collision | **Blocked** - suffix acak 12 byte; unggahan bernama sama menghasilkan file berbeda |
| Upload / IDOR | SVG / polyglot XSS | **Blocked** - MIME SVG tidak diizinkan; `nosniff` saat disajikan |
| Upload / IDOR | Download token tamper / replay / cross-entity | **Blocked** - token tersegel NaCl/AES-GCM, `sid = sha256(session_id)`, `hash_equals`, expiry; 403 |
| Upload / IDOR | IDOR pada download / nama file mentah / path storage langsung | **Blocked** - token wajib; `.htaccess` + router menolak; 403 |
| Upload / IDOR | IDOR / BOLA pada edit/confirm/delete pelanggaran (lintas-user) | **Blocked** - token ID tersegel terikat sesi + SQL ber-scope kepemilikan; 403 |
| Upload / IDOR | Role escalation (mahasiswa/dosen - aksi admin) | **Blocked** - enforcement role + CSRF di sisi server; 403 |

Dua **non-security defect** dicatat (bukan kerentanan): tautan PDF generik yang
di-hardcode di view pelanggaran mahasiswa, dan admin yang membuka halaman
khusus-dosen mendapat 500 yang ditangani dengan rapi.

## Remediation & Verification

Perbaikan 6 temuan run ini diverifikasi oleh re-scan 2026-09-22 dengan instruksi +
guardrail identik (MD5 instruksi dicocokkan). Hasil: **keenamnya HILANG**.

| ID | Temuan run sebelumnya | Fix | Commit | Hasil re-scan |
| --- | --- | --- | --- | --- |
| A1-vuln-0001 | NUL-byte truncation pada verifikasi password (CWE-230) | tolak NUL sebelum hashing | `ba4e8d1` | HILANG |
| A1-vuln-0002 | Lockout brute-force per-sesi (CWE-307) | throttle durable berbasis tabel audit (akun 5 / IP 15 / 15 menit) | `ba4e8d1` | HILANG |
| A2-vuln-0001 | Cookie sesi tanpa `Secure` (CWE-614) | set `Secure` pada permintaan HTTPS / `X-Forwarded-Proto` | `9f55f2a` | HILANG |
| A2-vuln-0002 | Tidak ada absolute session lifetime (CWE-613) | `APP_SESSION_ABSOLUTE_TTL` + invalidasi sesi lain saat login | `484bb67` | HILANG |
| A4-vuln-0001 | Tier sanksi tidak divalidasi (CWE-20) | validasi tier sanksi terhadap tier pelanggaran | `487dd93` | HILANG |
| A5-vuln-0001 | Stored XSS via batas kutip (CWE-79) | sanitizer event-handler sadar-kutip | `7b4c39d` | HILANG |

Regression test yang mengunci tiap perbaikan terdaftar di `tests/security/**` dan
`tests/unit/**` (lihat `tests/run.php`).

> **Lihat juga run berikutnya:** re-scan 2026-09-22 di
> [`../strix-2026-09-22/`](../strix-2026-09-22/) - memuat matriks penutupan lengkap
> plus **temuan baru** yang muncul setelah perbaikan.

## Raw Artifacts

Struktur folder mengikuti kontrak di [`../README.md`](../README.md):

```
strix-2026-09-21/
  README.md                    dokumen ini
  instructions/                instruksi area yang dijalankan (areaN_<slug>.md)
  _tools/                      skrip internal (build consolidated report)
  00-CONSOLIDATED-REPORT.md
  COMBINED.sarif
  CARA-REPRODUKSI.md
  areaN-<slug>/
    README.md                  indeks area (temuan + pointer)
    before/                    artefak mentah: findings.sarif, vulnerabilities/, csv/json, run.json, strix.log, .state/
    after/                     bukti fix: README.md, reproduce.sh, reproduce-after.log, evidence-*.png
```

Setiap `findings.sarif` adalah berkas SARIF 2.1.0 standar (`tool.driver.name = "Strix"`)
dan dapat dibuka di penampil SARIF mana pun.

> **Tentang `strix.log`:** kebijakan repo mengabaikan `*.log` secara umum, tetapi
> bukti pentest yang diarsipkan adalah pengecualian eksplisit - `.gitignore`
> memuat `!docs/intern/strix-runs*/**/strix.log`, sehingga jejak mentah `strix.log`
> **memang** tersimpan di sini (di dalam tiap `areaN/before/`).

## Limitations & Honesty Note

- **Tool/model:** Strix sendiri memperingatkan bahwa `dailyDriver` bukan model
  frontier; temuan karenanya bersifat *low-noise dan reproducible*, bukan ekshaustif.
- Run `completed` dengan nol temuan berarti kelas serangan yang diuji *bertahan*
  terhadap agen/model saat itu - **bukan** bukti ketiadaan kerentanan. Area dapat
  diuji ulang; lakukan re-test setelah perubahan apa pun pada jalur kode terkait.
- Semua pengujian **authorized**, hanya terhadap instance lokal milik sendiri.
- Artefak dimasukkan **verbatim** (apa adanya), bukan rangkuman tangan.
