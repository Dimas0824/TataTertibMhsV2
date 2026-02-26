<!-- edit pelaporan -->
<?php
session_start();

require_once dirname(__DIR__, 2) . '/Controllers/TatibController.php';
require_once dirname(__DIR__, 2) . '/Controllers/UserController.php';
require_once dirname(__DIR__, 2) . '/Controllers/PelanggaranController.php'; // Include PelanggaranController
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
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
} else {
    $id = 0;
}

$pelanggar = new PelanggaranController();
$detailPelanggar = $pelanggar->getDetailPelanggar($id);
// Ambil data user dari session
$userData = $_SESSION['user_data'];

$tatibController = new TatibController();
$tatibData = $tatibController->ReadTatib();
$sanksiData = $tatibController->ReadSanksi();

// semester
$detailPelanggar['angkatan'];
$currentYear = date('Y');
$currentMonth = date('n');
$yearDiff = $currentYear - $detailPelanggar['angkatan'];
$semester = ($yearDiff * 2);
if ($currentMonth >= 8) { // Semester ganjil dimulai sekitar Agustus
    $semester += 1;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pelaporan Pelanggaran | DiscipLink</title>
    <?php
    app_seo_meta_tags([
        'title' => 'Edit Pelaporan Pelanggaran | DiscipLink',
        'description' => 'Halaman edit pelaporan pelanggaran mahasiswa pada panel dosen DiscipLink.',
        'canonical_path' => '/views/pelanggaran/edit-pelaporan.php',
        'image' => 'img/GRAHA-POLINEMA1-slider-01.webp',
        'robots' => 'noindex, nofollow',
    ]);
    ?>
    <?php app_seo_favicon_tags('../../'); ?>
    <link rel="stylesheet" href="../../css/global.css">
    <link rel="stylesheet" href="../../css/pelaporan.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script>
        (function () {
            var loaded = false;
            var pending = null;

            function loadScript(src) {
                return new Promise(function (resolve, reject) {
                    var script = document.createElement('script');
                    script.src = src;
                    script.async = true;
                    script.onload = resolve;
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
            }

            function loadVendorScripts() {
                if (loaded) {
                    return Promise.resolve();
                }
                if (pending) {
                    return pending;
                }

                pending = loadScript('https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js')
                    .then(function () {
                        return loadScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js');
                    })
                    .then(function () {
                        loaded = true;
                        window.dispatchEvent(new Event('disciplink:select2-ready'));
                    })
                    .catch(function () {
                        pending = null;
                    });

                return pending;
            }

            ['pointerdown', 'touchstart', 'focusin', 'keydown'].forEach(function (eventName) {
                window.addEventListener(eventName, loadVendorScripts, { once: true });
            });

            window.addEventListener('load', function () {
                setTimeout(loadVendorScripts, 1400);
            }, { once: true });
        })();
    </script>
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
            'title' => 'Edit Pelaporan',
            'showLogin' => false,
            'loginHref' => '../auth/login.php',
            'roleLabel' => 'Dosen',
        ]);
        ?>
        <section class="reporting-page">
            <div class="reporting-header">
                <h2>Edit Pelaporan Pelanggaran</h2>
                <p>Perbarui data pelanggaran mahasiswa agar status tindak lanjut tetap akurat dan terdokumentasi dengan baik.</p>
            </div>

            <div class="reporting-grid">
                <aside class="reporting-info-card" aria-label="Informasi dosen pelapor">
                    <h3>Informasi Dosen</h3>
                    <div class="profile-details">
                        <p><span>Nama</span><strong><?= htmlspecialchars($userData['nama_lengkap']) ?></strong></p>
                        <p><span>NIP/NIDN</span><strong><?= htmlspecialchars($userData['nidn']) ?></strong></p>
                    </div>

                    <div class="reporting-steps">
                        <h4>Panduan Edit</h4>
                        <ol>
                            <li>Pastikan identitas mahasiswa sesuai.</li>
                            <li>Sesuaikan tingkat, jenis pelanggaran, dan sanksi.</li>
                            <li>Perbarui deskripsi pelanggaran atau tugas khusus.</li>
                            <li>Simpan perubahan untuk memperbarui laporan.</li>
                        </ol>
                    </div>
                </aside>

                <div class="form-container">
                    <form id="pelanggaranForm" method="POST" action="../../Request/Handler_Pelaporan.php">
                        <input type="hidden" name="id_detail" value="<?= $id ?>">

                        <div class="form-grid">
                            <div class="form-group form-group-wide">
                                <label for="nim">NIM Mahasiswa</label>
                                <input type="text" id="nim" name="nim" value="<?= htmlspecialchars($detailPelanggar['nim'] ?? '') ?>"
                                    required>
                                <small id="nimHelpText">Sesuaikan NIM jika data pelanggar perlu dikoreksi.</small>
                            </div>

                            <div class="form-group">
                                <label for="nama">Nama</label>
                                <input type="text" id="nama" name="nama"
                                    value="<?= htmlspecialchars($detailPelanggar['nama_lengkap'] ?? '') ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label for="semester">Semester</label>
                                <input type="text" id="semester" name="semester" value="<?= htmlspecialchars($semester ?? '') ?>"
                                    readonly>
                            </div>

                            <div class="form-group">
                                <label for="tingkat">Tingkat</label>
                                <select id="tingkat" name="tingkat" required>
                                    <option value="">Pilih Tingkat</option>
                                    <option value="I" <?= (($detailPelanggar['tingkat'] ?? '') === 'I') ? 'selected' : '' ?>>Tingkat 1</option>
                                    <option value="II" <?= (($detailPelanggar['tingkat'] ?? '') === 'II') ? 'selected' : '' ?>>Tingkat 2</option>
                                    <option value="III" <?= (($detailPelanggar['tingkat'] ?? '') === 'III') ? 'selected' : '' ?>>Tingkat 3</option>
                                    <option value="IV" <?= (($detailPelanggar['tingkat'] ?? '') === 'IV') ? 'selected' : '' ?>>Tingkat 4</option>
                                    <option value="V" <?= (($detailPelanggar['tingkat'] ?? '') === 'V') ? 'selected' : '' ?>>Tingkat 5</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="sanksi">Sanksi</label>
                                <select id="sanksi" name="sanksi" required>
                                    <option value="">Pilih Sanksi</option>
                                    <?php foreach ($sanksiData as $sanksi): ?>
                                        <option value="<?= $sanksi['id_sanksi'] ?>" data-tingkat="<?= $sanksi['tingkat'] ?>"
                                            <?= ((string) ($detailPelanggar['id_sanksi'] ?? '') === (string) $sanksi['id_sanksi']) ? 'selected' : '' ?>>
                                            <?= $sanksi['deskripsi'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group form-group-wide">
                                <label for="jenisPelanggaran">Jenis Pelanggaran</label>
                                <select id="jenisPelanggaran" name="jenisPelanggaran" required>
                                    <option value="" readonly>Pilih Jenis Pelanggaran</option>
                                    <?php foreach ($tatibData as $tatib): ?>
                                        <option value="<?= $tatib['id_tata_tertib'] ?>" data-tingkat="<?= $tatib['tingkat'] ?>"
                                            <?= ((string) ($detailPelanggar['id_tata_tertib'] ?? '') === (string) $tatib['id_tata_tertib']) ? 'selected' : '' ?>>
                                            <?= $tatib['deskripsi'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group form-group-wide">
                                <label for="deskripsiPelanggaran">Deskripsi Pelanggaran</label>
                                <textarea id="deskripsiPelanggaran" name="deskripsiPelanggaran"
                                    required><?= htmlspecialchars($detailPelanggar['detail_pelanggaran'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group form-group-wide" id="deskripsiTugas-container" style="display: none;">
                                <label for="deskripsiTugas">Deskripsi Tugas Khusus</label>
                                <textarea id="deskripsiTugas"
                                    name="deskripsiTugas"><?= htmlspecialchars($detailPelanggar['tugas_khusus'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="form-buttons">
                            <button type="submit" name="update" class="btn btn-primary">Simpan Perubahan</button>
                            <button onclick="showConfirmation()" type="button" class="btn btn-secondary">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        </div>
        <script defer
            src="<?= htmlspecialchars(app_seo_script_src('js/script_pelaporan.js', '../..'), ENT_QUOTES, 'UTF-8') ?>"></script>
        <script>
            function showConfirmation() {

                var confirmAction = confirm("Apakah Anda yakin ingin keluar dari pelaporan page?");

                if (confirmAction) {
                    window.location.href = "pelanggaran_dosen.php";
                }

            }
        </script>
        <?php
        render_app_footer([
            'context' => 'nested',
        ]);
        ?>
    </div>
</body>

</html>