<?php
session_start();
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/Controllers/NewsController.php';
require_once dirname(__DIR__, 2) . '/Controllers/UserController.php';
require_once dirname(__DIR__) . '/partials/app-shell.php';

if (isset($_SESSION['username'])) {
    // Redirect based on role
    if ($_SESSION['user_type'] === 'mahasiswa') {
        header("Location: ../pelanggaran/pelanggaranpage.php");
        exit();
    } elseif ($_SESSION['user_type'] === 'dosen') {
        header("Location: ../pelanggaran/pelanggaran_dosen.php");
        exit();
    }
} else {
    header("Location: ../auth/login.php");
    exit();
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
        'canonical_path' => '/views/admin/news-admin.php',
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
            'loginHref' => '../auth/login.php',
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
                <a href="tambah-berita.php" class="add-button" id="addButton">+ Tambah Berita</a>
            </div>

            <section class="admin-table-card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Judul</th>
                                <th>Gambar</th>
                                <th>Konten</th>
                                <th>Penulis</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($newsData): ?>
                                <?php foreach ($newsData as $news): ?>
                                    <tr>
                                        <td class="news-title-cell"><?= htmlspecialchars($news['judul']) ?></td>
                                        <td>
                                            <?php if (!empty($news['gambar'])): ?>
                                                <img class="news-thumb" src="../../<?= htmlspecialchars($news['gambar']) ?>"
                                                    alt="Gambar News" width="160" height="90" loading="lazy" decoding="async">
                                            <?php else: ?>
                                                <span class="muted-text">Tidak ada gambar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="news-content-cell"><?= nl2br(htmlspecialchars($news['konten'])) ?></td>
                                        <td><?= htmlspecialchars($penulis_nama) ?></td>
                                        <td class="button-cell">
                                            <a href="edit-berita.php?id=<?= $news['id_news'] ?>" class="edit-button"
                                                aria-label="Edit berita <?= htmlspecialchars($news['judul']) ?>">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <form action="../../Request/Handler_News.php" method="post">
                                                <input type="hidden" name="news_id" value="<?= $news['id_news'] ?>">
                                                <button class="delete" id="delete" name="delete"
                                                    onclick="return confirm('Apakah anda yakin ingin menghapus?');"
                                                    aria-label="Hapus berita <?= htmlspecialchars($news['judul']) ?>"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-cell">Belum ada berita untuk ditampilkan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>

        <!-- Modal Edit Berita -->
        <!-- <div id="editBeritaModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Edit Berita</h2>
        <form id="editBeritaForm" method="POST" action="../../Request/Handler_News.php">
            <input type="hidden" id="editNewsId" name="news_id" required>
            
            <label for="editPenulis">Penulis:</label>
            <input type="text" id="editPenulis" name="penulis" value="<?= htmlspecialchars($penulis_nama) ?>" required readonly>
            
            <label for="editJudul">Judul:</label>
            <input type="text" id="editJudul" name="judul" required>
            
            <label for="editKonten">Konten:</label>
            <textarea id="editKonten" name="konten" rows="4" required></textarea>
            
            <label for="editGambar">Unggah Gambar:</label>
            <input type="file" id="editGambar" name="gambar" accept="image/*">
            <button type="submit" class="save-button">Simpan</button>
        </form>
    </div>
</div> -->

        <!-- Modal Tambah Berita -->
        <!-- <div id="insertBeritaModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Tambah Berita</h2>
        <form id="insertBeritaForm" method="POST" action="../../Request/Handler_News.php" enctype="multipart/form-data">
            <label for="insertPenulis">Penulis:</label>
            <input type="text" id="insertPenulis" name="penulis" value="<?= htmlspecialchars($penulis_nama) ?>" required readonly>
            
            <label for="insertJudul">Judul:</label>
            <input type="text" id="insertJudul" name="judul" required>
            
            <label for="insertKonten">Konten:</label>
            <textarea id="insertKonten" name="konten" rows="4" required></textarea>

            <label for="insertGambar">Unggah Gambar:</label>
            <input type="file" id="insertGambar" name="gambar" accept="image/*">

            <button type="submit" class="save-button" name="store">Simpan</button>
        </form>
    </div>
</div> -->

        <!-- javascript -->
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
    ?>
</body>

</html>