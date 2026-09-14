<?php

/**
 * Router wrapper for coverage runs: starts Xdebug coverage for each request and
 * merges the per-request line data into XDEBUG_COV_SERVER, then delegates to the
 * real application router.
 *
 * Used only when SEC_COV_PREPEND points here (see tests/cover.php).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$covFile = getenv('XDEBUG_COV_SERVER') ?: '';
$srcDirs = ['models', 'controllers', 'helpers', 'request'];

if (extension_loaded('xdebug') && $covFile !== '') {
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
    register_shutdown_function(static function () use ($root, $srcDirs, $covFile): void {
        $cov = xdebug_get_code_coverage();
        xdebug_stop_code_coverage(false);
        $rootN = str_replace('\\', '/', $root);
        $summary = [];
        foreach ($cov as $file => $lines) {
            $f = str_replace('\\', '/', $file);
            foreach ($srcDirs as $d) {
                if (strpos($f, $rootN . '/' . $d . '/') === 0) {
                    $summary[$f] = $lines;
                    break;
                }
            }
        }
        if ($summary === []) {
            return;
        }
        $existing = [];
        if (is_file($covFile)) {
            $decoded = json_decode((string) file_get_contents($covFile), true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }
        foreach ($summary as $file => $lines) {
            foreach ($lines as $ln => $state) {
                if (!isset($existing[$file][$ln]) || $existing[$file][$ln] < $state) {
                    $existing[$file][$ln] = $state;
                }
            }
        }
        file_put_contents($covFile, json_encode($existing), LOCK_EX);
    });
}

require $root . '/router.php';
