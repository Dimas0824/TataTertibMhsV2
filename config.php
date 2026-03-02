<?php

if (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof PDO) {
    $connect = $GLOBALS['connect'];
    return;
}

if (isset($connect) && $connect instanceof PDO) {
    $GLOBALS['connect'] = $connect;
    return;
}

$envPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';
$env = [];

if (is_file($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim((string) $line);

            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $separatorPos = strpos($line, '=');
            if ($separatorPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separatorPos));
            $value = trim(substr($line, $separatorPos + 1));

            if ($key === '') {
                continue;
            }

            if ($value !== '') {
                $first = substr($value, 0, 1);
                $last = substr($value, -1);
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            $env[$key] = $value;
        }
    }
}

$dsn = $env['DB_DSN'] ?? getenv('DB_DSN') ?: '';
$user = $env['DB_USER'] ?? getenv('DB_USER') ?: null;
$pass = $env['DB_PASS'] ?? getenv('DB_PASS') ?: null;

if ($dsn === '') {
    throw new RuntimeException('DB_DSN tidak ditemukan. Isi file .env atau environment variable DB_DSN.');
}

$connect = new PDO($dsn, $user, $pass);
$connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$connect->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$GLOBALS['connect'] = $connect;

try {
    $connect->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (Throwable $exception) {
    // Driver tertentu tidak mendukung ATTR_EMULATE_PREPARES.
}
