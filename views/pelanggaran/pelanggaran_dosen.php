<?php
session_start();
require_once dirname(__DIR__, 2) . '/Controllers/UserController.php';
require_once dirname(__DIR__, 2) . '/Controllers/PelanggaranController.php';
require_once dirname(__DIR__) . '/partials/app-shell.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (isset($_GET['logout'])) {
    $userController = new UserController();
    $userController->logout();
    exit();
}

if ($_SESSION['user_type'] === 'mahasiswa') {
    header("Location: ../pelanggaran/pelanggaranpage.php");
    exit();
}

$userData = $_SESSION['user_data'];
$pelanggaranController = new PelanggaranController();
$nidn = $userData['nidn'];
$pelanggaranDetail = $pelanggaranController->getDetailLaporanDosen($nidn);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pelanggaran Mahasiswa Dosen | DiscipLink</title>
    <?php
    app_seo_meta_tags([
        'title' => 'Pelanggaran Mahasiswa Dosen | DiscipLink',
        'description' => 'Dashboard dosen DiscipLink untuk meninjau laporan pelanggaran mahasiswa, status, dan dokumen pendukung.',
        'canonical_path' => '/views/pelanggaran/pelanggaran_dosen.php',
        'image' => 'img/GRAHA-POLINEMA1-slider-01.webp',
        'robots' => 'noindex, nofollow',
    ]);
    ?>
    <?php app_seo_favicon_tags('../../'); ?>
    <link rel="stylesheet" href="../../css/global.css">
    <link rel="stylesheet" href="../../css/perlanggaranPage.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
</head>

<body>
    <?php
    render_app_sidebar([
        'variant' => 'student',
        'context' => 'nested',
        'active' => 'pelanggaran',
    ]);
    ?>

    <div class="content">
        <?php
        render_app_header([
            'title' => 'Pelanggaran',
            'showLogin' => false,
            'loginHref' => '../auth/login.php',
            'roleLabel' => 'Dosen',
        ]);
        ?>

        <div class="profile">
            <p><strong>Nama: <?= htmlspecialchars($userData['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?></strong></p>
            <p><strong>NIP: <?= htmlspecialchars($userData['nidn'], ENT_QUOTES, 'UTF-8') ?></strong></p>
        </div>

        <h3>Tabel Pelanggaran</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Pelanggar</th>
                        <th>Pelanggaran</th>
                        <th>Tingkat Pelanggaran</th>
                        <th>Dosen Pelapor</th>
                        <th>Tugas Khusus</th>
                        <th>Surat</th>
                        <th>Poin</th>
                        <th>Status</th>
                        <th>Status Tugas</th>
                        <th>Edit laporan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pelanggaranDetail)): ?>
                        <?php foreach ($pelanggaranDetail as $detail): ?>
                            <tr>
                                <td><?= htmlspecialchars($detail['nama_mahasiswa'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($detail['pelanggaran'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($detail['tingkat'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($detail['dosen_pelapor'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($detail['tugas_khusus'] ?? 'Tidak Ada Tugas', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?php if (!empty($detail['surat'])): ?>
                                        <a href="<?= htmlspecialchars('../../document/' . $detail['surat'], ENT_QUOTES, 'UTF-8') ?>"
                                            target="_blank" rel="noopener noreferrer">Unduh Surat Pernyataan</a>
                                    <?php else: ?>
                                        <span>Tidak ada file surat yang diunggah.</span>
                                    <?php endif; ?>
                                    <?php if (!empty($detail['pengumpulan_tgsKhusus'])): ?>
                                        <a href="<?= htmlspecialchars('../../document/' . $detail['pengumpulan_tgsKhusus'], ENT_QUOTES, 'UTF-8') ?>"
                                            target="_blank" rel="noopener noreferrer">Unduh Tugas Khusus</a>
                                    <?php else: ?>
                                        <span>Tidak ada file tugas yang diunggah.</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($detail['poin'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($detail['status_pelanggaran'], ENT_QUOTES, 'UTF-8') ?></td>
                                <?php if ($detail['tingkat'] === 'IV' || $detail['tingkat'] === 'V'): ?>
                                    <td>Tidak ada tugas</td>
                                <?php else: ?>
                                    <td><?= htmlspecialchars($detail['status_tugas'], ENT_QUOTES, 'UTF-8') ?></td>
                                <?php endif; ?>
                                <td>
                                    <button class="edit-laporan">
                                        <a href="edit-pelaporan.php?id=<?= urlencode((string) $detail['id_detail']) ?>"><i
                                                class="fa-solid fa-pen-to-square"></i></a>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10">Data pelanggaran tidak ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="statement-button">
                <button onclick="window.location.href='pelaporan.php'">Laporkan</button>
            </div>
        </div>

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