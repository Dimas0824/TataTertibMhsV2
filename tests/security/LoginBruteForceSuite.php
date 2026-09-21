<?php

/**
 * LoginBruteForceSuite — HTTP regression for two confirmed login findings
 * (Strix white-box run, 2026-09-21):
 *
 *   - CWE-307 (CRITICAL): lockout was per-session; a fresh session reset it.
 *   - CWE-230 (HIGH): NUL-byte truncation in password verification.
 *
 * Each test replays the exact exploit payload and asserts it now fails closed.
 * Runs against a live `php -S` via SecurityClient; skips gracefully if the
 * server or DB is unavailable.
 */

require_once __DIR__ . '/SecurityClient.php';

if (!SecurityClient::available()) {
    $runner->addTest('login-hardening suite skipped', function () {
        echo "\n       (skipped: cannot start php -S test server: " . SecurityClient::lastError() . ')';
    });
    return;
}

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/helpers/login_throttle_helper.php';

$lbDb = static function (): ?PDO {
    $c = $GLOBALS['connect'] ?? null;
    return $c instanceof PDO ? $c : null;
};

/**
 * Clear the durable throttle rows for one actor+ip so each test starts clean.
 * The lock is keyed on audit rows, so leftover rows would poison the next test.
 */
$lbReset = static function (string $actor, string $ip) use ($lbDb): void {
    $c = $lbDb();
    if ($c === null) {
        return;
    }
    $st = $c->prepare("DELETE FROM SECURITY_AUDIT_LOG WHERE event IN ('login_fail','login_reject_input') AND (actor_id = ? OR ip = ?)");
    $st->execute([substr($actor, 0, 32), $ip]);
};

// The test server talks to us over 127.0.0.1, so REMOTE_ADDR will be 127.0.0.1
// for every request. Reset that key between tests to avoid cross-contamination.
$lbTestIp = '127.0.0.1';

/* ------------------------------------------------------------------ */
/* S2 — NUL-byte truncation must be rejected (CWE-230)                 */
/* Run this FIRST so the account's failure window is clean.            */
/* ------------------------------------------------------------------ */

$runner->addTest('login: NUL byte in password is rejected (CWE-230)', function () use ($lbReset, $lbTestIp) {
    $lbReset('2341238901', $lbTestIp);

    $jar = SecurityClient::newJar();
    $token = SecurityClient::csrfFrom(SecurityClient::request('GET', '/login', ['jar' => $jar])['body']);

    // exact password + NUL + trailing junk (the live exploit)
    $r = SecurityClient::request('POST', '/action/login', [
        'jar' => $jar,
        'form' => [
            'csrf_token' => $token,
            'user_type'  => 'nim',
            'username'   => '2341238901',
            'password'   => "password123\0INJECTED",
        ],
    ]);

    assertEquals('/login', $r['headers']['location'] ?? '', 'NUL-suffixed password must NOT authenticate');
});

$runner->addTest('login: NUL byte in username is rejected (CWE-230)', function () use ($lbReset, $lbTestIp) {
    $lbReset('2341238901', $lbTestIp);

    $jar = SecurityClient::newJar();
    $token = SecurityClient::csrfFrom(SecurityClient::request('GET', '/login', ['jar' => $jar])['body']);

    $r = SecurityClient::request('POST', '/action/login', [
        'jar' => $jar,
        'form' => [
            'csrf_token' => $token,
            'user_type'  => 'nim',
            'username'   => "2341238901\0x",
            'password'   => 'password123',
        ],
    ]);

    assertEquals('/login', $r['headers']['location'] ?? '', 'NUL in username must NOT authenticate');
});

$runner->addTest('login: exact password still authenticates (control)', function () use ($lbReset, $lbTestIp) {
    $lbReset('2341238901', $lbTestIp);

    $jar = SecurityClient::login('2341238901', 'password123', 'nim');
    assertTrue($jar !== null, 'exact credentials must still authenticate after the NUL guard');
});

$runner->addTest('login: password longer than 72 bytes is rejected (CWE-230)', function () use ($lbReset, $lbTestIp) {
    $lbReset('2341238901', $lbTestIp);

    $jar = SecurityClient::newJar();
    $token = SecurityClient::csrfFrom(SecurityClient::request('GET', '/login', ['jar' => $jar])['body']);

    $r = SecurityClient::request('POST', '/action/login', [
        'jar' => $jar,
        'form' => [
            'csrf_token' => $token,
            'user_type'  => 'nim',
            'username'   => '2341238901',
            'password'   => str_repeat('a', 73),
        ],
    ]);

    assertEquals('/login', $r['headers']['location'] ?? '', 'over-long password must be rejected');
});

/* ------------------------------------------------------------------ */
/* S1 — durable lockout survives a fresh session (CWE-307)             */
/* ------------------------------------------------------------------ */

$runner->addTest('login: fresh session cannot reset the brute-force lock (CWE-307)', function () use ($lbReset, $lbTestIp) {
    $lbReset('2341238901', $lbTestIp);

    // Session A: five wrong attempts.
    $jarA = SecurityClient::newJar();
    for ($i = 0; $i < 5; $i++) {
        $t = SecurityClient::csrfFrom(SecurityClient::request('GET', '/login', ['jar' => $jarA])['body']);
        SecurityClient::request('POST', '/action/login', [
            'jar' => $jarA,
            'form' => [
                'csrf_token' => $t,
                'user_type'  => 'nim',
                'username'   => '2341238901',
                'password'   => 'bogus' . $i,
            ],
        ]);
    }

    // Session B: BRAND NEW cookie jar. The old code reset the counter here.
    $jarB = SecurityClient::newJar();
    $tB = SecurityClient::csrfFrom(SecurityClient::request('GET', '/login', ['jar' => $jarB])['body']);
    $r = SecurityClient::request('POST', '/action/login', [
        'jar' => $jarB,
        'form' => [
            'csrf_token' => $tB,
            'user_type'  => 'nim',
            'username'   => '2341238901',
            'password'   => 'password123',
        ],
    ]);

    assertEquals('/login', $r['headers']['location'] ?? '', 'a fresh session must NOT bypass the lockout');

    $view = SecurityClient::request('GET', '/login', ['jar' => $jarB]);
    assertStringContains('Terlalu banyak', $view['body'], 'lockout notice missing on a fresh session');
});

$runner->addTest('login: lockout is not applied to a different valid account (scope)', function () use ($lbReset, $lbTestIp) {
    // Lock account 2341238901 via failures, then a DIFFERENT account must still
    // be able to log in. NOTE: both share REMOTE_ADDR 127.0.0.1, so this asserts
    // the IP cap is looser than the actor cap (5 vs 15) — the NAT-friendly rule.
    $lbReset('2341238901', $lbTestIp);
    $lbReset('2341238902', $lbTestIp);

    $jarA = SecurityClient::newJar();
    for ($i = 0; $i < 5; $i++) {
        $t = SecurityClient::csrfFrom(SecurityClient::request('GET', '/login', ['jar' => $jarA])['body']);
        SecurityClient::request('POST', '/action/login', [
            'jar' => $jarA,
            'form' => [
                'csrf_token' => $t,
                'user_type'  => 'nim',
                'username'   => '2341238901',
                'password'   => 'bogus' . $i,
            ],
        ]);
    }

    // Different account, same IP, well under the IP cap -> must authenticate.
    $jarB = SecurityClient::login('2341238902', 'password456', 'nim');
    assertTrue($jarB !== null, 'a different account from the same IP must still log in under the IP cap');
});

$runner->addTest('login: window expiry releases the lock (CWE-307)', function () use ($lbDb, $lbReset, $lbTestIp) {
    $c = $lbDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }

    $lbReset('2341238901', $lbTestIp);

    $jarA = SecurityClient::newJar();
    for ($i = 0; $i < 5; $i++) {
        $t = SecurityClient::csrfFrom(SecurityClient::request('GET', '/login', ['jar' => $jarA])['body']);
        SecurityClient::request('POST', '/action/login', [
            'jar' => $jarA,
            'form' => [
                'csrf_token' => $t,
                'user_type'  => 'nim',
                'username'   => '2341238901',
                'password'   => 'bogus' . $i,
            ],
        ]);
    }

    // Backdate the failures beyond the window.
    $c->prepare("UPDATE SECURITY_AUDIT_LOG SET created_at = (NOW() - INTERVAL 901 SECOND)
                 WHERE event='login_fail' AND (actor_id = ? OR ip = ?)")
      ->execute(['2341238901', $lbTestIp]);

    $jarB = SecurityClient::login('2341238901', 'password123', 'nim');
    assertTrue($jarB !== null, 'after the window expires the account must be usable again');
});

/* ------------------------------------------------------------------ */
/* Teardown — the durable throttle keys on audit rows, so this suite    */
/* MUST clear the failures it generated. Otherwise later suites that    */
/* log in as the same account from 127.0.0.1 inherit a lockout.         */
/* ------------------------------------------------------------------ */

$runner->addTest('login: teardown clears throttle rows created by this suite', function () use ($lbReset) {
    foreach (['2341238901', '2341238902', '2341238903', 'ADMIN001', '1234567890', 'wronguser9999'] as $actor) {
        $lbReset($actor, '127.0.0.1');
    }
    assertTrue(true, 'throttle rows cleared');
});

