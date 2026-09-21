#!/usr/bin/env bash
#
# reproduce.sh - verify the AREA 2 session findings are FIXED (post-fix evidence).
#
# Asserts:
#   CWE-614 : the session cookie carries Secure when a proxy signals HTTPS,
#             and does NOT force Secure on plain HTTP.
#   CWE-613 : a second login for the same account revokes the first session.
#
# Usage:  bash reproduce.sh [BASE_URL]
# Exit 0 = all checks passed.  Exit 1 = a check failed.
#
# NOTE: clears USER_SESSION + login audit rows for the test accounts so the
# result is deterministic. Run only against a disposable lab DB.

set -u
BASE="${1:-http://127.0.0.1:8123}"
HERE="$(cd "$(dirname "$0")" && pwd)"

pass=0
fail=0
check() { # label expected actual
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
post() { # jar token username password
  curl -s -o /dev/null -D - -b "$1" -c "$1" -X POST "$BASE/action/login" \
    -H 'Content-Type: application/x-www-form-urlencoded' \
    --data-raw "csrf_token=$2&user_type=nim&username=$3&password=$4" \
    | grep -i '^location:' | tr -d '\r' | sed 's/^[Ll]ocation: *//'
}
status() { curl -s -o /dev/null -w '%{http_code}' -b "$1" "$BASE$2"; }
setcookie() { curl -s -o /dev/null -D - "$@" "$BASE/login" | grep -i '^set-cookie:' | tr -d '\r' | head -1; }

echo "=================================================================="
echo " Reproduce AFTER fix - session lifecycle (area2-session-csrf)"
echo " target: $BASE"
echo "=================================================================="

# Clear throttle + inventory state for the test accounts (disposable lab DB only).
if command -v php >/dev/null 2>&1; then
  php -r '
    require "config.php";
    $c = $GLOBALS["connect"];
    $c->exec("DELETE FROM USER_SESSION WHERE actor_id IN (\"2341238901\",\"2341238902\")");
    $c->exec("DELETE FROM SECURITY_AUDIT_LOG WHERE event IN (\"login_fail\",\"login_locked\",\"login_reject_input\")");
  ' 2>/dev/null || true
fi
echo

echo "CWE-614 (MEDIUM) - Secure session cookie behind a TLS-terminating proxy"
PROXY_COOKIE="$(setcookie -H 'X-Forwarded-Proto: https' -H 'Host: dimspersonal.my.id' | tr 'A-Z' 'a-z')"
PLAIN_COOKIE="$(setcookie | tr 'A-Z' 'a-z')"
case "$PROXY_COOKIE" in *secure*) V=secure ;; *) V=nosecure ;; esac
check "proxy HTTPS: cookie has Secure" "secure" "$V"
case "$PLAIN_COOKIE" in *secure*) V=secure ;; *) V=nosecure ;; esac
check "plain HTTP: cookie has NO Secure" "nosecure" "$V"
echo

echo "CWE-613 (LOW) - a second login revokes the earlier session"
JA=$(jar)
LOC_A="$(T=$(csrf "$JA"); post "$JA" "$T" 2341238901 password123)"
check "first login reaches a dashboard (302 not /login)" "/pelanggaran" "$LOC_A"
JB=$(jar)
LOC_B="$(T=$(csrf "$JB"); post "$JB" "$T" 2341238901 password123)"
check "second login reaches a dashboard" "/pelanggaran" "$LOC_B"
check "first session is now revoked (not 200)" "302" "$(status "$JA" /pelanggaran)"
check "second session is still valid" "200" "$(status "$JB" /pelanggaran)"
echo
echo "=================================================================="
printf ' RESULT: %d passed, %d failed\n' "$pass" "$fail"
echo "=================================================================="
[ "$fail" -eq 0 ] || exit 1
