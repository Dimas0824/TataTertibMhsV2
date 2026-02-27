<?php

declare(strict_types=1);

require_once __DIR__ . '/path_helper.php';

if (!function_exists('app_session_start_if_needed')) {
    function app_session_start_if_needed(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }
}

if (!function_exists('app_session_touch_or_expire')) {
    function app_session_touch_or_expire(int $idleTtl = 1800): bool
    {
        app_session_start_if_needed();

        $now = time();
        $lastActivity = isset($_SESSION['__last_activity']) ? (int) $_SESSION['__last_activity'] : 0;

        if ($lastActivity > 0 && ($now - $lastActivity) > $idleTtl) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            @session_start();
            $_SESSION['__last_activity'] = $now;
            return false;
        }

        $_SESSION['__last_activity'] = $now;
        return true;
    }
}

if (!function_exists('app_token_key_path')) {
    function app_token_key_path(): string
    {
        return app_path('storage/keys/app_token.key');
    }
}

if (!function_exists('app_token_base64url_encode')) {
    function app_token_base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('app_token_base64url_decode')) {
    function app_token_base64url_decode(string $encoded): ?string
    {
        $normalized = strtr($encoded, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        return $decoded === false ? null : $decoded;
    }
}

if (!function_exists('app_token_secret_key')) {
    function app_token_secret_key(): string
    {
        static $cachedKey = null;
        if (is_string($cachedKey)) {
            return $cachedKey;
        }

        $keyPath = app_token_key_path();
        $keyDir = dirname($keyPath);

        if (!is_dir($keyDir)) {
            if (!mkdir($keyDir, 0700, true) && !is_dir($keyDir)) {
                throw new RuntimeException('Failed to create token key directory.');
            }
            @chmod($keyDir, 0700);
        }

        if (!is_file($keyPath)) {
            $newKey = random_bytes(32);
            $encoded = base64_encode($newKey);
            if (file_put_contents($keyPath, $encoded, LOCK_EX) === false) {
                throw new RuntimeException('Failed to write token key file.');
            }
            @chmod($keyPath, 0600);
            $cachedKey = $newKey;
            return $cachedKey;
        }

        $encoded = trim((string) file_get_contents($keyPath));
        if ($encoded === '') {
            throw new RuntimeException('Token key file is empty.');
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false || strlen($decoded) !== 32) {
            throw new RuntimeException('Token key file is invalid.');
        }

        $cachedKey = $decoded;
        return $cachedKey;
    }
}

if (!function_exists('app_token_session_hash')) {
    function app_token_session_hash(): string
    {
        app_session_start_if_needed();
        return hash('sha256', (string) session_id());
    }
}

if (!function_exists('app_token_encrypt_payload')) {
    function app_token_encrypt_payload(string $jsonPayload): string
    {
        $key = app_token_secret_key();

        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($jsonPayload, $nonce, $key);
            return 's1.' . app_token_base64url_encode($nonce . $cipher);
        }

        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('No cryptographic engine available for token encryption.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($jsonPayload, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if (!is_string($cipher)) {
            throw new RuntimeException('Failed to encrypt token payload.');
        }

        return 'o1.' . app_token_base64url_encode($iv . $tag . $cipher);
    }
}

if (!function_exists('app_token_decrypt_payload')) {
    function app_token_decrypt_payload(string $token): ?string
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$algorithm, $encodedPayload] = $parts;
        $raw = app_token_base64url_decode($encodedPayload);
        if (!is_string($raw)) {
            return null;
        }

        $key = app_token_secret_key();

        if ($algorithm === 's1' && function_exists('sodium_crypto_secretbox_open')) {
            if (strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                return null;
            }
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
            return is_string($plain) ? $plain : null;
        }

        if ($algorithm === 'o1' && function_exists('openssl_decrypt')) {
            if (strlen($raw) <= 28) {
                return null;
            }
            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $cipher = substr($raw, 28);
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '');
            return is_string($plain) ? $plain : null;
        }

        return null;
    }
}

if (!function_exists('app_token_issue')) {
    function app_token_issue(string $type, string $subject, array $data = [], int $ttl = 1800): string
    {
        if (!in_array($type, ['route', 'id'], true)) {
            throw new InvalidArgumentException('Token type must be route or id.');
        }

        $subject = trim($subject);
        if ($subject === '') {
            throw new InvalidArgumentException('Token subject is required.');
        }

        app_session_start_if_needed();
        app_session_touch_or_expire($ttl);

        $now = time();
        $payload = [
            'v' => 1,
            'typ' => $type,
            'sub' => $subject,
            'sid' => app_token_session_hash(),
            'iat' => $now,
            'exp' => $now + max(1, $ttl),
            'data' => $data,
            'n' => app_token_base64url_encode(random_bytes(12)),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Failed to encode token payload.');
        }

        return app_token_encrypt_payload($json);
    }
}

if (!function_exists('app_token_decode')) {
    function app_token_decode(string $token, ?string $expectedType = null, ?string $expectedSubject = null): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        app_session_start_if_needed();
        app_session_touch_or_expire();

        $json = app_token_decrypt_payload($token);
        if (!is_string($json)) {
            return null;
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return null;
        }

        $required = ['v', 'typ', 'sub', 'sid', 'iat', 'exp', 'data', 'n'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $payload)) {
                return null;
            }
        }

        if (!in_array($payload['typ'], ['route', 'id'], true)) {
            return null;
        }

        if (!is_numeric($payload['iat']) || !is_numeric($payload['exp'])) {
            return null;
        }

        if (!is_string($payload['sid']) || !hash_equals(app_token_session_hash(), $payload['sid'])) {
            return null;
        }

        if ((int) $payload['exp'] < time()) {
            return null;
        }

        if ($expectedType !== null && $payload['typ'] !== $expectedType) {
            return null;
        }

        if ($expectedSubject !== null && !hash_equals((string) $expectedSubject, (string) $payload['sub'])) {
            return null;
        }

        return $payload;
    }
}

if (!function_exists('app_id_token')) {
    function app_id_token(string $entity, int $id, int $ttl = 1800): string
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID must be positive.');
        }

        return app_token_issue('id', $entity, ['id' => $id], $ttl);
    }
}

if (!function_exists('app_id_resolve')) {
    function app_id_resolve(string $token, string $entity): ?int
    {
        $payload = app_token_decode($token, 'id', $entity);
        if (!is_array($payload)) {
            return null;
        }

        $data = $payload['data'] ?? null;
        if (!is_array($data) || !isset($data['id']) || !is_numeric($data['id'])) {
            return null;
        }

        $id = (int) $data['id'];
        return $id > 0 ? $id : null;
    }
}

if (!function_exists('app_abort_forbidden')) {
    function app_abort_forbidden(): void
    {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            'success' => false,
            'message' => 'Forbidden',
        ]);
        exit();
    }
}
