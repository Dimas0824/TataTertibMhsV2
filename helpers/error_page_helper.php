<?php

declare(strict_types=1);

require_once __DIR__ . '/path_helper.php';
require_once __DIR__ . '/route_helper.php';
require_once __DIR__ . '/seo_helper.php';

if (!function_exists('app_error_catalog')) {
    function app_error_catalog(): array
    {
        return [
            303 => [
                'title' => 'Sedang Dialihkan',
                'message' => 'Dokumen yang Anda minta dapat ditemukan di tempat lain. Anda sedang dialihkan ke halaman yang tepat.',
                'icon' => 'external-link',
                'theme' => 'blue',
                'blob1' => 'blob-blue',
                'blob2' => 'blob-cyan',
            ],
            400 => [
                'title' => 'Permintaan Buruk',
                'message' => 'Sistem tidak dapat memproses permintaan Anda karena format yang tidak valid. Silakan periksa kembali tautan Anda.',
                'icon' => 'alert-circle',
                'theme' => 'amber',
                'blob1' => 'blob-yellow',
                'blob2' => 'blob-orange',
            ],
            403 => [
                'title' => 'Akses Dibatasi',
                'message' => 'Maaf, Anda tidak memiliki hak akses untuk melihat data kedisiplinan ini. Silakan hubungi admin kampus.',
                'icon' => 'shield-alert',
                'theme' => 'red',
                'blob1' => 'blob-red',
                'blob2' => 'blob-rose',
            ],
            404 => [
                'title' => 'Halaman Hilang',
                'message' => 'Oops! Informasi tata tertib atau halaman yang Anda tuju sepertinya tidak ada di sistem DiscipLink.',
                'icon' => 'search-x',
                'theme' => 'slate',
                'blob1' => 'blob-slate',
                'blob2' => 'blob-gray',
            ],
            500 => [
                'title' => 'Sistem Bermasalah',
                'message' => 'Terjadi kesalahan internal pada server DiscipLink. Tim teknis kami sedang memperbaikinya.',
                'icon' => 'server-crash',
                'theme' => 'red',
                'blob1' => 'blob-red-dark',
                'blob2' => 'blob-orange-dark',
            ],
            503 => [
                'title' => 'Sedang Pemeliharaan',
                'message' => 'DiscipLink sedang dalam proses peningkatan sistem. Layanan akan segera kembali normal.',
                'icon' => 'wrench',
                'theme' => 'amber',
                'blob1' => 'blob-amber',
                'blob2' => 'blob-yellow',
            ],
        ];
    }
}

if (!function_exists('app_error_meta_title')) {
    function app_error_meta_title(int $statusCode, string $title): string
    {
        return $statusCode . ' - ' . $title . ' | DiscipLink';
    }
}

if (!function_exists('app_error_meta_description')) {
    function app_error_meta_description(int $statusCode, string $message): string
    {
        return 'Error ' . $statusCode . '. ' . $message;
    }
}

if (!function_exists('app_error_data')) {
    function app_error_data(int $statusCode): array
    {
        $catalog = app_error_catalog();
        $fallback = $catalog[404];
        $entry = $catalog[$statusCode] ?? $fallback;

        return [
            'statusCode' => array_key_exists($statusCode, $catalog) ? $statusCode : 404,
            'title' => (string) $entry['title'],
            'message' => (string) $entry['message'],
            'icon' => (string) $entry['icon'],
            'theme' => (string) $entry['theme'],
            'blob1' => (string) $entry['blob1'],
            'blob2' => (string) $entry['blob2'],
        ];
    }
}

if (!function_exists('app_request_expects_json')) {
    function app_request_expects_json(?string $requestPath = null): bool
    {
        $requestPath = $requestPath ?? (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $requestPath = is_string($requestPath) ? $requestPath : '/';
        if (strpos($requestPath, '/a/') === 0 || strpos($requestPath, '/action/') === 0) {
            return true;
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }

        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return $requestedWith === 'xmlhttprequest';
    }
}

if (!function_exists('app_render_error_page')) {
    function app_render_error_page(int $statusCode, array $options = []): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
        }

        $error = app_error_data($statusCode);
        $canonicalPath = (string) ($options['canonical_path'] ?? ('/errors/' . $error['statusCode'] . '.php'));
        $robots = (string) ($options['robots'] ?? 'noindex, nofollow');

        require app_path('views/errors/error-page.php');
    }
}
