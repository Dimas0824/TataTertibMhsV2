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

        <div class="hero-panel reveal-up" data-delay="100">
            <h3>Ringkas Hari Ini</h3>
            <p>Semua informasi utama tersedia dalam satu halaman agar lebih cepat dipahami.</p>
            <div class="hero-stats">
                <article class="hero-stat">
                    <span class="hero-stat-label">Total News</span>
                    <strong><?= count($newsData) ?></strong>
                </article>
                <article class="hero-stat">
                    <span class="hero-stat-label">Akses</span>
                    <strong>24/7</strong>
                </article>
                <article class="hero-stat">
                    <span class="hero-stat-label">Status</span>
                    <strong>Aktif</strong>
                </article>
            </div>
        </div>
    </section>

    <section class="dashboard-container reveal-up" data-delay="120">
        <div class="about-logo-wrap">
            <div class="about-brand-card">
                <img class="logo-disciplink" src="img/ga logo aja.png" alt="Logo DiscipLink">
            </div>
        </div>
        <div class="about-copy">
            <h3>Akses Informasi Kedisiplinan Lebih Jelas</h3>
            <p>DiscipLink adalah platform digital inovatif yang dirancang untuk menghubungkan mahasiswa dengan
                sistem kedisiplinan kampus. Sebagai gabungan dari kata "Discipline" dan "Link", DiscipLink berfokus
                pada penyederhanaan proses pengelolaan tata tertib di lingkungan akademik, memudahkan mahasiswa dan
                pihak kampus untuk memahami, memantau, dan menegakkan aturan secara efisien.</p>
        </div>
    </section>

    <section class="news reveal-up" data-delay="160">
        <div class="news-header reveal-up" data-delay="160">
            <h2>News</h2>
            <p>Informasi terbaru seputar pengumuman dan pembaruan aturan kampus.</p>
        </div>
        <?php if (empty($newsData)): ?>
            <div class="news-empty reveal-up" data-delay="220">
                <i class="fa-regular fa-newspaper" aria-hidden="true"></i>
                <p>Belum ada news terbaru saat ini.</p>
            </div>
        <?php else: ?>
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
        <?php endif; ?>
    </section>
    <?php
    render_app_footer([
        'context' => 'root',
    ]);
    ?>

</div>