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
    function app_base_url(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName === '') {
            return '';
        }

        $markers = ['/requests/', '/controllers/', '/views/', '/models/', '/helpers/', '/database/'];
        foreach ($markers as $marker) {
            $position = strpos($scriptName, $marker);
            if ($position !== false) {
                $base = rtrim(substr($scriptName, 0, $position), '/');
                return $base === '/' ? '' : $base;
            }
        }

        $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        return $dir === '/' ? '' : $dir;
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
