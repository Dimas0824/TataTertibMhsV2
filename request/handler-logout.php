<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/path_helper.php';
require_once __DIR__ . '/../helpers/route_helper.php';
require_once __DIR__ . '/../helpers/token_helper.php';

app_session_start_if_needed();
app_verify_csrf();

app_audit_log('logout', [
    'actor_type' => (string) ($_SESSION['user_type'] ?? ''),
    'actor_id' => (string) ($_SESSION['username'] ?? ''),
]);

$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
}

app_redirect_page('page.home');
