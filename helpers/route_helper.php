<?php

declare(strict_types=1);

require_once __DIR__ . '/path_helper.php';
require_once __DIR__ . '/token_helper.php';

if (!function_exists('app_route_registry')) {
    function app_route_registry(): array
    {
        static $registry = null;
        if (is_array($registry)) {
            return $registry;
        }

        $registry = [
            'page.home' => ['kind' => 'page', 'path' => '/', 'target' => 'index.php', 'methods' => ['GET']],
            'page.login' => ['kind' => 'page', 'path' => '/login', 'target' => 'views/auth/login.php', 'methods' => ['GET']],
            'page.tatib' => ['kind' => 'page', 'path' => '/tatib', 'target' => 'views/tatib/list-tatib.php', 'methods' => ['GET']],
            'page.pelanggaran' => ['kind' => 'page', 'path' => '/pelanggaran', 'target' => 'views/pelanggaran/pelanggaran-page.php', 'methods' => ['GET']],
            'page.pelanggaran_dosen' => ['kind' => 'page', 'path' => '/pelanggaran/dosen', 'target' => 'views/pelanggaran/pelanggaran-dosen.php', 'methods' => ['GET']],
            'page.pelaporan' => ['kind' => 'page', 'path' => '/pelaporan', 'target' => 'views/pelanggaran/pelaporan.php', 'methods' => ['GET']],
            'page.edit_pelaporan' => ['kind' => 'page', 'path' => '/pelaporan/edit', 'target' => 'views/pelanggaran/edit-pelaporan.php', 'methods' => ['GET']],
            'page.notifikasi' => ['kind' => 'page', 'path' => '/notifikasi', 'target' => 'views/pelanggaran/notifikasi.php', 'methods' => ['GET']],
            'page.news_detail' => ['kind' => 'page', 'path' => '/berita', 'target' => 'views/public/berita-detail.php', 'methods' => ['GET']],
            'page.admin_home' => ['kind' => 'page', 'path' => '/admin', 'target' => 'views/admin/home-admin.php', 'methods' => ['GET']],
            'page.admin_tatib' => ['kind' => 'page', 'path' => '/admin/tatib', 'target' => 'views/tatib/list-tatib-admin.php', 'methods' => ['GET']],
            'page.admin_news' => ['kind' => 'page', 'path' => '/admin/news', 'target' => 'views/admin/news-admin.php', 'methods' => ['GET']],
            'page.admin_news_tambah' => ['kind' => 'page', 'path' => '/admin/news/tambah', 'target' => 'views/admin/tambah-berita.php', 'methods' => ['GET']],
            'page.admin_news_edit' => ['kind' => 'page', 'path' => '/admin/news/edit', 'target' => 'views/admin/edit-berita.php', 'methods' => ['GET', 'POST']],

            'action.login' => ['kind' => 'action', 'path' => '/action/login', 'target' => 'requests/handler-login.php', 'methods' => ['POST']],
            'action.pelanggaran' => ['kind' => 'action', 'path' => '/action/pelanggaran', 'target' => 'requests/handler-pelanggaran.php', 'methods' => ['GET', 'POST']],
            'action.notifikasi' => ['kind' => 'action', 'path' => '/action/notifikasi', 'target' => 'requests/handler-notifikasi.php', 'methods' => ['POST']],
            'action.news' => ['kind' => 'action', 'path' => '/action/news', 'target' => 'requests/handler-news.php', 'methods' => ['POST']],
            'action.tatib' => ['kind' => 'action', 'path' => '/action/tatib', 'target' => 'requests/handler-tatib.php', 'methods' => ['POST']],
            'action.upload' => ['kind' => 'action', 'path' => '/action/upload', 'target' => 'requests/handler-upload.php', 'methods' => ['POST']],
            'action.logout' => ['kind' => 'action', 'path' => '/action/logout', 'target' => 'requests/handler-logout.php', 'methods' => ['GET', 'POST']],
            'action.file_download' => ['kind' => 'action', 'path' => '/action/download', 'target' => 'requests/handler-download.php', 'methods' => ['GET']],
        ];

        return $registry;
    }
}

if (!function_exists('app_route_get')) {
    function app_route_get(string $routeName): ?array
    {
        $registry = app_route_registry();
        return $registry[$routeName] ?? null;
    }
}

if (!function_exists('app_route_normalize_path')) {
    function app_route_normalize_path(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/' . trim($path, '/');
    }
}

if (!function_exists('app_route_path')) {
    function app_route_path(string $routeName): ?string
    {
        $route = app_route_get($routeName);
        if (!is_array($route)) {
            return null;
        }

        $path = (string) ($route['path'] ?? '');
        if ($path === '') {
            return null;
        }

        return app_route_normalize_path($path);
    }
}

if (!function_exists('app_route_find_by_path')) {
    function app_route_find_by_path(string $requestPath, string $kind): ?string
    {
        $requestPath = app_route_normalize_path($requestPath);
        foreach (app_route_registry() as $routeName => $route) {
            if (($route['kind'] ?? null) !== $kind) {
                continue;
            }

            $routePath = app_route_normalize_path((string) ($route['path'] ?? ''));
            if ($routePath === $requestPath) {
                return (string) $routeName;
            }
        }

        return null;
    }
}

if (!function_exists('app_route_id_entity_map')) {
    function app_route_id_entity_map(): array
    {
        return [
            'id_detail' => 'detail_pelanggaran',
            'id_news' => 'news',
            'news_id' => 'news',
            'id_tatib' => 'tatib',
            'id_sanksi' => 'sanksi',
            'sanksi_id' => 'sanksi',
        ];
    }
}

if (!function_exists('app_route_id_entity_for_key')) {
    function app_route_id_entity_for_key(string $key): ?string
    {
        $map = app_route_id_entity_map();
        $key = strtolower(trim($key));
        if ($key === '') {
            return null;
        }

        return $map[$key] ?? null;
    }
}

if (!function_exists('app_route_encode_url_data')) {
    function app_route_encode_url_data(array $data, int $ttl): array
    {
        $encoded = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $key = (string) $key;
            if ($key === '' || $value === null) {
                continue;
            }

            $entity = app_route_id_entity_for_key($key);
            if ($entity !== null) {
                if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                    $id = (int) $value;
                    if ($id <= 0) {
                        continue;
                    }

                    $encoded[$key] = app_id_token($entity, $id, $ttl);
                    continue;
                }

                $encoded[$key] = (string) $value;
                continue;
            }

            $encoded[$key] = (string) $value;
        }

        return $encoded;
    }
}

if (!function_exists('app_route_decode_url_data')) {
    function app_route_decode_url_data(array $data): ?array
    {
        $decoded = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                return null;
            }

            $key = (string) $key;
            if ($key === '') {
                continue;
            }

            $entity = app_route_id_entity_for_key($key);
            if ($entity !== null) {
                $resolved = app_id_resolve((string) $value, $entity);
                if ($resolved === null) {
                    return null;
                }

                $decoded[$key] = $resolved;
                continue;
            }

            $decoded[$key] = $value;
        }

        return $decoded;
    }
}

if (!function_exists('app_route_build_url')) {
    function app_route_build_url(string $routeName, array $data, int $ttl, string $expectedKind): string
    {
        $route = app_route_get($routeName);
        if (!is_array($route) || ($route['kind'] ?? null) !== $expectedKind) {
            throw new InvalidArgumentException('Unknown ' . $expectedKind . ' route: ' . $routeName);
        }

        $path = app_route_path($routeName);
        if (!is_string($path)) {
            throw new RuntimeException('Route path is not configured: ' . $routeName);
        }

        $queryData = app_route_encode_url_data($data, $ttl);
        if ($queryData === []) {
            return $path;
        }

        return $path . '?' . http_build_query($queryData, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('app_page_url')) {
    function app_page_url(string $routeName, array $data = [], int $ttl = 1800): string
    {
        return app_route_build_url($routeName, $data, $ttl, 'page');
    }
}

if (!function_exists('app_action_url')) {
    function app_action_url(string $routeName, array $data = [], int $ttl = 1800): string
    {
        return app_route_build_url($routeName, $data, $ttl, 'action');
    }
}

if (!function_exists('app_redirect_page')) {
    function app_redirect_page(string $routeName, array $data = [], int $statusCode = 302): void
    {
        header('Location: ' . app_page_url($routeName, $data), true, $statusCode);
        exit();
    }
}

if (!function_exists('app_redirect_action')) {
    function app_redirect_action(string $routeName, array $data = [], int $statusCode = 302): void
    {
        header('Location: ' . app_action_url($routeName, $data), true, $statusCode);
        exit();
    }
}

if (!function_exists('app_route_set_context')) {
    function app_route_set_context(string $routeName, array $routeData = []): void
    {
        $GLOBALS['__app_route_name'] = $routeName;
        $GLOBALS['__app_route_data'] = $routeData;
    }
}

if (!function_exists('app_route_name')) {
    function app_route_name(): ?string
    {
        $routeName = $GLOBALS['__app_route_name'] ?? null;
        return is_string($routeName) ? $routeName : null;
    }
}

if (!function_exists('app_route_data')) {
    function app_route_data(?string $key = null, $default = null)
    {
        $data = $GLOBALS['__app_route_data'] ?? [];
        if (!is_array($data)) {
            return $default;
        }

        if ($key === null) {
            return $data;
        }

        return array_key_exists($key, $data) ? $data[$key] : $default;
    }
}

if (!function_exists('app_route_dispatch')) {
    function app_route_dispatch(string $token, string $kind): bool
    {
        $payload = app_token_decode($token, 'route');
        if (!is_array($payload)) {
            return false;
        }

        $routeName = (string) ($payload['sub'] ?? '');
        $routeData = $payload['data'] ?? [];
        if (!is_array($routeData)) {
            $routeData = [];
        }

        return app_route_dispatch_by_name($routeName, $kind, $routeData, false);
    }
}

if (!function_exists('app_route_dispatch_by_name')) {
    function app_route_dispatch_by_name(string $routeName, string $kind, array $routeData = [], bool $resolveIdToken = true): bool
    {
        $route = app_route_get($routeName);
        if (!is_array($route) || ($route['kind'] ?? null) !== $kind) {
            return false;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $allowedMethods = $route['methods'] ?? ['GET'];
        if (!in_array($method, $allowedMethods, true)) {
            http_response_code(405);
            return true;
        }

        if ($resolveIdToken) {
            $decodedRouteData = app_route_decode_url_data($routeData);
            if (!is_array($decodedRouteData)) {
                return false;
            }
            $routeData = $decodedRouteData;
        }

        app_route_set_context($routeName, $routeData);

        $target = app_path((string) $route['target']);
        if (!is_file($target)) {
            return false;
        }

        require $target;
        return true;
    }
}

if (!function_exists('app_legacy_path_to_public_url')) {
    function app_legacy_path_to_public_url(string $legacyPath): ?string
    {
        $legacyPath = trim(str_replace('\\', '/', $legacyPath));
        if ($legacyPath === '') {
            return '/';
        }

        if ($legacyPath === 'index.php' || $legacyPath === '/index.php') {
            return '/';
        }

        if (strpos($legacyPath, '?') === 0) {
            parse_str(ltrim($legacyPath, '?'), $query);
            if (($query['logout'] ?? null) === 'true') {
                return app_action_url('action.logout');
            }
            return null;
        }

        $queryString = (string) (parse_url($legacyPath, PHP_URL_QUERY) ?? '');
        $pathOnly = ltrim((string) (parse_url($legacyPath, PHP_URL_PATH) ?? ''), '/');

        $map = [
            'views/auth/login.php' => ['route' => 'page.login', 'kind' => 'page'],
            'views/tatib/list-tatib.php' => ['route' => 'page.tatib', 'kind' => 'page'],
            'views/pelanggaran/pelanggaran-page.php' => ['route' => 'page.pelanggaran', 'kind' => 'page'],
            'views/pelanggaran/pelanggaran-dosen.php' => ['route' => 'page.pelanggaran_dosen', 'kind' => 'page'],
            'views/pelanggaran/pelaporan.php' => ['route' => 'page.pelaporan', 'kind' => 'page'],
            'views/pelanggaran/edit-pelaporan.php' => ['route' => 'page.edit_pelaporan', 'kind' => 'page'],
            'views/pelanggaran/notifikasi.php' => ['route' => 'page.notifikasi', 'kind' => 'page'],
            'views/public/berita-detail.php' => ['route' => 'page.news_detail', 'kind' => 'page'],
            'views/admin/home-admin.php' => ['route' => 'page.admin_home', 'kind' => 'page'],
            'views/admin/news-admin.php' => ['route' => 'page.admin_news', 'kind' => 'page'],
            'views/admin/tambah-berita.php' => ['route' => 'page.admin_news_tambah', 'kind' => 'page'],
            'views/admin/edit-berita.php' => ['route' => 'page.admin_news_edit', 'kind' => 'page'],
            'views/tatib/list-tatib-admin.php' => ['route' => 'page.admin_tatib', 'kind' => 'page'],
            'requests/handler-login.php' => ['route' => 'action.login', 'kind' => 'action'],
            'requests/handler-pelanggaran.php' => ['route' => 'action.pelanggaran', 'kind' => 'action'],
            'requests/handler-notifikasi.php' => ['route' => 'action.notifikasi', 'kind' => 'action'],
            'requests/handler-news.php' => ['route' => 'action.news', 'kind' => 'action'],
            'requests/handler-tatib.php' => ['route' => 'action.tatib', 'kind' => 'action'],
            'requests/handler-upload.php' => ['route' => 'action.upload', 'kind' => 'action'],
            'requests/handler-logout.php' => ['route' => 'action.logout', 'kind' => 'action'],
            'requests/handler-download.php' => ['route' => 'action.file_download', 'kind' => 'action'],
        ];

        if (!isset($map[$pathOnly])) {
            return null;
        }

        $routeName = (string) $map[$pathOnly]['route'];
        parse_str($queryString, $query);

        $data = [];
        if ($pathOnly === 'views/pelanggaran/edit-pelaporan.php' && isset($query['id']) && is_numeric($query['id'])) {
            $data['id_detail'] = (int) $query['id'];
        }
        if ($pathOnly === 'views/admin/edit-berita.php' && isset($query['id']) && is_numeric($query['id'])) {
            $data['id_news'] = (int) $query['id'];
        }
        if ($pathOnly === 'views/public/berita-detail.php' && isset($query['slug']) && is_string($query['slug'])) {
            $slug = trim($query['slug']);
            if ($slug !== '') {
                $data['slug'] = $slug;
            }
        }

        if ($map[$pathOnly]['kind'] === 'page') {
            return app_page_url($routeName, $data);
        }

        if (!empty($query)) {
            $data = array_merge($data, $query);
        }

        return app_action_url($routeName, $data);
    }
}
