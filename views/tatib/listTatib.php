<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once dirname(__DIR__, 2) . '/config.php';

require_once dirname(__DIR__, 2) . '/Controllers/TatibController.php';
require_once dirname(__DIR__, 2) . '/Controllers/UserController.php';
require_once dirname(__DIR__) . '/partials/app-shell.php';

if (isset($_GET['logout'])) {
    $userController = new UserController();
    $userController->logout();
    exit();
}

$tatibController = new TatibController();
$tatibData = $tatibController->ReadTatib();
$sanksiData = $tatibController->ReadSanksi();

$listTatibVariant = isset($_SESSION['username']) ? 'student' : 'guest';
$listTatibRole = null;
if (isset($_SESSION['user_type'])) {
    $listTatibRole = $_SESSION['user_type'] === 'dosen' ? 'Dosen' : 'Mahasiswa';
}

$escapeHtml = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$tingkatPointMap = [
    'I' => 32,
    'II' => 16,
    'III' => 8,
    'IV' => 4,
    'V' => 2,
];

$tatibTableColumns = [
    [
        'label' => 'Pelanggaran',
        'cellClass' => 'tatib-col-desc',
        'render' => static function (array $tatib) use ($escapeHtml): string {
            $deskripsi = trim((string) ($tatib['deskripsi'] ?? ''));
            return '<p class="tatib-rule-text" title="' . $escapeHtml($deskripsi) . '">' . $escapeHtml($deskripsi) . '</p>';
        },
    ],
    [
        'label' => 'Tingkat',
        'cellClass' => 'tatib-col-level',
        'render' => static function (array $tatib) use ($escapeHtml, $tingkatPointMap): string {
            $tingkat = strtoupper(trim((string) ($tatib['tingkat'] ?? '')));
            $point = (int) ($tingkatPointMap[$tingkat] ?? 0);
            ob_start();
            ?>
            <span class="tingkat-badge" title="<?= $escapeHtml('Tingkat ' . $tingkat . ' (' . $point . ' poin)') ?>">
                <span class="tingkat-badge__level">Tingkat <?= $escapeHtml($tingkat) ?></span>
                <span class="tingkat-badge__point"><?= $escapeHtml((string) $point) ?> poin</span>
            </span>
            <?php
            return (string) ob_get_clean();
        },
    ],
];

$tatibRowMetaBuilder = static function (array $tatib): array {
    $tingkat = strtoupper(trim((string) ($tatib['tingkat'] ?? '')));
    return [
        'search' => implode(' ', [
            (string) ($tatib['deskripsi'] ?? ''),
            $tingkat,
        ]),
        'filters' => [
            'tingkat' => $tingkat,
        ],
    ];
};

$tatibTableConfig = [
    'id' => 'tatib-table',
    'title' => 'Filter Data Tata Tertib',
    'description' => 'Gunakan pencarian atau pilih tingkat untuk menampilkan pelanggaran yang relevan.',
    'stats' => [
        ['label' => (is_array($tatibData) ? count($tatibData) : 0) . ' aturan'],
    ],
    'filters' => [
        [
            'key' => 'tingkat',
            'label' => 'Tingkat Pelanggaran',
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
        'label' => 'Cari Aturan',
        'placeholder' => 'Cari deskripsi pelanggaran atau tingkat',
    ],
    'columns' => $tatibTableColumns,
    'rows' => is_array($tatibData) ? $tatibData : [],
    'rowMetaBuilder' => $tatibRowMetaBuilder,
    'emptyMessage' => 'Data tata tertib belum tersedia.',
    'tableCardClass' => 'tatib-table-card',
    'tableAriaLabel' => 'Tabel tata tertib mahasiswa',
];

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Tata Tertib Mahasiswa Polinema | DiscipLink</title>
    <?php
    app_seo_meta_tags([
        'title' => 'Daftar Tata Tertib Mahasiswa Polinema | DiscipLink',
        'description' => 'Baca daftar tata tertib mahasiswa Polinema lengkap dengan tingkat pelanggaran dan sanksi. Gunakan DiscipLink untuk memahami aturan kampus secara ringkas.',
        'canonical_path' => '/',
        'image' => 'img/GRAHA-POLINEMA1-slider-01.webp',
    ]);
    ?>
    <?php app_seo_favicon_tags('../../'); ?>
    <link rel="stylesheet" href="../../css/global.css">
    <link rel="stylesheet" href="../../css/listTatib.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
</head>

<body>

    <?php
    render_app_sidebar([
        'variant' => $listTatibVariant,
        'context' => 'nested',
        'active' => 'tatib',
    ]);
    ?>

    <!-- Konten Utama -->
    <div class="content">
        <?php
        render_app_header([
            'title' => 'List Tata Tertib',
            'showLogin' => !isset($_SESSION['username']),
            'loginHref' => app_page_url('page.login'),
            'roleLabel' => $listTatibRole,
        ]);
        ?>

        <section class="tatib-hero">
            <div class="tatib-hero-copy">
                <span class="tatib-kicker">DiscipLink · Tata Tertib</span>
                <h2>Daftar Aturan Mahasiswa</h2>
                <p>Semua poin tata tertib dan sanksi disajikan ringkas agar mudah dipahami sebelum melakukan aktivitas
                    akademik.</p>
            </div>
            <div class="tatib-hero-stats" aria-label="Ringkasan data tata tertib">
                <article>
                    <span>Total Aturan</span>
                    <strong><?= is_array($tatibData) ? count($tatibData) : 0 ?></strong>
                </article>
                <article>
                    <span>Total Tingkat</span>
                    <strong>5</strong>
                </article>
                <article>
                    <span>Total Sanksi</span>
                    <strong><?= is_array($sanksiData) ? count($sanksiData) : 0 ?></strong>
                </article>
            </div>
        </section>

        <section class="tatib-main-card">
            <?php render_universal_filterable_table_component($tatibTableConfig); ?>

            <div class="sanksi-section">
                <h3>Sanksi Berdasarkan Tingkat</h3>
                <p class="sanksi-caption">Sanksi ditampilkan sesuai filter tingkat untuk memudahkan pemahaman aturan.
                </p>
                <?php
                if ($sanksiData) {
                    $groupedSanksi = [];
                    foreach ($sanksiData as $sanksi) {
                        $tingkat = (string) $sanksi['tingkat'];
                        $groupedSanksi[$tingkat][] = (string) $sanksi['deskripsi'];
                    }

                    foreach ($groupedSanksi as $tingkat => $sanksiList) {
                        echo '<div class="sanksi-tingkat" data-tingkat="' . htmlspecialchars($tingkat, ENT_QUOTES, 'UTF-8') . '">';
                        echo '<b>Tingkat ' . htmlspecialchars($tingkat, ENT_QUOTES, 'UTF-8') . '</b>';
                        echo '<ol>';
                        foreach ($sanksiList as $deskripsi) {
                            echo '<li>' . htmlspecialchars($deskripsi, ENT_QUOTES, 'UTF-8') . '</li>';
                        }
                        echo '</ol>';
                        echo '</div>';
                    }
                } else {
                    echo '<p class="sanksi-empty">Data sanksi belum tersedia.</p>';
                }
                ?>
            </div>
        </section>

        <?php
        render_app_footer([
            'context' => 'nested',
        ]);
        ?>
    </div>
    <script defer
        src="<?= htmlspecialchars(app_seo_script_src('js/universal-table-filter.js', '../..'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script>
        (function () {
            const filterSelect = document.querySelector('[data-table-tools][data-table-target="tatib-table"] [data-table-filter-key="tingkat"]');
            const sanksiItems = document.querySelectorAll('.sanksi-tingkat');

            if (!(filterSelect instanceof HTMLSelectElement) || sanksiItems.length === 0) {
                return;
            }

            const applySanksiFilter = () => {
                const selected = String(filterSelect.value || '').trim().toLowerCase();
                sanksiItems.forEach((item) => {
                    const level = String(item.getAttribute('data-tingkat') || '').trim().toLowerCase();
                    item.style.display = selected === '' || selected === level ? '' : 'none';
                });
            };

            filterSelect.addEventListener('change', applySanksiFilter);
            applySanksiFilter();
        })();
    </script>

</body>

</html>
