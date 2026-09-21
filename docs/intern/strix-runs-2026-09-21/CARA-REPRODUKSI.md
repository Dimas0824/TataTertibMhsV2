# Cara Reproduksi — Strix Pentest DiscipLink

Panduan menjalankan ulang (reproduce) pentest Strix pada DiscipLink, **per area**, dengan
setelan yang terbukti tidak kena rate-limit crash (5/5 area sukses, 0× HTTP 503).

Diuji pada: 2026-09-21, Windows + WSL Ubuntu.

---

## 0. Ringkasan resep yang terbukti

| Komponen | Nilai | Alasan |
| --- | --- | --- |
| **Runtime** | **WSL Ubuntu** (bukan Windows `strix.exe`) | Docker hanya ada di WSL (via snap) |
| **Strix** | `/home/dimas/.strix/bin/strix` (v1.4.1) | Instalasi WSL |
| **Docker CLI** | `/snap/bin/docker` | Muncul hanya di *login shell* (`bash -lc`) |
| **Model** | `dailyDriver` (gateway lokal) | Satu-satunya yang aktif di gateway |
| **`STRIX_REASONING_EFFORT`** | `low` | Kurangi token & fan-out subagent |
| **`STRIX_FORCE_REQUIRED_TOOL_CHOICE`** | `true` | Paksa agent pakai tool |
| **`STRIX_IMAGE`** | `ghcr.io/usestrix/strix-sandbox:1.2.0` | Image sandbox yang tersedia |
| **Scan mode** | `--scan-mode deep --scope-mode full` | White-box menyeluruh |
| **`--max-turns`** | `120` | Batasi panjang tiap run |
| **Scope** | **1 area per run** | Mencegah burst paralel → 503 |
| **Cooldown** | `sleep 30` antar run | Beri jeda gateway |
| **Runner** | **tmux** | Proses tidak mati saat shell keluar |
| **Flag interaktif** | **`-n`** | v1.4.1 tidak punya `--non-interactive` |

> **Penting:** gagal berulang terjadi karena (a) menjalankan dari Windows, (b) mode `deep`
> dengan seluruh serangan sekaligus, (c) `reasoning=high`, (d) `--non-interactive` yang tidak
> dikenali v1.4.1. Resep di atas menyelesaikan keempatnya.

---

## 1. Prasyarat

```bash
# WSL: Docker harus hidup (snap)
wsl.exe -d Ubuntu -- bash -lc "docker info >/dev/null && echo 'docker OK'"

# Strix terpasang di WSL
wsl.exe -d Ubuntu -- bash -lc "ls -la /home/dimas/.strix/bin/strix"
```

Aplikasi target harus bisa dijangkau dari Windows dan dari container sandbox.
Jalankan app di host binding semua interface:

```powershell
# Windows (dari root repo PHP-native)
php artisan serve --host=0.0.0.0 --port=8001
```

Verifikasi dari WSL/container (harus 200):

```bash
wsl.exe -d Ubuntu -- bash -lc "curl -s -o /dev/null -w '%{http_code}\n' http://172.17.112.1:8001/login"
```

Alamat `172.17.112.1` = gateway default WSL (host). Cek ulang dengan `ip route | grep default`.

---

## 2. Siapkan instruksi per area

Setiap area punya satu file instruksi fokus. Contoh tersimpan di
`docs/intern/strix-runs/` (lihat instruksi area di riwayat commit) atau buat baru mengikuti pola:

- **AREA 1** — LOGIN: SQLi, auth bypass, enumeration, brute-force, session, CSRF, open redirect
- **AREA 2** — SESSION & CSRF: fixation, cookie flags, lifecycle, CSRF coverage
- **AREA 3** — UPLOAD/DOWNLOAD/IDOR: tipe file, traversal, token sealing, IDOR/BOLA, RBAC
- **AREA 4** — PELANGGARAN: business logic, mass assignment, SQLi, stored XSS, authz
- **AREA 5** — NEWS: stored XSS, sanitizer bypass, SQLi, CSRF, authz, output encoding

Salin file instruksi ke WSL:

```bash
mkdir -p /home/dimas/disciplink-work/pt-areas
# letakkan areaN_*.md di /home/dimas/disciplink-work/pt-areas/
```

---

## 3. Script run per area

Contoh `/home/dimas/disciplink-work/run_areaN.sh` (**wajib LF, bukan CRLF** — tulis dari sisi
Linux/heredoc, jangan lewat interpolasi PowerShell yang merusak `$PATH`):

```bash
#!/bin/bash
cd /home/dimas/disciplink-work

# PATH: strix di ~/.strix/bin ; docker CLI di /snap/bin
export PATH="$HOME/.strix/bin:/snap/bin:$PATH"

# tunggu docker siap (tmux bisa start sebelum dockerd)
for i in $(seq 1 30); do docker info >/dev/null 2>&1 && break; sleep 1; done
docker info >/dev/null 2>&1 || { echo "FATAL: docker not ready"; exit 1; }

export STRIX_LLM="dailyDriver"
export LLM_API_KEY="<GATEWAY_KEY>"
export LLM_API_BASE="http://172.17.112.1:20128/v1"
export OPENAI_API_KEY="$LLM_API_KEY"
export OPENAI_BASE_URL="$LLM_API_BASE"
export STRIX_IMAGE="ghcr.io/usestrix/strix-sandbox:1.2.0"
export STRIX_REASONING_EFFORT="low"
export STRIX_FORCE_REQUIRED_TOOL_CHOICE="true"
export LLM_TIMEOUT="300"

echo "starting-areaN at $(date)"
exec strix --target http://172.17.112.1:8001 \
  --mount "/mnt/d/MiniProject/TataTertibMhsV2" \
  --instruction-file /home/dimas/disciplink-work/pt-areas/areaN_<nama>.md \
  --scan-mode deep --scope-mode full --max-turns 120 -n
```

> Jangan simpan `LLM_API_KEY` di repo — beri nilai dari environment atau isi manual.

---

## 4. Jalankan di tmux (anti-mati)

```bash
wsl.exe -d Ubuntu -- bash -lc "
  tmux kill-session -t strixpt 2>/dev/null || true
  cd /home/dimas/disciplink-work
  tmux new-session -d -s strixpt 'bash /home/dimas/disciplink-work/run_areaN.sh > /home/dimas/disciplink-work/PT_areaN.log 2>&1'
  sleep 4; tmux ls
"
```

**Cooldown antar area** (beri jeda gateway):

```bash
sleep 30   # sebelum run area berikutnya
```

---

## 5. Pantau progres

```bash
# proses & sandbox
wsl.exe -d Ubuntu -- bash -lc "ps aux | grep '[s]trix' | wc -l; docker ps --format '{{.Names}} {{.Status}}'"

# log & hitung 503 (harus 0)
wsl.exe -d Ubuntu -- bash -lc "grep -c 'HTTP 503\|rate limit' /home/dimas/disciplink-work/PT_areaN.log"

# status run (completed?)
wsl.exe -d Ubuntu -- bash -lc "R=\$(ls -dt /home/dimas/disciplink-work/strix_runs/*/ | head -1); python3 -c \"import json;print(json.load(open('\$R/run.json'))['status'])\""
```

---

## 6. Web view (opsional, live)

```bash
wsl.exe -d Ubuntu -- bash -lc "
  export PATH=\"\$HOME/.strix/bin:/snap/bin:\$PATH\"
  cd /home/dimas/disciplink-work
  tmux new-session -d -s strixview 'strix view <RUN_NAME> --port 8322 --host 0.0.0.0'
"
# buka: http://127.0.0.1:8322/?token=<TOKEN dari log>
```

Token ada di keluaran `strix view`; setiap kali viewer di-restart token berubah.

---

## 7. Ambil hasil

Hasil tiap run ada di `strix_runs/<RUN_NAME>/`:

| File | Isi |
| --- | --- |
| `findings.sarif` | SARIF 2.1.0 (`tool.driver.name = "Strix"`) — buka di SARIF viewer |
| `vulnerabilities/vuln-*.md` | Detail per temuan + PoC request/response |
| `vulnerabilities.csv` / `.json` | Indeks temuan |
| `penetration_test_report.md` | Laporan naratif + negative-result matrix |
| `run.json` | Status, usage LLM, target |
| `strix.log` | Log lengkap |

**Artefak yang sudah tersimpan di repo** (untuk perbandingan bila run ulang):

```
docs/intern/strix-runs/
├── README.md                 <- matriks + ringkasan
├── area1-login/
├── area2-session-csrf/
├── area3-upload-idor/
├── area4-pelanggaran/
└── area5-news-xss/
```

---

## 8. Troubleshooting (dari pengalaman nyata)

| Gejala | Sebab | Solusi |
| --- | --- | --- |
| `strix: command not found` | PATH tidak memuat `~/.strix/bin` | export PATH di script (langkah 3) |
| `docker: command not found` | `/snap/bin` tidak di PATH non-login | pakai `bash -lc`, export `/snap/bin` |
| `failed to connect to docker API ... /var/run/docker.sock` | dockerd belum siap saat tmux start | loop tunggu docker (langkah 3) |
| `unrecognized arguments: --non-interactive` | v1.4.1 pakai `-n` | ganti ke `-n` |
| `unrecognized arguments: -` | script ber-CRLF | tulis script LF (heredoc / dari Linux) |
| `HTTP 503` / `RemoteDisconnected` | burst request > limit gateway | 1 area/run, `reasoning=low`, `max-turns 120`, cooldown 30s |
| proses mati setelah beberapa detik | shell keluar membunuh proses | jalankan di **tmux** |
| `syntax error near unexpected token '('` di script | `$PATH`/`$HOME` ter-interpolasi PowerShell | tulis script langsung ke filesystem WSL |

---

## 9. Batasan & kejujuran

- Model `dailyDriver` **bukan** model frontier (Strix sendiri memperingatkan ini). Hasil bersifat
  *low-noise & reproducible*, bukan menyeluruh.
- Status `completed` dengan **0 temuan** berarti kelas serangan yang diuji **ditahan** oleh
  aplikasi pada run itu — **bukan** bukti tidak ada kerentanan.
- Jalankan ulang setelah ada perubahan pada kode terkait (login, session, upload, pelanggaran, news).
