<?php

declare(strict_types=1);

$rootPath = __DIR__;
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url((string) $requestUri, PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '/';
$requestPath = rawurldecode($requestPath);
$requestPath = $requestPath !== '' ? $requestPath : '/';

$targetPath = $rootPath . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $requestPath), DIRECTORY_SEPARATOR);

require_once __DIR__ . '/helpers/path_helper.php';
require_once __DIR__ . '/helpers/token_helper.php';
require_once __DIR__ . '/helpers/route_helper.php';
require_once __DIR__ . '/helpers/seo_helper.php';

app_seo_enforce_canonical_host();
app_seo_apply_security_headers();

app_session_start_if_needed();
$sessionAlive = app_session_touch_or_expire(1800);

if (preg_match('#^/(views|Request)(/|$)#', $requestPath) === 1) {
    http_response_code(403);
    require $rootPath . DIRECTORY_SEPARATOR . '404.php';
    return true;
}

if (PHP_SAPI === 'cli-server' && is_file($targetPath)) {
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

    http_response_code(403);
    require $rootPath . DIRECTORY_SEPARATOR . '404.php';
    return true;
}

$actionRouteName = app_route_find_by_path($requestPath, 'action');
if (is_string($actionRouteName) && $actionRouteName !== '') {
    if (app_route_dispatch_by_name($actionRouteName, 'action', $queryData, true)) {
        return true;
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

    http_response_code(403);
    require $rootPath . DIRECTORY_SEPARATOR . '404.php';
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

require $rootPath . DIRECTORY_SEPARATOR . '404.php';
return true;
