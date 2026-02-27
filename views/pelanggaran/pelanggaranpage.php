<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
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

if ($_SESSION['user_type'] === 'dosen') {
    app_redirect_page('page.pelanggaran_dosen');
}

// Ambil data user dari session
$userData = $_SESSION['user_data'];

$currentYear = date('Y');
$currentMonth = date('n');
$yearDiff = $currentYear - $userData['angkatan'];
$semester = ($yearDiff * 2);
if ($currentMonth >= 8) { // Semester ganjil dimulai sekitar Agustus
    $semester += 1;
}

// tabel
$pelanggaranController = new PelanggaranController();
$nim = $userData['nim'];
$pelanggaranDetail = $pelanggaranController->getDetailPelanggaranMahasiswa($nim);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pelanggaran Mahasiswa | DiscipLink</title>
    <?php
    app_seo_meta_tags([
        'title' => 'Status Pelanggaran Mahasiswa | DiscipLink',
        'description' => 'Pantau status pelanggaran mahasiswa, poin, sanksi, dan pengumpulan dokumen melalui dashboard DiscipLink.',
        'canonical_path' => '/',
        'image' => 'img/GRAHA-POLINEMA1-slider-01.webp',
        'robots' => 'noindex, nofollow',
    ]);
    ?>
    <?php app_seo_favicon_tags('../../'); ?>
    <link rel="stylesheet" href="../../css/global.css">
    <link rel="stylesheet" href="../../css/perlanggaranPage.css">
    <link rel="stylesheet" href="../../css/modal.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css"
        onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
    </noscript>
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

    <!-- Main Content -->
    <div class="content">
        <?php
        render_app_header([
            'title' => 'Pelanggaran',
            'showLogin' => false,
            'loginHref' => app_page_url('page.login'),
            'roleLabel' => 'Mahasiswa',
        ]);
        ?>
        <section class="violation-page">
            <div class="page-hero">
                <div class="page-hero-copy">
                    <span class="page-kicker">DiscipLink Student Dashboard</span>
                    <h2>Status Pelanggaran Mahasiswa</h2>
                    <p>Pantau riwayat pelanggaran, status tindak lanjut, serta unggah dokumen pendukung secara
                        terstruktur.</p>
                </div>
                <div class="summary-grid" aria-label="Ringkasan mahasiswa">
                    <article class="summary-item">
                        <span>Nama</span>
                        <strong><?= htmlspecialchars($userData['nama_lengkap']) ?></strong>
                    </article>
                    <article class="summary-item">
                        <span>NIM</span>
                        <strong><?= htmlspecialchars($userData['nim']) ?></strong>
                    </article>
                    <article class="summary-item">
                        <span>Semester</span>
                        <strong><?= htmlspecialchars((string) $semester) ?></strong>
                    </article>
                </div>
            </div>

            <section class="table-card">
                <div class="table-card-header">
                    <h3>Tabel Pelanggaran</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Pelanggaran</th>
                                <th>Tingkat</th>
                                <th>Sanksi</th>
                                <th>Dosen Pelapor</th>
                                <th>Tugas Khusus</th>
                                <th>Surat</th>
                                <th>Poin</th>
                                <th>Status</th>
                                <th>Pengumpulan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($pelanggaranDetail)) {
                                foreach ($pelanggaranDetail as $detail) { ?>
                                    <tr>
                                        <td><?= htmlspecialchars($detail['pelanggaran']) ?></td>
                                        <td><span class="tier-pill"><?= htmlspecialchars($detail['tingkat']) ?></span></td>
                                        <td><?= htmlspecialchars($detail['sanksi']) ?></td>
                                        <td><?= htmlspecialchars($detail['nama_lengkap']) ?></td>
                                        <td><?= htmlspecialchars($detail['tugas_khusus'] ?? 'Tidak Ada Tugas') ?></td>
                                        <td><a class="file-link"
                                                href="<?= htmlspecialchars(app_action_url('action.file_download', ['file' => 'SURAT PERNYATAAN TI.pdf']), ENT_QUOTES, 'UTF-8') ?>"
                                                target="_blank" rel="noopener noreferrer">Unduh File</a></td>
                                        <td><span class="point-badge"><?= htmlspecialchars($detail['poin']) ?></span></td>
                                        <td><span class="status-pill"><?= htmlspecialchars($detail['status']) ?></span></td>
                                        <td>
                                            <form class="uploadForm" enctype="multipart/form-data">
                                                <input type="hidden" name="id_detail" value="<?= htmlspecialchars(app_id_token('detail_pelanggaran', (int) $detail['id_detail']), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="file" name="suratPernyataan" required>
                                                <button type="button" class="submit-btn uploadButton">Upload Surat</button>
                                            </form>
                                            <?php if (in_array($detail['tingkat'], ['I', 'II', 'III'])): ?>
                                                <form class="uploadForm" enctype="multipart/form-data">
                                                    <input type="hidden" name="id_detail" value="<?= htmlspecialchars(app_id_token('detail_pelanggaran', (int) $detail['id_detail']), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="file" name="tugasKhusus" required>
                                                    <button type="button" class="submit-btn uploadButton">Upload Tugas</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php }
                            } else {
                                echo "<tr><td colspan='9' class='empty-cell'>Data pelanggaran tidak ditemukan.</td></tr>";
                            } ?>
                        </tbody>
                    </table>
                </div>
                <div class="mobile-violation-list" aria-label="Daftar pelanggaran mobile">
                    <?php if (!empty($pelanggaranDetail)): ?>
                        <?php foreach ($pelanggaranDetail as $mobileIndex => $detail):
                            $detailId = 'student-' . (string) ((int) $mobileIndex + 1);
                            $sheetId = 'mobile-sheet-student-' . $detailId;
                            $titleId = $sheetId . '-title';
                            ?>
                            <article class="mobile-violation-card" data-mobile-card>
                                <div class="mobile-violation-card__summary">
                                    <p class="mobile-violation-card__title"><?= htmlspecialchars($detail['pelanggaran'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <div class="mobile-violation-card__chips">
                                        <span class="mobile-chip mobile-chip--tier">Tingkat
                                            <?= htmlspecialchars($detail['tingkat'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="mobile-chip mobile-chip--status"><?= htmlspecialchars($detail['status'], ENT_QUOTES, 'UTF-8') ?></span>
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
                                            <h4 id="<?= htmlspecialchars($titleId, ENT_QUOTES, 'UTF-8') ?>">Detail Pelanggaran</h4>
                                            <button type="button" class="mobile-violation-sheet__close-btn" data-mobile-card-close
                                                aria-label="Tutup detail">
                                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                            </button>
                                        </header>
                                        <div class="mobile-violation-sheet__content">
                                            <dl class="mobile-detail-list">
                                                <div>
                                                    <dt>Pelanggaran</dt>
                                                    <dd><?= htmlspecialchars($detail['pelanggaran'], ENT_QUOTES, 'UTF-8') ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Tingkat</dt>
                                                    <dd><?= htmlspecialchars($detail['tingkat'], ENT_QUOTES, 'UTF-8') ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Sanksi</dt>
                                                    <dd><?= htmlspecialchars($detail['sanksi'], ENT_QUOTES, 'UTF-8') ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Dosen Pelapor</dt>
                                                    <dd><?= htmlspecialchars($detail['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Tugas Khusus</dt>
                                                    <dd><?= htmlspecialchars($detail['tugas_khusus'] ?? 'Tidak Ada Tugas', ENT_QUOTES, 'UTF-8') ?>
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt>Surat</dt>
                                                    <dd>
                                                        <a class="file-link"
                                                            href="<?= htmlspecialchars(app_action_url('action.file_download', ['file' => 'SURAT PERNYATAAN TI.pdf']), ENT_QUOTES, 'UTF-8') ?>"
                                                            target="_blank" rel="noopener noreferrer">Unduh File</a>
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt>Poin</dt>
                                                    <dd><?= htmlspecialchars((string) $detail['poin'], ENT_QUOTES, 'UTF-8') ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Status</dt>
                                                    <dd><?= htmlspecialchars($detail['status'], ENT_QUOTES, 'UTF-8') ?></dd>
                                                </div>
                                            </dl>

                                            <div class="mobile-sheet-actions">
                                                <form class="uploadForm" enctype="multipart/form-data">
                                                    <input type="hidden" name="id_detail"
                                                        value="<?= htmlspecialchars(app_id_token('detail_pelanggaran', (int) $detail['id_detail']), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="file" name="suratPernyataan" required>
                                                    <button type="button" class="submit-btn uploadButton">Upload Surat</button>
                                                </form>
                                                <?php if (in_array($detail['tingkat'], ['I', 'II', 'III'], true)): ?>
                                                    <form class="uploadForm" enctype="multipart/form-data">
                                                        <input type="hidden" name="id_detail"
                                                            value="<?= htmlspecialchars(app_id_token('detail_pelanggaran', (int) $detail['id_detail']), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="file" name="tugasKhusus" required>
                                                        <button type="button" class="submit-btn uploadButton">Upload Tugas</button>
                                                    </form>
                                                <?php endif; ?>
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

    <!-- untuk modal ini ada 2 pengumpulan tugas khusus dan akhir gimana caranya biar kalo tugas akhir nya ga ada, form untuk input tugas akhir nya ga ada juga?? -->
    <!-- Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h2>Upload File</h2>
                <p><i>*File yang diupload maksimal 2 MB</i></p>
            </div>
            <div class="modal-body">
                <!-- Form Surat Pernyataan -->
                <form id="formSuratPernyataan">
                    <div class="form-control">
                        <label for="suratPernyataan">Surat Pernyataan: *</label>
                        <input type="file" id="suratPernyataan" name="suratPernyataan">
                    </div>
                </form>

                <!-- Form Tugas Khusus -->
                <form id="formTugasKhusus">
                    <div class="form-control">
                        <label for="tugasKhusus">Tugas Khusus: *</label>
                        <input type="file" id="tugasKhusus" name="tugasKhusus">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary" form="formSuratPernyataan">Simpan</button>
            </div>
        </div>
    </div>

    <?php
    render_app_flash_modal([
        'context' => 'nested',
    ]);
    ?>

    <!-- JavaScript -->
    <script defer
        src="<?= htmlspecialchars(app_seo_script_src('js/mobile-violation-cards.js', '../..'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer
        src="<?= htmlspecialchars(app_seo_script_src('js/script-pelanggaran.js', '../..'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script>
        const showUploadFeedback = (payload) => {
            if (window.AppModal && typeof window.AppModal.show === 'function') {
                window.AppModal.show(payload);
                return;
            }

            alert(payload.message || 'Terjadi kesalahan.');
        };

        document.querySelectorAll('.uploadButton').forEach(button => {
            button.addEventListener('click', async function () {
                const form = this.closest('form');
                const formData = new FormData(form);

                try {
                    const response = await fetch('<?= htmlspecialchars(app_action_url('action.upload'), ENT_QUOTES, 'UTF-8') ?>', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            Accept: 'application/json',
                        },
                    });

                    const payload = await response.json();
                    showUploadFeedback({
                        type: payload.success ? 'success' : 'error',
                        message: payload.message || 'Operasi upload selesai.',
                    });

                    if (payload.success) {
                        form.reset();
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showUploadFeedback({
                        type: 'error',
                        message: 'Gagal mengunggah file.',
                    });
                }
            });
        });
    </script>
</body>

</html>
