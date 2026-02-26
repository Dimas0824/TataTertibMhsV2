<?php
session_start();
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/Controllers/NewsController.php';
require_once dirname(__DIR__, 2) . '/Controllers/UserController.php';
require_once dirname(__DIR__) . '/partials/app-shell.php';
require_once dirname(__DIR__, 2) . '/helpers/flash_modal.php';

// Ambil ID berita dari parameter URL
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $newsController = new NewsController($connect);
    $news = $newsController->getNewsById($id);

    if (!$news) {
        die("Berita tidak ditemukan!");
    }

    // Ambil nama penulis
    if (isset($_SESSION['username'])) {
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

    // Jika form disubmit
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $judul = $_POST['judul'] ?? '';
        $konten = $_POST['konten'] ?? '';
        $gambar = $_FILES['gambar'] ?? null;

        // Validasi input
        if (empty($judul) || empty($konten)) {
            set_app_flash_modal('error', 'Judul dan konten tidak boleh kosong.');
            header("Location: news-admin.php");
            exit();
        }

        // Proses unggah gambar baru
        if (isset($gambar) && $gambar['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../document/news/'; // Folder penyimpanan gambar
            $fileName = time() . '_' . basename($gambar['name']); // Nama unik gambar
            $uploadFile = $uploadDir . $fileName;

            // Pindahkan file ke folder uploads
            if (move_uploaded_file($gambar['tmp_name'], $uploadFile)) {
                $gambarPath = 'document/news/' . $fileName;
            } else {
                set_app_flash_modal('error', 'Gagal mengunggah gambar.');
                header("Location: news-admin.php");
                exit();
            }
        } else {
            // Gunakan gambar lama jika tidak ada file baru
            $gambarPath = $news['gambar'];
        }

        // Update data berita
        $result = $newsController->update($id, $judul, $konten, $gambarPath);

        if ($result['status'] === 'success') {
            set_app_flash_modal('success', $result['message'] ?? 'Berita berhasil diperbarui.');
            header("Location: news-admin.php");
            exit();
        } else {
            set_app_flash_modal('error', $result['message'] ?? 'Gagal memperbarui berita.');
            header("Location: news-admin.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Berita</title>
    <?php
    app_seo_meta_tags([
        'title' => 'Edit Berita | Admin DiscipLink',
        'description' => 'Halaman admin DiscipLink untuk memperbarui berita kedisiplinan kampus.',
        'canonical_path' => '/views/admin/edit-berita.php',
        'image' => 'img/GRAHA-POLINEMA1-slider-01.webp',
        'robots' => 'noindex, nofollow',
    ]);
    ?>
    <link rel="icon" type="image/png" href="../../img/logo aja.png">
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
            'title' => 'Edit Berita',
            'showLogin' => false,
            'loginHref' => '../auth/login.php',
            'roleLabel' => 'Admin',
        ]);
        ?>
        <div class="judul">
            <h1>Edit Berita</h1>
        </div>
        <form id="editBeritaForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" id="editNewsId" name="news_id" value="<?= htmlspecialchars($id) ?>" required>

            <label for="editPenulis">Penulis:</label>
            <input type="text" id="editPenulis" name="penulis" value="<?= htmlspecialchars($penulis_nama) ?>" required
                readonly>

            <label for="editJudul">Judul:</label>
            <input type="text" id="editJudul" name="judul" value="<?= htmlspecialchars($news['judul']) ?>" required>

            <label for="editKonten">Konten:</label>
            <textarea id="editKonten" name="konten" rows="4"
                required><?= htmlspecialchars($news['konten']) ?></textarea>

            <label for="editGambar">Unggah Gambar:</label>
            <input type="file" id="editGambar" name="gambar" accept="image/*">

            <button type="submit" class="save-button">Simpan</button>
            <button class="cancel-button" name="cancel" onclick="window.location.href='news-admin.php'">Cancel</button>
        </form>
        <?php
        render_app_footer([
            'context' => 'nested',
        ]);
        ?>
    </div>
</body>

</html>
