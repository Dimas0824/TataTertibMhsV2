<?php

declare(strict_types=1);

/**
 * login_throttle_helper.php — durable (server-side) login throttling + credential
 * input validation.
 *
 * Fixes two pentest findings (Strix white-box run, 2026-09-21):
 *   - CWE-307 (CRITICAL): the brute-force lockout lived only in $_SESSION, so a
 *     client that discarded its cookie started with a fresh budget. The durable
 *     decision is now derived from the SECURITY_AUDIT_LOG table, keyed on the
 *     attempted account identifier AND the source IP.
 *   - CWE-230 (HIGH): bcrypt comparison is NUL-terminated, so a valid password
 *     suffixed with "\0<junk>" authenticated. Malformed credentials are rejected
 *     before they reach the verifier.
 *
 * Design notes:
 *   - Fail-soft: any store error is treated as "not locked" so an infrastructure
 *     problem can never lock every user out of the application.
 *   - The source IP is taken from $_SERVER['REMOTE_ADDR'] only. Forwarded headers
 *     (X-Forwarded-For) are client-controllable and must never key a lockout.
 */

require_once __DIR__ . '/path_helper.php';

// Account identifier: 5 failures / 15 min. IP: looser cap so a shared NAT does
// not lock unrelated users after a few typos.
if (!defined('APP_LOGIN_MAX_FAILS_ACTOR')) {
    define('APP_LOGIN_MAX_FAILS_ACTOR', 5);
}
if (!defined('APP_LOGIN_MAX_FAILS_IP')) {
    define('APP_LOGIN_MAX_FAILS_IP', 15);
}
if (!defined('APP_LOGIN_LOCK_WINDOW')) {
    define('APP_LOGIN_LOCK_WINDOW', 900); // seconds
}
if (!defined('APP_LOGIN_MAX_PASSWORD_BYTES')) {
    define('APP_LOGIN_MAX_PASSWORD_BYTES', 72); // bcrypt limit
}
if (!defined('APP_LOGIN_MAX_USERNAME_BYTES')) {
    define('APP_LOGIN_MAX_USERNAME_BYTES', 64);
}

if (!function_exists('app_login_client_ip')) {
    /**
     * Trusted client IP for throttle keying. Remote-address only; never a header.
     */
    function app_login_client_ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return substr($ip, 0, 45);
    }
}

if (!function_exists('app_login_input_invalid')) {
    /**
     * Validate raw credential input before it reaches the password verifier.
     *
     * Returns a short machine-readable reason string when the input is
     * unacceptable, or null when it is acceptable.
     *
     * @return string|null one of 'nul_byte' | 'password_too_long' | 'username_too_long'
     */
    function app_login_input_invalid(string $username, string $password): ?string
    {
        // bcrypt / C-string semantics: a NUL anywhere would truncate the compare.
        if (strpos($username, "\0") !== false || strpos($password, "\0") !== false) {
            return 'nul_byte';
        }

        // Explicit bounds so inputs are rejected rather than silently truncated.
        if (strlen($password) > APP_LOGIN_MAX_PASSWORD_BYTES) {
            return 'password_too_long';
        }
        if (strlen($username) > APP_LOGIN_MAX_USERNAME_BYTES) {
            return 'username_too_long';
        }

        return null;
    }
}

if (!function_exists('app_login_throttle_status')) {
    /**
     * Durable lockout check, independent of the session.
     *
     * Counts recent 'login_fail' audit rows for the account identifier and for
     * the client IP within APP_LOGIN_LOCK_WINDOW, and locks when either exceeds
     * its threshold.
     *
     * @return array{locked: bool, retry_after: int, reason: string}
     */
    function app_login_throttle_status(string $username, ?string $ip = null): array
    {
        $open = ['locked' => false, 'retry_after' => 0, 'reason' => ''];

        $connect = $GLOBALS['connect'] ?? null;
        if (!$connect instanceof PDO) {
            return $open; // fail-soft: no store => do not lock anyone out
        }

        $ip = $ip === null ? app_login_client_ip() : substr($ip, 0, 45);
        $actor = substr($username, 0, 32);

        try {
            // The audit writer stores created_at via the column's CURRENT_TIMESTAMP
            // default, i.e. the DB session clock (NOW()). Compare against NOW() too:
            // mixing in UTC_TIMESTAMP() would be off by the session offset (e.g. WIB
            // is +07:00) and skew the window by hours.
            $where = "event = 'login_fail'
                        AND created_at >= (NOW() - INTERVAL :window SECOND)
                        AND (actor_id = :actor_b OR ip = :ip_b)";

            $stmt = $connect->prepare(
                "SELECT
                    SUM(CASE WHEN actor_id = :actor_a THEN 1 ELSE 0 END) AS by_actor,
                    SUM(CASE WHEN ip = :ip_a THEN 1 ELSE 0 END) AS by_ip
                 FROM SECURITY_AUDIT_LOG
                 WHERE $where"
            );
            $stmt->execute([
                ':actor_a' => $actor,
                ':ip_a'    => $ip,
                ':window'  => APP_LOGIN_LOCK_WINDOW,
                ':actor_b' => $actor,
                ':ip_b'    => $ip,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $byActor = (int) ($row['by_actor'] ?? 0);
            $byIp = (int) ($row['by_ip'] ?? 0);

            $reasons = [];
            if ($byActor >= APP_LOGIN_MAX_FAILS_ACTOR) {
                $reasons[] = 'actor';
            }
            if ($byIp >= APP_LOGIN_MAX_FAILS_IP) {
                $reasons[] = 'ip';
            }

            if ($reasons === []) {
                return $open;
            }

            // Retry hint: seconds until the oldest in-window failure ages out,
            // computed entirely on the DB clock (same as the WHERE above).
            $oldest = $connect->prepare(
                "SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(),
                            MIN(created_at) + INTERVAL :window2 SECOND)) AS retry_after
                 FROM SECURITY_AUDIT_LOG
                 WHERE event = 'login_fail'
                   AND created_at >= (NOW() - INTERVAL :window3 SECOND)
                   AND (actor_id = :actor_o OR ip = :ip_o)"
            );
            $oldest->execute([
                ':window2' => APP_LOGIN_LOCK_WINDOW,
                ':window3' => APP_LOGIN_LOCK_WINDOW,
                ':actor_o' => $actor,
                ':ip_o'    => $ip,
            ]);
            $retryAfter = max(0, (int) ($oldest->fetchColumn() ?: 0));

            return ['locked' => true, 'retry_after' => $retryAfter, 'reason' => implode('+', $reasons)];
        } catch (Throwable $e) {
            error_log('login throttle store error: ' . $e->getMessage());
            return $open; // fail-soft
        }
    }
}
