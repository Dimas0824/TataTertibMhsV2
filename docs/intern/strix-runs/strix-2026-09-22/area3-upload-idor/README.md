# area3-upload-idor --- RE-RUN (Strix re-scan 2026-09-22)

Run kedua dengan **instruksi + parameter identik** run pertama, terhadap kode
yang sudah diperbaiki. Tujuan: verifikasi independen klaim "sudah di-fix".

| | |
| --- | --- |
| **Area** | UPLOAD / DOWNLOAD / IDOR |
| **Run (re-run)** | `172-17-112-1-8001_929c` |
| **Hasil** | 1 temuan baru **MEDIUM** (missing role guard -> 500) --- **VALID**, DIFIX `d4aec0e` |

## Temuan re-run

| id | title | severity |
| --- | --- | --- |
| `vuln-0001` | Missing role guard in mahasiswa violation page causes unhandled HTTP 500 for administrators | MEDIUM |

## Artefak

- `findings.sarif` --- SARIF 2.1.0 (tool=Strix)
- `vulnerabilities/*.md` --- detail temuan + PoC
- `penetration_test_report.md` --- laporan naratif
- `vulnerabilities.csv` / `.json` --- indeks temuan
- `run.json` --- status + usage LLM
- `strix.log` --- log lengkap
- `.state/` --- database percakapan agent (agents.db)

> Ringkasan lengkap 5 area: [`../verification-analysis/README.md`](../verification-analysis/README.md)
