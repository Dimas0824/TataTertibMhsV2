# CSP Nonce (Phase #2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove `'unsafe-inline'` from the CSP `script-src` by emitting a per-request nonce on every inline `<script>` and in the CSP header.

**Architecture:** A single request-scoped nonce is generated once (static cache) inside `helpers/seo_helper.php`. `app_seo_apply_security_headers()` reads the same cached nonce and puts it in `script-src`; every inline `<script>` (and the `onload=` preload handlers, via `script-src-attr`) is emitted with `<?= app_csp_nonce_attr() ?>`. Because all 3 call-sites of the header function share the process static, the header value and the tag value always match.

**Tech Stack:** PHP 8.3 (native, no framework/composer), custom `router.php` front-controller, Playwright (E2E), curl.

## Global Constraints

- Project root: `D:\MiniProject\TTM-csp` (git worktree of branch `feat/csp-nonce`, repo `Dimas0824/TataTertibMhsV2`).
- PHP 8.3 native — no Composer, no framework, no `composer.json`. Helpers are `require_once`-ed manually.
- `helpers/seo_helper.php` is the ONLY file that owns CSP. Do not duplicate header logic.
- The nonce MUST be generated once per request and be identical in (a) the CSP header and (b) every nonce attribute. Use a function-static cache keyed on the request.
- Nonce must be cryptographically random (>=128 bits), base64-encoded, and emitted with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- `style-src 'unsafe-inline'` is **out of scope** — leave it unchanged. Only `script-src` (and `script-src-attr`) change.
- Do NOT trust any nonce value from user input; the cache is internal only.
- Windows PowerShell 5.1 quirks: no `&&`; `curl` is an alias for Invoke-WebRequest — always call `curl.exe`; use `[Math]::Min` etc. for slicing.
- Existing dev server convention: port 83xx–84xx for manual smoke (e.g. `php -S 127.0.0.1:8300 router.php`).
- Verification is manual (no DB-backed test runner on this branch): `curl.exe -I` header check + `cd tests/e2e; npx playwright test --project=chromium` (target: 21/21 green).

---

### Task 0: Baseline — capture current broken state & env

**Files:**
- Read: `helpers/seo_helper.php`, `index.php`, `router.php`
- Read: `tests/e2e/playwright.config.js`

**Interfaces:**
- Consumes: nothing.
- Produces: a recorded baseline (screenshots/headers) to compare against; confirms the server can boot.

- [ ] **Step 1: Confirm the current WIP breaks pages (proves the gap)**

The 7 tagged views already call `app_csp_nonce_attr()`, which does not exist. Boot the server and hit one tagged page.

Run (PowerShell, from `D:\MiniProject\TTM-csp`):
```powershell
Start-Process -WindowStyle Hidden php -ArgumentList '-S','127.0.0.1:8300','router.php'
Start-Sleep -Seconds 2
curl.exe -s http://127.0.0.1:8300/ | Select-String -Pattern 'app_csp_nonce_attr|Fatal error|Call to undefined'
```
Expected: output contains `Call to undefined function app_csp_nonce_attr()` (or a 500).

- [ ] **Step 2: Capture the current CSP header (baseline)**

Run:
```powershell
curl.exe -sI http://127.0.0.1:8300/ | Select-String -Pattern 'Content-Security-Policy'
```
Expected: header contains `script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net ...`. Record it.

- [ ] **Step 3: Record e2e baseline**

Run:
```powershell
cd tests\e2e; npx playwright test --project=chromium 2>&1 | Select-Object -Last 15
```
Expected: tests currently FAIL (pages 500). Record the count. This is the "before".

- [ ] **Step 4: Stop the manual server (leave no zombie)**

Run:
```powershell
Get-NetTCPConnection -LocalPort 8300 -State Listen -ErrorAction SilentlyContinue | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force }
```

---

### Task 1: Add the nonce helpers to `helpers/seo_helper.php`

**Files:**
- Modify: `helpers/seo_helper.php` (insert after line 4, before `app_seo_load_env`)
- Test: manual `php -r` one-liner

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `app_csp_nonce(): string` — returns the per-request nonce (generates once, caches in a function static).
  - `app_csp_nonce_attr(): string` — returns the raw HTML attribute string `nonce="<value>"` (escaped), or `''` when running under CLI.

- [ ] **Step 1: Write the failing check first**

Run:
```powershell
php -r "require 'helpers/seo_helper.php'; var_dump(function_exists('app_csp_nonce'), function_exists('app_csp_nonce_attr'));"
```
Expected: `bool(false) bool(false)` — both missing.

- [ ] **Step 2: Insert the helpers**

Open `helpers/seo_helper.php`. After the `require_once __DIR__ . '/path_helper.php';` line (line 4) and before `if (!function_exists('app_seo_load_env')) {` (line 6), insert exactly:

```php
if (!function_exists('app_csp_nonce')) {
    /**
     * Per-request CSP nonce. Generated once and cached for the whole request so the
     * value written into the Content-Security-Policy header always matches the value
     * written into every <script nonce="..."> attribute, regardless of which of the
     * (multiple) call-sites invokes apply_security_headers() first.
     */
    function app_csp_nonce(): string
    {
        static $nonce = null;
        if (is_string($nonce) && $nonce !== '') {
            return $nonce;
        }

        try {
            $bytes = random_bytes(16); // 128 bits
        } catch (\Throwable $e) {
            // Extremely unlikely; fail closed to a per-process random-ish value rather
            // than emitting a predictable constant.
            $bytes = hash('sha256', uniqid((string) getmypid(), true), true);
            $bytes = substr($bytes, 0, 16);
        }

        $nonce = base64_encode($bytes);
        return $nonce;
    }
}

if (!function_exists('app_csp_nonce_attr')) {
    /**
     * Ready-to-print HTML attribute: nonce="...". Returns '' under CLI (no HTTP
     * response, nothing to protect) so view code can always call it unconditionally.
     */
    function app_csp_nonce_attr(): string
    {
        if (PHP_SAPI === 'cli') {
            return '';
        }

        return 'nonce="' . htmlspecialchars(app_csp_nonce(), ENT_QUOTES, 'UTF-8') . '"';
    }
}
```

- [ ] **Step 3: Verify both functions now exist and are stable within a request**

Run:
```powershell
php -r "require 'helpers/seo_helper.php'; $a=app_csp_nonce(); $b=app_csp_nonce(); $c=app_csp_nonce_attr(); var_dump($a===$b, strlen($a), $c);"
```
Expected: `bool(true)`, `int(24)` (16 bytes → 24 base64 chars), and `string(31) "nonce=\"...\""`. Because `PHP_SAPI==='cli'`, `$c` should actually be `string(0) ""` — i.e. expect `bool(true) int(24) string(0) ""`. The stability check (`$a===$b`) is the key assertion.

- [ ] **Step 4: Commit**

```powershell
git add helpers/seo_helper.php
git commit -m "feat(csp): add request-scoped nonce helpers"
```

---

### Task 2: Inject the nonce into the CSP header and drop `'unsafe-inline'` from `script-src`

**Files:**
- Modify: `helpers/seo_helper.php:198-211` (the `$csp = implode(...)` block inside `app_seo_apply_security_headers`)

**Interfaces:**
- Consumes: `app_csp_nonce(): string` from Task 1.
- Produces: `Content-Security-Policy` header whose `script-src` begins `'self' 'nonce-<value>'` and no longer contains `'unsafe-inline'`; adds `script-src-attr 'none'` (inline handlers must not be allowed; see Task 4).

- [ ] **Step 1: Write the failing check first**

Run:
```powershell
(cd D:\MiniProject\TTM-csp; Start-Process -WindowStyle Hidden php -ArgumentList '-S','127.0.0.1:8301','router.php'; Start-Sleep 2; curl.exe -sI http://127.0.0.1:8301/ | Select-String 'Content-Security-Policy'; Get-NetTCPConnection -LocalPort 8301 -State Listen | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force })
```
Expected: header still shows `script-src 'self' 'unsafe-inline' ...` and NO `nonce-`. (The request may 500 because of Task 0's undefined function — that's fine; if it 500s before headers, temporarily comment the 7 view tags is NOT needed: check the header on `/` which is `index.php` and does not use the modal, so it should still emit headers.)

- [ ] **Step 2: Replace the CSP block**

In `helpers/seo_helper.php`, replace lines 196-210 (the comment + `$csp = implode(...)` array) with:

```php
        // script-src uses a per-request nonce instead of 'unsafe-inline': every inline
        // <script> carries nonce="..." matching the header. Inline event handler
        // attributes (onload=, onclick=) are disallowed outright via script-src-attr
        // 'none'; the one remaining preload handler is migrated to a non-handler in
        // Task 4. style-src keeps 'unsafe-inline' (out of scope for this change).
        $nonce = app_csp_nonce();
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-" . $nonce . "' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://www.googletagmanager.com",
            "script-src-attr 'none'",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
            "img-src 'self' data: https:",
            "connect-src 'self' https://cdn.jsdelivr.net https://www.googletagmanager.com https://*.google-analytics.com",
            "frame-src 'self' https://www.googletagmanager.com",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        header('Content-Security-Policy: ' . $csp);
```

- [ ] **Step 3: Verify the header now carries a nonce and no `'unsafe-inline'` in script-src**

Run:
```powershell
(cd D:\MiniProject\TTM-csp; Start-Process -WindowStyle Hidden php -ArgumentList '-S','127.0.0.1:8302','router.php'; Start-Sleep 2; $h=(curl.exe -sI http://127.0.0.1:8302/ | Select-String 'Content-Security-Policy').Line; $h; "HAS_NONCE=" + ($h -match "script-src 'self' 'nonce-[A-Za-z0-9+/=]+'"); "SCRIPT_UNSAFE_INLINE=" + ($h -match "script-src[^;]*'unsafe-inline'"); Get-NetTCPConnection -LocalPort 8302 -State Listen | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force })
```
Expected: `HAS_NONCE=True`, `SCRIPT_UNSAFE_INLINE=False`.

- [ ] **Step 4: Verify header nonce == tag nonce on a tagged page**

First untag is not needed — tag `index.php` outputs no inline script, so test on `/login` (its inline script lives in `app-feedback-modal.php`, only rendered when a flash modal exists — so instead assert against the shell page). Simplest deterministic check: add the nonce to one always-rendered inline script first (Task 3 does the analytics script). For now verify equality on the error page `/this-does-not-exist` (renders `error-page.php:98` which is tagged):

Run:
```powershell
(cd D:\MiniProject\TTM-csp; Start-Process -WindowStyle Hidden php -ArgumentList '-S','127.0.0.1:8303','router.php'; Start-Sleep 2; $hdr=((curl.exe -sI http://127.0.0.1:8303/nope | Select-String 'Content-Security-Policy').Line -replace '.*nonce-([A-Za-z0-9+/=]+).*','$1'); $body=(curl.exe -s http://127.0.0.1:8303/nope); $tag=([regex]::Match($body,'nonce="([A-Za-z0-9+/=]+)"')).Groups[1].Value; "HDR=$hdr"; "TAG=$tag"; "MATCH=" + ($hdr -eq $tag -and $hdr -ne ''); Get-NetTCPConnection -LocalPort 8303 -State Listen | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force })
```
Expected: `MATCH=True`. This is the single most important assertion of the whole plan.

- [ ] **Step 5: Commit**

```powershell
git add helpers/seo_helper.php
git commit -m "feat(csp): nonce for script-src, drop unsafe-inline, deny inline handlers"
```

---

### Task 3: Tag the remaining inline script — GA4 analytics

**Files:**
- Modify: `helpers/seo_helper.php:440` (inside `app_seo_analytics_tags()`)

**Interfaces:**
- Consumes: `app_csp_nonce_attr()` from Task 1; header nonce from Task 2.
- Produces: the GA4 inline `<script>` carries the nonce. Note: the `<script async src="...gtag/js...">` is external and must NOT get a nonce.

- [ ] **Step 1: Write the failing check**

Run:
```powershell
Select-String -Path helpers\seo_helper.php -Pattern 'window\.dataLayer = window\.dataLayer' -Context 2,0
```
Expected: shows `?> <script>` (no nonce) immediately preceding it.

- [ ] **Step 2: Add the nonce attribute**

In `app_seo_analytics_tags()`, change the inline block. Current:
```php
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= $escapedId ?>"></script>
        <script>
```
Change the second line to:
```php
        <script <?= app_csp_nonce_attr() ?>>
```
Leave the `<script async src=...>` line untouched.

- [ ] **Step 3: Verify**

Run:
```powershell
php -l helpers\seo_helper.php
```
Expected: `No syntax errors detected in helpers\seo_helper.php`.

Note: `app_seo_analytics_tags()` returns early when `GA4_MEASUREMENT_ID` is empty. To verify end-to-end you may temporarily set `GA4_MEASUREMENT_ID=G-TEST` in `.env`, load any page, confirm the inline gtag script has `nonce=` and no CSP violation appears in the browser console, then revert `.env`. Do NOT commit the test value.

- [ ] **Step 4: Commit**

```powershell
git add helpers/seo_helper.php
git commit -m "feat(csp): nonce the inline GA4 analytics script"
```

---

### Task 4: Migrate all inline event handlers off `on*=` attributes

**Files:**
- Modify: `index.php:36-42` (the `<link rel="preload" as="style" ... onload="this.onload=null;this.rel='stylesheet'">` + its `<noscript>`)
- Modify: `views/pelanggaran/edit-pelaporan.php:85,96` (+ paired `<noscript>` blocks)
- Modify: `views/pelanggaran/notifikasi.php:39` (+ paired `<noscript>`)
- Modify: `views/pelanggaran/pelanggaran-dosen.php:466` (+ paired `<noscript>`)
- Modify: `views/pelanggaran/pelanggaran-page.php:279` (+ paired `<noscript>`)
- Modify: `views/pelanggaran/pelanggaran-page.php:543` (the `onclick="closeModal()"` button)
- Modify: `views/pelanggaran/pelaporan.php:45,56` (+ paired `<noscript>` blocks)

**Interfaces:**
- Consumes: Task 2's `script-src-attr 'none'` (which blocks EVERY inline `on*=` handler).
- Produces: zero inline event-handler attributes remain; every previously-`onload=` stylesheet preload becomes a plain `<link rel="stylesheet">` (paired `<noscript>` removed as redundant); the `onclick="closeModal()"` button relies on its existing JS listener instead.

**Why this is required (not optional):** there are **9** inline handlers, not 8. Besides the 8 `onload=` preloads there is one `onclick="closeModal()"` (and no other `on*=` anywhere — verified by a repo-wide scan). Under `script-src-attr 'none'` its handler is blocked, so the "Batal" button would silently stop closing the modal. It is safe to drop because `js/pelanggaran-dashboard.js:25` already binds the same `closeModal` to `.btn-secondary` via `addEventListener`.

**Rationale:** The `onload=this.rel='stylesheet'` trick is a JS-dependent lazy-CSS pattern. With `script-src-attr 'none'` it silently stops working. The stylesheets involved (Select2 CSS + Google Fonts Inter) are small and already HTTP/2-cached; loading them unconditionally is the correct, CSP-clean simplification (YAGNI: no preload dance needed).

- [ ] **Step 1: Write the failing check (see the current handler count)**

Run:
```powershell
(Select-String -Path index.php,views\**\*.php -Pattern "\son\w+\s*=").Count
```
Expected: `9` (8 `onload=` preloads + 1 `onclick=`). Record it.

- [ ] **Step 2: Convert each `onload=` preload to a normal stylesheet link**

For each of the 8 `onload=` sites, the current pattern is:
```php
<link rel="preload" as="style" href="<URL>" onload="this.onload=null;this.rel='stylesheet'">
<noscript>
    <link href="<URL>" rel="stylesheet" />
</noscript>
```
Replace the WHOLE two-block group with a single:
```php
<link rel="stylesheet" href="<URL>">
```
(Keep the original `<URL>` expression exactly — e.g. `app_asset_url('css/...')`, the Select2 CDN URL, or the Google Fonts URL. Do not retype the URL; copy the existing `href` value.)

In `index.php`, the Google Fonts block at lines ~36-43 currently is:
```php
    <link rel="preload" as="style"
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link
            href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
            rel="stylesheet">
    </noscript>
```
Replace with:
```php
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap">
```
(copy the exact query string from the existing href.)

- [ ] **Step 3: Remove the standalone `onclick` handler (rely on existing JS listener)**

In `views/pelanggaran/pelanggaran-page.php:543`, change:
```php
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
```
to:
```php
                <button type="button" class="btn btn-secondary" data-modal-close="uploadModal">Batal</button>
```
Then in `js/pelanggaran-dashboard.js`, replace the fragile selector at line 11:
```js
    const closeModalBtn = document.querySelector(".btn-secondary"); // Tombol Batal
```
with:
```js
    const closeModalBtn = document.querySelector('[data-modal-close="uploadModal"]'); // Tombol Batal
```
(Line 25's `closeModalBtn.addEventListener("click", closeModal)` already does the work — no other change needed. This also fixes the latent bug that `.btn-secondary` grabbed the wrong button on pages with multiple secondary buttons.)

- [ ] **Step 4: Verify no inline event handlers remain**

Run:
```powershell
(Select-String -Path index.php,views\**\*.php,js\*.js -Pattern "\son\w+\s*=").Count
```
Expected: `0`.

- [ ] **Step 4: Verify pages still render CSS (no CSP violation)**

Run:
```powershell
php -l index.php
(cd D:\MiniProject\TTM-csp; Start-Process -WindowStyle Hidden php -ArgumentList '-S','127.0.0.1:8304','router.php'; Start-Sleep 2; $b=curl.exe -s http://127.0.0.1:8304/; "HAS_STYLESHEET_LINK=" + ($b -match 'rel="stylesheet"'); "HAS_PRELOAD_ONLOAD=" + ($b -match "onload="); Get-NetTCPConnection -LocalPort 8304 -State Listen | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force })
```
Expected: `HAS_STYLESHEET_LINK=True`, `HAS_PRELOAD_ONLOAD=False`.

- [ ] **Step 5: Commit**

```powershell
git add index.php views/pelanggaran/edit-pelaporan.php views/pelanggaran/notifikasi.php views/pelanggaran/pelanggaran-dosen.php views/pelanggaran/pelanggaran-page.php views/pelanggaran/pelaporan.php js/pelanggaran-dashboard.js
git commit -m "refactor(csp): remove all inline on*= handlers (preload hacks + modal close)"
```

---

### Task 5: Full verification — header/tag match everywhere + e2e green

**Files:**
- Read: all previously modified files (no new edits unless a violation is found)

**Interfaces:**
- Consumes: Tasks 1-4.
- Produces: evidence the CSP is enforced without breaking the app.

- [ ] **Step 1: Static sweep — every inline `<script>` has a nonce**

Run:
```powershell
# List inline <script> openers that are NOT followed by src= and lack a nonce
Get-ChildItem views -Recurse -File -Filter *.php | Select-String -Pattern '<script(?![^>]*\bsrc=)' | ForEach-Object {
  $ln=$_.LineNumber; $lines=Get-Content $_.Path
  $opener=$lines[($ln-1)..([Math]::Min($ln+1,$lines.Count-1))] -join ' '
  if ($opener -notmatch 'app_csp_nonce_attr') { "MISSING NONCE: {0}:{1}" -f $_.Path,$ln }
}
Select-String -Path helpers\seo_helper.php -Pattern '<script(?![^>]*\bsrc=)' | ForEach-Object { "CHECK seo_helper.php:{0}: {1}" -f $_.LineNumber,$_.Line.Trim() }
```
Expected: no `MISSING NONCE` lines; `seo_helper.php` inline script lines all show `app_csp_nonce_attr()`. (The `<script type="application/ld+json">` in `app_seo_json_ld_tags` is `type`-declared and does NOT execute, so it needs no nonce — confirm it is the only un-tagged one and note it.)

- [ ] **Step 2: Runtime — header/tag nonce match on 3 representative pages**

Run:
```powershell
(cd D:\MiniProject\TTM-csp; Start-Process -WindowStyle Hidden php -ArgumentList '-S','127.0.0.1:8305','router.php'; Start-Sleep 2
foreach ($p in @('/','/login','/nope')) {
  try { $r=curl.exe -si "http://127.0.0.1:8305$p" } catch { $r='' }
  $hdr=((($r -split "`r`n") | Select-String 'Content-Security-Policy') -replace '.*nonce-([A-Za-z0-9+/=]+).*','$1')
  $tag=([regex]::Match(($r -join "`n"),'nonce="([A-Za-z0-9+/=]+)"')).Groups[1].Value
  "{0}: HDR={1} TAG={2} MATCH={3}" -f $p,$hdr,$tag,(($hdr -eq $tag) -and $hdr -ne '')
}
Get-NetTCPConnection -LocalPort 8305 -State Listen | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force })
```
Expected: `MATCH=True` for `/nope` (has an inline script). For `/` and `/login`, TAG may be empty (no inline script on those exact renders) — `HAS_NONCE_IN_HEADER=True` is what must hold; if TAG is present it MUST equal HDR. Interpret accordingly and report.

- [ ] **Step 3: Browser console has no CSP violation**

Start the server on `8306`, open Chrome DevTools on `/pelanggaran` (log in first) or `/nope`, and confirm the Console shows **no** `Refused to execute inline script` / `Refused to apply inline style` (for scripts). Document any violation verbatim.

- [ ] **Step 4: E2E suite must be green (21/21)**

Run:
```powershell
cd D:\MiniProject\TTM-csp\tests\e2e
npm install
npx playwright install chromium
npx playwright test --project=chromium 2>&1 | Select-Object -Last 20
```
Expected: all pass (target 21/21, matching the V2 baseline). If a test fails, capture the failing trace/report — that is a real regression to fix before committing.

- [ ] **Step 5: Commit any fixes; then final commit**

```powershell
git add -A
git commit -m "test(csp): verify nonce matches header and e2e stays green"
```

---

### Task 6: Commit the pre-existing WIP and open the PR

**Files:**
- Modify: none (staging + branch ops only)

**Interfaces:**
- Consumes: Tasks 0-5.
- Produces: branch `feat/csp-nonce` with a clean history; PR ready against `fix/security-hardening` (per PR #7 stacking) or retargeted to `main`.

- [ ] **Step 1: Confirm the 7 view files are in the history (not lost)**

Run:
```powershell
git log --oneline -12
git status
```
Expected: the 7 view nonce tags appear in some commit (Tasks 2-5 or an explicit WIP commit). If they were never committed, add them now:
```powershell
git add views/components/modals/app-feedback-modal.php views/errors/error-page.php views/partials/app-shell.php views/pelanggaran/edit-pelaporan.php views/pelanggaran/pelanggaran-page.php views/pelanggaran/pelaporan.php views/tatib/list-tatib.php
git commit -m "feat(csp): nonce-tag existing inline scripts (was uncommitted WIP)"
```

- [ ] **Step 2: Push the branch**

Run:
```powershell
git push origin feat/csp-nonce
```

- [ ] **Step 3: Open the PR**

Run:
```powershell
gh pr create --base fix/security-hardening --head feat/csp-nonce --title "feat(csp): per-request nonce, drop unsafe-inline for scripts" --body "Phase #2 of security hardening. Adds app_csp_nonce()/app_csp_nonce_attr(), injects the nonce into script-src, removes 'unsafe-inline' from script-src, denies inline handlers via script-src-attr 'none', and migrates the onload= CSS preload hacks to plain stylesheets. Verified: header nonce == tag nonce, e2e chromium 21/21."
```
Expected: PR URL printed. Paste it in the final report.

---

## Self-Review

**1. Spec coverage**
- `app_csp_nonce()` + `app_csp_nonce_attr()` → Task 1 ✅
- inject nonce into header, replace `'unsafe-inline'` in script-src → Task 2 ✅
- cover all inline scripts (7 WIP views already tagged; analytics found + tagged) → Task 3 + Task 5 Step 1 sweep ✅
- 7 `onload=` preload hacks (+1 in index.php = 8) AND the 1 stray `onclick=` (9 total) → Task 4 ✅
- verify CSP works + e2e green → Task 5 ✅
- commit the pre-existing uncommitted WIP → Task 6 ✅

**2. Placeholder scan:** No TBD/TODO/"handle edge cases"/"similar to Task N". Every code step shows the actual code or the exact command.

**3. Type consistency:** `app_csp_nonce(): string` and `app_csp_nonce_attr(): string` are introduced in Task 1 and referenced with identical names/arity in Tasks 2, 3, 5. The header variable `$nonce` in Task 2 is local to `app_seo_apply_security_headers()`. Port numbers are unique per task (8300-8306) to avoid zombie-port collisions noted in the repo handoff doc.

**4. Self-review correction log (what changed after first draft):**
- Task 4 originally claimed "8 inline handlers = 8 `onload=`". A repo-wide scan found **9**: 8 `onload=` + 1 `onclick="closeModal()"` in `pelanggaran-page.php:543`. Left unaddressed, `script-src-attr 'none'` would have silently broken the modal "Batal" button. Task 4 now removes it and pins the JS selector to a stable `data-modal-close` attribute.
- `index.php` onload block line range corrected from `33` to `36-42`.
- Noted that `GA4_MEASUREMENT_ID` is unset in `.env`, so the analytics inline script (Task 3) does not render in dev — its verification requires temporarily setting the env var and must be reverted.
- Confirmed `<script type="application/ld+json">` (seo_helper.php:423) is non-executing JSON-LD and does NOT need a nonce; it is the single intentionally-un-tagged script.
