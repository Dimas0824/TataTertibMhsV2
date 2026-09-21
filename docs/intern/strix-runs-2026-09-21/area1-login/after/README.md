# area1-login — AFTER fix (verification evidence)

Bukti bahwa dua temuan pada run LOGIN **sudah diperbaiki dan terbukti tertutup**.

| | |
|---|---|
| **Findings** | `vuln-0001` CWE-230 HIGH · `vuln-0002` CWE-307 CRITICAL |
| **Fix commit** | `ba4e8d1` (fix) · `6e3f3b1` (regression tests) · `ffdaa26` (docs) |
| **Regression test** | `tests/security/LoginBruteForceSuite.php` |
| **Reproduce script** | [`reproduce.sh`](reproduce.sh) · output: [`reproduce-after.log`](reproduce-after.log) |

---

## Before vs After

`before/` = artefak Strix asli (kondisi rentan). `after/` = hasil reproduksi ulang
setelah perbaikan.

| # | Skenario | BEFORE (rentan) | AFTER (terbukti aman) |
|---|---|---|---|
| 1 | Login `password123` + `%00` + junk | `302 → /pelanggaran` ❌ **masuk** | `302 → /login` ✅ **ditolak** |
| 2 | Login `password123` (sah, kontrol) | `302 → /pelanggaran` | `302 → /pelanggaran` ✅ tetap jalan |
| 3 | 5 gagal, lalu **sesi BARU** min + password benar | `302 → /pelanggaran` ❌ **lockout terlewati** | `302 → /login` ✅ **terkunci** |

Angka BEFORE diambil dari `before/vulnerabilities/vuln-0001.md` & `vuln-0002.md`
(bukti dari run Strix). Angka AFTER dihasilkan oleh skrip di folder ini.

---

## Cara reproduksi (after)

```bash
# 1. Siapkan app (bind ke semua interface) + DB ter-seed
php artisan serve --host=0.0.0.0 --port=8123

# 2. Jalankan verifikasi (butuh bash + curl + php)
bash reproduce.sh http://127.0.0.1:8123
#    harapan: "RESULT: 3 passed, 0 failed"  (exit 0)

# 3. Simpan bukti
bash reproduce.sh http://127.0.0.1:8123 > reproduce-after.log 2>&1
```

> **Catatan:** skrip menghapus baris `login_fail`/`login_locked` dari
> `SECURITY_AUDIT_LOG` (agar hasil deterministik). **Jalankan hanya di lab/DB
> sekali-pakai, bukan produksi.**

---

## Kenapa hasilnya sah

- **Exploit ditolak** (skenario 1 & 3) → celah benar-benar tertutup.
- **Kontrol tetap jalan** (skenario 2) → perbaikan tidak merusak login normal.
- **Regression test otomatis** (`php tests/run.php` → `LoginBruteForceSuite`)
  mengunci perilaku ini agar tidak terbuka lagi; suite penuh 176/176 PASS.

Perbaikan kode:
- `helpers/login_throttle_helper.php` — `app_login_input_invalid()` (tolak NUL,
  batasi 72 byte) dan `app_login_throttle_status()` (lockout berbasis audit-log:
  akun 5 / IP 15 per 15 menit, fail-soft).
- `request/handler-login.php` — guard input sebelum verifier + cek throttle durable.
