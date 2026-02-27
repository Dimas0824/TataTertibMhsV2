<?php

declare(strict_types=1);

$errorCode = (int) ($error['statusCode'] ?? 404);
$errorTitle = (string) ($error['title'] ?? 'Halaman Hilang');
$errorMessage = (string) ($error['message'] ?? 'Halaman tidak ditemukan.');
$iconType = (string) ($error['icon'] ?? 'search-x');
$themeClass = (string) ($error['theme'] ?? 'slate');
$blob1Class = (string) ($error['blob1'] ?? 'blob-slate');
$blob2Class = (string) ($error['blob2'] ?? 'blob-gray');

$homeUrl = '/';
$tatibUrl = app_page_url('page.tatib');
$loginUrl = app_page_url('page.login');

if (!function_exists('app_error_icon_svg')) {
    function app_error_icon_svg(string $iconType): string
    {
        $stroke = 'currentColor';
        $icons = [
            'external-link' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14 5h5v5" stroke="' . $stroke . '" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 14L19 5" stroke="' . $stroke . '" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 13v5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5" stroke="' . $stroke . '" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'alert-circle' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="' . $stroke . '" stroke-width="1.6"/><path d="M12 8v5" stroke="' . $stroke . '" stroke-width="1.6" stroke-linecap="round"/><circle cx="12" cy="16.5" r="1" fill="' . $stroke . '"/></svg>',
            'shield-alert' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3l7 3v5c0 5-3.3 8.7-7 10-3.7-1.3-7-5-7-10V6l7-3z" stroke="' . $stroke . '" stroke-width="1.6" stroke-linejoin="round"/><path d="M12 8v5" stroke="' . $stroke . '" stroke-width="1.6" stroke-linecap="round"/><circle cx="12" cy="16.5" r="1" fill="' . $stroke . '"/></svg>',
            'search-x' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="11" cy="11" r="7" stroke="' . $stroke . '" stroke-width="1.6"/><path d="M20 20l-3.5-3.5" stroke="' . $stroke . '" stroke-width="1.6" stroke-linecap="round"/><path d="M9 9l4 4M13 9l-4 4" stroke="' . $stroke . '" stroke-width="1.6" stroke-linecap="round"/></svg>',
            'server-crash' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="4" width="16" height="6" rx="1.5" stroke="' . $stroke . '" stroke-width="1.6"/><rect x="4" y="14" width="16" height="6" rx="1.5" stroke="' . $stroke . '" stroke-width="1.6"/><path d="M8 7h.01M8 17h.01" stroke="' . $stroke . '" stroke-width="2" stroke-linecap="round"/><path d="M14 8l4-4M14 4l4 4" stroke="' . $stroke . '" stroke-width="1.6" stroke-linecap="round"/></svg>',
            'wrench' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14.5 5.5a4 4 0 0 0 4.9 4.9l-8.8 8.8a2 2 0 0 1-2.8-2.8l8.8-8.8a4 4 0 0 0-2.1-7.4" stroke="' . $stroke . '" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ];

        return $icons[$iconType] ?? $icons['search-x'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(app_error_meta_title($errorCode, $errorTitle), ENT_QUOTES, 'UTF-8') ?></title>
    <?php
    app_seo_meta_tags([
        'title' => app_error_meta_title($errorCode, $errorTitle),
        'description' => app_error_meta_description($errorCode, $errorMessage),
        'canonical_path' => $canonicalPath,
        'robots' => $robots,
        'image' => 'img/GRAHA-POLINEMA1-slider-01.webp',
    ]);
    app_seo_favicon_tags();
    ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('css/global.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('css/error-page.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
</head>

<body>
    <div class="error-page">
        <header class="error-header" aria-label="Brand DiscipLink">
            <div class="error-brand-mark" aria-hidden="true">⚙</div>
            <span class="error-brand-text">DISCIP<span>LINK</span></span>
        </header>

        <div class="error-watermark" aria-hidden="true"><?= htmlspecialchars((string) $errorCode, ENT_QUOTES, 'UTF-8') ?></div>

        <main class="error-main" aria-labelledby="errorTitle">
            <section class="error-content">
                <div class="error-badge <?= htmlspecialchars($themeClass, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="error-badge-dot"></span>
                    <span>Error <?= htmlspecialchars((string) $errorCode, ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <h1 id="errorTitle"><?= htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8') ?>.</h1>
                <p><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>

                <div class="error-actions">
                    <button type="button" class="btn btn-secondary" id="errorBackButton">Kembali</button>
                    <a class="btn btn-primary" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">Ke Beranda</a>
                </div>

                <p class="error-caption">DiscipLink - Rules Shape Greatness!</p>
            </section>

            <section class="error-visual" aria-hidden="true">
                <div class="blob-stack">
                    <div class="blob <?= htmlspecialchars($blob1Class, ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="blob <?= htmlspecialchars($blob2Class, ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="blob blob-slate"></div>
                    <div class="icon-shell"><?= app_error_icon_svg($iconType) ?></div>
                </div>
            </section>
        </main>
    </div>

    <script>
        (function () {
            var backButton = document.getElementById('errorBackButton');
            if (!backButton) {
                return;
            }

            backButton.addEventListener('click', function () {
                if (window.history.length > 1) {
                    window.history.back();
                    return;
                }

                window.location.href = '/';
            });
        })();
    </script>
</body>

</html>
