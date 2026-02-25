<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once "../Controllers/NewsController.php";
require_once '../Controllers/UserController.php';
require_once __DIR__ . '/partials/app-shell.php';

if (isset($_SESSION['username'])) {
    // Redirect based on role
    if ($_SESSION['user_type'] === 'mahasiswa') {
        header("Location: pelanggaranpage.php");
        exit();
    } elseif ($_SESSION['user_type'] === 'dosen') {
        header("Location: pelanggaran_dosen.php");
        exit();
    }
} else {
    header("Location: login.php");
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
    <title>Home Page</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Italiana&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/news-admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
</head>

<body>
    <?php
    render_app_sidebar([
        'variant' => 'admin',
        'context' => 'views',
        'active' => 'news',
    ]);
    ?>
    <div class="content">
        <?php
        render_app_header([
            'title' => 'News Admin',
            'showLogin' => false,
            'loginHref' => 'login.php',
            'roleLabel' => 'Admin',
        ]);
        ?>
        <div class="judul">
            <h1>DISCIPLINK NEWS</h1>
        </div>

        <a href="tambah-berita.php">
            <button class="add-button" id="addButton">Tambah</button>
        </a>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Judul</th>
                        <th>gambar</th>
                        <th>Konten</th>
                        <th>Penulis</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($newsData): ?>
                        <?php foreach ($newsData as $news): ?>
                            <tr>
                                <td><?= htmlspecialchars($news['judul']) ?></td>
                                <td>
                                    <?php if (!empty($news['gambar'])): ?>
                                        <img src="..//<?= htmlspecialchars($news['gambar']) ?>" alt="Gambar News"
                                            style="max-width: 100px;">
                                    <?php else: ?>
                                        <p>Tidak ada gambar</p>
                                    <?php endif; ?>
                                </td>
                                <td><?= nl2br(htmlspecialchars($news['konten'])) ?></td>
                                <td><?= htmlspecialchars($penulis_nama) ?></td>
                                <td class="button-cell">
                                    <a href="edit-berita.php?id=<?= $news['id_news'] ?>" class="edit-button">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <!--tombol delete -->
                                    <form action="../Request/Handler_News.php" method="post">
                                        <input type="hidden" name="news_id" value="<?= $news['id_news'] ?>">
                                        <button class="delete" id="delete" name="delete"
                                            onclick="return confirm('Apakah anda yakin ingin menghapus?');"><i
                                                class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Modal Edit Berita -->
        <!-- <div id="editBeritaModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Edit Berita</h2>
        <form id="editBeritaForm" method="POST" action="../Request/Handler_News.php">
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
        <form id="insertBeritaForm" method="POST" action="../Request/Handler_News.php" enctype="multipart/form-data">
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
        <script src="../js/script-news.js"></script>
    </div>
</body>
<footer class="footer">
    <div class="footer-left">
        <img class="footer-logo" src="../img/logo aja.png" alt="Logo">
        <img class="footer-logo" src="../img/logo.png" alt="logo polinema">
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
</footer>

</html>
