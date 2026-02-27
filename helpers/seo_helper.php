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

if (!function_exists('app_seo_apply_security_headers')) {
    function app_seo_apply_security_headers(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        $httpHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $currentHost = strtolower((string) preg_replace('/:\d+$/', '', $httpHost));
        if ($currentHost !== '' && app_seo_is_local_host($currentHost)) {
            return;
        }

        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
        );

        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
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

if (!function_exists('app_seo_script_path')) {
    function app_seo_script_path(string $relativePath): string
    {
        $normalized = ltrim($relativePath, '/');
        if (substr($normalized, -3) !== '.js') {
            return $relativePath;
        }

        $minifiedPath = preg_replace('/\.js$/', '.min.js', $normalized);
        if (!is_string($minifiedPath) || $minifiedPath === '') {
            return $relativePath;
        }

        $absoluteMinifiedPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $minifiedPath);
        if (is_file($absoluteMinifiedPath)) {
            return $minifiedPath;
        }

        return $normalized;
    }
}

if (!function_exists('app_seo_script_src')) {
    function app_seo_script_src(string $relativePath, string $prefix = ''): string
    {
        $scriptPath = app_seo_script_path($relativePath);
        $prefix = rtrim($prefix, '/');
        if ($prefix === '') {
            return $scriptPath;
        }

        return $prefix . '/' . ltrim($scriptPath, '/');
    }
}

if (!function_exists('app_seo_favicon_tags')) {
    function app_seo_favicon_tags(string $assetPrefix = ''): void
    {
        $assetPrefix = rtrim($assetPrefix, '/');
        if ($assetPrefix !== '') {
            $assetPrefix .= '/';
        }
        ?>
        <link rel="icon" href="<?= htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8') ?>img/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="96x96"
            href="<?= htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8') ?>img/favicon-96x96.png">
        <link rel="apple-touch-icon" sizes="180x180"
            href="<?= htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8') ?>img/apple-touch-icon.png">
        <link rel="manifest" href="<?= htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8') ?>img/site.webmanifest">
        <?php
    }
}

if (!function_exists('app_seo_json_ld_tags')) {
    function app_seo_json_ld_tags(array $config = []): void
    {
        $emitDefaults = !isset($config['emit_defaults']) || (bool) $config['emit_defaults'];
        $siteName = (string) ($config['site_name'] ?? 'DiscipLink Polinema');
        $canonicalPath = isset($config['canonical_path']) ? (string) $config['canonical_path'] : null;
        $canonicalUrl = app_seo_canonical_url($canonicalPath);
        $origin = rtrim(app_seo_canonical_origin(), '/');

        $organizationLogo = app_seo_asset_url((string) ($config['organization_logo'] ?? 'img/logo aja.png'));
        $organizationName = (string) ($config['organization_name'] ?? 'DiscipLink Polinema');
        $searchTarget = $origin . '/index.php?q={search_term_string}';

        $path = parse_url($canonicalUrl, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        $breadcrumbItems = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Home',
                'item' => $origin . '/',
            ]
        ];

        $builtPath = '';
        foreach ($segments as $index => $segment) {
            $builtPath .= '/' . rawurlencode($segment);
            $breadcrumbItems[] = [
                '@type' => 'ListItem',
                'position' => $index + 2,
                'name' => ucwords(str_replace(['-', '_', '.php'], [' ', ' ', ''], $segment)),
                'item' => $origin . $builtPath,
            ];
        }

        $schemas = [];

        if ($emitDefaults) {
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => $origin . '/',
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => $searchTarget,
                    'query-input' => 'required name=search_term_string',
                ],
            ];
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $organizationName,
                'url' => $origin . '/',
                'logo' => $organizationLogo,
            ];
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $breadcrumbItems,
            ];
        }

        if (isset($config['article']) && is_array($config['article'])) {
            $article = $config['article'];
            $headline = isset($article['headline']) ? trim((string) $article['headline']) : '';
            $description = isset($article['description']) ? trim((string) $article['description']) : '';

            if ($headline !== '' && $description !== '') {
                $schemas[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'Article',
                    'headline' => $headline,
                    'description' => $description,
                    'datePublished' => (string) ($article['datePublished'] ?? date('c')),
                    'dateModified' => (string) ($article['dateModified'] ?? date('c')),
                    'author' => [
                        '@type' => 'Person',
                        'name' => (string) ($article['author'] ?? 'Admin DiscipLink'),
                    ],
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => $organizationName,
                        'logo' => [
                            '@type' => 'ImageObject',
                            'url' => $organizationLogo,
                        ],
                    ],
                    'mainEntityOfPage' => [
                        '@type' => 'WebPage',
                        '@id' => $canonicalUrl,
                    ],
                    'image' => app_seo_asset_url((string) ($article['image'] ?? 'img/GRAHA-POLINEMA1-slider-01.webp')),
                ];
            }
        }

        foreach ($schemas as $schema) {
            ?>
            <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
            <?php
        }
    }
}

if (!function_exists('app_seo_analytics_tags')) {
    function app_seo_analytics_tags(): void
    {
        $measurementId = trim((string) app_seo_env('GA4_MEASUREMENT_ID', ''));
        if ($measurementId === '') {
            return;
        }

        $escapedId = htmlspecialchars($measurementId, ENT_QUOTES, 'UTF-8');
        ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= $escapedId ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() {
                dataLayer.push(arguments);
            }
            gtag('js', '<?= htmlspecialchars(date('c'), ENT_QUOTES, 'UTF-8') ?>');
            gtag('config', '<?= $escapedId ?>');
        </script>
        <?php
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
        app_seo_json_ld_tags($config);
        app_seo_analytics_tags();
    }
}
