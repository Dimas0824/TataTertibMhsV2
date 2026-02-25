<?php

declare(strict_types=1);

if (!function_exists('get_app_nav_items')) {
    function get_app_nav_items(string $variant, string $context): array
    {
        $variant = in_array($variant, ['guest', 'student', 'admin'], true) ? $variant : 'guest';
        $context = $context === 'root' ? 'root' : 'views';

        $rootMap = [
            'home' => 'index.php',
            'tatib' => 'views/listTatib.php',
            'pelanggaran' => 'views/pelanggaranpage.php',
            'notifikasi' => 'views/notifikasi.php',
            'logout' => '?logout=true',
            'news' => 'views/news-admin.php',
            'admin_home' => 'views/home-admin.php',
            'admin_tatib' => 'views/listTatib-admin.php',
        ];

        $viewsMap = [
            'home' => '../index.php',
            'tatib' => 'listTatib.php',
            'pelanggaran' => 'pelanggaranpage.php',
            'notifikasi' => 'notifikasi.php',
            'logout' => '../?logout=true',
            'news' => 'news-admin.php',
            'admin_home' => 'home-admin.php',
            'admin_tatib' => 'listTatib-admin.php',
        ];

        $hrefMap = $context === 'root' ? $rootMap : $viewsMap;

        $baseItems = [
            'guest' => [
                ['key' => 'home', 'label' => 'Home', 'icon' => 'fa-solid fa-house', 'href' => $hrefMap['home']],
                ['key' => 'tatib', 'label' => 'Tata Tertib', 'icon' => 'fa-solid fa-book', 'href' => $hrefMap['tatib']],
                ['key' => 'pelanggaran', 'label' => 'Pelanggaran', 'icon' => 'fa-solid fa-hand', 'href' => $hrefMap['pelanggaran']],
            ],
            'student' => [
                ['key' => 'home', 'label' => 'Home', 'icon' => 'fa-solid fa-house', 'href' => $hrefMap['home']],
                ['key' => 'tatib', 'label' => 'Tata Tertib', 'icon' => 'fa-solid fa-book', 'href' => $hrefMap['tatib']],
                ['key' => 'pelanggaran', 'label' => 'Pelanggaran', 'icon' => 'fa-solid fa-hand', 'href' => $hrefMap['pelanggaran']],
                ['key' => 'notifikasi', 'label' => 'Notifikasi', 'icon' => 'fa-solid fa-bell', 'href' => $hrefMap['notifikasi']],
                ['key' => 'logout', 'label' => 'Keluar', 'icon' => 'fa-solid fa-right-from-bracket', 'href' => $hrefMap['logout'], 'logout' => true],
            ],
            'admin' => [
                ['key' => 'home', 'label' => 'Home', 'icon' => 'fa-solid fa-house', 'href' => $hrefMap['admin_home']],
                ['key' => 'tatib', 'label' => 'Tata Tertib', 'icon' => 'fa-solid fa-book', 'href' => $hrefMap['admin_tatib']],
                ['key' => 'news', 'label' => 'News', 'icon' => 'fa-solid fa-newspaper', 'href' => $hrefMap['news']],
                ['key' => 'logout', 'label' => 'Keluar', 'icon' => 'fa-solid fa-right-from-bracket', 'href' => $hrefMap['logout'], 'logout' => true],
            ],
        ];

        return $baseItems[$variant];
    }
}

if (!function_exists('render_app_sidebar')) {
    function render_app_sidebar(array $config): void
    {
        $variant = (string) ($config['variant'] ?? 'guest');
        $context = (string) ($config['context'] ?? 'views');
        $active = isset($config['active']) ? (string) $config['active'] : null;
        $assetPrefix = $context === 'root' ? '' : '../';
        $homeHref = $context === 'root' ? 'index.php' : '../index.php';

        $navItems = get_app_nav_items($variant, $context);
        ?>
        <aside class="sidebar" aria-label="Navigasi utama">
            <a class="sidebar-brand" href="<?= htmlspecialchars($homeHref, ENT_QUOTES, 'UTF-8') ?>" aria-label="DiscipLink Home">
                <img class="logo" src="<?= htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8') ?>img/logo aja.png" alt="DiscipLink logo">
                <span class="brand-text">DiscipLink</span>
            </a>
            <div class="logo-separator"></div>
            <ul class="sidebar-nav" role="list">
                <?php foreach ($navItems as $item):
                    $isActive = $active !== null && $active === $item['key'];
                    $itemClasses = [];
                    if ($isActive) {
                        $itemClasses[] = 'active';
                    }
                    if (!empty($item['logout'])) {
                        $itemClasses[] = 'logout';
                    }
                    ?>
                    <li class="<?= implode(' ', $itemClasses) ?>">
                        <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
                           aria-label="<?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>"
                           <?= $isActive ? 'aria-current="page"' : '' ?>>
                            <span class="nav-icon" aria-hidden="true"><i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                            <span class="nav-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <script defer src="<?= htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8') ?>js/layout-nav.js"></script>
        <?php
    }
}

if (!function_exists('render_app_header')) {
    function render_app_header(array $config): void
    {
        $title = (string) ($config['title'] ?? 'DiscipLink');
        $showLogin = (bool) ($config['showLogin'] ?? false);
        $loginHref = (string) ($config['loginHref'] ?? 'views/login.php');
        $roleLabel = isset($config['roleLabel']) && $config['roleLabel'] !== ''
            ? (string) $config['roleLabel']
            : null;
        ?>
        <header class="header">
            <div class="header-left">
                <button type="button" class="nav-toggle" data-nav-toggle aria-label="Pin sidebar" aria-pressed="false">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
            <div class="header-actions">
                <?php if ($roleLabel !== null): ?>
                    <span class="role-pill"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($showLogin): ?>
                    <a class="login-btn" href="<?= htmlspecialchars($loginHref, ENT_QUOTES, 'UTF-8') ?>">Login</a>
                <?php endif; ?>
            </div>
        </header>
        <?php
    }
}
