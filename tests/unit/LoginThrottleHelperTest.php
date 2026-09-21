<?php

/**
 * LoginThrottleHelperTest — unit coverage for the login hardening helpers.
 *
 * Regression for two pentest findings (Strix, 2026-09-21):
 *   - CWE-230 HIGH : NUL-byte truncation in password verification.
 *   - CWE-307 CRIT : brute-force lockout is per-session only.
 *
 * The helper is intentionally fail-soft: if the DB/store is unavailable the
 * throttle must report NOT locked so login still works (availability wins).
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/helpers/login_throttle_helper.php';

$ltDb = static function (): ?PDO {
    $c = $GLOBALS['connect'] ?? null;
    return $c instanceof PDO ? $c : null;
};

// ---------------------------------------------------------------- constants
$runner->addTest('login-throttle: helper functions and constants exist', function () {
    assertTrue(function_exists('app_login_throttle_status'), 'app_login_throttle_status must exist');
    assertTrue(function_exists('app_login_input_invalid'), 'app_login_input_invalid must exist');
    assertTrue(defined('APP_LOGIN_MAX_FAILS_ACTOR'), 'actor threshold constant');
    assertTrue(defined('APP_LOGIN_MAX_FAILS_IP'), 'ip threshold constant');
    assertTrue(defined('APP_LOGIN_LOCK_WINDOW'), 'window constant');
});

// ------------------------------------------------------- input validation (B)
$runner->addTest('login-throttle: rejects NUL byte in password (CWE-230)', function () {
    assertNotNull(
        app_login_input_invalid('2341238901', "password123\0INJECTED"),
        'password containing a NUL byte must be rejected'
    );
});

$runner->addTest('login-throttle: rejects NUL byte in username (CWE-230)', function () {
    assertNotNull(
        app_login_input_invalid("2341238901\0x", 'password123'),
        'username containing a NUL byte must be rejected'
    );
});

$runner->addTest('login-throttle: rejects password longer than 72 bytes', function () {
    assertNotNull(app_login_input_invalid('2341238901', str_repeat('a', 73)), '73-byte password rejected');
});

$runner->addTest('login-throttle: accepts exact and 72-byte passwords', function () {
    assertNull(app_login_input_invalid('2341238901', 'password123'), 'normal password accepted');
    assertNull(app_login_input_invalid('2341238901', str_repeat('a', 72)), 'exactly 72 bytes accepted');
});

// --------------------------------------------------------- fail-soft plumbing
$runner->addTest('login-throttle: fails soft when the DB is unavailable', function () {
    $saved = $GLOBALS['connect'] ?? null;
    $GLOBALS['connect'] = null;
    try {
        $s = app_login_throttle_status('2341238901', '203.0.113.250');
        assertFalse($s['locked'], 'must NOT lock when store is unavailable');
        assertEquals(0, $s['retry_after'], 'retry_after is 0 when store unavailable');
    } finally {
        $GLOBALS['connect'] = $saved;
    }
});

// ------------------------------------------------- windows count (DB-backed)
$runner->addTest('login-throttle: actor lock after N in-window failures (DB)', function () use ($ltDb) {
    $c = $ltDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }

    $marker = 'ZZTHR' . bin2hex(random_bytes(4));
    $ip = '203.0.113.251';
    $cleanup = static function () use ($c, $marker, $ip) {
        $st = $c->prepare("DELETE FROM SECURITY_AUDIT_LOG WHERE (actor_id = ? OR ip = ?) AND event IN ('login_fail','login_reject_input')");
        $st->execute([$marker, $ip]);
    };
    $cleanup();
    try {
        $ins = $c->prepare("INSERT INTO SECURITY_AUDIT_LOG (event, actor_id, ip) VALUES ('login_fail', ?, ?)");

        // 4 recent failures -> not locked
        for ($i = 0; $i < 4; $i++) {
            $ins->execute([$marker, $ip]);
        }
        $s = app_login_throttle_status($marker, $ip);
        assertFalse($s['locked'], '4 failures must not lock');

        // 5th recent failure -> locked
        $ins->execute([$marker, $ip]);
        $s = app_login_throttle_status($marker, $ip);
        assertTrue($s['locked'], '5 failures must lock');

        // window expiry: backdate everything > 900s -> unlocked again
        $c->prepare("UPDATE SECURITY_AUDIT_LOG SET created_at = (NOW() - INTERVAL 901 SECOND) WHERE actor_id = ?")
          ->execute([$marker]);
        $s = app_login_throttle_status($marker, $ip);
        assertFalse($s['locked'], 'failures older than the window must not lock');
    } finally {
        $cleanup();
    }
});

$runner->addTest('login-throttle: IP lock is separate from the actor lock (DB)', function () use ($ltDb) {
    $c = $ltDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }

    $ip = '203.0.113.252';
    $cleanup = static function () use ($c, $ip) {
        $c->prepare("DELETE FROM SECURITY_AUDIT_LOG WHERE ip = ? AND event = 'login_fail'")->execute([$ip]);
    };
    $cleanup();
    try {
        $ins = $c->prepare("INSERT INTO SECURITY_AUDIT_LOG (event, actor_id, ip) VALUES ('login_fail', ?, ?)");

        // spread failures across DIFFERENT actors on one IP, under the IP threshold
        for ($i = 0; $i < (int) APP_LOGIN_MAX_FAILS_IP - 1; $i++) {
            $ins->execute(['ZZIP' . $i, $ip]);
        }
        $s = app_login_throttle_status('ZZOTHERACTOR', $ip);
        assertFalse($s['locked'], 'IP under threshold must not lock a fresh actor');

        // one more -> IP threshold reached -> locked for any actor on that IP
        $ins->execute(['ZZIPLAST', $ip]);
        $s = app_login_throttle_status('ZZOTHERACTOR', $ip);
        assertTrue($s['locked'], 'IP at threshold must lock');
    } finally {
        $cleanup();
    }
});
