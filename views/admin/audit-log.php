<?php
require_once dirname(__DIR__, 2) . '/helpers/token_helper.php';
app_session_start_if_needed();
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/helpers/audit_helper.php';
require_once dirname(__DIR__, 2) . '/controllers/UserController.php';
require_once dirname(__DIR__) . '/partials/app-shell.php';
require_once dirname(__DIR__) . '/components/tables/universal-filterable-table.php';

// deny-by-default di GATE, bukan di query
if (($_SESSION['user_type'] ?? '') !== 'admin') {
    if (!isset($_SESSION['username'])) {
        app_redirect_page('page.login');
    }
    if (($_SESSION['user_type'] ?? '') === 'mahasiswa') {
        app_redirect_page('page.pelanggaran');
    }
    if (($_SESSION['user_type'] ?? '') === 'dosen') {
        app_redirect_page('page.pelanggaran_dosen');
    }
    app_redirect_page('page.home');
}

$connect = $GLOBALS['connect'] ?? null;
$auditRows = [];
$knownEvents = [];
$pdoMessage = '';

if (($GLOBALS['connect'] ?? null) instanceof PDO) {
    $connect = $GLOBALS['connect'];
    try {
        $stmt = $connect->query(
            'SELECT event, actor_type, actor_id, sid, ip, detail, created_at
             FROM SECURITY_AUDIT_LOG
             ORDER BY id_audit DESC
             LIMIT 300'
        );
        $auditRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $knownEvents = array_values(array_unique(array_column($auditRows, 'event')));
        sort($knownEvents);
    } catch (Throwable $e) {
        // tabel belum dimigrasi / koneksi gagal: halaman harus tetap bisa dibuka, detail ke log saja
        error_log('Audit viewer query failed: ' . $e->getMessage());
        $pdoMessage = 'Log audit belum tersedia di database ini. Jalankan `php artisan migrate` untuk membuat SECURITY_AUDIT_LOG.';
        $auditRows = [];
    }
}

$escapeHtml = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$eventOptions = array_map(
    static function (string $event): array {
        return ['value' => $event, 'label' => $event];
    },
    $knownEvents
);

$eventClass = static function (string $event): string {
    if (preg_match('/(fail|denied|locked)$/i', $event) === 1) {
        return 'audit-event--bad';
    }
    if (substr($event, -3) === '_ok') {
        return 'audit-event--ok';
    }
    return 'audit-event';
};

$auditColumns = [
    [
        'label' => 'Waktu',
        'cellClass' => 'audit-cell--time',
        'render' => static function (array $row) use ($escapeHtml): string {
            return '<time datetime="' . $escapeHtml((string) ($row['created_at'] ?? '')) . '">'
                . $escapeHtml((string) ($row['created_at'] ?? '')) . '</time>';
        },
    ],
    [
        'label' => 'Event',
        'render' => static function (array $row) use ($escapeHtml, $eventClass): string {
            $event = (string) ($row['event'] ?? '');
            return '<span class="' . $escapeHtml($eventClass($event)) . '">' . $escapeHtml($event) . '</span>';
        },
    ],
    [
        'label' => 'Aktor',
        'render' => static function (array $row) use ($escapeHtml): string {
            $actor = trim((string) ($row['actor_id'] ?? ''));
            $type = trim((string) ($row['actor_type'] ?? ''));
            if ($actor === '') {
                return '<span class="audit-muted">anonim</span>';
            }
            $render = '<strong>' . $escapeHtml($actor) . '</strong>';
            if ($type !== '') {
                $render .= '<small>' . $escapeHtml($type) . '</small>';
            }
            return $render;
        },
    ],
    [
        'label' => 'Sesi (hash)',
        'render' => static function (array $row) use ($escapeHtml): string {
            $sid = (string) ($row['sid'] ?? '');
            return $sid !== '' ? '<code>' . $escapeHtml($sid) . '</code>' : '<span class="audit-muted">&mdash;</span>';
        },
    ],
    [
        'label' => 'IP',
        'render' => static function (array $row) use ($escapeHtml): string {
            return $escapeHtml((string) ($row['ip'] ?? ''));
        },
    ],
    [
        'label' => 'Detail',
        'cellClass' => 'audit-cell--detail',
        'render' => static function (array $row) use ($escapeHtml): string {
            $detail = (string) ($row['detail'] ?? '');
            return $detail !== '' ? $escapeHtml($detail) : '<span class="audit-muted">&mdash;</span>';
        },
    ],
];

$deniedCount = 0;
foreach ($auditRows as $auditRow) {
    if (preg_match('/(fail|denied|locked)$/i', (string) ($auditRow['event'] ?? '')) === 1) {
        $deniedCount++;
    }
}

$auditTableConfig = [
    'id' => 'audit-log-table',
    'title' => 'Security Audit Trail',
    'description' => '300 event keamanan terakhir: login, logout, unduhan, penolakan CSRF & otorisasi. Append-only — tidak bisa diedit dari UI.',
    'stats' => [
        ['label' => count($auditRows) . ' event tampil'],
        ['label' => $deniedCount . ' event penolakan/kegagalan', 'class' => 'table-stat-chip--warning'],
    ],
    'filters' => [
        [
            'key' => 'event',
            'label' => 'Event',
            'options' => $eventOptions,
        ],
    ],
    'search' => [
        'enabled' => true,
        'label' => 'Cari log',
        'placeholder' => 'Cari aktor, IP, atau detail',
    ],
    'columns' => $auditColumns,
    'rows' => $auditRows,
    'rowMetaBuilder' => static function (array $row): array {
        return [
            'search' => $row['event'] . ' ' . $row['actor_id'] . ' ' . $row['ip'] . ' ' . $row['detail'],
            'filters' => [
                'event' => $row['event'],
            ],
        ];
    },
    'emptyMessage' => $pdoMessage !== '' ? $pdoMessage : 'Belum ada event keamanan tercatat.',
    'tableCardClass' => 'admin-table-card',
    'tableAriaLabel' => 'Tabel log keamanan',
];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log | DiscipLink</title>
    <?php
    app_seo_meta_tags([
        'title' => 'Security Audit Log | DiscipLink',
        'description' => 'Panel admin untuk meninjau jejak event keamanan DiscipLink.',
        'canonical_path' => '/',
        'image' => 'img/GRAHA-POLINEMA1-slider-01.webp',
        'robots' => 'noindex, nofollow',
    ]);
    ?>
    <?php app_seo_favicon_tags(); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_asset_url('css/global.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_asset_url('css/news-admin.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
    <style>
        .audit-event--bad { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #fde8e8; color: #b91c1c; font-size: 12px; }
        .audit-event--ok { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #dcfce7; color: #15803d; font-size: 12px; }
        .audit-event { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #e2e8f0; color: #334155; font-size: 12px; }
        .audit-muted { color: #94a3b8; font-style: italic; }
        .audit-cell--detail { max-width: 340px; word-break: break-word; }
        .audit-cell--time { white-space: nowrap; font-variant-numeric: tabular-nums; color: #475569; }
        code { background: #0f172a0d; padding: 1px 5px; border-radius: 4px; font-size: 12px; }
    </style>
</head>

<body>
    <?php
    render_app_sidebar([
        'variant' => 'admin',
        'context' => 'nested',
        'active' => 'audit',
    ]);
    ?>
    <div class="content">
        <?php
        render_app_header([
            'title' => 'Security Audit Log',
            'showLogin' => false,
            'loginHref' => app_page_url('page.login'),
            'roleLabel' => 'Admin',
        ]);
        ?>
        <section class="admin-news-page">
            <div class="admin-news-hero">
                <div>
                    <span class="admin-news-kicker">DiscipLink Admin</span>
                    <h1>Audit Keamanan</h1>
                    <p>Jejak append-only event login/unduh/penolakan. Bukan pengganti monitoring, cukup untuk portofolio &amp; forensik ringan.</p>
                </div>
                <div class="admin-news-stat">
                    <span>Baris terakhir</span>
                    <strong><?= count($auditRows) ?></strong>
                </div>
            </div>

            <?php render_universal_filterable_table_component($auditTableConfig); ?>
        </section>

        <script defer
            src="<?= htmlspecialchars(app_seo_script_src('js/universal-table-filter.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
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
