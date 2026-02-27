<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/helpers/flash_modal.php';
require_once dirname(__DIR__, 2) . '/helpers/seo_helper.php';
require_once dirname(__DIR__, 2) . '/helpers/route_helper.php';
require_once dirname(__DIR__) . '/components/modals/app-feedback-modal.php';

if (!defined('APP_CANONICAL_ENFORCED')) {
    app_seo_enforce_canonical_host();
    app_seo_apply_security_headers();
    define('APP_CANONICAL_ENFORCED', true);
}

if (!function_exists('get_app_nav_items')) {
    function get_app_nav_items(string $variant, string $context): array
    {
        $variant = in_array($variant, ['guest', 'student', 'admin'], true) ? $variant : 'guest';
        $context = in_array($context, ['root', 'views', 'nested'], true) ? $context : 'views';

        $hrefMap = [
            'home' => '/',
            'tatib' => app_page_url('page.tatib'),
            'pelanggaran' => app_page_url('page.pelanggaran'),
            'notifikasi' => app_page_url('page.notifikasi'),
            'logout' => app_action_url('action.logout'),
            'news' => app_page_url('page.admin_news'),
            'admin_home' => app_page_url('page.admin_home'),
            'admin_tatib' => app_page_url('page.admin_tatib'),
        ];

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
        $context = in_array($context, ['root', 'views', 'nested'], true) ? $context : 'views';
        $active = isset($config['active']) ? (string) $config['active'] : null;
        $assetPrefix = $context === 'root' ? '' : ($context === 'nested' ? '../../' : '../');
        $homeHref = '/';

        $navItems = get_app_nav_items($variant, $context);
        ?>
        <aside class="sidebar" aria-label="Navigasi utama">
            <a class="sidebar-brand" href="<?= htmlspecialchars($homeHref, ENT_QUOTES, 'UTF-8') ?>" aria-label="DiscipLink Home">
                <img class="logo" src="<?= htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8') ?>img/logo aja.png" alt="DiscipLink logo" width="42" height="42" decoding="async">
                <span class="brand-text">DiscipLink</span>
            </a>
            <button type="button" class="nav-toggle sidebar-rail-toggle" data-nav-toggle aria-label="Pin sidebar" aria-pressed="false">
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
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

        <script defer src="<?= htmlspecialchars(app_seo_script_src('js/layout-nav.js', $assetPrefix), ENT_QUOTES, 'UTF-8') ?>"></script>
        <?php
    }
}

if (!function_exists('render_app_header')) {
    function render_app_header(array $config): void
    {
        $title = (string) ($config['title'] ?? 'DiscipLink');
        $showLogin = (bool) ($config['showLogin'] ?? false);
        $loginHref = (string) ($config['loginHref'] ?? app_page_url('page.login'));
        $roleLabel = isset($config['roleLabel']) && $config['roleLabel'] !== ''
            ? (string) $config['roleLabel']
            : null;
        ?>
        <header class="header">
            <div class="header-left">
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

if (!function_exists('render_app_footer')) {
    function render_app_footer(array $config = []): void
    {
        $context = (string) ($config['context'] ?? 'views');
        $context = in_array($context, ['root', 'views', 'nested'], true) ? $context : 'views';
        $assetPrefix = $context === 'root' ? '' : ($context === 'nested' ? '../../' : '../');

        $address = (string) ($config['address'] ?? 'Jl. Soekarno Hatta No.9, Jatimulyo, Kec. Lowokwaru, Kota Malang, Jawa Timur 65141');
        $phoneLabel = (string) ($config['phoneLabel'] ?? '(0341) 404424');
        $phoneHref = (string) ($config['phoneHref'] ?? 'tel:+62341404424');
        $instagramHref = (string) ($config['instagramHref'] ?? 'https://instagram.com');
        $whatsappHref = (string) ($config['whatsappHref'] ?? 'https://wa.me/1234567890');
        $emailHref = (string) ($config['emailHref'] ?? 'mailto:info@disciplink.local');
        $copyright = (string) ($config['copyright'] ?? '© Copyright 2026 Web Tatib. All Rights Reserved.');
        ?>
        <footer class="footer" aria-label="Informasi kontak website">
            <div class="footer-main">
                <div class="footer-brand">
                    <div class="footer-brand-logos">
                        <img class="footer-logo" src="<?= htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8') ?>img/logo aja.png" alt="Logo DiscipLink" width="76" height="76" loading="lazy" decoding="async">
                        <img class="footer-logo" src="<?= htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8') ?>img/favicon-96x96.png" alt="Favicon DiscipLink" width="76" height="76" loading="lazy" decoding="async">
                    </div>
                    <p class="footer-brand-copy">DiscipLink · Platform informasi tata tertib mahasiswa.</p>
                </div>

                <div class="footer-contact">
                    <p class="footer-address"><?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?></p>
                    <a href="<?= htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8') ?>" class="footer-link"><?= htmlspecialchars($phoneLabel, ENT_QUOTES, 'UTF-8') ?></a>
                </div>

                <div class="footer-social" aria-label="Media sosial">
                    <a href="<?= htmlspecialchars($instagramHref, ENT_QUOTES, 'UTF-8') ?>" class="social-link" aria-label="Instagram">
                        <i class="fa-brands fa-instagram" aria-hidden="true"></i>
                    </a>
                    <a href="<?= htmlspecialchars($whatsappHref, ENT_QUOTES, 'UTF-8') ?>" class="social-link" aria-label="WhatsApp">
                        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                    </a>
                    <a href="<?= htmlspecialchars($emailHref, ENT_QUOTES, 'UTF-8') ?>" class="social-link" aria-label="Email">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
            <div class="footer-bottom">
                <p><?= htmlspecialchars($copyright, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </footer>
        <?php
    }
}

if (!function_exists('render_app_flash_modal')) {
    function render_app_flash_modal(array $config = []): void
    {
        $context = (string) ($config['context'] ?? 'views');
        $context = in_array($context, ['root', 'views', 'nested'], true) ? $context : 'views';
        $assetPrefix = $context === 'root' ? '' : ($context === 'nested' ? '../../' : '../');
        $flashModal = consume_app_flash_modal();

        render_app_feedback_modal_component([
            'assetPrefix' => $assetPrefix,
            'flashModal' => $flashModal,
        ]);
    }
}
