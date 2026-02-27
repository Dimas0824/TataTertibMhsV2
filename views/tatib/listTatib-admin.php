<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/config.php';

require_once dirname(__DIR__, 2) . '/Controllers/TatibController.php';
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
} else {
    app_redirect_page('page.login');
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
$tatibCount = is_array($tatibData) ? count($tatibData) : 0;

$tingkatCounts = [
    'I' => 0,
    'II' => 0,
    'III' => 0,
    'IV' => 0,
    'V' => 0,
];

if ($tatibCount > 0) {
    foreach ($tatibData as $rule) {
        $level = strtoupper(trim((string) ($rule['tingkat'] ?? '')));
        if (array_key_exists($level, $tingkatCounts)) {
            $tingkatCounts[$level]++;
        }
    }
}

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
            'loginHref' => app_page_url('page.login'),
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
                <div class="tatib-table-head">
                    <div>
                        <h2>Daftar Aturan Tata Tertib</h2>
                        <p>Rangkuman aturan aktif berdasarkan level pelanggaran.</p>
                    </div>
                    <div class="tatib-level-chips" aria-label="Ringkasan tingkat pelanggaran">
                        <span class="level-chip">I:
                            <?= htmlspecialchars((string) $tingkatCounts['I'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="level-chip">II:
                            <?= htmlspecialchars((string) $tingkatCounts['II'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="level-chip">III:
                            <?= htmlspecialchars((string) $tingkatCounts['III'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="level-chip">IV:
                            <?= htmlspecialchars((string) $tingkatCounts['IV'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="level-chip">V:
                            <?= htmlspecialchars((string) $tingkatCounts['V'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
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
                                <?php foreach ($tatibData as $tatib):
                                    $tingkat = strtoupper(trim((string) ($tatib['tingkat'] ?? '')));
                                    $tierClass = 'tier-pill';
                                    if ($tingkat === 'I') {
                                        $tierClass .= ' tier-pill--one';
                                    } elseif ($tingkat === 'II') {
                                        $tierClass .= ' tier-pill--two';
                                    } elseif ($tingkat === 'III') {
                                        $tierClass .= ' tier-pill--three';
                                    } elseif ($tingkat === 'IV') {
                                        $tierClass .= ' tier-pill--four';
                                    } elseif ($tingkat === 'V') {
                                        $tierClass .= ' tier-pill--five';
                                    }

                                    $point = (int) ($tatib['poin'] ?? 0);
                                    $pointClass = 'point-badge';
                                    if ($point >= 20) {
                                        $pointClass .= ' point-badge--high';
                                    } elseif ($point >= 8) {
                                        $pointClass .= ' point-badge--medium';
                                    } else {
                                        $pointClass .= ' point-badge--low';
                                    }

                                    $adminName = trim((string) ($tatib['nama_admin'] ?? ''));
                                    if ($adminName === '') {
                                        $adminName = 'Admin Tidak Diketahui';
                                    }
                                    ?>
                                    <tr>
                                        <td><?= $i ?></td>
                                        <td><span
                                                class="admin-pill"><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td class="tatib-desc-cell">
                                            <p class="tatib-desc-text"><?= htmlspecialchars($tatib['deskripsi']) ?></p>
                                        </td>
                                        <td><span
                                                class="<?= htmlspecialchars($tierClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tatib['tingkat']) ?></span>
                                        </td>
                                        <td><span
                                                class="<?= htmlspecialchars($pointClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tatib['poin']) ?></span>
                                        </td>
                                        <td class="button-cell">
                                            <form
                                                action="<?= htmlspecialchars(app_action_url('action.tatib'), ENT_QUOTES, 'UTF-8') ?>"
                                                method="post">
                                                <input type="hidden" name="id_tatib"
                                                    value="<?= htmlspecialchars(app_id_token('tatib', (int) $tatib['id_tata_tertib']), ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="button" class="delete" id="delete" name="delete"
                                                    data-admin-confirm-trigger data-admin-confirm-title="Hapus tata tertib?"
                                                    data-admin-confirm-message="Data tata tertib yang dihapus tidak dapat dikembalikan. Lanjutkan penghapusan?"
                                                    data-admin-confirm-label="Ya, Hapus" data-admin-confirm-action="submit-form"
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
                <form id="insertForm" method="POST"
                    action="<?= htmlspecialchars(app_action_url('action.tatib'), ENT_QUOTES, 'UTF-8') ?>">
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
    render_admin_confirm_modal_component([
        'context' => 'nested',
    ]);
    ?>

</body>

</html>