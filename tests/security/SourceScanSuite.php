<?php
/**
 * SourceScanSuite — structural guardrails.
 * Cheap regex scan that fails the build if known-safe patterns regress
 * (error disclosures, bare session_start, 0777, dangerous functions,
 * missing server deny rules...). Complements the runtime suites.
 */

$scanRoot = dirname(__DIR__, 2);

$secCollectFiles = static function () use ($scanRoot): array {
    $dirs = ['models', 'controllers', 'request', 'helpers', 'views'];
    $files = ['index.php', 'router.php', 'config.php'];
    $out = [];
    foreach ($dirs as $d) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $scanRoot . DIRECTORY_SEPARATOR . $d,
            FilesystemIterator::SKIP_DOTS
        ));
        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                $out[] = $f->getPathname();
            }
        }
    }
    foreach ($files as $f) {
        $out[] = $scanRoot . DIRECTORY_SEPARATOR . $f;
    }
    return $out;
};

$secGrep = static function (string $pattern) use ($secCollectFiles): array {
    $bad = [];
    foreach ($secCollectFiles() as $file) {
        $lines = file($file) ?: [];
        foreach ($lines as $i => $line) {
            if (preg_match($pattern, $line) === 1) {
                $bad[] = basename($file) . ':' . ($i + 1) . ': ' . trim($line);
            }
        }
    }
    return $bad;
};

$runner->addTest('scan: no error messages echoed to response', function () use ($secGrep) {
    $hits = $secGrep('/^\s*echo\s+["\']Error[: ]/i');
    assertEquals([], $hits, 'echo "Error..." found');
});

$runner->addTest('scan: getMessage only used inside error_log or RuntimeException guards', function () use ($secGrep) {
    $hits = [];
    foreach ($secGrep('/\$e->getMessage\(\)|\$exception->getMessage\(\)|\$err->getMessage\(\)/') as $line) {
        if (preg_match('/error_log\(|instanceof RuntimeException|instanceof PDOException|throw /', $line) !== 1) {
            $hits[] = $line;
        }
    }
    assertEquals([], $hits, 'raw getMessage() reaches output:');
});

$runner->addTest('scan: no bare session_start() outside token_helper', function () use ($secGrep) {
    $hits = [];
    foreach ($secGrep('/\bsession_start\s*\(\s*\)/') as $line) {
        if (strpos($line, 'token_helper.php:') !== 0) {
            $hits[] = $line; // token_helper owns the only legal raw starts (hardened helper + post-destroy revive)
        }
    }
    assertEquals([], $hits, 'bare session_start() found — cookie params would be skipped:');
});

$runner->addTest('scan: no world-writable mkdir modes', function () use ($secGrep) {
    $hits = $secGrep('/\b0777\b/');
    assertEquals([], $hits, 'mkdir 0777 found');
});

$runner->addTest('scan: no dynamic-execution constructs in web layer', function () use ($secGrep) {
    $hits = array_merge(
        $secGrep('/\beval\s*\(/'),
        $secGrep('/\bunserialize\s*\(/'),
        $secGrep('/\bpassthru\s*\(/'),
        $secGrep('/\bshell_exec\s*\(/'),
        $secGrep('/\bproc_open\s*\(/'),
        $secGrep('/\bcreate_function\s*\(/'),
        $secGrep('/\bextract\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/')
    );
    assertEquals([], $hits, 'dangerous construct found:');
});

$runner->addTest('scan: no raw superglobal short-echo in views', function () use ($secGrep) {
    $hits = $secGrep('/<\?=\s*\$_(GET|POST|SESSION|COOKIE|REQUEST)\b/');
    assertEquals([], $hits, 'raw <?= $_SUPERGLOBAL in view');
});

$runner->addTest('scan: server deny rules present (.htaccess + router.php)', function () use ($secCollectFiles) {
    $root = dirname(__DIR__, 2);
    $ht = (string) file_get_contents($root . '/.htaccess');
    assertStringContains('Options -Indexes', $ht);
    assertStringContains('well-known', $ht);
    assertStringContains('RewriteRule ^config\\.php$', $ht);
    assertStringContains('.env', $ht);
    $rt = (string) file_get_contents($root . '/router.php');
    assertStringContains('isSensitiveStatic', $rt);
    assertStringContains("'/config.php'", $rt);
    assertStringContains('cli-server', $rt);
});

$runner->addTest('scan: sanitizer uses boundary class that beats <p/on...> bypass', function () {
    $root = dirname(__DIR__, 2);
    $nc = (string) file_get_contents($root . '/controllers/NewsController.php');
    $bd = (string) file_get_contents($root . '/views/public/berita-detail.php');
    assertStringContains('[\\s\\/]', $nc, 'NewsController sanitizer regressed to weak \\s+ boundary');
    assertStringContains('[\\s\\/]', $bd, 'berita-detail sanitizer regressed');
});

$runner->addTest('scan: inline-script JSON embed is hex-escaped', function () {
    $f = (string) file_get_contents(dirname(__DIR__, 2) . '/views/components/modals/app-feedback-modal.php');
    assertStringContains('JSON_HEX_TAG', $f);
    assertTrue(preg_match('/JSON_UNESCAPED_SLASHES/', $f) !== 1, 'UNESCAPED_SLASHES in <script> embed reopens </script> breakout');
});

$runner->addTest('scan: gitignore keeps env & signing keys out of VCS', function () {
    $g = (string) file_get_contents(dirname(__DIR__, 2) . '/.gitignore');
    foreach (['.env', 'storage/keys/app_token.key'] as $needle) {
        assertStringContains($needle, $g);
    }
});

$runner->addTest('scan: logout route and handlers are POST/CSRF shaped', function () {
    $root = dirname(__DIR__, 2);
    $rh = (string) file_get_contents($root . '/helpers/route_helper.php');
    preg_match("/'action\\.logout' => \\[.*?'methods' => \\[([^\\]]*)\\]/s", $rh, $m);
    assertStringContains("['POST']", $rh, 'logout registry must be POST-only');
    $hl = (string) file_get_contents($root . '/request/handler-logout.php');
    assertStringContains('app_verify_csrf', $hl);
    assertStringContains('setcookie', $hl, 'logout must expire the cookie');
});
