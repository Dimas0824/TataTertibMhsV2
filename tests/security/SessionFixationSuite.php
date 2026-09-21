<?php

/**
 * SessionFixationSuite — regression for the finding found during the Strix
 * re-run (AREA 2 re-scan, MEDIUM, CWE-384).
 *
 * PHP ran with session.use_strict_mode disabled, so the server adopted any
 * client-supplied PHPSESSID verbatim and created server-side state (including
 * the CSRF token) under an attacker-chosen identifier. Strict mode makes PHP
 * reject an unknown id and issue a fresh one.
 */

require_once __DIR__ . '/SecurityClient.php';

if (!SecurityClient::available()) {
    $runner->addTest('session-fixation suite skipped', function () {
        echo "\n       (skipped: cannot start php -S test server: " . SecurityClient::lastError() . ')';
    });
    return;
}

$runner->addTest('session: an unknown client-supplied PHPSESSID is replaced (CWE-384)', function () {
    // A random id never issued by the server and not reused across runs.
    $supplied = 'fix' . bin2hex(random_bytes(12));
    $jar = SecurityClient::newJar();
    file_put_contents($jar, "127.0.0.1\tFALSE\t/\tFALSE\t0\tPHPSESSID\t{$supplied}\n");

    $r = SecurityClient::request('GET', '/login', ['jar' => $jar]);
    $setCookie = (string) ($r['headers']['set-cookie'] ?? '');

    assertTrue(
        $setCookie !== '' && stripos($setCookie, 'phpsessid=') !== false,
        'the server must issue a replacement session id for an unknown one'
    );
    assertTrue(
        stripos($setCookie, $supplied) === false,
        'the client-supplied identifier must NOT be adopted (it must be replaced)'
    );
});
