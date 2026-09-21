#!/usr/bin/env bash
#
# reproduce.sh - verify the two login findings are FIXED (post-fix evidence).
#
# Runs against a local instance and asserts:
#   CWE-230 : a NUL-suffixed password is rejected; the exact password still works.
#   CWE-307 : a fresh session does NOT reset the lockout.
#
# Usage:
#   bash reproduce.sh [BASE_URL]
#
# Exit code 0 = all checks passed (vulnerable behaviour gone).
# Exit code 1 = a check failed (exploit still works, or regression).
#
# NOTE: this clears throttle rows for the test account/IP first, so run it
# against a disposable lab DB - not production.

set -u
BASE="${1:-http://127.0.0.1:8123}"
HERE="$(cd "$(dirname "$0")" && pwd)"

pass=0
fail=0
check() { # label expected actual
  if [ "$2" = "$3" ]; then
    printf '  [PASS] %-46s -> %s\n' "$1" "$3"; pass=$((pass+1))
  else
    printf '  [FAIL] %-46s -> %s (expected %s)\n' "$1" "$3" "$2"; fail=$((fail+1))
  fi
}

JARDIR="$(mktemp -d)"
trap 'rm -rf "$JARDIR"' EXIT
jar() { mktemp "$JARDIR/.jar.XXXXXX"; }
csrf() { curl -s -c "$1" -b "$1" "$BASE/login" | grep -oE 'name="csrf_token" value="[0-9a-f]{64}"' | head -1 | grep -oE '[0-9a-f]{64}'; }
post() { # jar token username password
  curl -s -o /dev/null -D - -b "$1" -c "$1" -X POST "$BASE/action/login" \
    -H 'Content-Type: application/x-www-form-urlencoded' \
    --data-raw "csrf_token=$2&user_type=nim&username=$3&password=$4" \
    | grep -i '^location:' | tr -d '\r' | sed 's/^[Ll]ocation: *//'
}

echo "=================================================================="
echo " Reproduce AFTER fix - login hardening (area1-login)"
echo " target: $BASE"
echo "=================================================================="

# Clear throttle rows for the test account so the result is deterministic
# (deletes from SECURITY_AUDIT_LOG - run against a disposable lab DB only).
if command -v php >/dev/null 2>&1; then
  php -r '
    require "config.php";
    $c = $GLOBALS["connect"];
    $c->exec("DELETE FROM SECURITY_AUDIT_LOG WHERE event IN (\"login_fail\",\"login_reject_input\",\"login_locked\") AND actor_id = \"2341238901\"");
    $c->exec("DELETE FROM SECURITY_AUDIT_LOG WHERE event IN (\"login_fail\",\"login_reject_input\",\"login_locked\") AND created_at >= (NOW() - INTERVAL 900 SECOND)");
  ' 2>/dev/null || true
fi
echo
echo "CWE-230 (HIGH) - NUL-byte truncation in password verification"
J=$(jar); T=$(csrf "$J"); R=$(post "$J" "$T" 2341238901 "password123%00INJECTED"); rm -f "$J"
check "NUL-suffixed password rejected" "/login" "$R"
J=$(jar); T=$(csrf "$J"); R=$(post "$J" "$T" 2341238901 "password123"); rm -f "$J"
check "exact password still authenticates" "/pelanggaran" "$R"
echo
echo "CWE-307 (CRITICAL) - brute-force lockout is per-session only"
JA=$(jar)
for i in 1 2 3 4 5; do T=$(csrf "$JA"); post "$JA" "$T" 2341238901 "bogus$i" >/dev/null; done
JB=$(jar); TB=$(csrf "$JB"); R=$(post "$JB" "$TB" 2341238901 "password123"); rm -f "$JA" "$JB"
check "fresh session cannot bypass lockout" "/login" "$R"
echo
echo "=================================================================="
printf ' RESULT: %d passed, %d failed\n' "$pass" "$fail"
echo "=================================================================="
[ "$fail" -eq 0 ] || exit 1
