<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once dirname(__DIR__, 2) . '/controllers/UserController.php';
require_once dirname(__DIR__) . '/partials/app-shell.php';

if (!isset($_SESSION['username'])) {
    app_redirect_page('page.login');
}

if (isset($_GET['logout'])) {
    $userController = new UserController();
    $userController->logout();
    exit();
}

$notificationRole = $_SESSION['user_type'] === 'dosen' ? 'Dosen' : 'Mahasiswa';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi Pelanggaran | DiscipLink</title>
    <?php
    app_seo_meta_tags([
        'title' => 'Notifikasi Pelanggaran | DiscipLink',
        'description' => 'Inbox notifikasi DiscipLink untuk memantau pembaruan laporan pelanggaran mahasiswa secara real-time.',
        'canonical_path' => '/',
        'image' => 'img/GRAHA-POLINEMA1-slider-01.webp',
        'robots' => 'noindex, nofollow',
    ]);
    ?>
    <?php app_seo_favicon_tags('../../'); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../../css/global.css">
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css"
        onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
    </noscript>
</head>

<body>
    <?php
    render_app_sidebar([
        'variant' => 'student',
        'context' => 'nested',
        'active' => 'notifikasi',
    ]);
    ?>
    <div class="content">
        <?php
        render_app_header([
            'title' => 'Notifikasi',
            'showLogin' => false,
            'loginHref' => app_page_url('page.login'),
            'roleLabel' => $notificationRole,
        ]);
        ?>

        <?php
        render_notification_center_component([
            'context' => 'nested',
            'endpoint' => app_action_url('action.notifikasi'),
            'roleLabel' => $notificationRole,
        ]);
        ?>

        <?php
        render_app_footer([
            'context' => 'nested',
        ]);
        ?>
    </div>

</body>

</html>
