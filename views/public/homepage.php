<?php
require_once dirname(__DIR__) . '/partials/app-shell.php';

$homeVariant = isset($_SESSION['username']) ? 'student' : 'guest';
$homeRoleLabel = null;
if (isset($_SESSION['user_type'])) {
    $homeRoleLabel = $_SESSION['user_type'] === 'dosen'
        ? 'Dosen'
        : ($_SESSION['user_type'] === 'admin' ? 'Admin' : 'Mahasiswa');
}

$newsExcerpt = static function (string $content, int $limit = 160): string {
    $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($content)));
    if ($plain === '') {
        return '';
    }

    $slice = function_exists('mb_substr') ? mb_substr($plain, 0, $limit) : substr($plain, 0, $limit);
    $fullLength = function_exists('mb_strlen') ? mb_strlen($plain) : strlen($plain);
    if ($fullLength > $limit) {
        return rtrim($slice) . '...';
    }

    return $slice;
};

$newsDetailUrl = static function (array $news): string {
    $newsId = (int) ($news['id_news'] ?? 0);
    $title = (string) ($news['judul'] ?? 'berita');
    $slug = NewsController::news_build_slug($title, $newsId);
    return app_page_url('page.news_detail', ['slug' => $slug]);
};

render_app_sidebar([
    'variant' => $homeVariant,
    'context' => 'root',
    'active' => 'home',
]);
?>

<div class="content">
    <?php
    render_app_header([
        'title' => 'Tata Tertib Mahasiswa Polinema (DiscipLink)',
        'showLogin' => !isset($_SESSION['username']),
        'loginHref' => app_page_url('page.login'),
        'roleLabel' => $homeRoleLabel,
    ]);
    ?>

    <section class="judul">
        <div class="hero-inner reveal-up">
            <span class="hero-kicker">DiscipLink · Sistem Informasi Tata Tertib</span>
            <h2>Tata Tertib Mahasiswa</h2>
            <p>Satu pusat informasi untuk aturan, pelanggaran, dan sanksi di lingkungan Politeknik Negeri Malang.</p>
            <div class="hero-actions">
                <a href="<?= htmlspecialchars(app_page_url('page.tatib'), ENT_QUOTES, 'UTF-8') ?>"
                    class="hero-btn hero-btn-primary">Lihat Tata Tertib</a>
                <a href="<?= htmlspecialchars(app_page_url('page.pelanggaran'), ENT_QUOTES, 'UTF-8') ?>"
                    class="hero-btn hero-btn-secondary">Lihat
                    Pelanggaran</a>
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
                <img class="logo-disciplink" src="img/logo-full.png" alt="Logo DiscipLink" width="250" height="250"
                    loading="lazy" decoding="async" srcset="img/logo-full.png 250w"
                    sizes="(max-width: 992px) 220px, 250px">
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

    <section class="news info-overview reveal-up" data-delay="140">
        <div class="news-header info-overview-header">
            <h2>Aturan Kampus, Pelanggaran, dan Sanksi Mahasiswa</h2>
            <p>DiscipLink membantu mahasiswa memahami tata tertib kampus Polinema secara bertahap, mulai dari membaca
                aturan,
                memahami tingkat pelanggaran, hingga melihat konsekuensi sanksi yang berlaku.</p>
        </div>

        <div class="info-overview-grid">
            <article class="info-card info-card-flow">
                <h3>Alur Pahami Tata Tertib</h3>
                <p>Ikuti langkah singkat berikut agar aktivitas akademik tetap aman dan terarah.</p>
                <ul class="info-steps" aria-label="Langkah memahami tata tertib kampus">
                    <li>
                        <span>1</span>
                        <div>
                            <strong>Baca Aturan</strong>
                            <p>Pelajari ketentuan resmi agar memahami batasan perilaku akademik dan administratif.</p>
                        </div>
                    </li>
                    <li>
                        <span>2</span>
                        <div>
                            <strong>Pahami Pelanggaran</strong>
                            <p>Kenali kategori pelanggaran dan dampak poin untuk mencegah kesalahan berulang.</p>
                        </div>
                    </li>
                    <li>
                        <span>3</span>
                        <div>
                            <strong>Cek Sanksi & Pelaporan</strong>
                            <p>Lihat konsekuensi serta alur pelaporan agar koordinasi mahasiswa dan dosen lebih jelas.
                            </p>
                        </div>
                    </li>
                </ul>
            </article>

            <article class="info-card info-card-links">
                <h3>Akses Cepat</h3>
                <p>Masuk ke halaman penting DiscipLink dalam satu klik.</p>
                <div class="info-links" aria-label="Tautan cepat DiscipLink">
                    <a href="<?= htmlspecialchars(app_page_url('page.tatib'), ENT_QUOTES, 'UTF-8') ?>"
                        title="Daftar tata tertib mahasiswa Polinema">Lihat daftar tata
                        tertib</a>
                    <a href="<?= htmlspecialchars(app_page_url('page.pelanggaran'), ENT_QUOTES, 'UTF-8') ?>"
                        title="Halaman pelanggaran mahasiswa di DiscipLink">Cek data pelanggaran</a>
                    <a href="<?= htmlspecialchars(app_page_url('page.notifikasi'), ENT_QUOTES, 'UTF-8') ?>"
                        title="Notifikasi pelanggaran DiscipLink">Lihat
                        notifikasi</a>
                    <a href="<?= htmlspecialchars(app_page_url('page.login'), ENT_QUOTES, 'UTF-8') ?>"
                        title="Login DiscipLink untuk mahasiswa dan dosen">Masuk ke akun
                        DiscipLink</a>
                    <a href="https://www.polinema.ac.id" target="_blank" rel="noopener noreferrer"
                        title="Website resmi Politeknik Negeri Malang">Website resmi Polinema</a>
                </div>
            </article>
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
                <?php foreach ($newsData as $index => $news): ?>
                    <?php
                    $detailUrl = $newsDetailUrl($news);
                    $summary = $newsExcerpt((string) ($news['konten'] ?? ''));
                    $publishedTimestamp = strtotime((string) ($news['published_at'] ?? ''));
                    $publishedLabel = $publishedTimestamp !== false ? date('d M Y', $publishedTimestamp) : 'Tanggal tidak tersedia';
                    ?>
                    <article class="news-content reveal-up" data-delay="220">
                        <?php if (!empty($news['gambar'])): ?>
                            <img src="<?= htmlspecialchars($news['gambar']) ?>"
                                alt="Gambar berita: <?= htmlspecialchars($news['judul']) ?>" width="1200" height="675"
                                loading="<?= $index === 0 ? 'eager' : 'lazy' ?>" decoding="async"
                                srcset="<?= htmlspecialchars($news['gambar']) ?> 1200w"
                                fetchpriority="<?= $index === 0 ? 'high' : 'low' ?>"
                                sizes="(max-width: 768px) 100vw, (max-width: 992px) 48vw, <?= $index === 0 ? '100vw' : '32vw' ?>">
                        <?php else: ?>
                            <img src="img/news.jpg" alt="Gambar berita DiscipLink" width="1200" height="675"
                                loading="<?= $index === 0 ? 'eager' : 'lazy' ?>" decoding="async" srcset="img/news.jpg 1200w"
                                fetchpriority="<?= $index === 0 ? 'high' : 'low' ?>"
                                sizes="(max-width: 768px) 100vw, (max-width: 992px) 48vw, <?= $index === 0 ? '100vw' : '32vw' ?>">
                        <?php endif; ?>
                        <div class="news-text">
                            <h3>
                                <a class="news-title-link" href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($news['judul']) ?>
                                </a>
                            </h3>
                            <h5><?= htmlspecialchars($news['penulis_nama']) ?></h5>
                            <span class="news-date"><?= htmlspecialchars($publishedLabel) ?></span>
                            <p><?= htmlspecialchars($summary) ?></p>
                            <a class="news-read-more" href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>">
                                Baca Selengkapnya <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
    if (!empty($newsData)) {
        $firstNews = $newsData[0];
        $firstNewsContent = trim(strip_tags((string) ($firstNews['konten'] ?? '')));
        $articleDescription = function_exists('mb_substr')
            ? mb_substr($firstNewsContent, 0, 240)
            : substr($firstNewsContent, 0, 240);
        app_seo_json_ld_tags([
            'emit_defaults' => false,
            'canonical_path' => '/',
            'article' => [
                'headline' => (string) ($firstNews['judul'] ?? ''),
                'description' => $articleDescription,
                'author' => (string) ($firstNews['penulis_nama'] ?? 'Admin DiscipLink'),
                'image' => !empty($firstNews['gambar']) ? (string) $firstNews['gambar'] : 'img/news.jpg',
                'datePublished' => !empty($firstNews['published_at']) ? (string) $firstNews['published_at'] : date('c'),
                'dateModified' => !empty($firstNews['published_at']) ? (string) $firstNews['published_at'] : date('c'),
            ],
        ]);
    }

    render_app_footer([
        'context' => 'root',
    ]);
    ?>

</div>