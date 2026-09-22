# Area 3 - After fix (verification evidence)

AREA 3 (upload / download / IDOR / access control) produced **zero vulnerabilities**:
every offensive attempt was blocked by server-side controls. This `after/` records
that result and the one non-security defect that was fixed.

| | |
| --- | --- |
| **Findings** | **0** (Strix: "no exploitable weakness was identified") |
| **Fix commit** | `78cdf02` (non-security defect: 403 instead of 500) |
| **Regression test** | `tests/security/Area3AccessSuite.php` (2 tests) |
| **Reproduce** | [`reproduce.sh`](reproduce.sh) - output: [`reproduce-after.log`](reproduce-after.log) |

---

## Held defenses (verified in `before/penetration_test_report.md`)

There is no vulnerability to "fix" here; the value is the evidence that these classes
were tested and **failed to break through**:

| Attack class | Attempted | Outcome |
| --- | --- | --- |
| Unrestricted file type (`.php`, PHP-in-image, mismatched Content-Type) | server-side `finfo` MIME + extension allowlist | **Blocked** |
| Path traversal in filename (`../`, `..%2f`, null byte, absolute path) | server-generated filename; client name never used | **Blocked** |
| Download token tamper / cross-session replay / cross-entity | sealed session-bound capability token | **Blocked** (403) |
| IDOR / BOLA on violation edit/confirm/delete (cross-user) | sealed tokens + ownership-scoped SQL | **Blocked** (403) |
| Role escalation (mahasiswa/dosen -> admin actions) | server-side role + CSRF enforcement | **Blocked** (403) |

---

## Non-security defects flagged by Strix

1. **Fixed (`78cdf02`)** - an administrator reaching the lecturer-only page
   `/pelanggaran/dosen` got an unhandled **HTTP 500** (the page blocked only
   `mahasiswa`, then read `$userData['nidn']`, which an admin lacks). Now a clean
   **403**. Regression: `Area3AccessSuite`.
2. **Deferred (functional, not security)** - `views/pelanggaran/pelanggaran-page.php:140`
   and `:464` hardcode a download link to a generic `SURAT PERNYATAAN TI.pdf`, so every
   student receives the same template file instead of their own uploaded document. This is
   a correctness bug in a feature; not a disclosure. Tracked for a later change.

---

## Before vs After (only the defect we fixed)

| Skenario | BEFORE | AFTER |
| --- | --- | --- |
| Admin `GET /pelanggaran/dosen` | **500** `{"Koneksi database tidak tersedia."}`/`nidn` undefined | **403** |
| Dosen `GET /pelanggaran/dosen` | 200 | 200 (tidak berubah) |

---

## Cara reproduksi (after)

```bash
php artisan serve --host=0.0.0.0 --port=8123
bash reproduce.sh http://127.0.0.1:8123     # harapan: "RESULT: 2 passed, 0 failed"
```

> **Catatan:** AREA 3 tidak punya temuan keamanan, jadi `after/` ini **bukan** klaim
> "celah ditutup" - ini catatan bahwa (a) kelas serangan yang diuji ditahan, dan
> (b) satu defect non-keamanan diperbaiki.

## Status verifikasi

Diverifikasi oleh **re-scan 2026-09-22** (area 3): seluruh kelas serangan tetap
**hening** (0 temuan valid untuk kelas upload/IDOR), dan defect role guard
diperbaiki. Re-scan menemukan 1 temuan baru di area ini (halaman mahasiswa 500
untuk admin) - lihat
[`../../../strix-2026-09-22/area3-upload-idor/`](../../../strix-2026-09-22/area3-upload-idor/).
