<?php
/**
 * Security audit trail — append-only event log (login, logout, download, CSRF...).
 * Design rules: logging must NEVER break or replace the request path; failures
 * only go to error_log. PII-light: session reference is a truncated sha256, no
 * passwords/tokens/names of uploaded files beyond ids.
 */

declare(strict_types=1);

if (!function_exists('app_audit_log')) {
    function app_audit_log(string $event, array $context = []): void
    {
        $pdo = $GLOBALS['connect'] ?? null;
        if (!($pdo instanceof PDO)) {
            return; // app not booted with DB (CLI probes, early failures) — audit is best-effort
        }

        try {
            $sid = null;
            if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
                $sid = substr(hash('sha256', (string) session_id()), 0, 16);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO SECURITY_AUDIT_LOG (event, actor_type, actor_id, sid, ip, detail) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                substr($event, 0, 40),
                isset($context['actor_type']) ? substr((string) $context['actor_type'], 0, 20) : null,
                isset($context['actor_id']) ? substr((string) $context['actor_id'], 0, 32) : null,
                $sid,
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                substr((string) ($context['detail'] ?? ''), 0, 255),
            ]);
        } catch (Throwable $e) {
            error_log('audit write failed [' . $event . ']: ' . $e->getMessage());
        }
    }
}
