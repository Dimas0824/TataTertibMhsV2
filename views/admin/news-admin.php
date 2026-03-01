<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/Controllers/NewsController.php';
require_once dirname(__DIR__, 2) . '/Controllers/UserController.php';
require_once dirname(__DIR__) . '/partials/app-shell.php';
require_once dirname(__DIR__) . '/components/modals/admin-confirm-modal.php';

if (isset($_SESSION['username'])) {
    // Redirect based on role
    if ($_SESSION['user_type'] === 'mahasiswa') {
        app_redirect_page('page.pelanggaran');
    } elseif ($_SESSION['user_type'] === 'dosen') {
        app_redirect_page('page.pelanggaran_dosen');
    }
} else {
    app_redirect_page('page.login');
}

if (isset($_GET['logout'])) {
    $userController = new UserController();
    $userController->logout();
    exit();
}

// Ambil data user dari session
$userData = $_SESSION['user_data'] ?? null;
$id_admin = $userData['id_admin'] ?? null;

$userController = new UserController();
$penulis_nama = $id_admin ? $userController->getAdminName($id_admin) : 'Admin';

// Ambil berita terkait admin
$newsController = new NewsController();
$newsData = $newsController->AdminNews(id: $id_admin);

$newsExcerpt = static function (string $html, int $limit = 210): string {
    $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));
    if ($plain === '') {
        return '-';
    }

    $slice = function_exists('mb_substr') ? mb_substr($plain, 0, $limit) : substr($plain, 0, $limit);
    $length = function_exists('mb_strlen') ? mb_strlen($plain) : strlen($plain);
    return $length > $limit ? rtrim($slice) . '...' : $slice;
};

$escapeHtml = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$newsWithImage = 0;
if (is_array($newsData)) {
    foreach ($newsData as $newsItem) {
        if (trim((string) ($newsItem['gambar'] ?? '')) !== '') {
            $newsWithImage++;
        }
    }
}
$newsWithoutImage = (is_array($newsData) ? count($newsData) : 0) - $newsWithImage;

$newsAdminColumns = [
    [
        'label' => 'Judul',
        'cellClass' => 'news-title-cell',
        'render' => static function (array $news) use ($escapeHtml): string {
            return $escapeHtml((string) ($news['judul'] ?? ''));
        },
    ],
    [
        'label' => 'Gambar',
        'render' => static function (array $news) use ($escapeHtml): string {
            $gambar = trim((string) ($news['gambar'] ?? ''));
            if ($gambar === '') {
                return '<span class="muted-text">Tidak ada gambar</span>';
            }
            return '<img class="news-thumb" src="../../' . $escapeHtml($gambar) . '" alt="Gambar News" width="160" height="90" loading="lazy" decoding="async">';
        },
    ],
    [
        'label' => 'Konten',
        'cellClass' => 'news-content-cell',
        'render' => static function (array $news) use ($escapeHtml, $newsExcerpt): string {
            return $escapeHtml($newsExcerpt((string) ($news['konten'] ?? '')));
        },
    ],
    [
        'label' => 'Penulis',
        'render' => static function () use ($escapeHtml, $penulis_nama): string {
            return $escapeHtml((string) $penulis_nama);
        },
    ],
    [
        'label' => 'Aksi',
        'cellClass' => 'button-cell',
        'render' => static function (array $news) use ($escapeHtml): string {
            $judul = (string) ($news['judul'] ?? '');
            ob_start();
            ?>
            <a href="<?= $escapeHtml(app_page_url('page.admin_news_edit', ['id_news' => (int) ($news['id_news'] ?? 0)])) ?>"
                class="edit-button"
                aria-label="Edit berita <?= $escapeHtml($judul) ?>">
                <i class="fa-solid fa-pen-to-square"></i>
            </a>
            <form action="<?= $escapeHtml(app_action_url('action.news')) ?>" method="post">
                <input type="hidden" name="news_id"
                    value="<?= $escapeHtml(app_id_token('news', (int) ($news['id_news'] ?? 0))) ?>">
                <button type="button" class="delete" id="delete" name="delete"
                    data-admin-confirm-trigger data-admin-confirm-title="Hapus berita?"
                    data-admin-confirm-message="Berita yang dihapus tidak dapat dipulihkan. Yakin ingin melanjutkan?"
                    data-admin-confirm-label="Ya, Hapus" data-admin-confirm-action="submit-form"
                    aria-label="Hapus berita <?= $escapeHtml($judul) ?>"><i class="fa-solid fa-trash"></i></button>
            </form>
            <?php
            return (string) ob_get_clean();
        },
    ],
];

$newsAdminRowMetaBuilder = static function (array $news) use ($penulis_nama): array {
    $hasImage = trim((string) ($news['gambar'] ?? '')) !== '';
    return [
        'search' => implode(' ', [
            (string) ($news['judul'] ?? ''),
            (string) strip_tags((string) ($news['konten'] ?? '')),
            (string) $penulis_nama,
        ]),
        'filters' => [
            'gambar' => $hasImage ? 'dengan' : 'tanpa',
        ],
    ];
};

$newsAdminTableConfig = [
    'id' => 'admin-news-table',
    'title' => 'Daftar Berita',
    'description' => 'Gunakan pencarian dan filter untuk mempercepat manajemen berita.',
    'stats' => [
        ['label' => (is_array($newsData) ? count($newsData) : 0) . ' berita'],
        ['label' => $newsWithImage . ' dengan gambar'],
        ['label' => $newsWithoutImage . ' tanpa gambar', 'class' => 'table-stat-chip--warning'],
    ],
    'filters' => [
        [
            'key' => 'gambar',
            'label' => 'Gambar',
            'options' => [
                ['value' => 'dengan', 'label' => 'Dengan Gambar'],
                ['value' => 'tanpa', 'label' => 'Tanpa Gambar'],
            ],
        ],
    ],
    'search' => [
        'enabled' => true,
        'label' => 'Cari Berita',
        'placeholder' => 'Cari judul atau isi berita',
    ],
    'columns' => $newsAdminColumns,
    'rows' => is_array($newsData) ? $newsData : [],
    'rowMetaBuilder' => $newsAdminRowMetaBuilder,
    'emptyMessage' => 'Belum ada berita untuk ditampilkan.',
    'tableCardClass' => 'admin-table-card',
    'tableAriaLabel' => 'Tabel manajemen berita admin',
];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen News Admin | DiscipLink</title>
    <?php
    app_seo_meta_tags([
        'title' => 'Manajemen News Admin | DiscipLink',
        'description' => 'Panel admin untuk mengelola berita kedisiplinan kampus pada sistem DiscipLink.',
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
    <link href="https://fonts.googleapis.com/css2?family=Italiana&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../css/global.css">
    <link rel="stylesheet" href="../../css/news-admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
</head>

<body>
    <?php
    render_app_sidebar([
        'variant' => 'admin',
        'context' => 'nested',
        'active' => 'news',
    ]);
    ?>
    <div class="content">
        <?php
        render_app_header([
            'title' => 'News Admin',
            'showLogin' => false,
            'loginHref' => app_page_url('page.login'),
            'roleLabel' => 'Admin',
        ]);
        ?>
        <section class="admin-news-page">
            <div class="admin-news-hero">
                <div>
                    <span class="admin-news-kicker">DiscipLink Admin</span>
                    <h1>Manajemen News</h1>
                    <p>Kelola publikasi berita kedisiplinan kampus secara ringkas, terstruktur, dan mudah dipantau.</p>
                </div>
                <div class="admin-news-stat">
                    <span>Total Berita</span>
                    <strong><?= count($newsData) ?></strong>
                </div>
            </div>

            <div class="admin-news-toolbar">
                <a href="<?= htmlspecialchars(app_page_url('page.admin_news_tambah'), ENT_QUOTES, 'UTF-8') ?>"
                    class="add-button" id="addButton">+ Tambah Berita</a>
            </div>

            <?php render_universal_filterable_table_component($newsAdminTableConfig); ?>
        </section>

        <!-- javascript -->
        <script defer
            src="<?= htmlspecialchars(app_seo_script_src('js/universal-table-filter.js', '../..'), ENT_QUOTES, 'UTF-8') ?>"></script>
        <script defer
            src="<?= htmlspecialchars(app_seo_script_src('js/script-news.js', '../..'), ENT_QUOTES, 'UTF-8') ?>"></script>
        <?php
        render_app_footer([
            'context' => 'nested',
        ]);
        ?>
    </div>
    <?php
    render_app_flash_modal([
        'context' => 'nested',
    ]);
    render_admin_confirm_modal_component([
        'context' => 'nested',
    ]);
    ?>
</body>

</html>
