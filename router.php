<?php

declare(strict_types=1);

$rootPath = __DIR__;
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url((string) $requestUri, PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '/';

$decodedRequestPath = rawurldecode($requestPath);
$decodedRequestPath = $decodedRequestPath !== '' ? $decodedRequestPath : '/';

$targetPath = $rootPath . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $decodedRequestPath), DIRECTORY_SEPARATOR);

if ($decodedRequestPath !== '/' && is_file($targetPath)) {
    return false;
}

if (is_dir($targetPath)) {
    $indexInDir = rtrim($targetPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
    if (is_file($indexInDir)) {
        require $indexInDir;
        return true;
    }
}

if ($decodedRequestPath === '/' || $decodedRequestPath === '/index.php') {
    require $rootPath . DIRECTORY_SEPARATOR . 'index.php';
    return true;
}

require $rootPath . DIRECTORY_SEPARATOR . '404.php';
return true;
