<?php

declare(strict_types=1);

if (!defined('APP_ROOT_PATH')) {
    define('APP_ROOT_PATH', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
}

if (!function_exists('app_path')) {
    function app_path(string $relativePath = ''): string
    {
        $relativePath = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
        if ($relativePath === '') {
            return APP_ROOT_PATH;
        }

        return APP_ROOT_PATH . DIRECTORY_SEPARATOR . $relativePath;
    }
}

if (!function_exists('app_require')) {
    function app_require(string $relativePath): void
    {
        $resolvedPath = app_path($relativePath);
        if (!is_file($resolvedPath)) {
            throw new RuntimeException('Required file not found: ' . $relativePath . ' (resolved: ' . $resolvedPath . ')');
        }

        require_once $resolvedPath;
    }
}

if (!function_exists('app_base_url')) {
    function app_env_value(string $targetKey): ?string
    {
        $envPath = app_path('.env');
        if (!is_file($envPath)) {
            return null;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return null;
        }

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
            if ($key !== $targetKey) {
                continue;
            }

            $raw = trim(substr($line, $separatorPos + 1));
            if ($raw === '') {
                return '';
            }

            $first = substr($raw, 0, 1);
            $last = substr($raw, -1);
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $raw = substr($raw, 1, -1);
            }

            return trim((string) $raw);
        }

        return null;
    }

    function app_base_path_override(): ?string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $raw = app_env_value('APP_BASE_PATH');
        if (!is_string($raw) || trim($raw) === '') {
            $raw = getenv('APP_BASE_PATH');
        }

        if (!is_string($raw) || trim($raw) === '' || trim($raw) === '/' || strcasecmp(trim($raw), 'auto') === 0) {
            $cached = '';
            return $cached;
        }

        $normalized = '/' . trim(str_replace('\\', '/', trim($raw)), '/');
        $cached = $normalized === '/' ? '' : $normalized;
        return $cached;
    }

    function app_detect_base_url_from_server(): string
    {
        $isLocalEnv = static function (): bool {
            $appEnvRaw = app_env_value('APP_ENV');
            if (!is_string($appEnvRaw) || trim($appEnvRaw) === '') {
                $appEnvRaw = getenv('APP_ENV') ?: 'local';
            }

            $appEnv = strtolower(trim((string) $appEnvRaw));
            return in_array($appEnv, ['local', 'development', 'dev'], true);
        };

        $localProjectBaseFromRequest = static function (string $requestPath) use ($isLocalEnv): string {
            if (!$isLocalEnv()) {
                return '';
            }

            $projectDirName = basename(APP_ROOT_PATH);
            $trimmedPath = trim($requestPath, '/');
            if ($trimmedPath === '') {
                return '';
            }

            $firstSegment = strtok($trimmedPath, '/');
            if (!is_string($firstSegment) || $firstSegment === '') {
                return '';
            }

            if (strcasecmp($firstSegment, $projectDirName) !== 0) {
                return '';
            }

            return '/' . $firstSegment;
        };

        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName === '') {
            return '';
        }

        $markers = ['/request/', '/controllers/', '/views/', '/models/', '/helpers/', '/database/'];
        foreach ($markers as $marker) {
            $position = strpos($scriptName, $marker);
            if ($position !== false) {
                $base = rtrim(substr($scriptName, 0, $position), '/');
                return $base === '/' ? '' : $base;
            }
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        $requestPath = is_string($requestPath) && $requestPath !== '' ? $requestPath : '/';

        $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $base = $dir === '/' ? '' : $dir;
        if ($base === '') {
            return $localProjectBaseFromRequest($requestPath);
        }

        $isPrefixedRequest = ($requestPath === $base || strpos($requestPath, $base . '/') === 0);
        if (!$isPrefixedRequest) {
            // Pada local stack tertentu, REQUEST_URI bisa tampil sebagai "/"
            // meski aplikasi berjalan di subfolder. Dalam mode local, pakai
            // base dari SCRIPT_NAME agar URL aset tetap valid.
            if ($isLocalEnv()) {
                return $base;
            }

            return $localProjectBaseFromRequest($requestPath);
        }

        return $base;
    }

    function app_base_url(): string
    {
        $override = app_base_path_override();
        $detected = app_detect_base_url_from_server();

        if ($override === '') {
            return $detected;
        }

        if ($detected === '') {
            return $override;
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        $requestPath = is_string($requestPath) && $requestPath !== '' ? $requestPath : '/';

        $isOverridePrefixed = ($requestPath === $override || strpos($requestPath, $override . '/') === 0);
        $isDetectedPrefixed = ($requestPath === $detected || strpos($requestPath, $detected . '/') === 0);

        if ($isOverridePrefixed || !$isDetectedPrefixed) {
            return $override;
        }

        // Override dari env tidak cocok dengan path request saat ini, gunakan hasil deteksi.
        return $detected;
    }
}

if (!function_exists('app_url')) {
    function app_url(string $relativePath = ''): string
    {
        if (function_exists('app_legacy_path_to_public_url')) {
            $mapped = app_legacy_path_to_public_url($relativePath);
            if (is_string($mapped) && $mapped !== '') {
                return $mapped;
            }
        }

        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $base = app_base_url();

        if ($relativePath === '') {
            return $base === '' ? '/' : $base . '/';
        }

        if ($base === '' || $base === '/') {
            return '/' . $relativePath;
        }

        return $base . '/' . $relativePath;
    }
}

if (!function_exists('app_asset_url')) {
    function app_asset_url(string $relativePath): string
    {
        return app_url(ltrim($relativePath, '/'));
    }
}

if (!function_exists('app_redirect')) {
    function app_redirect(string $relativePath, int $statusCode = 302): void
    {
        if (function_exists('app_legacy_path_to_public_url')) {
            $mapped = app_legacy_path_to_public_url($relativePath);
            if (is_string($mapped) && $mapped !== '') {
                header('Location: ' . $mapped, true, $statusCode);
                exit();
            }
        }

        header('Location: ' . app_url($relativePath), true, $statusCode);
        exit();
    }
}
