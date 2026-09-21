<?php

/**
 * SessionLifecycleSuite — HTTP + white-box regression for the AREA 2 findings
 * (Strix white-box run, 2026-09-21):
 *
 *   - CWE-614 (MEDIUM): session cookie issued without Secure when TLS is
 *     terminated by a reverse proxy (X-Forwarded-Proto / port 443).
 *   - CWE-613 (LOW): no absolute session lifetime; a second login does not
 *     revoke the account's earlier sessions.
 *
 * Runs against a live `php -S` via SecurityClient; skips gracefully if the
 * server or DB is unavailable.
 */

require_once __DIR__ . '/SecurityClient.php';

if (!SecurityClient::available()) {
    $runner->addTest('session-lifecycle suite skipped', function () {
        echo "\n       (skipped: cannot start php -S test server: " . SecurityClient::lastError() . ')';
    });
    return;
}

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/helpers/path_helper.php';
require_once dirname(__DIR__, 2) . '/helpers/token_helper.php';

$slDb = static function (): ?PDO {
    $c = $GLOBALS['connect'] ?? null;
    return $c instanceof PDO ? $c : null;
};

$slTableExists = static function (PDO $c, string $table): bool {
    try {
        $c->query("SELECT 1 FROM $table LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
};

/* ------------------------------------------------------------------ */
/* CWE-614 — Secure cookie on proxy-signalled HTTPS                    */
/* ------------------------------------------------------------------ */

$runner->addTest('session: proxy HTTPS signal marks the session cookie Secure (CWE-614)', function () {
    // Strict-Transport-Security is only emitted for a non-local Host, so use a
    // real host name while still hitting the local test server via Host header.
    $r = SecurityClient::request('GET', '/login', [
        'headers' => ['X-Forwarded-Proto: https', 'Host: dimspersonal.my.id'],
    ]);
    $setCookie = strtolower($r['headers']['set-cookie'] ?? '');

    assertStringContains('phpsessid', $setCookie, 'session cookie must be set');
    assertTrue(
        strpos($setCookie, 'secure') !== false,
        'session cookie must carry Secure when the proxy signals HTTPS (CWE-614)'
    );
});

$runner->addTest('session: plain HTTP localhost does not force the cookie Secure', function () {
    $r = SecurityClient::request('GET', '/login');
    $setCookie = strtolower($r['headers']['set-cookie'] ?? '');

    assertStringContains('phpsessid', $setCookie, 'session cookie must be set');
    assertStringContains('httponly', $setCookie, 'cookie keeps HttpOnly');
    assertStringContains('samesite=lax', $setCookie, 'cookie keeps SameSite=Lax');
    assertTrue(
        strpos($setCookie, 'secure') === false,
        'plain HTTP must NOT set Secure (lab login has to keep working)'
    );
});

/* ------------------------------------------------------------------ */
/* CWE-613a — absolute session lifetime (white-box, in-process)        */
/* ------------------------------------------------------------------ */

$runner->addTest('session: an aged session is rejected beyond the absolute lifetime (CWE-613)', function () {
    app_session_start_if_needed();

    $savedSession = $GLOBALS['_SESSION'] ?? $_SESSION ?? [];

    try {
        // Fresh creation time -> still valid.
        $_SESSION = ['__created_at' => time(), '__last_activity' => time(), 'username' => 'zz-sess-probe'];
        assertTrue(app_session_touch_or_expire(1800), 'a fresh session must stay alive');

        // Created beyond the ceiling -> must be torn down (return false).
        $_SESSION = [
            '__created_at'    => time() - (APP_SESSION_ABSOLUTE_TTL + 60),
            '__last_activity' => time(),
            'username'        => 'zz-sess-probe',
        ];
        assertFalse(app_session_touch_or_expire(1800), 'a session past the absolute ceiling must be rejected');
        assertFalse(isset($_SESSION['username']), 'session state must be cleared on absolute expiry');
    } finally {
        $_SESSION = $savedSession;
    }
});

$runner->addTest('session: a legacy session without __created_at is upgraded, not rejected (CWE-613)', function () {
    app_session_start_if_needed();
    $savedSession = $_SESSION;

    try {
        $_SESSION = ['__last_activity' => time(), 'username' => 'zz-sess-probe'];
        assertTrue(app_session_touch_or_expire(1800), 'legacy session must not be logged out on first request');
        assertTrue(isset($_SESSION['__created_at']), 'a creation timestamp must be stamped for later requests');
    } finally {
        $_SESSION = $savedSession;
    }
});

/* ------------------------------------------------------------------ */
/* CWE-613b — a second login revokes the earlier session (HTTP)        */
/* ------------------------------------------------------------------ */

$runner->addTest('session: a second login for the same account revokes the first session (CWE-613)', function () use ($slDb, $slTableExists) {
    $c = $slDb();
    if ($c === null || !$slTableExists($c, 'USER_SESSION')) {
        echo "\n       (skipped: USER_SESSION table unavailable)";
        return;
    }

    $cleanup = static function () use ($c) {
        $c->prepare("DELETE FROM USER_SESSION WHERE actor_id IN ('2341238901','2341238902')")->execute();
    };
    $cleanup();

    try {
        $jarA = SecurityClient::login('2341238901', 'password123', 'nim');
        assertTrue($jarA !== null, 'first login must succeed');

        $jarB = SecurityClient::login('2341238901', 'password123', 'nim');
        assertTrue($jarB !== null, 'second login must succeed');

        $a = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jarA]);
        assertTrue(
            $a['status'] !== 200,
            'the first session must be revoked after a second login'
        );

        $b = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jarB]);
        assertEquals(200, $b['status'], 'the newest session must remain valid');
    } finally {
        $cleanup();
    }
});

$runner->addTest('session: re-login of one account does not revoke a different account (CWE-613)', function () use ($slDb, $slTableExists) {
    $c = $slDb();
    if ($c === null || !$slTableExists($c, 'USER_SESSION')) {
        echo "\n       (skipped: USER_SESSION table unavailable)";
        return;
    }

    $cleanup = static function () use ($c) {
        $c->prepare("DELETE FROM USER_SESSION WHERE actor_id IN ('2341238901','2341238902')")->execute();
    };
    $cleanup();

    try {
        $jarOther = SecurityClient::login('2341238902', 'password456', 'nim');
        assertTrue($jarOther !== null, 'second account must log in');

        $jarOne = SecurityClient::login('2341238901', 'password123', 'nim');
        assertTrue($jarOne !== null, 'first account must log in');

        $other = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jarOther]);
        assertEquals(200, $other['status'], 'another account session must survive a different account login');
    } finally {
        $cleanup();
    }
});
