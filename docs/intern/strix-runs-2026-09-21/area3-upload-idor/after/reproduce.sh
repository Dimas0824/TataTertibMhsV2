#!/usr/bin/env bash
#
# reproduce.sh - AREA 3 (upload/download/IDOR) after-state checks.
#
# AREA 3 had zero security findings, so this verifies:
#   - the fixed non-security defect: admin on the lecturer-only page -> 403 (not 500)
#   - a held defense spot-check: a raw filename download is refused (403)
#
# Usage:  bash reproduce.sh [BASE_URL]
# Exit 0 = all checks passed.

set -u
BASE="${1:-http://127.0.0.1:8123}"

pass=0
fail=0
check() {
  if [ "$2" = "$3" ]; then
    printf '  [PASS] %-52s -> %s\n' "$1" "$3"; pass=$((pass+1))
  else
    printf '  [FAIL] %-52s -> %s (expected %s)\n' "$1" "$3" "$2"; fail=$((fail+1))
  fi
}

JARDIR="$(mktemp -d)"
trap 'rm -rf "$JARDIR"' EXIT
jar() { mktemp "$JARDIR/.jar.XXXXXX"; }
csrf() { curl -s -c "$1" -b "$1" "$BASE/login" | grep -oE 'name="csrf_token" value="[0-9a-f]{64}"' | head -1 | grep -oE '[0-9a-f]{64}'; }
login() { # jar username password type -> prints Location
  curl -s -o /dev/null -D - -b "$1" -c "$1" -X POST "$BASE/action/login" \
    -H 'Content-Type: application/x-www-form-urlencoded' \
    --data-raw "csrf_token=$2&user_type=$4&username=$3&password=$5" \
    | grep -i '^location:' | tr -d '\r' | sed 's/^[Ll]ocation: *//'
}
status() { curl -s -o /dev/null -w '%{http_code}' -b "$1" "$BASE$2"; }

echo "=================================================================="
echo " AREA 3 after-state (upload/download/IDOR)"
echo " target: $BASE"
echo "=================================================================="
if command -v php >/dev/null 2>&1; then
  php -r '
    require "config.php";
    $c = $GLOBALS["connect"];
    $c->exec("DELETE FROM USER_SESSION WHERE actor_id IN (\"ADMIN001\",\"1234567890\")");
    $c->exec("DELETE FROM SECURITY_AUDIT_LOG WHERE event IN (\"login_fail\",\"login_locked\",\"login_reject_input\")");
  ' 2>/dev/null || true
fi
echo

ADM=$(jar); T=$(csrf "$ADM"); login "$ADM" "$T" ADMIN001 nip admin123 >/dev/null
DOS=$(jar); T=$(csrf "$DOS"); login "$DOS" "$T" 1234567890 nidn password123 >/dev/null

echo "defect fixed - non-dosen role on lecturer-only page"
check "admin GET /pelanggaran/dosen -> 403" "403" "$(status "$ADM" /pelanggaran/dosen)"
check "dosen GET /pelanggaran/dosen -> 200" "200" "$(status "$DOS" /pelanggaran/dosen)"
echo
echo "held defense spot-check"
check "raw filename download refused" "403" "$(status "$DOS" '/action/download?file=x.pdf')"
check "anonymous download refused" "403" "$(status "" '/action/download?file=x.pdf')"
echo
echo "=================================================================="
printf ' RESULT: %d passed, %d failed\n' "$pass" "$fail"
echo "=================================================================="
[ "$fail" -eq 0 ] || exit 1
