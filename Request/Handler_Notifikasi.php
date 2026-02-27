<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/Controllers/PelanggaranController.php';
require_once dirname(__DIR__) . '/helpers/token_helper.php';

function respond_json(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(405, [
        'success' => false,
        'message' => 'Metode request tidak diizinkan.',
    ]);
}

if (!isset($_SESSION['username'], $_SESSION['user_type'], $_SESSION['user_data']) || !is_array($_SESSION['user_data'])) {
    respond_json(401, [
        'success' => false,
        'message' => 'Sesi tidak valid. Silakan login ulang.',
    ]);
}

$role = trim((string) $_SESSION['user_type']);
if (!in_array($role, ['mahasiswa', 'dosen'], true)) {
    respond_json(403, [
        'success' => false,
        'message' => 'Role pengguna tidak diizinkan.',
    ]);
}

$rawInput = file_get_contents('php://input');
$decodedInput = json_decode($rawInput ?? '', true);
$input = is_array($decodedInput) ? $decodedInput : $_POST;

$action = isset($input['action']) ? trim((string) $input['action']) : '';
if ($action === '') {
    respond_json(422, [
        'success' => false,
        'message' => 'Action wajib diisi.',
    ]);
}

$controller = new PelanggaranController();
$sessionData = $_SESSION['user_data'];

if ($action === 'fetch_list') {
    if ($role === 'mahasiswa') {
        $identity = trim((string) ($sessionData['nim'] ?? ''));
        if ($identity === '') {
            respond_json(400, [
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan.',
            ]);
        }
        $notifications = $controller->getNotifikasiMahasiswa($identity);
    } else {
        $identity = trim((string) ($sessionData['nidn'] ?? ''));
        if ($identity === '') {
            respond_json(400, [
                'success' => false,
                'message' => 'Data dosen tidak ditemukan.',
            ]);
        }
        $notifications = $controller->getNotifikasiDosen($identity);
    }

    $mappedNotifications = [];
    foreach ((array) $notifications as $notification) {
        $statusRaw = strtolower(trim((string) ($notification['status'] ?? 'unread')));
        $rawId = (int) ($notification['id_notifikasi'] ?? 0);
        if ($rawId <= 0) {
            continue;
        }
        $mappedNotifications[] = [
            'id_notifikasi' => app_id_token('notifikasi', $rawId),
            'pesan' => (string) ($notification['pesan'] ?? ''),
            'status' => $statusRaw === 'read' ? 'read' : 'unread',
        ];
    }

    respond_json(200, [
        'success' => true,
        'notifications' => $mappedNotifications,
    ]);
}

if ($action === 'mark_read') {
    $idNotifikasi = app_id_resolve((string) ($input['id_notifikasi'] ?? ''), 'notifikasi');
    if ($idNotifikasi === null || $idNotifikasi <= 0) {
        respond_json(422, [
            'success' => false,
            'message' => 'ID notifikasi tidak valid.',
        ]);
    }

    $result = $controller->markNotifikasiAsRead($sessionData, $role, $idNotifikasi);
    $statusCode = ($result['success'] ?? false) ? 200 : 400;
    respond_json($statusCode, $result);
}

if ($action === 'mark_all_read') {
    $result = $controller->markAllNotifikasiAsRead($sessionData, $role);
    $statusCode = ($result['success'] ?? false) ? 200 : 400;
    respond_json($statusCode, $result);
}

respond_json(422, [
    'success' => false,
    'message' => 'Action tidak dikenal.',
]);
