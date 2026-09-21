<?php

declare(strict_types=1);

// Fail-closed defaults before any code can throw; config.php may re-enable display via APP_DEBUG.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$rootPath = __DIR__;
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url((string) $requestUri, PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '/';
$requestPath = rawurldecode($requestPath);
$requestPath = $requestPath !== '' ? $requestPath : '/';

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

// Skip on cli-server: `php -S host:port router.php` sets SCRIPT_NAME to the REQUESTED
// path (verified), so dirname() there would wrongly strip a real path prefix ("/css").
if ($basePath === '/' || $basePath === '.' || PHP_SAPI === 'cli-server') {
    $basePath = '';
}

if ($basePath !== '' && ($requestPath === $basePath || strpos($requestPath, $basePath . '/') === 0)) {
    $requestPath = substr($requestPath, strlen($basePath));
    $requestPath = $requestPath !== '' ? $requestPath : '/';
}

$targetPath = $rootPath . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $requestPath), DIRECTORY_SEPARATOR);

require_once __DIR__ . '/helpers/path_helper.php';
require_once __DIR__ . '/helpers/token_helper.php';
require_once __DIR__ . '/helpers/route_helper.php';
require_once __DIR__ . '/helpers/seo_helper.php';
require_once __DIR__ . '/helpers/error_page_helper.php';
require_once __DIR__ . '/helpers/audit_helper.php';

app_seo_enforce_canonical_host();
app_seo_apply_security_headers();

// Load the DB handle before the session check: the revoked-session guard in
// app_session_touch_or_expire() needs $GLOBALS['connect'], and without it the
// guard fail-softs to "not revoked" and silently does nothing.
require_once __DIR__ . '/config.php';

app_session_start_if_needed();
$sessionAlive = app_session_touch_or_expire(1800);

$renderPageError = static function (int $statusCode): void {
    app_render_error_page($statusCode);
};

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception) use ($renderPageError, $requestPath): void {
    if (app_request_expects_json($requestPath)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
        return;
    }

    $renderPageError(500);
});

register_shutdown_function(static function () use ($renderPageError, $requestPath): void {
    $lastError = error_get_last();
    if (!is_array($lastError)) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($lastError['type'] ?? 0), $fatalTypes, true)) {
        return;
    }

    if (app_request_expects_json($requestPath)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
        return;
    }

    $renderPageError(500);
});

if (preg_match('#^/(views|request)(/|$)#i', $requestPath) === 1) {
    $renderPageError(403);
    return true;
}

// Mirror of the .htaccess deny rules: the PHP built-in server ignores .htaccess,
// so dev (`php artisan serve`) must block the same sensitive paths fail-closed.
$isSensitiveStatic = static function (string $path): bool {
    $normalized = strtolower(str_replace('\\', '/', $path));
    if (preg_match('#^/storage/uploads/news/#', $normalized) === 1) {
        return false; // news images are referenced by published <img> tags
    }
    if (preg_match('#(^|/)\.(?!well-known(/|$))#', $normalized) === 1) {
        return true; // dotfiles: .env, .git, ...
    }
    if (preg_match('#\.(env|key|pem|crt|p12|sql|log|ini|bak|old|save|swp|dist|lock|zip|tar|tgz|gz|bz2|7z|rar|sh|bash|ps1|bat|cmd|py|pl|rb|md)$#', $normalized) === 1) {
        return true;
    }
    if (in_array($normalized, ['/config.php', '/artisan'], true)) {
        return true;
    }
    return preg_match('#^/(controllers|models|helpers|database|docs|document|tests|storage)(/|$)#', $normalized) === 1;
};

if ($isSensitiveStatic($requestPath)) {
    $renderPageError(403);
    return true;
}

// ponytail: /index.php must run through this router (hardened session start + headers), never as a static passthrough.
if (PHP_SAPI === 'cli-server' && $requestPath !== '/index.php' && is_file($targetPath)) {
    return false;
}

if (!$sessionAlive && $requestPath !== '/') {
    if (strpos($requestPath, '/a/') === 0 || strpos($requestPath, '/action/') === 0) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Session expired.']);
        return true;
    }

    header('Location: ' . app_page_url('page.login'), true, 302);
    return true;
}

if ($requestPath === '/' || $requestPath === '/index.php') {
    require $rootPath . DIRECTORY_SEPARATOR . 'index.php';
    return true;
}

// Readable route path support: only ID params are masked.
$queryData = $_GET;
$pageRouteName = app_route_find_by_path($requestPath, 'page');
if (is_string($pageRouteName) && $pageRouteName !== '') {
    if (app_route_dispatch_by_name($pageRouteName, 'page', $queryData, true)) {
        return true;
    }

    $renderPageError(403);
    return true;
}

$actionRouteName = app_route_find_by_path($requestPath, 'action');
if (is_string($actionRouteName) && $actionRouteName !== '') {
    if (app_route_dispatch_by_name($actionRouteName, 'action', $queryData, true)) {
        return true;
    }

    if ($actionRouteName === 'action.file_download') {
        app_audit_log('download_denied', ['detail' => 'token invalid/expired/foreign']);
    }

    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    return true;
}

// Legacy tokenized route format (/p/<token> and /a/<token>) retained for backward compatibility.
if (preg_match('#^/p/([^/]+)$#', $requestPath, $matches) === 1) {
    $token = rawurldecode((string) $matches[1]);
    if (app_route_dispatch($token, 'page')) {
        return true;
    }

    $renderPageError(403);
    return true;
}

if (preg_match('#^/a/([^/]+)$#', $requestPath, $matches) === 1) {
    $token = rawurldecode((string) $matches[1]);
    if (app_route_dispatch($token, 'action')) {
        return true;
    }

    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    return true;
}

$renderPageError(404);
return true;
