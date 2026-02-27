<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/config.php';

require_once dirname(__DIR__, 2) . '/Controllers/NewsController.php';
require_once dirname(__DIR__, 2) . '/Controllers/UserController.php';
require_once dirname(__DIR__) . '/partials/app-shell.php';
require_once dirname(__DIR__) . '/components/modals/admin-confirm-modal.php';

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
    <title>Tambah Berita | Admin DiscipLink</title>
    <?php
    app_seo_meta_tags([
        'title' => 'Tambah Berita | Admin DiscipLink',
        'description' => 'Halaman admin DiscipLink untuk menambahkan berita kedisiplinan kampus.',
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
            'title' => 'Tambah Berita',
            'showLogin' => false,
            'loginHref' => app_page_url('page.login'),
            'roleLabel' => 'Admin',
        ]);
        ?>
        <section class="admin-news-form-page">
            <div class="admin-news-form-header">
                <h1>Tambah Berita</h1>
                <p>Publikasikan informasi terbaru kedisiplinan kampus dengan format yang jelas dan konsisten.</p>
            </div>

            <div class="admin-news-form-layout">
                <aside class="admin-news-form-info">
                    <h3>Petunjuk Singkat</h3>
                    <ol>
                        <li>Tulis judul yang singkat dan spesifik.</li>
                        <li>Isi konten dengan informasi utama yang mudah dipahami.</li>
                        <li>Tambahkan gambar pendukung agar berita lebih informatif.</li>
                    </ol>
                </aside>

                <form id="insertBeritaForm" method="POST"
                    action="<?= htmlspecialchars(app_action_url('action.news'), ENT_QUOTES, 'UTF-8') ?>"
                    enctype="multipart/form-data">
                    <label for="insertPenulisNama">Penulis:</label>
                    <input type="text" id="insertPenulisNama" name="penulis_nama"
                        value="<?= htmlspecialchars($userData['nama_admin']) ?>" required readonly>
                    <input type="hidden" id="insertPenulis" name="penulis"
                        value="<?= htmlspecialchars($userData['id_admin']) ?>" required>

                    <label for="insertJudul">Judul:</label>
                    <input type="text" id="insertJudul" name="judul" required>

                    <label for="insertKonten">Konten:</label>
                    <div class="news-rich-editor" data-rich-editor-wrapper>
                        <div class="news-rich-toolbar" role="toolbar" aria-label="Toolbar format konten berita">
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
                            placeholder="Tulis isi berita formal di sini..." aria-label="Editor konten berita"></div>
                    </div>
                    <textarea id="insertKonten" name="konten" rows="4" class="news-rich-source"></textarea>
                    <p class="news-rich-hint">Gunakan subjudul, paragraf, poin, dan numbering agar berita lebih
                        profesional.</p>

                    <label for="insertGambar">Unggah Gambar:</label>
                    <input type="file" id="insertGambar" name="gambar" accept="image/*">

                    <div class="form-actions">
                        <button type="button" class="cancel-button" data-admin-confirm-trigger
                            data-admin-confirm-title="Batalkan tambah berita?"
                            data-admin-confirm-message="Data berita yang belum disimpan akan hilang. Yakin ingin kembali?"
                            data-admin-confirm-label="Ya, Batalkan" data-admin-confirm-action="navigate"
                            data-admin-confirm-target="<?= htmlspecialchars(app_page_url('page.admin_news'), ENT_QUOTES, 'UTF-8') ?>">Batal</button>
                        <button type="submit" class="save-button" name="store">Simpan Berita</button>
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