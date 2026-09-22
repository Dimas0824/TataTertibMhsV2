# Re-run verification (2026-09-22)

Strix dijalankan **ulang** terhadap kode yang sudah diperbaiki, memakai
**instruksi + parameter identik** dengan run pertama (MD5 instruksi diverifikasi
sama). Tujuan: membuktikan klaim "sudah di-fix" secara independen.

- Workspace: `/home/dimas/disciplink-rerun` (terpisah dari run-1 agar tidak tertimpa)
- Target: `http://172.17.112.1:8001` (kode saat ini, sudah difix)
- Instruksi: sama dengan `docs/intern/strix-runs-2026-09-21/instructions/`

## Hasil per area (run-1 vs re-run)

| Area | Run-1 (sebelum fix) | Re-run (sesudah fix) | Verifikasi |
| ---- | ------------------- | -------------------- | ---------- |
| 1 LOGIN | 2 temuan (CRITICAL lockout per-sesi, HIGH NUL-byte) | 2 temuan LAMA HILANG; 1 **baru** (case-variant lockout, CRITICAL) | temuan baru = **FALSE POSITIVE** (lihat bawah) |
| 2 SESSION/CSRF | 2 temuan (MEDIUM cookie Secure, LOW absolute lifetime) | 2 temuan LAMA HILANG; 1 **baru** (use_strict_mode, MEDIUM) | VALID -> **DIFIX** (`a56baca`) |
| 3 UPLOAD/IDOR | 0 temuan | 1 **baru** (missing role guard -> 500, MEDIUM) | VALID -> **DIFIX** (`d4aec0e`) |
| 4 PELANGGARAN | 1 temuan (MEDIUM sanksi tier) | 1 temuan LAMA HILANG; 1 **baru** (delete finalized, HIGH) | VALID -> **DIFIX** (`579a4c6`) |
| 5 NEWS | 1 temuan (MEDIUM stored XSS body) | 1 temuan LAMA HILANG; 1 **baru** (XSS via JSON-LD title, MEDIUM) | VALID -> **DIFIX** (`1181dec`) |

**Poin kunci:** **SEMUA 5 temuan LAMA hilang** di re-run -> seluruh perbaikan
terdahulu terbukti efektif. Temuan "baru" muncul karena area yang sama kini diuji
dengan kode berbeda (surface bergeser), bukan karena regresi. Dari 5 temuan baru:
**1 false positive** (area 1) dan **4 valid** (area 2, 3, 4, 5) - keempatnya sudah
diperbaiki.

**Rekap temuan baru re-run:**

| # | Area | Temuan baru | Severity | Status |
|---|------|-------------|----------|--------|
| 1 | LOGIN | case-variant lockout bypass | CRITICAL | false positive (collation `_ci`) |
| 2 | SESSION | use_strict_mode disabled | MEDIUM | fixed `a56baca` |
| 3 | UPLOAD/IDOR | missing role guard (500) | MEDIUM | fixed `d4aec0e` |
| 4 | PELANGGARAN | delete finalized violation | HIGH | fixed `579a4c6` |
| 5 | NEWS | XSS via JSON-LD title | MEDIUM | fixed `1181dec` |

## Analisis false positive: case-variant lockout (area 1)

Strix (re-run) melaporkan CRITICAL: *"Login lockout can be bypassed via
case-varied account identifiers (CI collation vs case-sensitive throttle key)"*.

**Klaim Strix:** lookup akun case-INSENSITIVE (`utf8mb4_..._ci`), tapi throttle
key `substr($username,0,32)` case-SENSITIVE -> tiap varian huruf dapat budget 5
percobaan sendiri -> lockout per-akun bisa dikalikan.

**Strix menyimpulkan dari PEMBACAAN KODE**, dan mengakui di Assumptions bahwa
konfirmasi end-to-end-nya **gagal** (kena IP cap).

**Verifikasi yang kami lakukan (4 lapis):**

1. **Collation kolom audit** (akar asumsi Strix):
   ```sql
   -- SECURITY_AUDIT_LOG.actor_id
   COLLATION_NAME = utf8mb4_unicode_ci   -- case-INSENSITIVE
   ```
   Strix mengasumsikan `actor_id = :actor` case-sensitive. **Salah.**

2. **Bukti match lintas-huruf:**
   ```sql
   SELECT COUNT(*) FROM SECURITY_AUDIT_LOG
   WHERE event='login_fail' AND actor_id='admin001';
   -- = 1  (cocok dengan baris 'ADMIN001')
   ```

3. **Unit (helper throttle):** setelah 5 gagal dicatat sebagai `ADMIN001`,
   `app_login_throttle_status('admin001'|'Admin001'|'aDmIn001')` -> **locked = TRUE**.

4. **End-to-end HTTP** (level yang sama dengan PoC Strix - lihat
   `prove_case_variant_not_bypass.py`):
   ```
   STEP 2: 5 gagal 'ADMIN001' -> terkunci
   STEP 3: 'admin001'/'Admin001'/'aDmIn001' -> locked=YES (time 0.04-0.06s, short-circuit)
   ```

**Kesimpulan:** kedua sisi (lookup & throttle) **sama-sama case-insensitive**
karena collation kolom audit `_ci`. **Tidak ada bypass.** Temuan ini
**false positive** - hasil analisis statis tanpa uji runtime.

Cara menjalankan ulang bukti:
```bash
# butuh app jalan + DB
php artisan serve --host=0.0.0.0 --port=8001
python prove_case_variant_not_bypass.py http://127.0.0.1:8001
# STEP 3 semua 'locked=YES' => tidak ada bypass
```

## Artefak

- `prove_case_variant_not_bypass.py` - skrip repro end-to-end (4 langkah Strix)
- Screenshot re-run: `../area1-login/after/evidence-login2.png`
