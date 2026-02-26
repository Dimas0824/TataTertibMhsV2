<?php
session_start();
require_once dirname(__DIR__, 2) . '/config.php';

require_once dirname(__DIR__, 2) . '/Controllers/TatibController.php';
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
$userData = $_SESSION['user_data'];

$tatibController = new TatibController();
$tatibData = $tatibController->ReadTatib();

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Tata Tertib Mahasiswa | DiscipLink</title>
    <?php
    app_seo_meta_tags([
        'title' => 'Admin Tata Tertib Mahasiswa | DiscipLink',
        'description' => 'Panel admin DiscipLink untuk mengelola data tata tertib mahasiswa, tingkat pelanggaran, dan poin aturan kampus.',
        'canonical_path' => '/views/tatib/listTatib-admin.php',
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
    <link rel="stylesheet" href="../../css/tatib-admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
</head>

<body>
    <?php
    render_app_sidebar([
        'variant' => 'admin',
        'context' => 'nested',
        'active' => 'tatib',
    ]);
    ?>
    <div class="content">
        <?php
        render_app_header([
            'title' => 'Tata Tertib Admin',
            'showLogin' => false,
            'loginHref' => '../auth/login.php',
            'roleLabel' => 'Admin',
        ]);
        ?>
        <section class="tatib-admin-page">
            <div class="tatib-admin-hero">
                <div>
                    <span class="tatib-admin-kicker">DiscipLink Admin</span>
                    <h1>Manajemen Tata Tertib</h1>
                    <p>Kelola daftar pelanggaran, tingkat, dan poin aturan kampus secara terstruktur dari satu
                        dashboard.</p>
                </div>
                <div class="tatib-admin-stat">
                    <span>Total Aturan</span>
                    <strong><?= count($tatibData) ?></strong>
                </div>
            </div>

            <div class="tatib-admin-toolbar">
                <button class="add-button" id="addButton">+ Tambah Aturan</button>
            </div>

            <section class="tatib-table-card">
                <div class="table-container">
                    <table id="tatib-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Admin</th>
                                <th>Pelanggaran</th>
                                <th>Tingkat</th>
                                <th>Poin</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1 ?>
                            <?php if ($tatibData): ?>
                                <?php foreach ($tatibData as $tatib): ?>
                                    <tr>
                                        <td><?= $i ?></td>
                                        <td><?= htmlspecialchars($tatib['id_adminTatib']) ?></td>
                                        <td class="tatib-desc-cell"><?= htmlspecialchars($tatib['deskripsi']) ?></td>
                                        <td><span class="tier-pill"><?= htmlspecialchars($tatib['tingkat']) ?></span></td>
                                        <td><span class="point-badge"><?= htmlspecialchars($tatib['poin']) ?></span></td>
                                        <td class="button-cell">
                                            <form action="../../Request/Handler_Tatib.php" method="post">
                                                <input type="hidden" name="id_tatib"
                                                    value="<?= htmlspecialchars($tatib['id_tata_tertib']) ?>">
                                                <button class="delete" id="delete" name="delete"
                                                    onclick="return confirm('Apakah anda yakin ingin menghapus?');"
                                                    aria-label="Hapus tata tertib <?= htmlspecialchars($tatib['deskripsi']) ?>"><i
                                                        class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php $i++ ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-cell">Data tata tertib tidak ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
        <!-- Modal edit -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Edit Pelanggaran</h2>
                <form id="editForm">
                    <label for="nomor">No.</label>
                    <input type="text" id="nomor" name="nomor" readonly>

                    <label for="editAdmin">Id Admin:</label>
                    <input type="text" id="admin" name="admin" required>

                    <label for="editKonten">Pelanggaran:</label>
                    <textarea id="editKonten" name="konten" rows="4" required></textarea>

                    <label for="editTingkat">Tingkat:</label>
                    <select id="tingkat" name="tingkat" required>
                        <option value="">Pilih Tingkat</option>
                        <option value="I">Tingkat I</option>
                        <option value="II">Tingkat II</option>
                        <option value="III">Tingkat III</option>
                        <option value="IV">Tingkat IV</option>
                        <option value="V">Tingkat V</option>

                    </select>

                    <label for="editPoin">Poin:</label>
                    <input type="text" id="poin" name="poin" readonly>

                    <button type="submit" class="save-button">Simpan</button>
                </form>
            </div>
        </div>

        <!-- Modal insert-->
        <div id="insertModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Tambah Pelanggaran</h2>
                <form id="insertForm" method="POST" action="../../Request/Handler_Tatib.php">
                    <label for="insertAdmin">Id Admin:</label>
                    <input type="text" id="admin" name="admin" value="<?= $userData['id_admin'] ?>" required readonly>

                    <label for="insertDeskripsi">Pelanggaran:</label>
                    <textarea id="insertDeskripsi" name="deskripsi" rows="4" required></textarea>

                    <label for="insertTingkat">Tingkat:</label>
                    <select id="tingkat" name="tingkat" required>
                        <option value="">Pilih Tingkat</option>
                        <option value="I">Tingkat I</option>
                        <option value="II">Tingkat II</option>
                        <option value="III">Tingkat III</option>
                        <option value="IV">Tingkat IV</option>
                        <option value="V">Tingkat V</option>

                    </select>

                    <label for="editPoin">Poin:</label>
                    <input type="text" id="poin" name="poin" readonly>

                    <button type="submit" class="save-button" name="store">Simpan</button>
                </form>
            </div>
        </div>

        <!-- javascript -->
        <script defer
            src="<?= htmlspecialchars(app_seo_script_src('js/admin-tatib.js', '../..'), ENT_QUOTES, 'UTF-8') ?>"></script>
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