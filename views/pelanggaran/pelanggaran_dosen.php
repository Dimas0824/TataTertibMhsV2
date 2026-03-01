<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once dirname(__DIR__, 2) . '/Controllers/UserController.php';
require_once dirname(__DIR__, 2) . '/Controllers/PelanggaranController.php';
require_once dirname(__DIR__) . '/partials/app-shell.php';
require_once dirname(__DIR__) . '/components/modals/admin-confirm-modal.php';

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
$confirmSelesaiAction = app_action_url('action.pelanggaran', ['action' => 'confirm_selesai']);
$deleteLaporanAction = app_action_url('action.pelanggaran', ['action' => 'delete']);
$totalLaporan = is_array($pelanggaranDetail) ? count($pelanggaranDetail) : 0;
$pendingLaporan = 0;
$activeLaporan = 0;
$selesaiLaporan = 0;
$dokumenBelumLengkap = 0;

if ($totalLaporan > 0) {
    foreach ($pelanggaranDetail as $item) {
        $tingkatItem = strtoupper(trim((string) ($item['tingkat'] ?? '')));
        $requiresTugasItem = in_array($tingkatItem, ['I', 'II', 'III'], true);
        $hasSuratItem = trim((string) ($item['surat'] ?? '')) !== '';
        $hasTugasItem = trim((string) ($item['pengumpulan_tgsKhusus'] ?? '')) !== '';
        $dokumenLengkapItem = $hasSuratItem && (!$requiresTugasItem || $hasTugasItem);
        $statusItem = strtolower(trim((string) ($item['status_pelanggaran'] ?? '')));

        if ($statusItem !== 'selesai' && $statusItem !== 'done') {
            $pendingLaporan++;
            $activeLaporan++;
        } else {
            $selesaiLaporan++;
        }

        if (!$dokumenLengkapItem) {
            $dokumenBelumLengkap++;
        }
    }
}

$escapeHtml = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$buildLecturerRowState = static function (array $detail): array {
    $tingkat = strtoupper(trim((string) ($detail['tingkat'] ?? '')));
    $requiresTugas = in_array($tingkat, ['I', 'II', 'III'], true);
    $hasSurat = trim((string) ($detail['surat'] ?? '')) !== '';
    $hasTugas = trim((string) ($detail['pengumpulan_tgsKhusus'] ?? '')) !== '';
    $dokumenLengkap = $hasSurat && (!$requiresTugas || $hasTugas);
    $statusLower = strtolower(trim((string) ($detail['status_pelanggaran'] ?? '')));
    $statusTugasLower = strtolower(trim((string) ($detail['status_tugas'] ?? '')));
    $isSelesai = ($statusLower === 'selesai' || $statusLower === 'done');
    $isTugasSelesai = in_array($statusTugasLower, ['sudah dikumpulkan', 'selesai', 'done'], true);
    $isDosenPelapor = ((int) ($detail['is_dosen_pelapor'] ?? 0)) === 1;
    $isPenanggungJawab = ((int) ($detail['is_penanggung_jawab'] ?? 0)) === 1;
    $delegasiKeDpa = ((int) ($detail['delegasi_tugas_ke_dpa'] ?? 0)) === 1;
    $namaPenanggungJawab = trim((string) ($detail['dosen_penanggung_jawab'] ?? ''));
    if ($namaPenanggungJawab === '') {
        $namaPenanggungJawab = trim((string) ($detail['dosen_pelapor'] ?? '-'));
    }
    $canConfirm = $dokumenLengkap && !$isSelesai && $isPenanggungJawab;
    $canEdit = !$isSelesai && !$isTugasSelesai && $isPenanggungJawab;
    $canDelete = $isDosenPelapor && !$isSelesai;
    $roleLabel = 'Dosen Pelapor';
    if ($isPenanggungJawab && $isDosenPelapor) {
        $roleLabel = 'Pelapor & Penanggung';
    } elseif ($isPenanggungJawab) {
        $roleLabel = 'DPA Penanggung Jawab';
    } elseif ($isDosenPelapor && $delegasiKeDpa) {
        $roleLabel = 'Dosen Pelapor (Notifikasi)';
    }

    $editLockNote = '';
    if (!$canEdit) {
        if (!$isPenanggungJawab) {
            $editLockNote = 'Edit dikunci. Penanganan dialihkan ke ' . $namaPenanggungJawab . '.';
        } else {
            $editLockNote = $isSelesai
                ? 'Edit dikunci karena laporan sudah selesai.'
                : 'Edit dikunci karena tugas sudah diselesaikan.';
        }
    }
    $confirmNote = 'Dokumen sudah lengkap. Laporan bisa dikonfirmasi selesai.';

    if ($isSelesai) {
        $confirmNote = 'Laporan sudah berstatus selesai.';
    } elseif (!$isPenanggungJawab) {
        $confirmNote = 'Konfirmasi ditangani oleh ' . $namaPenanggungJawab . '. Anda hanya menerima notifikasi.';
    } elseif (!$hasSurat) {
        $confirmNote = 'Menunggu upload surat pernyataan.';
    } elseif ($requiresTugas && !$hasTugas) {
        $confirmNote = 'Menunggu upload tugas khusus.';
    }

    $statusClass = 'status-pill';
    if ($statusLower === 'pending') {
        $statusClass .= ' status-pill--pending';
    } elseif ($isSelesai) {
        $statusClass .= ' status-pill--done';
    } else {
        $statusClass .= ' status-pill--progress';
    }

    $taskStatusLabel = 'Tidak diwajibkan';
    $taskStatusClass = 'status-pill status-pill-soft';
    if ($requiresTugas) {
        if ($statusTugasLower === 'menunggu penugasan dpa') {
            $taskStatusLabel = 'Menunggu penugasan DPA';
            $taskStatusClass = 'status-pill status-pill--pending';
        } elseif ($hasTugas) {
            $taskStatusLabel = 'Sudah dikumpulkan';
            $taskStatusClass = 'status-pill status-pill--done';
        } else {
            $taskStatusLabel = 'Belum dikumpulkan';
            $taskStatusClass = 'status-pill status-pill--pending';
        }
    }

    $tierClass = 'tier-pill';
    if ($tingkat === 'I') {
        $tierClass .= ' tier-pill--one';
    } elseif ($tingkat === 'II') {
        $tierClass .= ' tier-pill--two';
    } elseif ($tingkat === 'III') {
        $tierClass .= ' tier-pill--three';
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

    $requiredDocCount = $requiresTugas ? 2 : 1;
    $uploadedDocCount = ($hasSurat ? 1 : 0) + (($requiresTugas && $hasTugas) ? 1 : 0);
    $docProgressPercent = (int) round(($uploadedDocCount / $requiredDocCount) * 100);

    return [
        'tingkat' => $tingkat,
        'requiresTugas' => $requiresTugas,
        'hasSurat' => $hasSurat,
        'hasTugas' => $hasTugas,
        'dokumenLengkap' => $dokumenLengkap,
        'statusLower' => $statusLower,
        'isSelesai' => $isSelesai,
        'isTugasSelesai' => $isTugasSelesai,
        'isDosenPelapor' => $isDosenPelapor,
        'isPenanggungJawab' => $isPenanggungJawab,
        'delegasiKeDpa' => $delegasiKeDpa,
        'namaPenanggungJawab' => $namaPenanggungJawab,
        'roleLabel' => $roleLabel,
        'canConfirm' => $canConfirm,
        'canEdit' => $canEdit,
        'canDelete' => $canDelete,
        'editLockNote' => $editLockNote,
        'confirmNote' => $confirmNote,
        'statusClass' => $statusClass,
        'taskStatusLabel' => $taskStatusLabel,
        'taskStatusClass' => $taskStatusClass,
        'tierClass' => $tierClass,
        'pointClass' => $pointClass,
        'requiredDocCount' => $requiredDocCount,
        'uploadedDocCount' => $uploadedDocCount,
        'docProgressPercent' => $docProgressPercent,
    ];
};

$lecturerTableColumns = [
    [
        'label' => 'Kasus',
        'cellClass' => 'case-column',
        'render' => static function (array $detail) use ($escapeHtml, $buildLecturerRowState): string {
            $state = $buildLecturerRowState($detail);
            ob_start();
            ?>
            <div class="case-main">
                <p class="case-student"><?= $escapeHtml((string) ($detail['nama_mahasiswa'] ?? '')) ?></p>
                <p class="case-title"><?= $escapeHtml((string) ($detail['pelanggaran'] ?? '')) ?></p>
                <div class="case-tags">
                    <span class="<?= $escapeHtml((string) $state['tierClass']) ?>">Tingkat
                        <?= $escapeHtml((string) ($detail['tingkat'] ?? '')) ?></span>
                    <span class="<?= $escapeHtml((string) $state['pointClass']) ?>">
                        <?= $escapeHtml((string) ($detail['poin'] ?? '0')) ?> poin
                    </span>
                </div>
            </div>
            <details class="case-detail-toggle">
                <summary>Lihat rincian</summary>
                <dl class="case-detail-grid">
                    <?php
                    $tugasKhususText = 'Tidak diwajibkan';
                    if ($state['requiresTugas']) {
                        $rawTugasKhusus = trim((string) ($detail['tugas_khusus'] ?? ''));
                        if ($rawTugasKhusus !== '') {
                            $tugasKhususText = $rawTugasKhusus;
                        } elseif (strtolower(trim((string) ($detail['status_tugas'] ?? ''))) === 'menunggu penugasan dpa') {
                            $tugasKhususText = 'Menunggu penugasan dari DPA';
                        } else {
                            $tugasKhususText = 'Belum diisi';
                        }
                    }
                    ?>
                    <div>
                        <dt>Dosen Pelapor</dt>
                        <dd><?= $escapeHtml((string) ($detail['dosen_pelapor'] ?? '')) ?></dd>
                    </div>
                    <div>
                        <dt>Dosen Penanggung</dt>
                        <dd><?= $escapeHtml((string) $state['namaPenanggungJawab']) ?></dd>
                    </div>
                    <div>
                        <dt>Tugas Khusus</dt>
                        <dd><?= $escapeHtml($tugasKhususText) ?></dd>
                    </div>
                    <div>
                        <dt>Status Tugas</dt>
                        <dd><?= $escapeHtml((string) $state['taskStatusLabel']) ?></dd>
                    </div>
                    <div>
                        <dt>Peran Anda</dt>
                        <dd><?= $escapeHtml((string) $state['roleLabel']) ?></dd>
                    </div>
                </dl>
            </details>
            <?php
            return (string) ob_get_clean();
        },
    ],
    [
        'label' => 'Progress',
        'cellClass' => 'progress-column',
        'render' => static function (array $detail) use ($escapeHtml, $buildLecturerRowState): string {
            $state = $buildLecturerRowState($detail);
            ob_start();
            ?>
            <span class="<?= $escapeHtml((string) $state['statusClass']) ?>">
                <?= $escapeHtml((string) ($detail['status_pelanggaran'] ?? '')) ?>
            </span>
            <span class="<?= $escapeHtml((string) $state['taskStatusClass']) ?>">
                <?= $escapeHtml((string) $state['taskStatusLabel']) ?>
            </span>
            <p class="action-note"><?= $escapeHtml((string) $state['confirmNote']) ?></p>
            <?php
            return (string) ob_get_clean();
        },
    ],
    [
        'label' => 'Dokumen',
        'cellClass' => 'document-column',
        'render' => static function (array $detail) use ($escapeHtml, $buildLecturerRowState): string {
            $state = $buildLecturerRowState($detail);
            ob_start();
            ?>
            <div class="doc-progress">
                <span class="doc-progress__label">
                    <?= $escapeHtml((string) $state['uploadedDocCount']) ?>/<?= $escapeHtml((string) $state['requiredDocCount']) ?>
                    dokumen terunggah
                </span>
                <span class="doc-progress__track" aria-hidden="true">
                    <span class="doc-progress__value"
                        style="width: <?= $escapeHtml((string) $state['docProgressPercent']) ?>%;"></span>
                </span>
            </div>
            <ul class="doc-checklist">
                <li class="<?= $state['hasSurat'] ? 'is-ready' : 'is-missing' ?>">
                    <span>Surat Pernyataan</span>
                    <?php if ($state['hasSurat']): ?>
                        <a class="file-link"
                            href="<?= $escapeHtml(app_action_url('action.file_download', ['file' => (string) $detail['surat']])) ?>"
                            target="_blank" rel="noopener noreferrer">Lihat</a>
                    <?php else: ?>
                        <span class="muted-text">Belum ada</span>
                    <?php endif; ?>
                </li>
                <li class="<?= $state['requiresTugas'] ? ($state['hasTugas'] ? 'is-ready' : 'is-missing') : 'is-neutral' ?>">
                    <span>Tugas Khusus</span>
                    <?php if ($state['requiresTugas'] && $state['hasTugas']): ?>
                        <a class="file-link"
                            href="<?= $escapeHtml(app_action_url('action.file_download', ['file' => (string) $detail['pengumpulan_tgsKhusus']])) ?>"
                            target="_blank" rel="noopener noreferrer">Lihat</a>
                    <?php elseif ($state['requiresTugas']): ?>
                        <span class="muted-text">Belum ada</span>
                    <?php else: ?>
                        <span class="muted-text">Tidak wajib</span>
                    <?php endif; ?>
                </li>
            </ul>
            <?php
            return (string) ob_get_clean();
        },
    ],
    [
        'label' => 'Aksi',
        'cellClass' => 'action-column',
        'render' => static function (array $detail) use ($escapeHtml, $buildLecturerRowState, $confirmSelesaiAction, $deleteLaporanAction): string {
            $state = $buildLecturerRowState($detail);
            ob_start();
            ?>
            <div class="action-stack">
                <?php if ($state['canEdit']): ?>
                    <a class="edit-laporan"
                        href="<?= $escapeHtml(app_page_url('page.edit_pelaporan', ['id_detail' => (int) ($detail['id_detail'] ?? 0)])) ?>"
                        aria-label="Edit laporan">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                        Edit
                    </a>
                <?php else: ?>
                    <span class="muted-text">Edit dikunci</span>
                <?php endif; ?>
                <form method="POST" class="confirm-form"
                    action="<?= $escapeHtml($confirmSelesaiAction) ?>"
                    >
                    <input type="hidden" name="id_detail"
                        value="<?= $escapeHtml(app_id_token('detail_pelanggaran', (int) ($detail['id_detail'] ?? 0))) ?>">
                    <button type="button" class="confirm-laporan" <?= $state['canConfirm'] ? '' : 'disabled' ?>
                        data-admin-confirm-trigger data-admin-confirm-title="Konfirmasi laporan selesai?"
                        data-admin-confirm-message="Status laporan akan diubah menjadi selesai. Lanjutkan?"
                        data-admin-confirm-label="Ya, Konfirmasi" data-admin-confirm-action="submit-form"
                        data-admin-confirm-variant="primary">
                        <?= $escapeHtml($state['isSelesai'] ? 'Selesai' : 'Konfirmasi') ?>
                    </button>
                </form>
                <form method="POST" class="delete-form"
                    action="<?= $escapeHtml($deleteLaporanAction) ?>">
                    <input type="hidden" name="id_detail"
                        value="<?= $escapeHtml(app_id_token('detail_pelanggaran', (int) ($detail['id_detail'] ?? 0))) ?>">
                    <button type="button" class="delete-laporan" <?= $state['canDelete'] ? '' : 'disabled' ?> data-admin-confirm-trigger
                        data-admin-confirm-title="Hapus laporan?"
                        data-admin-confirm-message="Data laporan yang dihapus tidak dapat dikembalikan. Yakin lanjut?"
                        data-admin-confirm-label="Ya, Hapus" data-admin-confirm-action="submit-form">Hapus</button>
                </form>
                <?php if (!$state['canEdit']): ?>
                    <span class="action-note"><?= $escapeHtml((string) $state['editLockNote']) ?></span>
                <?php endif; ?>
                <?php if (!$state['canDelete']): ?>
                    <span class="action-note">Hapus hanya tersedia untuk dosen pelapor pada kasus aktif.</span>
                <?php endif; ?>
            </div>
            <?php
            return (string) ob_get_clean();
        },
    ],
];

$lecturerRowMetaBuilder = static function (array $detail) use ($buildLecturerRowState): array {
    $state = $buildLecturerRowState($detail);
    $statusTab = ((bool) $state['isSelesai']) ? 'selesai' : 'aktif';

    return [
        'search' => implode(' ', [
            (string) ($detail['nama_mahasiswa'] ?? ''),
            (string) ($detail['pelanggaran'] ?? ''),
            (string) ($detail['dosen_pelapor'] ?? ''),
            (string) ($detail['dosen_penanggung_jawab'] ?? ''),
            (string) ($detail['status_pelanggaran'] ?? ''),
            (string) ($detail['status_tugas'] ?? ''),
            (string) ($detail['tingkat'] ?? ''),
            (string) ($state['roleLabel'] ?? ''),
        ]),
        'filters' => [
            'status_tab' => $statusTab,
            'tingkat' => (string) $state['tingkat'],
            'dokumen' => ((bool) $state['dokumenLengkap']) ? 'lengkap' : 'belum',
        ],
    ];
};

$lecturerTableConfig = [
    'id' => 'lecturer-violation-table',
    'title' => 'Kasus Pelanggaran',
    'description' => 'Fokuskan perhatian pada progres kasus. Detail tambahan bisa dibuka per baris.',
    'stats' => [
        ['label' => $totalLaporan . ' kasus'],
        ['label' => $pendingLaporan . ' aktif', 'class' => 'table-stat-chip--warning'],
        ['label' => $dokumenBelumLengkap . ' dokumen belum lengkap'],
    ],
    'action' => [
        'label' => '+ Laporkan',
        'href' => app_page_url('page.pelaporan'),
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
        [
            'key' => 'dokumen',
            'label' => 'Dokumen',
            'options' => [
                ['value' => 'belum', 'label' => 'Belum Lengkap'],
                ['value' => 'lengkap', 'label' => 'Lengkap'],
            ],
        ],
    ],
    'search' => [
        'enabled' => true,
        'label' => 'Cari Kasus',
        'placeholder' => 'Cari nama mahasiswa, pelanggaran, pelapor, atau penanggung jawab',
    ],
    'columns' => $lecturerTableColumns,
    'rows' => $pelanggaranDetail,
    'rowMetaBuilder' => $lecturerRowMetaBuilder,
    'emptyMessage' => 'Data pelanggaran tidak ditemukan.',
    'tableCardClass' => 'table-card--desktop-only table-card--lecturer',
    'tableContainerClass' => 'table-container--compact',
    'tableClass' => 'violation-compact-table',
    'tableAriaLabel' => 'Tabel pelanggaran dosen',
];
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
                        <strong><?= htmlspecialchars((string) $totalLaporan, ENT_QUOTES, 'UTF-8') ?></strong>
                    </article>
                </div>
            </div>

            <?php render_universal_filterable_table_component($lecturerTableConfig); ?>
            <section class="table-card table-card--mobile-only" data-mobile-violation-section>
                <div class="mobile-violation-tools" data-mobile-violation-tools>
                    <div class="mobile-violation-tabs" role="group" aria-label="Filter status pelanggaran">
                        <button type="button" class="mobile-violation-tab-btn is-active" data-mobile-status-value="aktif"
                            aria-pressed="true">
                            Pelanggaran Aktif (<?= htmlspecialchars((string) $activeLaporan, ENT_QUOTES, 'UTF-8') ?>)
                        </button>
                        <button type="button" class="mobile-violation-tab-btn" data-mobile-status-value="selesai"
                            aria-pressed="false">
                            Selesai (<?= htmlspecialchars((string) $selesaiLaporan, ENT_QUOTES, 'UTF-8') ?>)
                        </button>
                    </div>
                    <div class="mobile-violation-controls">
                        <label class="mobile-violation-field mobile-violation-field--search">
                            <span>Cari Kasus</span>
                            <input type="search" data-mobile-search
                                placeholder="Cari nama mahasiswa, pelanggaran, pelapor, atau penanggung jawab">
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
                        <label class="mobile-violation-field">
                            <span>Dokumen</span>
                            <select data-mobile-filter-key="dokumen">
                                <option value="">Semua Dokumen</option>
                                <option value="belum">Belum Lengkap</option>
                                <option value="lengkap">Lengkap</option>
                            </select>
                        </label>
                    </div>
                    <p class="mobile-violation-result">
                        Menampilkan
                        <strong data-mobile-visible-count><?= htmlspecialchars((string) $activeLaporan, ENT_QUOTES, 'UTF-8') ?></strong>
                        dari
                        <strong data-mobile-total-count><?= htmlspecialchars((string) $totalLaporan, ENT_QUOTES, 'UTF-8') ?></strong>
                        kasus
                    </p>
                </div>
                <div class="mobile-violation-list" data-mobile-violation-list aria-label="Daftar pelanggaran mobile">
                    <?php if (!empty($pelanggaranDetail)): ?>
                        <?php foreach ($pelanggaranDetail as $mobileIndex => $detail):
                            $state = $buildLecturerRowState($detail);
                            $requiresTugas = (bool) ($state['requiresTugas'] ?? false);
                            $dokumenLengkap = (bool) ($state['dokumenLengkap'] ?? false);
                            $isSelesai = (bool) ($state['isSelesai'] ?? false);
                            $canConfirm = (bool) ($state['canConfirm'] ?? false);
                            $canEdit = (bool) ($state['canEdit'] ?? false);
                            $canDelete = (bool) ($state['canDelete'] ?? false);
                            $editLockNote = (string) ($state['editLockNote'] ?? '');
                            $confirmNote = (string) ($state['confirmNote'] ?? '');
                            $detailId = 'lecturer-' . (string) ((int) $mobileIndex + 1);
                            $sheetId = 'mobile-sheet-lecturer-' . $detailId;
                            $titleId = $sheetId . '-title';
                            $statusTabMobile = $isSelesai ? 'selesai' : 'aktif';
                            $tingkatMobile = strtolower(trim((string) ($detail['tingkat'] ?? '')));
                            $dokumenFilterMobile = $dokumenLengkap ? 'lengkap' : 'belum';
                            $mobileSearchText = trim(implode(' ', [
                                (string) ($detail['nama_mahasiswa'] ?? ''),
                                (string) ($detail['pelanggaran'] ?? ''),
                                (string) ($detail['dosen_pelapor'] ?? ''),
                                (string) ($detail['dosen_penanggung_jawab'] ?? ''),
                                (string) ($detail['status_pelanggaran'] ?? ''),
                                (string) ($detail['status_tugas'] ?? ''),
                                (string) ($detail['tingkat'] ?? ''),
                                (string) ($state['roleLabel'] ?? ''),
                            ]));
                            ?>
                            <article class="mobile-violation-card" data-mobile-card
                                data-mobile-card-status="<?= htmlspecialchars($statusTabMobile, ENT_QUOTES, 'UTF-8') ?>"
                                data-mobile-search="<?= htmlspecialchars($mobileSearchText, ENT_QUOTES, 'UTF-8') ?>"
                                data-mobile-filter-tingkat="<?= htmlspecialchars($tingkatMobile, ENT_QUOTES, 'UTF-8') ?>"
                                data-mobile-filter-dokumen="<?= htmlspecialchars($dokumenFilterMobile, ENT_QUOTES, 'UTF-8') ?>">
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
                                                    <dt>Dosen Penanggung</dt>
                                                    <dd><?= htmlspecialchars((string) ($state['namaPenanggungJawab'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Tugas Khusus</dt>
                                                    <?php if (!$requiresTugas): ?>
                                                        <dd>Tidak diwajibkan</dd>
                                                    <?php else: ?>
                                                        <?php
                                                        $mobileTugasKhusus = trim((string) ($detail['tugas_khusus'] ?? ''));
                                                        if ($mobileTugasKhusus === '' && strtolower(trim((string) ($detail['status_tugas'] ?? ''))) === 'menunggu penugasan dpa') {
                                                            $mobileTugasKhusus = 'Menunggu penugasan dari DPA';
                                                        }
                                                        if ($mobileTugasKhusus === '') {
                                                            $mobileTugasKhusus = 'Belum diisi';
                                                        }
                                                        ?>
                                                        <dd><?= htmlspecialchars($mobileTugasKhusus, ENT_QUOTES, 'UTF-8') ?></dd>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <dt>Status</dt>
                                                    <dd><?= htmlspecialchars($detail['status_pelanggaran'], ENT_QUOTES, 'UTF-8') ?></dd>
                                                </div>
                                                <div>
                                                    <dt>Status Tugas</dt>
                                                    <?php if (!$requiresTugas): ?>
                                                        <dd>Tidak diwajibkan</dd>
                                                    <?php else: ?>
                                                        <dd><?= htmlspecialchars((string) ($state['taskStatusLabel'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <dt>Peran Anda</dt>
                                                    <dd><?= htmlspecialchars((string) ($state['roleLabel'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
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
                                                            <?php if ($requiresTugas): ?>
                                                                <?php if (!empty($detail['pengumpulan_tgsKhusus'])): ?>
                                                                    <a class="file-link"
                                                                        href="<?= htmlspecialchars(app_action_url('action.file_download', ['file' => (string) $detail['pengumpulan_tgsKhusus']]), ENT_QUOTES, 'UTF-8') ?>"
                                                                        target="_blank" rel="noopener noreferrer">Tugas Khusus</a>
                                                                <?php else: ?>
                                                                    <span class="muted-text">Tugas belum diunggah</span>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="muted-text">Tugas khusus tidak diwajibkan</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </dd>
                                                </div>
                                            </dl>

                                            <div class="mobile-sheet-actions">
                                                <?php if ($canEdit): ?>
                                                    <a class="edit-laporan"
                                                        href="<?= htmlspecialchars(app_page_url('page.edit_pelaporan', ['id_detail' => (int) $detail['id_detail']]), ENT_QUOTES, 'UTF-8') ?>"
                                                        aria-label="Edit laporan">
                                                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                                        Edit Laporan
                                                    </a>
                                                <?php else: ?>
                                                    <span class="muted-text">Edit dikunci</span>
                                                <?php endif; ?>
                                                <form method="POST" class="confirm-form"
                                                    action="<?= htmlspecialchars($confirmSelesaiAction, ENT_QUOTES, 'UTF-8') ?>"
                                                    >
                                                    <input type="hidden" name="id_detail"
                                                        value="<?= htmlspecialchars(app_id_token('detail_pelanggaran', (int) $detail['id_detail']), ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="button" class="confirm-laporan" <?= $canConfirm ? '' : 'disabled' ?>
                                                        data-admin-confirm-trigger data-admin-confirm-title="Konfirmasi laporan selesai?"
                                                        data-admin-confirm-message="Status laporan akan diubah menjadi selesai. Lanjutkan?"
                                                        data-admin-confirm-label="Ya, Konfirmasi" data-admin-confirm-action="submit-form"
                                                        data-admin-confirm-variant="primary">
                                                        <?= htmlspecialchars($isSelesai ? 'Selesai' : 'Konfirmasi Selesai', ENT_QUOTES, 'UTF-8') ?>
                                                    </button>
                                                </form>
                                                <form method="POST" class="delete-form"
                                                    action="<?= htmlspecialchars($deleteLaporanAction, ENT_QUOTES, 'UTF-8') ?>"
                                                    >
                                                    <input type="hidden" name="id_detail"
                                                        value="<?= htmlspecialchars(app_id_token('detail_pelanggaran', (int) $detail['id_detail']), ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="button" class="delete-laporan" <?= $canDelete ? '' : 'disabled' ?> data-admin-confirm-trigger
                                                        data-admin-confirm-title="Hapus laporan?"
                                                        data-admin-confirm-message="Data laporan yang dihapus tidak dapat dikembalikan. Yakin lanjut?"
                                                        data-admin-confirm-label="Ya, Hapus" data-admin-confirm-action="submit-form">Hapus Laporan</button>
                                                </form>
                                                <?php if (!$canEdit): ?>
                                                    <span class="action-note"><?= htmlspecialchars($editLockNote, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                                <?php if (!$canDelete): ?>
                                                    <span class="action-note">Hapus hanya tersedia untuk dosen pelapor pada kasus aktif.</span>
                                                <?php endif; ?>
                                                <span class="action-note"><?= htmlspecialchars($confirmNote, ENT_QUOTES, 'UTF-8') ?></span>
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

    <?php
    render_admin_confirm_modal_component([
        'context' => 'nested',
    ]);

    render_app_flash_modal([
        'context' => 'nested',
    ]);
    ?>
    <script defer
        src="<?= htmlspecialchars(app_seo_script_src('js/universal-table-filter.js', '../..'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer
        src="<?= htmlspecialchars(app_seo_script_src('js/mobile-violation-cards.js', '../..'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>

</html>
