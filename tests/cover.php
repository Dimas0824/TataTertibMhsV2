<?php

/**
 * Coverage runner — measures line coverage with Xdebug.
 *
 * Usage (Xdebug must be loadable):
 *   PHP_INI_SCAN_DIR=<dir with xdebug.ini> XDEBUG_MODE=coverage \
 *     php tests/cover.php
 *
 * Chooses an instrumented router so the test HTTP server (booted by
 * SecurityClient) also records coverage; unions CLI + server coverage and
 * prints per-file + total line coverage for models/, controllers/, helpers/,
 * request/.
 */

declare(strict_types=1);

$testsDir = __DIR__;
$root = dirname($testsDir);

// Load .env into the process env (bootstrap only reads .env.testing).
$envFile = $root . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (preg_match('/^["\'](.*)["\']\s*$/', $v, $m)) $v = $m[1];
        $_ENV[$k] = $v;
        putenv("$k=$v");
    }
}

if (!extension_loaded('xdebug') || !function_exists('xdebug_start_code_coverage')) {
    fwrite(STDERR, "Xdebug is not loaded. Set PHP_INI_SCAN_DIR to a dir containing xdebug.ini and XDEBUG_MODE=coverage.\n");
    exit(2);
}

$srcDirs = ['models', 'controllers', 'helpers', 'request'];
$rootN = str_replace('\\', '/', $root);
$filter = static function (string $file) use ($rootN, $srcDirs): bool {
    $f = str_replace('\\', '/', $file);
    foreach ($srcDirs as $d) {
        if (strpos($f, $rootN . '/' . $d . '/') === 0) {
            return true;
        }
    }
    return false;
};

// Instrument the HTTP server through the coverage router wrapper.
$covFile = $root . '/tests/.cov-server.json';
@unlink($covFile);
putenv('XDEBUG_COV_SERVER=' . $covFile);
$_ENV['XDEBUG_COV_SERVER'] = $covFile;
putenv('SEC_COV_PREPEND=' . $testsDir . '/cov_router.php');
$_ENV['SEC_COV_PREPEND'] = $testsDir . '/cov_router.php';

xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);

register_shutdown_function(static function () use ($root, $srcDirs, $covFile, $filter): void {
    $cov = xdebug_get_code_coverage();
    xdebug_stop_code_coverage(false);

    if (is_file($covFile)) {
        $serverCov = json_decode((string) file_get_contents($covFile), true);
        if (is_array($serverCov)) {
            foreach ($serverCov as $file => $lines) {
                $key = str_replace('\\', '/', $file);
                // Re-key existing entries to the normalized path as well.
                if (!isset($cov[$key])) {
                    $cov[$key] = $lines;
                } else {
                    foreach ($lines as $ln => $state) {
                        if (!isset($cov[$key][$ln]) || $cov[$key][$ln] < $state) {
                            $cov[$key][$ln] = $state;
                        }
                    }
                }
            }
        }
        @unlink($covFile);
    }

    $rootN = str_replace('\\', '/', $root);
    $perFile = [];
    $totExec = 0;
    $totCov = 0;
    // Xdebug may record the SAME file under two keys (Windows backslash from the
    // CLI process, forward-slash from the HTTP server). Normalize the path and
    // MERGE line states so a file is never reported twice / under-counted.
    $merged = [];
    foreach ($cov as $file => $lines) {
        if (!$filter($file)) {
            continue;
        }
        $key = str_replace('\\', '/', $file);
        foreach ($lines as $ln => $state) {
            if (!isset($merged[$key][$ln]) || $merged[$key][$ln] < $state) {
                $merged[$key][$ln] = $state;
            }
        }
    }
    foreach ($merged as $file => $lines) {
        $exec = 0;
        $hit = 0;
        foreach ($lines as $state) {
            if ($state === -2) {
                continue;
            }
            if ($state === -1) {
                $exec++;
            } elseif ($state >= 1) {
                $exec++;
                $hit++;
            }
        }
        $rel = str_replace($rootN . '/', '', $file);
        $perFile[$rel] = [$hit, $exec];
        $totCov += $hit;
        $totExec += $exec;
    }

    foreach ($srcDirs as $d) {
        foreach (glob("$root/$d/*.php") ?: [] as $f) {
            $rel = $d . '/' . basename($f);
            if (!isset($perFile[$rel])) {
                $perFile[$rel] = [0, 0];
            }
        }
    }
    ksort($perFile);

    $out = [];
    $out[] = '';
    $out[] = '========== LINE COVERAGE (Xdebug ' . phpversion('xdebug') . ') ==========';
    $out[] = sprintf('%-42s %7s %7s %8s', 'FILE', 'HIT', 'TOTAL', 'COVER%');
    $out[] = str_repeat('-', 68);
    foreach ($perFile as $rel => [$hit, $exec]) {
        $pct = $exec > 0 ? 100 * $hit / $exec : 0.0;
        $out[] = sprintf('%-42s %7d %7d %7.1f%%', $rel, $hit, $exec, $pct);
    }
    $out[] = str_repeat('-', 68);
    $totalPct = $totExec > 0 ? 100 * $totCov / $totExec : 0.0;
    $out[] = sprintf('%-42s %7d %7d %7.1f%%', 'TOTAL', $totCov, $totExec, $totalPct);
    $out[] = '=' . str_repeat('=', 67);
    echo implode(PHP_EOL, $out) . PHP_EOL;
});

require $root . '/tests/run.php';
