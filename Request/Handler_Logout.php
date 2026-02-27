<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/path_helper.php';
require_once __DIR__ . '/../helpers/route_helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

header('Location: /', true, 302);
exit();
