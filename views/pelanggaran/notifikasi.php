<?php
session_start();
require_once dirname(__DIR__, 2) . '/Controllers/UserController.php';
require_once dirname(__DIR__) . '/partials/app-shell.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
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
        'canonical_path' => '/views/pelanggaran/notifikasi.php',
        'image' => 'img/GRAHA-POLINEMA1-slider-01.webp',
        'robots' => 'noindex, nofollow',
    ]);
    ?>
    <link rel="icon" type="image/png" href="../../img/logo aja.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Italiana&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
    <link rel="stylesheet" href="../../css/notifikasi.css">
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
            'loginHref' => '../auth/login.php',
            'roleLabel' => $notificationRole,
        ]);
        ?>

        <section class="notif-page" data-notif-root data-endpoint="../../Request/Handler_Notifikasi.php">
            <section class="notif-overview" aria-label="Ringkasan notifikasi">
                <div class="notif-overview-copy">
                    <span class="notif-kicker">DiscipLink Inbox</span>
                    <h2>Notifikasi Aktivitas</h2>
                    <p>Pantau pembaruan pelanggaran dengan cepat, ringkas, dan mudah dicari.</p>
                </div>
                <div class="notif-stats" aria-live="polite">
                    <article class="notif-stat">
                        <span>Total</span>
                        <strong data-counter="total">0</strong>
                    </article>
                    <article class="notif-stat notif-stat--unread">
                        <span>Unread</span>
                        <strong data-counter="unread">0</strong>
                    </article>
                    <article class="notif-stat notif-stat--read">
                        <span>Read</span>
                        <strong data-counter="read">0</strong>
                    </article>
                </div>
            </section>

            <section class="notif-toolbar" aria-label="Kontrol notifikasi">
                <label class="notif-search" for="notifSearchInput">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input
                        type="search"
                        id="notifSearchInput"
                        placeholder="Cari isi notifikasi..."
                        autocomplete="off">
                </label>

                <div class="notif-filters" role="tablist" aria-label="Filter status notifikasi">
                    <button type="button" class="notif-filter-btn is-active" data-filter="all" aria-selected="true">Semua</button>
                    <button type="button" class="notif-filter-btn" data-filter="unread" aria-selected="false">Unread</button>
                    <button type="button" class="notif-filter-btn" data-filter="read" aria-selected="false">Read</button>
                </div>

                <button type="button" class="notif-mark-all-btn" data-action="mark-all-read" disabled>
                    <i class="fa-solid fa-check-double" aria-hidden="true"></i>
                    Tandai semua dibaca
                </button>
            </section>

            <section class="notifications-panel" aria-live="polite">
                <div class="notifications" id="notifList" role="list"></div>

                <div class="notifications-empty is-hidden" id="notifEmptyServer">
                    <i class="fa-regular fa-bell-slash" aria-hidden="true"></i>
                    <p>Belum ada notifikasi untuk akun ini.</p>
                </div>

                <div class="notifications-empty is-hidden" id="notifEmptyFiltered">
                    <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i>
                    <p>Tidak ada notifikasi yang sesuai dengan pencarian/filter.</p>
                </div>
            </section>
        </section>

        <?php
        render_app_footer([
            'context' => 'nested',
        ]);
        ?>
    </div>

    <script defer src="../../js/notifikasi.js"></script>
</body>

</html>
