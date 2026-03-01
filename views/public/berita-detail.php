<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/controllers/NewsController.php';
require_once dirname(__DIR__) . '/partials/app-shell.php';

$rawSlug = (string) app_route_data('slug', '');
$normalizedSlug = trim(strtolower($rawSlug));
if ($normalizedSlug === '') {
    app_render_error_page(404);
    return;
}

$newsController = new NewsController($connect ?? null);
$newsId = NewsController::news_extract_id_from_slug($normalizedSlug);
if ($newsId === null) {
    app_render_error_page(404);
    return;
}

$news = $newsController->getNewsById($newsId);
if (!is_array($news)) {
    app_render_error_page(404);
    return;
}

$canonicalSlug = NewsController::news_build_slug((string) ($news['judul'] ?? ''), (int) ($news['id_news'] ?? 0));
if (!hash_equals($canonicalSlug, $normalizedSlug)) {
    app_redirect_page('page.news_detail', ['slug' => $canonicalSlug], 301);
    return;
}

$relatedNewsData = $newsController->getLatestNewsExcluding((int) $news['id_news'], 8);
if (!is_array($relatedNewsData)) {
    $relatedNewsData = [];
}

$detailVariant = isset($_SESSION['username']) ? 'student' : 'guest';
$detailRoleLabel = null;
if (isset($_SESSION['user_type'])) {
    $detailRoleLabel = $_SESSION['user_type'] === 'dosen'
        ? 'Dosen'
        : ($_SESSION['user_type'] === 'admin' ? 'Admin' : 'Mahasiswa');
}

$newsImage = !empty($news['gambar']) ? (string) $news['gambar'] : 'img/news.jpg';
$plainContent = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) ($news['konten'] ?? ''))));
$excerpt = function_exists('mb_substr') ? mb_substr($plainContent, 0, 180) : substr($plainContent, 0, 180);
if ($excerpt === '') {
    $excerpt = 'Informasi lengkap berita kedisiplinan mahasiswa DiscipLink.';
}

$contentWords = $plainContent === ''
    ? []
    : preg_split('/\s+/u', $plainContent, -1, PREG_SPLIT_NO_EMPTY);
$wordCount = is_array($contentWords) ? count($contentWords) : 0;
$readMinutes = max(1, (int) ceil($wordCount / 200));

$rawContent = (string) ($news['konten'] ?? '');
$containsHtml = $rawContent !== strip_tags($rawContent);
$allowedContentTags = '<div><p><br><strong><em><ul><ol><li><h3><blockquote>';
$safeHtmlContent = strip_tags($rawContent, $allowedContentTags);
$safeHtmlContent = (string) preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $safeHtmlContent);
$safeHtmlContent = (string) preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $safeHtmlContent);
$safeHtmlContent = (string) preg_replace_callback('/\sclass\s*=\s*("([^"]*)"|\'([^\']*)\')/i', static function (array $matches): string {
    $rawClasses = trim((string) (($matches[2] ?? '') !== '' ? $matches[2] : ($matches[3] ?? '')));
    if ($rawClasses === '') {
        return '';
    }

    $allowed = ['news-font-small', 'news-font-normal', 'news-font-large'];
    $selected = [];
    foreach (preg_split('/\s+/', $rawClasses) ?: [] as $className) {
        if (in_array($className, $allowed, true)) {
            $selected[] = $className;
        }
    }

    if (empty($selected)) {
        return '';
    }

    return ' class="' . implode(' ', array_unique($selected)) . '"';
}, $safeHtmlContent);
$formattedContent = $containsHtml
    ? $safeHtmlContent
    : nl2br(htmlspecialchars($rawContent, ENT_QUOTES, 'UTF-8'));

$publishedRaw = (string) ($news['published_at'] ?? '');
$publishedTimestamp = strtotime($publishedRaw);
$publishedDisplay = $publishedTimestamp !== false ? date('d M Y · H:i', $publishedTimestamp) : 'Tanggal tidak tersedia';
$publishedIso = $publishedTimestamp !== false ? date('c', $publishedTimestamp) : date('c');

$canonicalPath = '/berita?slug=' . rawurlencode($canonicalSlug);
$relatedExcerpt = static function (string $text, int $limit = 120): string {
    $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
    if ($plain === '') {
        return '';
    }

    $slice = function_exists('mb_substr') ? mb_substr($plain, 0, $limit) : substr($plain, 0, $limit);
    if ((function_exists('mb_strlen') ? mb_strlen($plain) : strlen($plain)) > $limit) {
        return rtrim($slice) . '...';
    }

    return $slice;
};
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) ($news['judul'] ?? 'Detail Berita')) ?> | DiscipLink</title>
    <?php
    app_seo_meta_tags([
        'title' => (string) ($news['judul'] ?? 'Detail Berita') . ' | DiscipLink',
        'description' => $excerpt,
        'canonical_path' => $canonicalPath,
        'image' => $newsImage,
        'article' => [
            'headline' => (string) ($news['judul'] ?? ''),
            'description' => $excerpt,
            'author' => (string) ($news['penulis_nama'] ?? 'Admin DiscipLink'),
            'image' => $newsImage,
            'datePublished' => $publishedIso,
            'dateModified' => $publishedIso,
        ],
    ]);
    ?>
    <?php app_seo_favicon_tags('../../'); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../../css/global.css">
    <link rel="stylesheet" href="../../css/news-detail.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
</head>

<body>
    <?php
    render_app_sidebar([
        'variant' => $detailVariant,
        'context' => 'nested',
        'active' => 'home',
    ]);
    ?>

    <div class="content">
        <?php
        render_app_header([
            'title' => 'Detail Berita',
            'showLogin' => !isset($_SESSION['username']),
            'loginHref' => app_page_url('page.login'),
            'roleLabel' => $detailRoleLabel,
        ]);
        ?>

        <main class="news-detail-page">
            <section class="news-detail-hero">
                <nav class="news-breadcrumb" aria-label="Breadcrumb">
                    <a href="<?= htmlspecialchars(app_page_url('page.home'), ENT_QUOTES, 'UTF-8') ?>">Home</a>
                    <span aria-hidden="true">/</span>
                    <span>Berita</span>
                    <span aria-hidden="true">/</span>
                    <strong><?= htmlspecialchars((string) ($news['judul'] ?? '')) ?></strong>
                </nav>
                <p class="news-detail-kicker">DiscipLink · Berita Kedisiplinan</p>
                <h2><?= htmlspecialchars((string) ($news['judul'] ?? '')) ?></h2>
                <div class="news-detail-meta">
                    <span><i class="fa-regular fa-user" aria-hidden="true"></i>
                        <?= htmlspecialchars((string) ($news['penulis_nama'] ?? 'Admin DiscipLink')) ?></span>
                    <span><i class="fa-regular fa-calendar" aria-hidden="true"></i>
                        <?= htmlspecialchars($publishedDisplay) ?></span>
                    <span><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= $readMinutes ?> menit baca</span>
                </div>
            </section>

            <article class="news-detail-article">
                <div class="news-detail-image-wrap">
                    <img src="../../<?= htmlspecialchars($newsImage, ENT_QUOTES, 'UTF-8') ?>"
                        alt="Gambar berita: <?= htmlspecialchars((string) ($news['judul'] ?? '')) ?>" width="1200"
                        height="675" loading="eager" decoding="async" fetchpriority="high">
                </div>
                <div class="news-detail-body">
                    <?= $formattedContent ?>
                </div>
            </article>

            <section class="related-news-section" aria-label="Berita lainnya">
                <div class="related-news-head">
                    <div>
                        <h3>Berita Lainnya</h3>
                        <p>Baca informasi kedisiplinan kampus terbaru lainnya.</p>
                    </div>
                    <div class="related-news-nav" data-news-carousel-controls>
                        <button type="button" class="carousel-btn" data-carousel-prev
                            aria-label="Geser ke berita sebelumnya">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="carousel-btn" data-carousel-next
                            aria-label="Geser ke berita berikutnya">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <?php if (empty($relatedNewsData)): ?>
                    <div class="related-news-empty">
                        <p>Belum ada berita lain untuk ditampilkan.</p>
                    </div>
                <?php else: ?>
                    <div class="related-news-carousel" data-news-carousel>
                        <div class="related-news-track" data-carousel-track>
                            <?php foreach ($relatedNewsData as $item): ?>
                                <?php
                                $itemSlug = NewsController::news_build_slug((string) ($item['judul'] ?? ''), (int) ($item['id_news'] ?? 0));
                                $itemUrl = app_page_url('page.news_detail', ['slug' => $itemSlug]);
                                $itemImage = !empty($item['gambar']) ? (string) $item['gambar'] : 'img/news.jpg';
                                $itemText = $relatedExcerpt((string) ($item['konten'] ?? ''));
                                $itemPublished = strtotime((string) ($item['published_at'] ?? ''));
                                $itemDateLabel = $itemPublished !== false ? date('d M Y', $itemPublished) : '-';
                                ?>
                                <article class="related-news-card">
                                    <img src="../../<?= htmlspecialchars($itemImage, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="Gambar berita: <?= htmlspecialchars((string) ($item['judul'] ?? '')) ?>"
                                        width="720" height="405" loading="lazy" decoding="async">
                                    <div class="related-news-content">
                                        <span class="related-news-date"><?= htmlspecialchars($itemDateLabel) ?></span>
                                        <h4><?= htmlspecialchars((string) ($item['judul'] ?? '')) ?></h4>
                                        <p><?= htmlspecialchars($itemText) ?></p>
                                        <a href="<?= htmlspecialchars($itemUrl, ENT_QUOTES, 'UTF-8') ?>">Baca Selengkapnya</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </main>

        <?php
        render_app_footer([
            'context' => 'nested',
        ]);
        ?>
    </div>

    <script defer
        src="<?= htmlspecialchars(app_seo_script_src('js/news-detail.js', '../..'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>

</html>
