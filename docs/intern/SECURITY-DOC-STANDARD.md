# Standar Dokumentasi Security Testing - DiscipLink

> **Status: WAJIB DIIKUTI** untuk semua README run pentest (manual maupun AI-agent)
> di bawah `docs/intern/`. File ini adalah rujukan tunggal (single source of truth)
> untuk struktur, terminologi, dan proses dokumentasi keamanan proyek ini.
>
> Ditulis mengacu pada konvensi industri **PTES** (Penetration Testing Execution
> Standard) dan **OWASP WSTG** untuk struktur laporan naratif, serta **SARIF 2.1.0**
> (OASIS) untuk data temuan mentah. Bukan sertifikasi formal - ini adaptasi internal
> proyek yang mengikuti pola yang sudah mapan di industri.

---

## 1. Filosofi

Dua dokumen punya dua fungsi berbeda dan **tidak boleh dicampur**:

| Dokumen | Fungsi | Analogi |
| --- | --- | --- |
| `strix-runs/<tanggal>/README.md` | Laporan **satu run**, naratif, lengkap dengan konteks & evidence | commit log / release notes |
| `VULN-LOG.md` (root `docs/intern/`) | Indeks **semua temuan dari semua run**, satu baris per bug, mudah di-grep | CHANGELOG.md |

Orang yang baru buka repo dan ingin tahu *"bug IDOR apa saja yang pernah ada di sini"*
harus bisa menjawabnya dari `VULN-LOG.md` saja, **tanpa** membuka satu pun folder
tanggal. Orang yang ingin tahu detail satu insiden tertentu baru masuk ke README
per-run yang bersangkutan.

Konvensi folder (struktur `before/` + `after/` + `areaN/README.md` + `instructions/`)
tersendiri didokumentasikan di [`strix-runs/README.md`](strix-runs/README.md).
Dokumen ini mengatur **isi** README; dokumen itu mengatur **bentuk** folder.

---

## 2. Struktur wajib README per-run

Setiap `docs/intern/strix-runs/<YYYY-MM-DD>/README.md` **harus** memuat section-section
berikut, **dalam urutan ini**, dengan judul persis seperti tertulis (boleh tambah
section lain di luar ini, tidak boleh menghilangkan salah satu):

```markdown
# DiscipLink - Strix Automated Pentest (Run YYYY-MM-DD)

## Metadata
## Executive Summary
## Scope & Methodology
## Run Summary
## Findings Matrix
## Coverage / Negative-Result Matrix
## Remediation & Verification         (khusus jika run ini adalah re-scan)
## Raw Artifacts
## Limitations & Honesty Note
```

### 2.1 Metadata

Tabel di baris pertama setelah judul, kolom **persis** seperti ini:

```markdown
| Field | Value |
|---|---|
| Target | ... |
| Mode | white-box / black-box (--mount source + live target, atau tidak) |
| Tool | Strix vX.X.X (sandbox image: ...) |
| Model | ... |
| Guardrails | one-area-per-run, REASONING_EFFORT=..., max-turns ..., cooldown ... |
| Baseline run | link ke run sebelumnya (jika re-scan) + MD5 instruksi |
| Status | SELESAI (CLOSED) / IN PROGRESS |
```

### 2.2 Executive Summary

2-4 kalimat, bahasa non-teknis. Wajib menjawab: berapa temuan total, severity
tertinggi, kesimpulan status. Ini bagian yang dibaca reviewer yang cuma punya
30 detik.

### 2.3 Scope & Methodology

Area yang diuji, jenis serangan yang dicoba per-area, apa yang **di luar** scope
run ini dan kenapa. Sebutkan model/tool + parameter guardrail (boleh duplikat
ringkas dari Metadata, di sini boleh lebih naratif).

### 2.4 Run Summary

Tabel per-area: Area | Scope tested | LLM reqs | Tokens | Status | Findings.
**Wajib ada di setiap README**, termasuk README re-scan - jangan diganti dengan
tabel before/after saja (before/after masuk ke section Remediation & Verification,
bukan menggantikan Run Summary).

### 2.5 Findings Matrix

Kolom **baku, tidak boleh diubah urutannya**:

```markdown
| ID | Area | Severity | CVSS | CWE | Finding | Endpoint | Status | Evidence |
```

Kolom `Status` wajib salah satu dari: `New` / `Confirmed-fixed` / `False-positive` /
`Carried-over`. Ini yang membuat tabel findings run re-scan bisa memakai **struktur
persis sama** dengan run pertama - bukan tabel terpisah dengan kolom berbeda.

ID temuan mengikuti pola `AREA-vuln-NNNN` sesuai penomoran Strix (`A1-vuln-0002`, dst.),
konsisten dengan penamaan file di `vulnerabilities/vuln-*.md`.

### 2.6 Coverage / Negative-Result Matrix

**Wajib ada meskipun kosong.** Kolom **baku, tidak boleh diubah urutannya**:

```markdown
| Area | Attack class | Outcome |
```

Isi tabel adalah kelas serangan yang **dicoba dan bertahan** (outcome `Blocked` /
`bertahan`) - ini bukti suatu kelas serangan benar-benar diuji, bukan cuma "tidak
sempat diuji". Gunakan struktur kolom yang sama di **setiap** run (run pertama maupun
re-scan) supaya tabelnya bisa dibandingkan antar-run. Jika benar-benar tidak ada yang
diuji di area itu pada run ini, tulis eksplisit:
`_Tidak ada pengujian baru di area ini pada run ini._`

### 2.7 Remediation & Verification (khusus re-scan)

Tabel: `ID | Temuan run sebelumnya | Fix | Commit | Hasil re-scan`. Hasil
re-scan hanya boleh salah satu dari: `HILANG` (terverifikasi tertutup) atau
`MASIH ADA`. Jangan campur dengan temuan baru run ini - temuan baru run ini
masuk ke Findings Matrix (2.5) dengan Status `New`.

Jika ada temuan yang diklasifikasi **false positive**, wajib subsection
`### Analisis: mengapa temuan X false positive` berisi bukti konkret (skrip,
query, atau reasoning), bukan klaim tanpa evidence.

### 2.8 Raw Artifacts

Struktur folder run (before/after, instructions/, dsb.) - boleh disalin apa
adanya dari run sebelumnya karena strukturnya memang tetap.

### 2.9 Limitations & Honesty Note

Wajib ada di akhir setiap README, minimal menyatakan: run yang `completed`
dengan 0 temuan bukan bukti tidak adanya kerentanan, hanya bukti kelas
serangan tertentu bertahan terhadap agent/model yang dipakai saat itu.

### 2.10 areaN/README.md (indeks area)

Setiap `strix-runs/<tanggal>/areaN-<slug>/README.md` **wajib** memakai bentuk berikut,
sama untuk **semua** area dan **semua** tanggal:

```markdown
# Area N - <Nama Area> (run YYYY-MM-DD)

| Bagian | Isi |
| ------ | --- |
| [`before/`](before/) | <deskripsi artefak mentah> |
| [`after/`](after/) | <deskripsi bukti perbaikan> |

## Temuan run ini

| ID | Severity | CWE | Temuan | Status |
| --- | -------- | --- | ------ | ------ |
| ... |

Fix: `hash` (...)  -  Regression: `path/test`.
```

Kolom tabel temuan **baku**: `ID | Severity | CWE | Temuan | Status`.
Jika area tidak punya temuan, tulis satu baris `| - | - | - | 0 temuan (semua pertahanan bertahan) | - |`
- **jangan** hilangkan tabelnya. H1 selalu berpola
`# Area N - <Nama Area> (run YYYY-MM-DD)`.

### 2.11 after/README.md (bukti perbaikan)

Setiap `areaN-<slug>/after/README.md` **wajib** memakai bentuk berikut, sama untuk
**semua** area dan **semua** tanggal:

```markdown
# Area N - After fix (verification evidence)

| | |
| --- | --- |
| Findings | <ID + CWE + severity> |
| Fix commit | `hash` ... |
| Regression test | `path` |
| Reproduce | [`reproduce.sh`](reproduce.sh) -> [`reproduce-after.log`](reproduce-after.log) |

## Before vs After

| # | Skenario | BEFORE (rentan) | AFTER (terbukti aman) |
| --- | --- | --- | --- |

## Cara reproduksi (after)

```bash
...
```

## Status verifikasi

<"diverifikasi re-scan" ATAU "belum re-scan; dikunci regression test">
```

Aturan: (1) judul section **persis** seperti di atas; (2) tabel
`Before vs After` (atau `Before vs After (kondisi terverifikasi)`) wajib; (3) bila
perbaikan **belum** diverifikasi re-scan Strix ketiga, section Status verifikasi
**wajib** menyatakannya jujur - dilarang mengklaim "terbukti" bila hanya berbasis test.

---

## 3. Definisi baku (jangan didefinisikan ulang per-file)

### 3.1 Severity

| Level | Kriteria singkat |
| --- | --- |
| CRITICAL | Eksploitasi langsung menghasilkan full compromise / bypass kontrol inti (mis. auth bypass permanen) |
| HIGH | Eksploitasi menghasilkan akses/dampak signifikan dengan prasyarat terbatas |
| MEDIUM | Butuh kondisi spesifik atau dampak terbatas pada scope tertentu |
| LOW | Dampak minor, defense-in-depth, atau butuh banyak prasyarat |

Severity yang dilaporkan **selalu severity dari Strix/SARIF apa adanya** (jangan
di-downgrade/upgrade manual di README tanpa catatan alasan eksplisit).

### 3.2 Status temuan

| Status | Arti |
| --- | --- |
| `New` | Pertama kali muncul pada run ini |
| `Confirmed-fixed` | Muncul di run sebelumnya, hilang di re-scan dengan instruksi identik |
| `False-positive` | Diklaim Strix, dibuktikan salah dengan analisis manual (wajib evidence) |
| `Carried-over` | Muncul lagi di run berikutnya meski sudah "fixed" - regresi |

---

## 4. VULN-LOG.md (indeks lintas-run)

Lokasi: `docs/intern/VULN-LOG.md`. Satu baris per temuan, **urut dari yang
terbaru ke terlama**. Kolom baku:

```markdown
| ID | CWE | Area | Severity | Ditemukan | Diperbaiki | Commit Fix | Status Saat Ini | Detail |
```

- `ID` - sama persis dengan ID di README run asal (`A1-vuln-0002`)
- `Ditemukan` / `Diperbaiki` - tanggal (YYYY-MM-DD)
- `Detail` - link relatif ke `vuln-*.md` yang bersangkutan
- `Status Saat Ini` - status terkini (bukan status saat ditemukan) - kalau ada
  yang regresi (`Carried-over`), baris ini yang mencerminkan kondisi terbaru,
  dengan catatan link ke run regresinya.

**Tabrakan ID antar-run.** Penomoran Strix mulai dari `vuln-0001` di tiap run,
sehingga ID bisa sama di dua tanggal berbeda. Untuk menghindari tabrakan di
tabel ini, ID yang bentrok diberi suffix `(tanggal)` - mis.
`A2-vuln-0001 (2026-09-21)` dan `A2-vuln-0001 (2026-09-22)` adalah dua temuan
berbeda.

File ini **di-update setiap kali** ada README run baru yang menghasilkan temuan
baru atau mengubah status temuan lama - bagian dari definisi selesai (definition
of done) suatu run, bukan pekerjaan opsional belakangan.

---

## 5. Konvensi commit message untuk fix security

Commit yang memperbaiki temuan security wajib memakai prefix:

```
fix(security): <ringkasan singkat> [CWE-XXX]
```

Contoh: `fix(security): reject NUL byte before password hash [CWE-230]`

Ini memungkinkan `git log --grep "fix(security)"` menemukan semua fix security
tanpa buka dokumen sama sekali, dan hash commit ini yang dirujuk di kolom
`Commit Fix` pada `VULN-LOG.md` dan section Remediation & Verification README.

> **Catatan historis (jujur).** Perbaikan temuan run 2026-09-21 memakai prefix
> `fix(auth)` / `fix(authz)` (belum `fix(security)`), dan hash-nya tidak dicatat
> di README saat itu. Tabel `VULN-LOG.md` mengisi kolom `Commit Fix` untuk temuan
> tersebut berdasarkan hasil penelusuran `git log` pada tanggal terkait. Konvensi
> `fix(security): ... [CWE-XXX]` berlaku **mulai** run 2026-09-22 dan seterusnya.

---

## 6. Checklist definition-of-done untuk satu run

Sebuah run pentest **tidak dianggap terdokumentasi lengkap** sampai:

- [ ] README run mengikuti section 2.1-2.9 secara lengkap dan berurutan
- [ ] Findings Matrix pakai kolom baku (2.5), Status terisi untuk semua baris
- [ ] Coverage Matrix ada (boleh isi "tidak ada pengujian baru")
- [ ] Jika re-scan: section Remediation & Verification ada, false-positive
      (jika ada) disertai bukti
- [ ] `VULN-LOG.md` sudah di-update dengan baris temuan baru / perubahan status
- [ ] Commit fix (jika ada) memakai prefix `fix(security): ... [CWE-XXX]`
- [ ] Link silang dua arah: README run baru me-link ke run sebelumnya (baseline),
      dan (jika relevan) README run sebelumnya di-update untuk menambahkan
      pointer "lihat juga run berikutnya"
