# Area 3 - Upload / Download / IDOR (run 2026-09-21)

Indeks area. Artefak mentah ada di `before/`, bukti perbaikan ada di `after/`.

| Bagian | Isi |
| ------ | --- |
| [`before/`](before/) | Artefak mentah Strix: `findings.sarif`, `penetration_test_report.md` (0 findings + negative-result matrix), `run.json`, `strix.log`, `.state/` |
| [`after/`](after/) | Bukti perbaikan (role guard) + skrip reproduce + screenshot |

## Temuan run ini

| ID | Severity | CWE | Temuan | Status |
| --- | -------- | --- | ------ | ------ |
| - | - | - | 0 temuan (semua pertahanan bertahan) | - |

Matriks kelas serangan yang **dicoba dan bertahan** ada di [README run](../README.md)
section *Coverage / Negative-Result Matrix* dan di `before/penetration_test_report.md`.

> Catatan: re-scan 2026-09-22 menemukan **temuan baru** di area ini (role guard
> halaman mahasiswa) - lihat [`../../strix-2026-09-22/area3-upload-idor/`](../../strix-2026-09-22/area3-upload-idor/).
