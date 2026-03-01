<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/path_helper.php';
require_once __DIR__ . '/../helpers/route_helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit();
}

$fileName = trim((string) app_route_data('file', ''));
if ($fileName === '') {
    http_response_code(404);
    echo 'File not found';
    exit();
}

$fileName = basename($fileName);
$extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
if (!in_array($extension, $allowedExtensions, true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit();
}

$candidatePaths = [
    app_path('storage/uploads/' . $fileName),
    app_path('document/' . $fileName),
    dirname(app_path()) . DIRECTORY_SEPARATOR . 'document' . DIRECTORY_SEPARATOR . $fileName, // kompatibilitas file lama
];

$filePath = '';
foreach ($candidatePaths as $candidatePath) {
    if (is_file($candidatePath)) {
        $filePath = $candidatePath;
        break;
    }
}

if ($filePath === '') {
    http_response_code(404);
    echo 'File not found';
    exit();
}

$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $detected = finfo_file($finfo, $filePath);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
header('Content-Length: ' . (string) filesize($filePath));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

readfile($filePath);
exit();
