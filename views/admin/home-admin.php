<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/controllers/UserController.php';
require_once dirname(__DIR__, 2) . '/controllers/NewsController.php';
require_once dirname(__DIR__) . '/partials/app-shell.php';

if (isset($_SESSION['username'])) {
    // Redirect based on role
    if ($_SESSION['user_type'] === 'mahasiswa') {
        app_redirect_page('page.pelanggaran');
    } else if ($_SESSION['user_type'] === 'dosen') {
        app_redirect_page('page.pelanggaran_dosen');
    }
}
if (!isset($_SESSION['username'])) {
    app_redirect_page('page.login');
}

if (isset($_GET['logout'])) {
    $userController = new UserController();
    $userController->logout();
    exit();
}
$newsController = new NewsController();
$newsData = $newsController->ReadNews();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin DiscipLink</title>
    <?php
    app_seo_meta_tags([
        'title' => 'Dashboard Admin DiscipLink',
        'description' => 'Dashboard admin DiscipLink untuk mengelola informasi tata tertib mahasiswa, berita, dan kedisiplinan kampus.',
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
    <link rel="stylesheet" href="../../css/homepage.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
</head>

<body>
    <?php
    render_app_sidebar([
        'variant' => 'admin',
        'context' => 'nested',
        'active' => 'home',
    ]);
    ?>
    <div class="content">
        <?php
        render_app_header([
            'title' => 'Home Admin',
            'showLogin' => false,
            'loginHref' => app_page_url('page.login'),
            'roleLabel' => 'Admin',
        ]);
        ?>

        <section class="judul">
            <div class="hero-inner">
                <span class="hero-kicker">DiscipLink Admin Panel</span>
                <h2>Dashboard Tata Tertib Mahasiswa</h2>
                <p>Pusat kontrol admin untuk memantau informasi kedisiplinan, mengelola konten berita, dan menjaga alur
                    informasi tetap konsisten.</p>
                <div class="hero-actions">
                    <a href="<?= htmlspecialchars(app_page_url('page.admin_news'), ENT_QUOTES, 'UTF-8') ?>"
                        class="hero-btn hero-btn-primary">Kelola Berita</a>
                    <a href="<?= htmlspecialchars(app_page_url('page.admin_news_tambah'), ENT_QUOTES, 'UTF-8') ?>"
                        class="hero-btn hero-btn-secondary">Tambah Berita</a>
                </div>
            </div>

            <div class="hero-panel">
                <h3>Ringkas Hari Ini</h3>
                <p>Gunakan akses cepat untuk memperbarui informasi dan menjaga komunikasi dengan pengguna.</p>
                <div class="hero-stats">
                    <article class="hero-stat">
                        <span class="hero-stat-label">Total News</span>
                        <strong><?= count($newsData) ?></strong>
                    </article>
                    <article class="hero-stat">
                        <span class="hero-stat-label">Peran</span>
                        <strong>Admin</strong>
                    </article>
                    <article class="hero-stat">
                        <span class="hero-stat-label">Status</span>
                        <strong>Aktif</strong>
                    </article>
                </div>
            </div>
        </section>

        <section class="dashboard-container">
            <div class="about-logo-wrap">
                <div class="about-brand-card">
                    <img class="logo-disciplink" src="../../img/logo-full.png" width="250" height="250" loading="lazy"
                        decoding="async" alt="Logo DiscipLink">
                    <p>Panel admin DiscipLink dirancang untuk mempercepat pengelolaan konten dan menjaga akurasi
                        informasi kedisiplinan.</p>
                </div>
            </div>
            <div class="about-copy">
                <h3>Kontrol Informasi Kedisiplinan Secara Terpusat</h3>
                <p>Melalui dashboard ini, admin dapat mengelola berita kedisiplinan, memastikan informasi terbaru
                    tersampaikan, dan mendukung transparansi proses pembinaan mahasiswa. Struktur antarmuka dibuat
                    sederhana agar proses operasional harian lebih efisien.</p>
            </div>
        </section>

        <section class="news">
            <div class="news-header">
                <h2>News Terbaru</h2>
                <p>Pratinjau berita yang sudah dipublikasikan untuk memudahkan monitoring konten.</p>
            </div>
            <?php if (empty($newsData)): ?>
                <div class="news-empty">
                    <i class="fa-regular fa-newspaper" aria-hidden="true"></i>
                    <p>Belum ada news yang tersedia.</p>
                </div>
            <?php else: ?>
                <div class="news-grid">
                    <?php foreach ($newsData as $news): ?>
                        <article class="news-content">
                            <?php if (!empty($news['gambar'])): ?>
                                <img src="../../<?= htmlspecialchars($news['gambar']) ?>" alt="Gambar News" width="1200"
                                    height="675" loading="lazy" decoding="async">
                            <?php else: ?>
                                <img src="../../img/news.jpg" alt="Gambar News" width="1200" height="675" loading="lazy"
                                    decoding="async">
                            <?php endif; ?>
                            <div class="news-text">
                                <h3><?= htmlspecialchars($news['judul']) ?></h3>
                                <h5><?= htmlspecialchars($news['penulis_nama']) ?></h5>
                                <p><?= nl2br(htmlspecialchars($news['konten'])) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
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
    ?>
</body>

</html>