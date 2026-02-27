<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/Controllers/NewsController.php';
require_once dirname(__DIR__, 2) . '/Controllers/UserController.php';
require_once dirname(__DIR__) . '/partials/app-shell.php';
require_once dirname(__DIR__) . '/components/modals/admin-confirm-modal.php';
require_once dirname(__DIR__, 2) . '/helpers/flash_modal.php';

// Ambil ID berita dari route token
$id = (int) app_route_data('id_news', 0);
if ($id > 0) {
    $newsController = new NewsController($connect);
    $news = $newsController->getNewsById($id);

    if (!$news) {
        die("Berita tidak ditemukan!");
    }

    // Ambil nama penulis
    if (isset($_SESSION['username'])) {
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

    // Jika form disubmit
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $judul = $_POST['judul'] ?? '';
        $konten = $_POST['konten'] ?? '';
        $gambar = $_FILES['gambar'] ?? null;

        // Validasi input
        if (empty($judul) || empty($konten)) {
            set_app_flash_modal('error', 'Judul dan konten tidak boleh kosong.');
            app_redirect_page('page.admin_news');
        }

        $postNewsId = app_id_resolve((string) ($_POST['news_id'] ?? ''), 'news');
        if ($postNewsId === null || $postNewsId !== (int) $id) {
            set_app_flash_modal('error', 'Token berita tidak valid.');
            app_redirect_page('page.admin_news');
        }

        // Proses unggah gambar baru
        if (isset($gambar) && $gambar['error'] === UPLOAD_ERR_OK) {
            $uploadDir = app_path('document/news');
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                set_app_flash_modal('error', 'Direktori upload gambar tidak tersedia.');
                app_redirect_page('page.admin_news');
            }

            $sanitizedName = (string) preg_replace('/[^a-zA-Z0-9._-]/', '_', basename((string) $gambar['name']));
            $sanitizedName = trim($sanitizedName, '._');
            if ($sanitizedName === '') {
                $sanitizedName = 'news_image';
            }

            $fileName = time() . '_' . $sanitizedName;
            $uploadFile = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

            // Pindahkan file ke folder uploads
            if (move_uploaded_file($gambar['tmp_name'], $uploadFile)) {
                $gambarPath = 'document/news/' . $fileName;
            } else {
                set_app_flash_modal('error', 'Gagal mengunggah gambar.');
                app_redirect_page('page.admin_news');
            }
        } else {
            // Gunakan gambar lama jika tidak ada file baru
            $gambarPath = $news['gambar'];
        }

        // Update data berita
        $result = $newsController->update($id, $judul, $konten, $gambarPath);

        if ($result['status'] === 'success') {
            set_app_flash_modal('success', $result['message'] ?? 'Berita berhasil diperbarui.');
            app_redirect_page('page.admin_news');
        } else {
            set_app_flash_modal('error', $result['message'] ?? 'Gagal memperbarui berita.');
            app_redirect_page('page.admin_news');
        }
    }
} else {
    http_response_code(404);
    die('Berita tidak ditemukan!');
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
            'loginHref' => app_page_url('page.login'),
            'roleLabel' => 'Admin',
        ]);
        ?>
        <section class="admin-news-form-page">
            <div class="admin-news-form-header">
                <h1>Edit Berita</h1>
                <p>Perbarui konten berita agar informasi kedisiplinan selalu relevan dan terbaru.</p>
            </div>

            <div class="admin-news-form-layout">
                <aside class="admin-news-form-info">
                    <h3>Petunjuk Edit</h3>
                    <ol>
                        <li>Periksa kembali judul agar tetap ringkas.</li>
                        <li>Perbarui isi konten jika ada perubahan kebijakan.</li>
                        <li>Unggah gambar baru bila diperlukan.</li>
                    </ol>
                </aside>

                <form id="editBeritaForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" id="editNewsId" name="news_id"
                        value="<?= htmlspecialchars(app_id_token('news', (int) $id), ENT_QUOTES, 'UTF-8') ?>" required>

                    <label for="editPenulis">Penulis:</label>
                    <input type="text" id="editPenulis" name="penulis" value="<?= htmlspecialchars($penulis_nama) ?>"
                        required readonly>

                    <label for="editJudul">Judul:</label>
                    <input type="text" id="editJudul" name="judul" value="<?= htmlspecialchars($news['judul']) ?>"
                        required>

                    <label for="editKonten">Konten:</label>
                    <div class="news-rich-editor" data-rich-editor-wrapper>
                        <div class="news-rich-toolbar" role="toolbar" aria-label="Toolbar format konten berita">
                            <label class="news-rich-size-label" for="editKontenFontSize">Ukuran</label>
                            <select id="editKontenFontSize" class="news-rich-size" data-editor-font-size
                                aria-label="Ukuran font konten">
                                <option value="small">Kecil</option>
                                <option value="normal" selected>Normal</option>
                                <option value="large">Besar</option>
                            </select>
                            <button type="button" class="news-rich-btn" data-editor-command="formatBlock"
                                data-editor-value="P" aria-label="Paragraf">Paragraf</button>
                            <button type="button" class="news-rich-btn" data-editor-command="formatBlock"
                                data-editor-value="H3" aria-label="Subjudul">Subjudul</button>
                            <button type="button" class="news-rich-btn" data-editor-command="bold" aria-label="Tebal"><i
                                    class="fa-solid fa-bold" aria-hidden="true"></i></button>
                            <button type="button" class="news-rich-btn" data-editor-command="italic"
                                aria-label="Miring"><i class="fa-solid fa-italic" aria-hidden="true"></i></button>
                            <button type="button" class="news-rich-btn" data-editor-command="insertUnorderedList"
                                aria-label="Daftar poin"><i class="fa-solid fa-list-ul" aria-hidden="true"></i></button>
                            <button type="button" class="news-rich-btn" data-editor-command="insertOrderedList"
                                aria-label="Daftar bernomor"><i class="fa-solid fa-list-ol"
                                    aria-hidden="true"></i></button>
                            <button type="button" class="news-rich-btn" data-editor-command="removeFormat"
                                aria-label="Hapus format"><i class="fa-solid fa-eraser" aria-hidden="true"></i></button>
                        </div>
                        <div class="news-rich-surface" contenteditable="true" data-rich-editor
                            placeholder="Perbarui isi berita formal di sini..." aria-label="Editor konten berita"></div>
                    </div>
                    <textarea id="editKonten" name="konten" rows="4"
                        class="news-rich-source"><?= htmlspecialchars($news['konten']) ?></textarea>
                    <p class="news-rich-hint">Pastikan struktur tulisan rapi: subjudul, paragraf, dan daftar poin/nomor.
                    </p>

                    <label for="editGambar">Unggah Gambar:</label>
                    <input type="file" id="editGambar" name="gambar" accept="image/*">

                    <div class="form-actions">
                        <button type="button" class="cancel-button" name="cancel" data-admin-confirm-trigger
                            data-admin-confirm-title="Batalkan perubahan?"
                            data-admin-confirm-message="Perubahan yang belum disimpan akan hilang. Yakin ingin kembali?"
                            data-admin-confirm-label="Ya, Batalkan" data-admin-confirm-action="navigate"
                            data-admin-confirm-target="<?= htmlspecialchars(app_page_url('page.admin_news'), ENT_QUOTES, 'UTF-8') ?>">Batal</button>
                        <button type="submit" class="save-button">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </section>
        <?php
        render_app_footer([
            'context' => 'nested',
        ]);
        render_admin_confirm_modal_component([
            'context' => 'nested',
        ]);
        ?>
    </div>
    <script defer
        src="<?= htmlspecialchars(app_seo_script_src('js/news-form-editor.js', '../..'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>

</html>