<?php

declare(strict_types=1);

/**
 * session_inventory_helper.php ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â server-side list of the live sessions per
 * account, so a new login can revoke the account's earlier sessions (CWE-613).
 *
 * Keyed on the same non-reversible session reference the audit log uses
 * (first 16 hex of sha256(session_id)); the raw session id is never stored.
 *
 * Fail-soft: when there is no database handle every function no-ops, so login
 * and the test suite still work without the inventory table.
 */

require_once __DIR__ . '/path_helper.php';
require_once __DIR__ . '/audit_helper.php';

if (!function_exists('app_session_inventory_sid')) {
    function app_session_inventory_sid(): string
    {
        return substr(hash('sha256', (string) session_id()), 0, 16);
    }
}

if (!function_exists('app_session_inventory_register')) {
    /**
     * Record the current session for this account and revoke every other one.
     */
    function app_session_inventory_register(string $actorType, string $actorId): void
    {
        $connect = $GLOBALS['connect'] ?? null;
        if (!$connect instanceof PDO) {
            return;
        }

        $actorType = substr($actorType, 0, 20);
        $actorId = substr($actorId, 0, 32);
        $sid = app_session_inventory_sid();

        try {
            $connect->prepare(
                "INSERT INTO USER_SESSION (actor_type, actor_id, sid) VALUES (:t, :a, :s)
                 ON DUPLICATE KEY UPDATE actor_type = :t2, actor_id = :a2, created_at = CURRENT_TIMESTAMP"
            )->execute([':t' => $actorType, ':a' => $actorId, ':s' => $sid, ':t2' => $actorType, ':a2' => $actorId]);

            $connect->prepare("DELETE FROM USER_SESSION WHERE actor_type = :t AND actor_id = :a AND sid <> :s2")
                    ->execute([':t' => $actorType, ':a' => $actorId, ':s2' => $sid]);
        } catch (Throwable $e) {
            error_log('session inventory register failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('app_session_inventory_is_current')) {
    /**
     * True when the current session is still the account's live session.
     * Returns true when the store is unavailable (fail-soft: never lock out).
     */
    function app_session_inventory_is_current(string $actorType, string $actorId): bool
    {
        $connect = $GLOBALS['connect'] ?? null;
        if (!$connect instanceof PDO) {
            return true;
        }

        try {
            $sid = app_session_inventory_sid();
            $stmt = $connect->prepare("SELECT 1 FROM USER_SESSION WHERE actor_type = :t AND actor_id = :a AND sid = :s LIMIT 1");
            $stmt->execute([
                ':t' => substr($actorType, 0, 20),
                ':a' => substr($actorId, 0, 32),
                ':s' => $sid,
            ]);
            return $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            error_log('session inventory check failed: ' . $e->getMessage());
            return true;
        }
    }
}

if (!function_exists('app_session_inventory_forget')) {
    /**
     * Drop the current session's inventory row (called on logout).
     */
    function app_session_inventory_forget(): void
    {
        $connect = $GLOBALS['connect'] ?? null;
        if (!$connect instanceof PDO) {
            return;
        }

        try {
            $connect->prepare("DELETE FROM USER_SESSION WHERE sid = :s")
                    ->execute([':s' => app_session_inventory_sid()]);
        } catch (Throwable $e) {
            error_log('session inventory forget failed: ' . $e->getMessage());
        }
    }
}
