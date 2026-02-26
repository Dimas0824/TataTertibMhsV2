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

// Ambil data user dari session
$userData = $_SESSION['user_data'];

$newsController = new NewsController();
$id_admin = $userData['id_admin'];
$newsData = $newsController->AdminNews($id_admin);

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
    <link rel="stylesheet" href="../../css/global.css">
    <link rel="stylesheet" href="../../css/news-form.css">
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
            'title' => 'Tambah Berita',
            'showLogin' => false,
            'loginHref' => '../auth/login.php',
            'roleLabel' => 'Admin',
        ]);
        ?>
        <div class="judul">
            <h1>Tambah Berita</h1>
        </div>
        <form id="insertBeritaForm" method="POST" action="../../Request/Handler_News.php" enctype="multipart/form-data">
            <label for="insertPenulisNama">Penulis:</label>
            <input type="text" id="insertPenulisNama" name="penulis_nama"
                value="<?= htmlspecialchars($userData['nama_admin']) ?>" required readonly>
            <input type="hidden" id="insertPenulis" name="penulis"
                value="<?= htmlspecialchars($userData['id_admin']) ?>" required>

            <label for="insertJudul">Judul:</label>
            <input type="text" id="insertJudul" name="judul" required>

            <label for="insertKonten">Konten:</label>
            <textarea id="insertKonten" name="konten" rows="4" required></textarea>

            <label for="insertGambar">Unggah Gambar:</label>
            <input type="file" id="insertGambar" name="gambar" accept="image/*">

            <button type="submit" class="save-button" name="store">Simpan</button>
        </form>
        <?php
        render_app_footer([
            'context' => 'nested',
        ]);
        ?>
    </div>
</body>

</html>
