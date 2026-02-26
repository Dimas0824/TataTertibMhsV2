<?php
session_start();

require_once dirname(__DIR__, 2) . '/Controllers/UserController.php';
require_once dirname(__DIR__, 2) . '/Controllers/NewsController.php';
require_once dirname(__DIR__) . '/partials/app-shell.php';

if (isset($_SESSION['username'])) {
    // Redirect based on role
    if ($_SESSION['user_type'] === 'mahasiswa') {
        header("Location: ../pelanggaran/pelanggaranpage.php");
        exit();
    } else if ($_SESSION['user_type'] === 'dosen') {
        header("Location: ../pelanggaran/pelanggaran_dosen.php");
        exit();
    }
}
if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
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
        'canonical_path' => '/views/admin/home-admin.php',
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
            'loginHref' => '../auth/login.php',
            'roleLabel' => 'Admin',
        ]);
        ?>

        <div class="judul">
            <h2>TATA TERTIB <br>MAHASISWA </h2>
            <p>Sebuah sistem yang dirancang untuk mengelola aturan, <br>pelanggaran, dan sanksi di Universitas</p>
        </div>
        <div class="dashboard-container">
            <img class="logo-disciplink" src="../../img/ga logo aja.png" width="250" height="250" loading="lazy"
                decoding="async" alt="Logo DiscipLink">
            <p>Disciplink adalah platform digital inovatif yang dirancang untuk menghubungkan mahasiswa dengan
                sistem kedisiplinan kampus. Sebagai gabungan dari kata "Discipline" dan "Link", Disciplink berfokus
                pada penyederhanaan proses pengelolaan tata tertib di lingkungan akademik, memudahkan mahasiswa dan
                pihak kampus untuk memahami, memantau, dan menegakkan aturan secara efisien.</p>
        </div>
        <div class="news">
            <h2>News</h2>
            <div class="row">
                <?php foreach ($newsData as $news): ?>
                    <div class="news-content">
                        <?php if (!empty($news['gambar'])): ?>
                            <img src="../../<?= htmlspecialchars($news['gambar']) ?>" alt="Gambar News" width="1200"
                                height="675" loading="lazy" decoding="async">
                        <?php else: ?>
                            <img src="../../img/news.jpg" alt="Gambar News" width="1200" height="675" loading="lazy"
                                decoding="async">
                        <?php endif; ?>
                        <h3><?= htmlspecialchars($news['judul']) ?></h3>
                        <!-- ini nanti di ganti nama -->
                        <h5><?= htmlspecialchars($news['penulis_nama']) ?></h5>
                        <!-- ini -->
                        <p><?= nl2br(htmlspecialchars($news['konten'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
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