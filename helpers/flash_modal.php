<?php

declare(strict_types=1);

if (!function_exists('set_app_flash_modal')) {
    function set_app_flash_modal(string $type, string $message, ?string $title = null): void
    {
        $normalizedType = strtolower(trim($type));
        if (!in_array($normalizedType, ['success', 'error', 'info', 'warning'], true)) {
            $normalizedType = 'info';
        }

        $cleanMessage = trim($message);
        if ($cleanMessage === '') {
            $cleanMessage = $normalizedType === 'success' ? 'Operasi berhasil.' : 'Terjadi kesalahan.';
        }

        $_SESSION['app_flash_modal'] = [
            'type' => $normalizedType,
            'title' => $title,
            'message' => $cleanMessage,
        ];
    }
}

if (!function_exists('consume_app_flash_modal')) {
    function consume_app_flash_modal(): ?array
    {
        $flash = $_SESSION['app_flash_modal'] ?? null;
        unset($_SESSION['app_flash_modal']);

        if (!is_array($flash)) {
            return null;
        }

        $type = strtolower((string) ($flash['type'] ?? 'info'));
        if (!in_array($type, ['success', 'error', 'info', 'warning'], true)) {
            $type = 'info';
        }

        $message = trim((string) ($flash['message'] ?? ''));
        if ($message === '') {
            return null;
        }

        $title = isset($flash['title']) ? trim((string) $flash['title']) : null;
        if ($title === '') {
            $title = null;
        }

        return [
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ];
    }
}
