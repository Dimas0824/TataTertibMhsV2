<?php
session_start();
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
        'canonical_path' => '/views/tatib/listTatib.php',
        'image' => 'img/GRAHA-POLINEMA1-slider-01.webp',
    ]);
    ?>
    <?php app_seo_favicon_tags('../../'); ?>
    <link rel="stylesheet" href="../../css/global.css">
    <link rel="stylesheet" href="../../css/listTatib.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script defer
        src="<?= htmlspecialchars(app_seo_script_src('js/script.js', '../..'), ENT_QUOTES, 'UTF-8') ?>"></script>
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
            'loginHref' => '../auth/login.php',
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
            <div class="filter-container">
                <div class="filter-copy">
                    <h3>Filter Data Tata Tertib</h3>
                    <p>Pilih tingkat untuk menampilkan pelanggaran dan sanksi yang relevan.</p>
                </div>
                <div class="filter-field">
                    <label for="filter-tingkat">Tingkat Pelanggaran</label>
                    <div class="select-wrap">
                        <select id="filter-tingkat" onchange="filterTingkat()">
                            <option value="">Semua Tingkat</option>
                            <option value="I">I</option>
                            <option value="II">II</option>
                            <option value="III">III</option>
                            <option value="IV">IV</option>
                            <option value="V">V</option>
                        </select>
                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table id="tatib-table">
                    <thead>
                        <tr>
                            <th>Pelanggaran</th>
                            <th>Tingkat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($tatibData): ?>
                            <?php foreach ($tatibData as $tatib): ?>
                                <tr data-tingkat="<?= htmlspecialchars((string) $tatib['tingkat'], ENT_QUOTES, 'UTF-8') ?>">
                                    <td><?= htmlspecialchars((string) $tatib['deskripsi'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><span class="tingkat-badge">Tingkat
                                            <?= htmlspecialchars((string) $tatib['tingkat'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" class="table-empty">Data tata tertib belum tersedia.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

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

</body>

</html>