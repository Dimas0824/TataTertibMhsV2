<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/path_helper.php';
require_once __DIR__ . '/../helpers/route_helper.php';
require_once __DIR__ . '/../helpers/token_helper.php';

app_session_start_if_needed();

if (!isset($_SESSION['username'])) {
    app_audit_log('download_denied', ['detail' => 'unauthenticated', 'actor_id' => '', 'actor_type' => '']);
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
    app_audit_log('download_denied', [
        'actor_type' => (string) ($_SESSION['user_type'] ?? ''),
        'actor_id' => (string) ($_SESSION['username'] ?? ''),
        'detail' => 'extension not allowed',
    ]);
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

app_audit_log('download_ok', [
    'actor_type' => (string) ($_SESSION['user_type'] ?? ''),
    'actor_id' => (string) ($_SESSION['username'] ?? ''),
    'detail' => 'ext=' . $extension,
]);

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
