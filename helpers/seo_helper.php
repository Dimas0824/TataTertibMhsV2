<?php

declare(strict_types=1);

if (!function_exists('app_seo_load_env')) {
    function app_seo_load_env(): array
    {
        static $envCache = null;
        if (is_array($envCache)) {
            return $envCache;
        }

        $envCache = [];
        $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envPath)) {
            return $envCache;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $envCache;
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

            $envCache[$key] = $value;
        }

        return $envCache;
    }
}

if (!function_exists('app_seo_env')) {
    function app_seo_env(string $key, ?string $default = null): ?string
    {
        $env = app_seo_load_env();
        if (array_key_exists($key, $env)) {
            return $env[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return (string) $value;
        }

        return $default;
    }
}

if (!function_exists('app_seo_canonical_origin')) {
    function app_seo_canonical_origin(): string
    {
        static $origin = null;
        if (is_string($origin)) {
            return $origin;
        }

        $candidate = (string) app_seo_env('APP_CANONICAL_URL', 'https://dimspersonal.my.id');
        $candidate = trim($candidate);
        if ($candidate === '') {
            $candidate = 'https://dimspersonal.my.id';
        }

        if (stripos($candidate, 'http://') !== 0 && stripos($candidate, 'https://') !== 0) {
            $candidate = 'https://' . ltrim($candidate, '/');
        }

        $parts = parse_url($candidate);
        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : 'https';
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : 'dimspersonal.my.id';
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        $origin = $scheme . '://' . $host . $port;
        return $origin;
    }
}

if (!function_exists('app_seo_is_local_host')) {
    function app_seo_is_local_host(string $host): bool
    {
        $normalized = strtolower(trim($host));
        if ($normalized === 'localhost' || $normalized === '::1') {
            return true;
        }

        if (strpos($normalized, '127.') === 0) {
            return true;
        }

        return false;
    }
}

if (!function_exists('app_seo_enforce_canonical_host')) {
    function app_seo_enforce_canonical_host(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        $canonicalOrigin = app_seo_canonical_origin();
        $canonicalParts = parse_url($canonicalOrigin);
        $canonicalScheme = strtolower((string) ($canonicalParts['scheme'] ?? 'https'));
        $canonicalHost = strtolower((string) ($canonicalParts['host'] ?? ''));

        $httpHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($httpHost === '' || $canonicalHost === '') {
            return;
        }

        $currentHost = strtolower((string) preg_replace('/:\d+$/', '', $httpHost));
        if (app_seo_is_local_host($currentHost)) {
            return;
        }

        $canonicalVariant = strpos($canonicalHost, 'www.') === 0
            ? substr($canonicalHost, 4)
            : 'www.' . $canonicalHost;

        if ($currentHost !== $canonicalHost && $currentHost !== $canonicalVariant) {
            return;
        }

        $currentScheme = 'http';
        if (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
        ) {
            $currentScheme = 'https';
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        if ($requestUri === '') {
            $requestUri = '/';
        }

        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        $shouldNormalizeIndexPath = is_string($requestPath) && in_array($requestPath, ['/index.php', '/index.html'], true);

        if ($currentHost === $canonicalHost && $currentScheme === $canonicalScheme && !$shouldNormalizeIndexPath) {
            return;
        }

        if ($shouldNormalizeIndexPath) {
            $query = parse_url($requestUri, PHP_URL_QUERY);
            $requestUri = '/';
            if (is_string($query) && $query !== '') {
                $requestUri .= '?' . $query;
            }
        }

        header('Location: ' . $canonicalScheme . '://' . $canonicalHost . $requestUri, true, 301);
        exit();
    }
}

if (!function_exists('app_seo_canonical_url')) {
    function app_seo_canonical_url(?string $path = null): string
    {
        $origin = app_seo_canonical_origin();
        if ($path === null || trim($path) === '') {
            $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            $path = is_string($requestPath) ? $requestPath : '/';
        }

        $path = '/' . ltrim($path, '/');
        return rtrim($origin, '/') . $path;
    }
}

if (!function_exists('app_seo_asset_url')) {
    function app_seo_asset_url(string $relativePath): string
    {
        return rtrim(app_seo_canonical_origin(), '/') . '/' . ltrim($relativePath, '/');
    }
}

if (!function_exists('app_seo_meta_tags')) {
    function app_seo_meta_tags(array $config = []): void
    {
        $title = (string) ($config['title'] ?? 'DiscipLink - Tata Tertib Mahasiswa Polinema');
        $description = (string) ($config['description'] ?? 'DiscipLink adalah pusat informasi tata tertib mahasiswa Polinema untuk aturan, pelanggaran, sanksi, dan pembaruan kedisiplinan kampus.');
        $keywords = (string) ($config['keywords'] ?? 'tata tertib mahasiswa, aturan kampus, pelanggaran mahasiswa, sanksi mahasiswa, DiscipLink, Polinema');
        $type = (string) ($config['type'] ?? 'website');
        $siteName = (string) ($config['site_name'] ?? 'DiscipLink Polinema');
        $canonicalPath = isset($config['canonical_path']) ? (string) $config['canonical_path'] : null;
        $canonicalUrl = app_seo_canonical_url($canonicalPath);
        $imageUrl = app_seo_asset_url((string) ($config['image'] ?? 'img/GRAHA-POLINEMA1-slider-01.webp'));
        $robots = (string) ($config['robots'] ?? 'index, follow');

        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $escapedDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        $escapedKeywords = htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8');
        $escapedCanonical = htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8');
        $escapedImage = htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8');
        $escapedType = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
        $escapedSiteName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
        $escapedRobots = htmlspecialchars($robots, ENT_QUOTES, 'UTF-8');
        ?>
        <meta name="description" content="<?= $escapedDescription ?>">
        <meta name="keywords" content="<?= $escapedKeywords ?>">
        <meta name="robots" content="<?= $escapedRobots ?>">
        <link rel="canonical" href="<?= $escapedCanonical ?>">
        <meta property="og:type" content="<?= $escapedType ?>">
        <meta property="og:site_name" content="<?= $escapedSiteName ?>">
        <meta property="og:title" content="<?= $escapedTitle ?>">
        <meta property="og:description" content="<?= $escapedDescription ?>">
        <meta property="og:url" content="<?= $escapedCanonical ?>">
        <meta property="og:image" content="<?= $escapedImage ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?= $escapedTitle ?>">
        <meta name="twitter:description" content="<?= $escapedDescription ?>">
        <meta name="twitter:image" content="<?= $escapedImage ?>">
        <?php
    }
}
