# DiscipLink - Strix Automated Penetration Test (Run 2026-09-21)

> **Status: SELESAI (CLOSED).** Seluruh **6 temuan** di bawah sudah **diperbaiki**
> dan **terbukti hilang** saat diuji ulang oleh re-scan 2026-09-22 dengan
> instruksi + parameter **identik** (MD5 instruksi diverifikasi sama). Lihat
> [Verifikasi penutupan](#verifikasi-penutupan--re-scan-2026-09-22) di bawah.
>
> **Celah keamanan BARU** (bukan temuan run ini) ditemukan re-scan 2026-09-22 -
> arahkan ke [`../strix-2026-09-22/`](../strix-2026-09-22/) untuk daftar lengkapnya.
>
> Hierarki folder: [`../strix-runs/README.md`](../README.md) - panduan repro: [`CARA-REPRODUKSI.md`](CARA-REPRODUKSI.md)

Automated offensive security assessment of **DiscipLink** (student-discipline web app, PHP 8.3
native, no framework) using [Strix](https://github.com/usestrix/strix) (AI pentest agent,
Apache-2.0) in **white-box** mode: each run received both the live target and the source tree
(`--mount`).

Every run was scoped to **one attack area** and executed **sequentially with a cooldown**
(one area per run, `STRIX_REASONING_EFFORT=low`, `--max-turns 120`, `sleep 30` between runs) to
stay inside the LLM gateway's RPM ceiling. All testing was **authorized, local, self-owned**
(Lab instance: `http://172.17.112.1:8001`).

> **Provenance.** Every finding below is a verbatim artifact produced by Strix - each area folder
> contains the raw `findings.sarif` (SARIF 2.1.0, `tool.driver.name = "Strix"`), the per-finding
> `vulnerabilities/vuln-*.md` reports with request/response PoCs, `vulnerabilities.csv/json`,
> `penetration_test_report.md`, and the run log. Nothing here is hand-written or estimated.

---

## Run summary

| # | Area | Scope tested | Runs | LLM reqs | Tokens | Status | Findings |
| --- | ------ | -------------- | ------ | ---------- | -------- | -------- | ---------- |
| 1 | **Login / Authentication** | SQLi, auth bypass, enumeration, brute-force, session, CSRF, open redirect | 1 | 70 | 3.99M | completed | **1 CRITICAL, 1 HIGH** |
| 2 | **Session & CSRF** | session fixation, cookie flags, lifecycle, CSRF coverage | 1 | 38 | 2.56M | completed | **1 MEDIUM, 1 LOW** |
| 3 | **Upload / Download / IDOR** | unrestricted upload, traversal, token sealing, IDOR/BOLA, RBAC | 1 | 69 | 6.07M | completed | **0** (all defenses held) |
| 4 | **Violation workflow** | business logic, mass assignment, SQLi, stored XSS, authz | 1 | 89 | 8.35M | completed | **1 MEDIUM** |
| 5 | **News module (XSS)** | stored XSS, sanitizer bypass, SQLi, CSRF, authz, output encoding | 1 | 88 | 7.25M | completed | **1 MEDIUM** |
| | **TOTAL** | | **5** | **354** | **28.2M** | 5/5 completed | **1 CRITICAL - 1 HIGH - 3 MEDIUM - 1 LOW** |

**Reliability note:** **zero** HTTP 503 / rate-limit events across all five runs (grepping each
`strix.log` for `HTTP 503` / `rate limit` / `RemoteDisconnected` returns nothing - the only "503"
strings in the logs are timestamp milliseconds). The sequential, limited-reasoning,
one-area-per-run strategy kept the agent under the gateway ceiling.

---

## Findings matrix

Severity is as reported by Strix; CVSS and CWE are taken from each `vuln-*.md`.

| ID | Area | Severity | CVSS | CWE | Finding | Endpoint | Evidence |
| ---- | ------ | ---------- | ------ | ----- | --------- | ---------- | ---------- |
| **A1-vuln-0002** | Login | -- **CRITICAL** | 9.1 | CWE-307 | Brute-force lockout is **per-session only** - discarding the session cookie resets the counter, so the 5-failure/15-min lock is bypassed indefinitely | `POST /action/login` | [vuln](area1-login/before/vulnerabilities/vuln-0002.md) |
| **A1-vuln-0001** | Login | -- **HIGH** | 7.4 | CWE-230 | **NUL-byte truncation** in password verification - `password123%00INJECTED` authenticates because bcrypt is NUL-terminated | `POST /action/login` | [vuln](area1-login/before/vulnerabilities/vuln-0001.md) |
| **A2-vuln-0001** | Session | -- MEDIUM | 5.9 | CWE-614 | Session cookie issued **without `Secure`** on HTTPS-terminated (proxy) requests | `Set-Cookie` | [vuln](area2-session-csrf/before/vulnerabilities/vuln-0001.md) |
| **A2-vuln-0002** | Session | -- LOW | 3.7 | CWE-613 | No **absolute session lifetime**; concurrent sessions are never invalidated | session lifecycle | [vuln](area2-session-csrf/before/vulnerabilities/vuln-0002.md) |
| **A4-vuln-0001** | Violation | -- MEDIUM | 6.5 | CWE-20 | **Sanction tier not validated** against the violation tier - a lecturer can attach the most severe sanction (Tier I) to a trivial Tier V violation; client-selectable `sanksi` | `POST /action/pelanggaran` | [vuln](area4-pelanggaran/before/vulnerabilities/vuln-0001.md) |
| **A5-vuln-0001** | News | -- MEDIUM | 5.4 | CWE-79 | **Stored XSS** on the public article page - event-handler sanitizer bypassed via a quote boundary (`<div title="x"onmouseover=alert(1)>`); confirmed executing in a headless browser | `GET /berita?slug=-` | [vuln](area5-news-xss/before/vulnerabilities/vuln-0001.md) |

---

## Verifikasi penutupan - re-scan 2026-09-22

Setelah semua temuan diperbaiki, **area-area yang sama diuji ulang** dengan Strix
(instruksi + guardrail identik, target = kode yang sudah dipatch; verifikasi MD5
instruksi ada di [`../strix-2026-09-22/`](../strix-2026-09-22/)). Hasilnya:
**keenam temuan hilang** - tidak satu pun muncul kembali.

| ID | Temuan 2026-09-21 | Fix | Hasil re-scan 2026-09-22 |
| ---- | ----------------- | --- | ------------------------ |
| **A1-vuln-0001** | NUL-byte truncation in password verification (CWE-230) | reject raw NUL before hashing | **HILANG** |
| **A1-vuln-0002** | Brute-force lockout per-session only (CWE-307) | input `trim`/NUL-reject + throttle durable (akun 5 / IP 15 / 15 mnt, tabel `SECURITY_AUDIT_LOG`) | **HILANG** |
| **A2-vuln-0001** | Session cookie without `Secure` (CWE-614) | `app_session_start_if_needed()` set `Secure` (HTTPS/`X-Forwarded-Proto`) | **HILANG** |
| **A2-vuln-0002** | No absolute session lifetime (CWE-613) | `APP_SESSION_ABSOLUTE_TTL` + `app_session_touch_or_expire()` | **HILANG** |
| **A4-vuln-0001** | Sanction tier not validated (CWE-20) | server memvalidasi tier sanksi terhadap tier pelanggaran | **HILANG** |
| **A5-vuln-0001** | Stored XSS via quote boundary (CWE-79) | quote-aware attribute sanitizer | **HILANG** |

**Cara membaca bukti:** setiap area re-scan tersimpan di
[`../strix-2026-09-22/areaN/`](../strix-2026-09-22/) sebagai artefak verbatim
(`findings.sarif`, `vulnerabilities/`, `run.json`, `strix.log`). Per-area README
di sana menyatakan temuan mana yang hilang dan mana yang baru.

> **Re-scan 2026-09-22 juga menemukan celah BARU** yang tidak ada di run ini
> (4 valid + 1 false positive, semuanya sudah diklasifikasi & difix).
> **Daftar lengkap ada di**
> [`strix-2026-09-22/README.md`](../strix-2026-09-22/README.md) ->
> [`strix-2026-09-22/areaN/README.md`](../strix-2026-09-22/).

---

## Area 3 - negative-result (coverage) matrix

Area 3 returned **no findings**, but the evidence of what was tested and *held* is first-class
security evidence. Reproduced from that run's report:

| Attack class | Attempted | Outcome |
| -------------- | ----------- | --------- |
| Unrestricted file type (`.php`, `.phtml`, PHP-in-image, mismatched Content-Type) | server-side `finfo` MIME + extension allowlist | **Blocked** - rejected "Tipe file tidak diizinkan" |
| Path traversal in filename (`../`, `..%2f`, `....//`, null byte, absolute path) | server-generated `<id>_<type>_<24-hex>.<ext>` name; client name never used | **Blocked** - all landed inside `storage/uploads/` |
| Overwrite / collision | 12-byte random suffix | **Blocked** - same-named uploads produce distinct files |
| SVG / polyglot XSS | SVG MIME not permitted; `nosniff` on serve | **Blocked** |
| Download token tamper / replay / cross-entity | sealed NaCl/AES-GCM token, `sid = sha256(session_id)`, `hash_equals`, expiry | **Blocked** - 403 |
| IDOR on download / raw filename / direct storage path | token required; `.htaccess` + router deny | **Blocked** - 403 |
| IDOR / BOLA on violation edit/confirm/delete (cross-user) | sealed session-bound ID tokens + ownership-scoped SQL (`id_mhs`/`id_dosen`) | **Blocked** - 403 |
| Role escalation (mahasiswa/dosen - admin actions) | server-side role + CSRF enforcement | **Blocked** - 403 |

Two **non-security** defects were noted (not vulnerabilities): a hardcoded generic PDF link in the
student violation view, and an admin hitting a lecturer-only page returns a graceful 500.

---

## Raw artifacts

Struktur mengikuti [kontrak struktur & dokumentasi](../README.md#struktur-wajib--sama-untuk-setiap-tanggal):

```
strix-2026-09-21/
├── README.md                    dokumen ini
├── instructions/                instruksi area yang dijalankan (areaN_<slug>.md)
├── _tools/                      skrip internal (build consolidated report)
├── 00-CONSOLIDATED-REPORT.md
├── COMBINED.sarif
├── CARA-REPRODUKSI.md
└── areaN-<slug>/
    ├── README.md                indeks area (temuan + pointer)
    ├── before/                  artefak mentah: findings.sarif, vulnerabilities/, csv/json, run.json, strix.log, .state/
    └── after/                   bukti fix: README.md, reproduce.sh, reproduce-after.log, evidence-*.png
```

Each `findings.sarif` is a standard SARIF 2.1.0 file (`tool.driver.name = "Strix"`, version 1.4.1)
and can be opened in any SARIF viewer. (Note: the Docker sandbox image used was
`ghcr.io/usestrix/strix-sandbox:1.2.0`.)

> **About `strix.log`:** project policy excludes `*.log` from git in general, but archived
> pentest evidence is an explicit exception - `.gitignore` carries
> `!docs/intern/strix-runs*/**/strix.log`, so the raw per-run `strix.log` traces **are**
> committed here (inside each `areaN/before/`). Everything needed to verify each finding is
> therefore present: `run.json` (status, targets, LLM usage), `findings.sarif`, the
> `vulnerabilities/*.md` PoCs, `penetration_test_report.md`, and `strix.log`.

---

## Methodology & limits

- **Tool:** Strix AI pentest agent, white-box (`--mount` source + live target).
- **Model:** `dailyDriver` (self-hosted gateway). Strix itself warns it is not a frontier model;
  findings are therefore *low-noise and reproducible* rather than exhaustive.
- **Guardrails:** one area per run, `STRIX_REASONING_EFFORT=low`, `--max-turns 120`, `sleep 30`
  between runs, Docker sandbox (`ghcr.io/usestrix/strix-sandbox`).
- **Honesty note:** a `completed` run with zero findings means the tested classes *held* under that
  agent/model - it is **not** a proof of absence. Areas are re-runnable; re-test after any change to
  the relevant code paths.
