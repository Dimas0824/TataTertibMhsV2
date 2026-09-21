# area1-login --- RE-RUN (Strix re-scan 2026-09-22)

Run kedua dengan **instruksi + parameter identik** run pertama, terhadap kode
yang sudah diperbaiki. Tujuan: verifikasi independen klaim "sudah di-fix".

| | |
| --- | --- |
| **Area** | LOGIN / AUTHENTICATION |
| **Run (re-run)** | `172-17-112-1-8001_1a29` |
| **Hasil** | 2 temuan LAMA HILANG; 1 temuan baru **CRITICAL** (case-variant lockout) --- **FALSE POSITIVE** (lihat verification-analysis/README.md) |

## Temuan re-run

| id | title | severity |
| --- | --- | --- |
| `vuln-0001` | Login lockout can be bypassed via case-varied account identifiers (CI collation vs case-sensitive throttle key) | CRITICAL |

## Artefak

- `findings.sarif` --- SARIF 2.1.0 (tool=Strix)
- `vulnerabilities/*.md` --- detail temuan + PoC
- `penetration_test_report.md` --- laporan naratif
- `vulnerabilities.csv` / `.json` --- indeks temuan
- `run.json` --- status + usage LLM
- `strix.log` --- log lengkap
- `.state/` --- database percakapan agent (agents.db)

> Ringkasan lengkap 5 area: [`../verification-analysis/README.md`](../verification-analysis/README.md)
