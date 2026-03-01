<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
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
$totalPelanggaran = is_array($pelanggaranDetail) ? count($pelanggaranDetail) : 0;
$pendingPelanggaran = 0;
$activePelanggaran = 0;
$selesaiPelanggaran = 0;
if ($totalPelanggaran > 0) {
    foreach ($pelanggaranDetail as $item) {
        $statusValue = strtolower(trim((string) ($item['status'] ?? '')));
        if ($statusValue === 'selesai' || $statusValue === 'done') {
            $selesaiPelanggaran++;
        } else {
            $activePelanggaran++;
        }
        if ($statusValue === 'pending') {
            $pendingPelanggaran++;
        }
    }
}

$escapeHtml = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$requiresTugasKhusus = static function (array $detail): bool {
    $tingkat = strtoupper(trim((string) ($detail['tingkat'] ?? '')));
    return in_array($tingkat, ['I', 'II', 'III'], true);
};

$buildStudentRowState = static function (array $detail): array {
    $tingkat = strtoupper(trim((string) ($detail['tingkat'] ?? '')));
    $tierClass = 'tier-pill';
    if ($tingkat === 'I') {
        $tierClass .= ' tier-pill--one';
    } elseif ($tingkat === 'II') {
        $tierClass .= ' tier-pill--two';
    } elseif ($tingkat === 'III') {
        $tierClass .= ' tier-pill--three';
    }

    $statusText = strtolower(trim((string) ($detail['status'] ?? '')));
    $statusClass = 'status-pill';
    if ($statusText === 'pending') {
        $statusClass .= ' status-pill--pending';
    } elseif ($statusText === 'selesai' || $statusText === 'done') {
        $statusClass .= ' status-pill--done';
    } else {
        $statusClass .= ' status-pill--progress';
    }

    $poin = (int) ($detail['poin'] ?? 0);
    $pointClass = 'point-badge';
    if ($poin >= 15) {
        $pointClass .= ' point-badge--high';
    } elseif ($poin >= 9) {
        $pointClass .= ' point-badge--medium';
    } else {
        $pointClass .= ' point-badge--low';
    }

    return [
        'tingkat' => $tingkat,
        'tierClass' => $tierClass,
        'statusText' => $statusText,
        'statusClass' => $statusClass,
        'pointClass' => $pointClass,
    ];
};

$studentTableColumns = [
    [
        'label' => 'Pelanggaran',
        'render' => static function (array $detail) use ($escapeHtml): string {
            return '<p class="violation-desc">' . $escapeHtml((string) ($detail['pelanggaran'] ?? '')) . '</p>';
        },
    ],
    [
        'label' => 'Tingkat',
        'render' => static function (array $detail) use ($escapeHtml, $buildStudentRowState): string {
            $state = $buildStudentRowState($detail);
            return '<span class="' . $escapeHtml((string) $state['tierClass']) . '">' . $escapeHtml((string) ($detail['tingkat'] ?? '')) . '</span>';
        },
    ],
    [
        'label' => 'Sanksi',
        'render' => static function (array $detail) use ($escapeHtml): string {
            return '<p class="sanction-desc">' . $escapeHtml((string) ($detail['sanksi'] ?? '')) . '</p>';
        },
    ],
    [
        'label' => 'Dosen Pelapor',
        'key' => 'nama_lengkap',
    ],
    [
        'label' => 'Tugas Khusus',
        'render' => static function (array $detail) use ($escapeHtml, $requiresTugasKhusus): string {
            if (!$requiresTugasKhusus($detail)) {
                return $escapeHtml('Tidak diwajibkan');
            }

            $tugasKhusus = trim((string) ($detail['tugas_khusus'] ?? ''));
            return $escapeHtml($tugasKhusus !== '' ? $tugasKhusus : 'Belum diisi');
        },
    ],
    [
        'label' => 'Surat',
        'render' => static function () use ($escapeHtml): string {
            ob_start();
            ?>
            <div class="doc-links">
                <a class="file-link"
                    href="<?= $escapeHtml(app_action_url('action.file_download', ['file' => 'SURAT PERNYATAAN TI.pdf'])) ?>"
                    target="_blank" rel="noopener noreferrer">Unduh Surat Pernyataan</a>
                <span class="muted-text">Format PDF, maksimal 2 MB.</span>
            </div>
            <?php
            return (string) ob_get_clean();
        },
    ],
    [
        'label' => 'Poin',
        'render' => static function (array $detail) use ($escapeHtml, $buildStudentRowState): string {
            $state = $buildStudentRowState($detail);
            return '<span class="' . $escapeHtml((string) $state['pointClass']) . '">' . $escapeHtml((string) ($detail['poin'] ?? '0')) . '</span>';
        },
    ],
    [
        'label' => 'Status',
        'render' => static function (array $detail) use ($escapeHtml, $buildStudentRowState): string {
            $state = $buildStudentRowState($detail);
            return '<span class="' . $escapeHtml((string) $state['statusClass']) . '">' . $escapeHtml((string) ($detail['status'] ?? '')) . '</span>';
        },
    ],
    [
        'label' => 'Pengumpulan',
        'render' => static function (array $detail) use ($escapeHtml, $requiresTugasKhusus): string {
            $requiresTugas = $requiresTugasKhusus($detail);
            ob_start();
            ?>
            <form class="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="id_detail"
                    value="<?= $escapeHtml(app_id_token('detail_pelanggaran', (int) ($detail['id_detail'] ?? 0))) ?>">
                <input type="file" name="suratPernyataan" required>
                <button type="button" class="submit-btn uploadButton">Upload Surat</button>
            </form>
            <?php if ($requiresTugas): ?>
                <form class="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="id_detail"
                        value="<?= $escapeHtml(app_id_token('detail_pelanggaran', (int) ($detail['id_detail'] ?? 0))) ?>">
                    <input type="file" name="tugasKhusus" required>
                    <button type="button" class="submit-btn uploadButton">Upload Tugas</button>
                </form>
            <?php endif; ?>
            <?php
            return (string) ob_get_clean();
        },
    ],
];

$studentRowMetaBuilder = static function (array $detail) use ($buildStudentRowState): array {
    $state = $buildStudentRowState($detail);
    $statusTab = 'aktif';
    if ((string) $state['statusText'] === 'selesai' || (string) $state['statusText'] === 'done') {
        $statusTab = 'selesai';
    }

    return [
        'search' => implode(' ', [
            (string) ($detail['pelanggaran'] ?? ''),
            (string) ($detail['sanksi'] ?? ''),
            (string) ($detail['nama_lengkap'] ?? ''),
            (string) ($detail['status'] ?? ''),
            (string) ($detail['tingkat'] ?? ''),
            (string) ($detail['tugas_khusus'] ?? ''),
        ]),
        'filters' => [
            'status_tab' => $statusTab,
            'tingkat' => (string) $state['tingkat'],
        ],
    ];
};

$studentTableConfig = [
    'id' => 'student-violation-table',
    'title' => 'Tabel Pelanggaran',
    'description' => 'Riwayat pelanggaran aktif dan status tindak lanjut Anda.',
    'stats' => [
        ['label' => $totalPelanggaran . ' kasus'],
        ['label' => $pendingPelanggaran . ' pending', 'class' => 'table-stat-chip--warning'],
    ],
    'tabs' => [
        [
            'key' => 'status_tab',
            'label' => 'Status Pelanggaran',
            'defaultValue' => 'aktif',
            'options' => [
                ['value' => 'aktif', 'label' => 'Pelanggaran Aktif'],
                ['value' => 'selesai', 'label' => 'Selesai'],
            ],
        ],
    ],
    'filters' => [
        [
            'key' => 'tingkat',
            'label' => 'Tingkat',
            'options' => [
                ['value' => 'I', 'label' => 'Tingkat I'],
                ['value' => 'II', 'label' => 'Tingkat II'],
                ['value' => 'III', 'label' => 'Tingkat III'],
                ['value' => 'IV', 'label' => 'Tingkat IV'],
                ['value' => 'V', 'label' => 'Tingkat V'],
            ],
        ],
    ],
    'search' => [
        'enabled' => true,
        'label' => 'Cari Pelanggaran',
        'placeholder' => 'Cari pelanggaran, sanksi, dosen pelapor, atau status',
    ],
    'columns' => $studentTableColumns,
    'rows' => $pelanggaranDetail,
    'rowMetaBuilder' => $studentRowMetaBuilder,
    'emptyMessage' => 'Data pelanggaran tidak ditemukan.',
    'tableCardClass' => 'table-card--desktop-only',
];
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

            <?php render_universal_filterable_table_component($studentTableConfig); ?>
            <section class="table-card table-card--mobile-only" data-mobile-violation-section>
                <div class="mobile-violation-tools" data-mobile-violation-tools>
                    <div class="mobile-violation-tabs" role="group" aria-label="Filter status pelanggaran">
                        <button type="button" class="mobile-violation-tab-btn is-active" data-mobile-status-value="aktif"
                            aria-pressed="true">
                            Pelanggaran Aktif (<?= htmlspecialchars((string) $activePelanggaran, ENT_QUOTES, 'UTF-8') ?>)
                        </button>
                        <button type="button" class="mobile-violation-tab-btn" data-mobile-status-value="selesai"
                            aria-pressed="false">
                            Selesai (<?= htmlspecialchars((string) $selesaiPelanggaran, ENT_QUOTES, 'UTF-8') ?>)
                        </button>
                    </div>
                    <div class="mobile-violation-controls">
                        <label class="mobile-violation-field mobile-violation-field--search">
                            <span>Cari Pelanggaran</span>
                            <input type="search" data-mobile-search
                                placeholder="Cari pelanggaran, sanksi, dosen pelapor, atau status">
                        </label>
                        <label class="mobile-violation-field">
                            <span>Tingkat</span>
                            <select data-mobile-filter-key="tingkat">
                                <option value="">Semua Tingkat</option>
                                <option value="i">Tingkat I</option>
                                <option value="ii">Tingkat II</option>
                                <option value="iii">Tingkat III</option>
                                <option value="iv">Tingkat IV</option>
                                <option value="v">Tingkat V</option>
                            </select>
                        </label>
                    </div>
                    <p class="mobile-violation-result">
                        Menampilkan
                        <strong data-mobile-visible-count><?= htmlspecialchars((string) $activePelanggaran, ENT_QUOTES, 'UTF-8') ?></strong>
                        dari
                        <strong data-mobile-total-count><?= htmlspecialchars((string) $totalPelanggaran, ENT_QUOTES, 'UTF-8') ?></strong>
                        kasus
                    </p>
                </div>
                <div class="mobile-violation-list" data-mobile-violation-list aria-label="Daftar pelanggaran mobile">
                    <?php if (!empty($pelanggaranDetail)): ?>
                        <?php foreach ($pelanggaranDetail as $mobileIndex => $detail):
                            $statusLowerMobile = strtolower(trim((string) ($detail['status'] ?? '')));
                            $statusTabMobile = ($statusLowerMobile === 'selesai' || $statusLowerMobile === 'done') ? 'selesai' : 'aktif';
                            $tingkatMobile = strtolower(trim((string) ($detail['tingkat'] ?? '')));
                            $requiresTugas = $requiresTugasKhusus($detail);
                            $mobileSearchText = trim(implode(' ', [
                                (string) ($detail['pelanggaran'] ?? ''),
                                (string) ($detail['sanksi'] ?? ''),
                                (string) ($detail['nama_lengkap'] ?? ''),
                                (string) ($detail['status'] ?? ''),
                                (string) ($detail['tingkat'] ?? ''),
                                (string) ($detail['tugas_khusus'] ?? ''),
                            ]));
                            $detailId = 'student-' . (string) ((int) $mobileIndex + 1);
                            $sheetId = 'mobile-sheet-student-' . $detailId;
                            $titleId = $sheetId . '-title';
                            ?>
                            <article class="mobile-violation-card" data-mobile-card
                                data-mobile-card-status="<?= htmlspecialchars($statusTabMobile, ENT_QUOTES, 'UTF-8') ?>"
                                data-mobile-search="<?= htmlspecialchars($mobileSearchText, ENT_QUOTES, 'UTF-8') ?>"
                                data-mobile-filter-tingkat="<?= htmlspecialchars($tingkatMobile, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="mobile-violation-card__summary">
                                    <p class="mobile-violation-card__title">
                                        <?= htmlspecialchars($detail['pelanggaran'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <div class="mobile-violation-card__chips">
                                        <span class="mobile-chip mobile-chip--tier">Tingkat
                                            <?= htmlspecialchars($detail['tingkat'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span
                                            class="mobile-chip mobile-chip--status"><?= htmlspecialchars($detail['status'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span
                                            class="mobile-chip mobile-chip--point"><?= htmlspecialchars((string) $detail['poin'], ENT_QUOTES, 'UTF-8') ?>
                                            poin</span>
                                    </div>
                                </div>
                                <button type="button" class="mobile-violation-card__open-btn" data-mobile-card-open
                                    aria-expanded="false"
                                    aria-controls="<?= htmlspecialchars($sheetId, ENT_QUOTES, 'UTF-8') ?>">
                                    Lihat detail
                                </button>

                                <div class="mobile-violation-sheet" data-mobile-sheet
                                    id="<?= htmlspecialchars($sheetId, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                                    <button type="button" class="mobile-violation-sheet__backdrop" data-mobile-card-close
                                        aria-label="Tutup detail pelanggaran"></button>
                                    <section class="mobile-violation-sheet__panel" role="dialog" aria-modal="true"
                                        aria-labelledby="<?= htmlspecialchars($titleId, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1">
                                        <header class="mobile-violation-sheet__header">
                                            <h4 id="<?= htmlspecialchars($titleId, ENT_QUOTES, 'UTF-8') ?>">Detail Pelanggaran
                                            </h4>
                                            <button type="button" class="mobile-violation-sheet__close-btn"
                                                data-mobile-card-close aria-label="Tutup detail">
                                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                            </button>
                                        </header>
                                        <div class="mobile-violation-sheet__content">
                                            <dl class="mobile-detail-list">
                                                <div>
                                                    <dt>Pelanggaran</dt>
                                                    <dd><?= htmlspecialchars($detail['pelanggaran'], ENT_QUOTES, 'UTF-8') ?>
                                                    </dd>
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
                                                    <dd><?= htmlspecialchars($detail['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?>
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt>Tugas Khusus</dt>
                                                    <?php if (!$requiresTugas): ?>
                                                        <dd>Tidak diwajibkan</dd>
                                                    <?php else: ?>
                                                        <dd><?= htmlspecialchars((string) ($detail['tugas_khusus'] ?? 'Belum diisi'), ENT_QUOTES, 'UTF-8') ?>
                                                        </dd>
                                                    <?php endif; ?>
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
                                                    <dd><?= htmlspecialchars((string) $detail['poin'], ENT_QUOTES, 'UTF-8') ?>
                                                    </dd>
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
                                                <?php if ($requiresTugas): ?>
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
                        <div class="mobile-violation-empty" data-mobile-empty-filtered hidden>Tidak ada data pada status ini.</div>
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
        src="<?= htmlspecialchars(app_seo_script_src('js/universal-table-filter.js', '../..'), ENT_QUOTES, 'UTF-8') ?>"></script>
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
