<?php
require_once __DIR__ . '/partials/app-shell.php';

$homeVariant = isset($_SESSION['username']) ? 'student' : 'guest';
$homeRoleLabel = null;
if (isset($_SESSION['user_type'])) {
    $homeRoleLabel = $_SESSION['user_type'] === 'dosen'
        ? 'Dosen'
        : ($_SESSION['user_type'] === 'admin' ? 'Admin' : 'Mahasiswa');
}

render_app_sidebar([
    'variant' => $homeVariant,
    'context' => 'root',
    'active' => 'home',
]);
?>

<div class="content">
    <?php
    render_app_header([
        'title' => 'Home',
        'showLogin' => !isset($_SESSION['username']),
        'loginHref' => 'views/login.php',
        'roleLabel' => $homeRoleLabel,
    ]);
    ?>

    <section class="judul">
        <div class="hero-inner reveal-up">
            <span class="hero-kicker">DiscipLink · Sistem Informasi Tata Tertib</span>
            <h2>Tata Tertib Mahasiswa</h2>
            <p>Satu pusat informasi untuk aturan, pelanggaran, dan sanksi di lingkungan Politeknik Negeri Malang.</p>
            <div class="hero-actions">
                <a href="views/listTatib.php" class="hero-btn hero-btn-primary">Lihat Tata Tertib</a>
                <a href="views/pelanggaranpage.php" class="hero-btn hero-btn-secondary">Lihat Pelanggaran</a>
            </div>
        </div>
    </section>

    <section class="dashboard-container reveal-up" data-delay="120">
        <div class="about-logo-wrap">
            <img class="logo-disciplink" src="img/ga logo aja.png" alt="Logo DiscipLink">
        </div>
        <div class="about-copy">
            <h3>Akses Informasi Kedisiplinan Lebih Jelas</h3>
            <p>DiscipLink adalah platform digital inovatif yang dirancang untuk menghubungkan mahasiswa dengan
                sistem kedisiplinan kampus. Sebagai gabungan dari kata "Discipline" dan "Link", DiscipLink berfokus
                pada penyederhanaan proses pengelolaan tata tertib di lingkungan akademik, memudahkan mahasiswa dan
                pihak kampus untuk memahami, memantau, dan menegakkan aturan secara efisien.</p>
        </div>
    </section>

    <section class="news">
        <div class="news-header reveal-up" data-delay="160">
            <h2>News</h2>
            <p>Informasi terbaru seputar pengumuman dan pembaruan aturan kampus.</p>
        </div>
        <div class="news-grid">
            <?php foreach ($newsData as $news): ?>
                <article class="news-content reveal-up" data-delay="220">
                    <?php if (!empty($news['gambar'])): ?>
                        <img src="<?= htmlspecialchars($news['gambar']) ?>" alt="Gambar News">
                    <?php else: ?>
                        <img src="img/news.jpg" alt="Gambar News">
                    <?php endif; ?>
                    <div class="news-text">
                        <h3><?= htmlspecialchars($news['judul']) ?></h3>
                        <h5><?= htmlspecialchars($news['penulis_nama']) ?></h5>
                        <p><?= nl2br(htmlspecialchars($news['konten'])) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>


    <div class="footer">
        <div class="footer-left">
            <img class="footer-logo" src="img/logo aja.png" alt="Logo">
            <img class="footer-logo" src="img/logo.png" alt="logo polinema">
        </div>
        <div class="footer-center">
            <p>Jl. Soekarno Hatta No.9, Jatimulyo, Kec. Lowokwaru, <br>Kota Malang, Jawa Timur 65141</p>
            <p><a href="tel:(0341)404424" class="footer-link">(0341) 404424</a></p>
        </div>
        <div class="footer-right">
            <a href="https://instagram.com" class="social-link"><i class="fa-brands fa-instagram" alt="Instagram"
                    class="social-icon"></i></a>
            <a href="https://wa.me/1234567890" class="social-link"><i class="fa-brands fa-whatsapp" alt="WhatsApp"
                    class="social-icon"></i></a>
            <a href="https://wa.me/1234567890" class="social-link"><i class="fa-solid fa-envelope" alt="Email"
                    class="social-icon"></i></a>
        </div>
        <div class="footer-bottom">
            <p>© Copyright 2024 web Tatib. All Rights Reserved.</p>
        </div>
    </div>

</div>
