<?php
session_start();
require_once dirname(__DIR__, 2) . '/Controllers/UserController.php';
require_once dirname(__DIR__, 2) . '/Controllers/PelanggaranController.php';
require_once dirname(__DIR__) . '/partials/app-shell.php';

if (!isset($_SESSION['username'])) {
    app_redirect_page('page.login');
}

if (isset($_GET['logout'])) {
    $userController = new UserController();
    $userController->logout();
    exit();
}

if ($_SESSION['user_type'] === 'mahasiswa') {
    app_redirect_page('page.pelanggaran');
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
        'canonical_path' => '/',
        'image' => 'img/GRAHA-POLINEMA1-slider-01.webp',
        'robots' => 'noindex, nofollow',
    ]);
    ?>
    <?php app_seo_favicon_tags('../../'); ?>
    <link rel="stylesheet" href="../../css/global.css">
    <link rel="stylesheet" href="../../css/perlanggaranPage.css">
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css"
        onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
    </noscript>
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
            'loginHref' => app_page_url('page.login'),
            'roleLabel' => 'Dosen',
        ]);
        ?>
        <section class="violation-page">
            <div class="page-hero">
                <div class="page-hero-copy">
                    <span class="page-kicker">DiscipLink Lecturer Dashboard</span>
                    <h2>Rekap Pelanggaran Mahasiswa</h2>
                    <p>Tinjau status pelanggaran, file pendukung, dan lakukan pembaruan laporan secara terpusat.</p>
                </div>
                <div class="summary-grid" aria-label="Ringkasan dosen">
                    <article class="summary-item">
                        <span>Nama</span>
                        <strong><?= htmlspecialchars($userData['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?></strong>
                    </article>
                    <article class="summary-item">
                        <span>NIP/NIDN</span>
                        <strong><?= htmlspecialchars($userData['nidn'], ENT_QUOTES, 'UTF-8') ?></strong>
                    </article>
                    <article class="summary-item">
                        <span>Total Laporan</span>
                        <strong><?= count($pelanggaranDetail) ?></strong>
                    </article>
                </div>
            </div>

            <section class="table-card">
                <div class="table-card-header table-card-header-between">
                    <h3>Tabel Pelanggaran</h3>
                    <button class="primary-action-btn" onclick="window.location.href='<?= htmlspecialchars(app_page_url('page.pelaporan'), ENT_QUOTES, 'UTF-8') ?>'">+
                        Laporkan</button>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Pelanggar</th>
                                <th>Pelanggaran</th>
                                <th>Tingkat</th>
                                <th>Dosen Pelapor</th>
                                <th>Tugas Khusus</th>
                                <th>Dokumen</th>
                                <th>Poin</th>
                                <th>Status</th>
                                <th>Status Tugas</th>
                                <th>Edit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($pelanggaranDetail)): ?>
                                <?php foreach ($pelanggaranDetail as $detail): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($detail['nama_mahasiswa'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($detail['pelanggaran'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span
                                                class="tier-pill"><?= htmlspecialchars($detail['tingkat'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($detail['dosen_pelapor'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($detail['tugas_khusus'] ?? 'Tidak Ada Tugas', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td>
                                            <div class="doc-links">
                                                <?php if (!empty($detail['surat'])): ?>
                                                    <a class="file-link"
                                                        href="<?= htmlspecialchars(app_action_url('action.file_download', ['file' => (string) $detail['surat']]), ENT_QUOTES, 'UTF-8') ?>"
                                                        target="_blank" rel="noopener noreferrer">Surat Pernyataan</a>
                                                <?php else: ?>
                                                    <span class="muted-text">Surat belum diunggah</span>
                                                <?php endif; ?>
                                                <?php if (!empty($detail['pengumpulan_tgsKhusus'])): ?>
                                                    <a class="file-link"
                                                        href="<?= htmlspecialchars(app_action_url('action.file_download', ['file' => (string) $detail['pengumpulan_tgsKhusus']]), ENT_QUOTES, 'UTF-8') ?>"
                                                        target="_blank" rel="noopener noreferrer">Tugas Khusus</a>
                                                <?php else: ?>
                                                    <span class="muted-text">Tugas belum diunggah</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><span
                                                class="point-badge"><?= htmlspecialchars($detail['poin'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td><span
                                                class="status-pill"><?= htmlspecialchars($detail['status_pelanggaran'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <?php if ($detail['tingkat'] === 'IV' || $detail['tingkat'] === 'V'): ?>
                                            <td><span class="muted-text">Tidak ada tugas</span></td>
                                        <?php else: ?>
                                            <td><span
                                                    class="status-pill status-pill-soft"><?= htmlspecialchars($detail['status_tugas'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <a class="edit-laporan"
                                                href="<?= htmlspecialchars(app_page_url('page.edit_pelaporan', ['id_detail' => (int) $detail['id_detail']]), ENT_QUOTES, 'UTF-8') ?>"
                                                aria-label="Edit laporan">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="empty-cell">Data pelanggaran tidak ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mobile-violation-list" aria-label="Daftar pelanggaran mobile">
                    <?php if (!empty($pelanggaranDetail)): ?>
                        <?php foreach ($pelanggaranDetail as $mobileIndex => $detail):
                            $detailId = 'lecturer-' . (string) ((int) $mobileIndex + 1);
                            $sheetId = 'mobile-sheet-lecturer-' . $detailId;
                            $titleId = $sheetId . '-title';
                            ?>
                            <article class="mobile-violation-card" data-mobile-card>
                                <div class="mobile-violation-card__summary">
                                    <p class="mobile-violation-card__title"><?= htmlspecialchars($detail['pelanggaran'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <div class="mobile-violation-card__chips">
                                        <span class="mobile-chip mobile-chip--tier">Tingkat
                                            <?= htmlspecialchars($detail['tingkat'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span
                                            class="mobile-chip mobile-chip--status"><?= htmlspecialchars($detail['status_pelanggaran'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="mobile-chip mobile-chip--point"><?= htmlspecialchars((string) $detail['poin'], ENT_QUOTES, 'UTF-8') ?>
                                            poin</span>
                                    </div>
                                </div>
                                <button type="button" class="mobile-violation-card__open-btn" data-mobile-card-open
                                    aria-expanded="false" aria-controls="<?= htmlspecialchars($sheetId, ENT_QUOTES, 'UTF-8') ?>">
                                    Lihat detail
                                </button>

                                <div class="mobile-violation-sheet" data-mobile-sheet
                                    id="<?= htmlspecialchars($sheetId, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                                    <button type="button" class="mobile-violation-sheet__backdrop" data-mobile-card-close
                                        aria-label="Tutup detail pelanggaran"></button>
                                    <section class="mobile-violation-sheet__panel" role="dialog" aria-modal="true"
                                        aria-labelledby="<?= htmlspecialchars($titleId, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1">
                                        <header class="mobile-violation-sheet__header">
                                            <h4 id="<?= htmlspecialchars($titleId, ENT_QUOTES, 'UTF-8') ?>">Detail Pelaporan</h4>
                                            <button type="button" class="mobile-violation-sheet__close-btn" data-mobile-card-close
                                                aria-label="Tutup detail">
                                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                            </button>
                                        </header>
                                        <div class="mobile-violation-sheet__content">
                                            <dl class="mobile-detail-list">
                                                <div>
                                                    <dt>Pelanggar</dt>
                                                    <dd><?= htmlspecialchars($detail['nama_mahasiswa'], ENT_QUOTES, 'UTF-8') ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Pelanggaran</dt>
                                                    <dd><?= htmlspecialchars($detail['pelanggaran'], ENT_QUOTES, 'UTF-8') ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Tingkat</dt>
                                                    <dd><?= htmlspecialchars($detail['tingkat'], ENT_QUOTES, 'UTF-8') ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Dosen Pelapor</dt>
                                                    <dd><?= htmlspecialchars($detail['dosen_pelapor'], ENT_QUOTES, 'UTF-8') ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Tugas Khusus</dt>
                                                    <dd><?= htmlspecialchars($detail['tugas_khusus'] ?? 'Tidak Ada Tugas', ENT_QUOTES, 'UTF-8') ?>
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt>Status</dt>
                                                    <dd><?= htmlspecialchars($detail['status_pelanggaran'], ENT_QUOTES, 'UTF-8') ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Status Tugas</dt>
                                                    <?php if ($detail['tingkat'] === 'IV' || $detail['tingkat'] === 'V'): ?>
                                                        <dd>Tidak ada tugas</dd>
                                                    <?php else: ?>
                                                        <dd><?= htmlspecialchars($detail['status_tugas'], ENT_QUOTES, 'UTF-8') ?></dd>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <dt>Poin</dt>
                                                    <dd><?= htmlspecialchars((string) $detail['poin'], ENT_QUOTES, 'UTF-8') ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Dokumen</dt>
                                                    <dd>
                                                        <div class="doc-links">
                                                            <?php if (!empty($detail['surat'])): ?>
                                                                <a class="file-link"
                                                                    href="<?= htmlspecialchars(app_action_url('action.file_download', ['file' => (string) $detail['surat']]), ENT_QUOTES, 'UTF-8') ?>"
                                                                    target="_blank" rel="noopener noreferrer">Surat Pernyataan</a>
                                                            <?php else: ?>
                                                                <span class="muted-text">Surat belum diunggah</span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($detail['pengumpulan_tgsKhusus'])): ?>
                                                                <a class="file-link"
                                                                    href="<?= htmlspecialchars(app_action_url('action.file_download', ['file' => (string) $detail['pengumpulan_tgsKhusus']]), ENT_QUOTES, 'UTF-8') ?>"
                                                                    target="_blank" rel="noopener noreferrer">Tugas Khusus</a>
                                                            <?php else: ?>
                                                                <span class="muted-text">Tugas belum diunggah</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </dd>
                                                </div>
                                            </dl>

                                            <div class="mobile-sheet-actions">
                                                <a class="edit-laporan"
                                                    href="<?= htmlspecialchars(app_page_url('page.edit_pelaporan', ['id_detail' => (int) $detail['id_detail']]), ENT_QUOTES, 'UTF-8') ?>"
                                                    aria-label="Edit laporan">
                                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                                    Edit Laporan
                                                </a>
                                            </div>
                                        </div>
                                    </section>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="mobile-violation-empty">Data pelanggaran tidak ditemukan.</div>
                    <?php endif; ?>
                </div>
            </section>
        </section>

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
    <script defer
        src="<?= htmlspecialchars(app_seo_script_src('js/mobile-violation-cards.js', '../..'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>

</html>
