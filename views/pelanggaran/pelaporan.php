<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/controllers/TatibController.php';
require_once dirname(__DIR__, 2) . '/controllers/UserController.php';
require_once dirname(__DIR__, 2) . '/controllers/PelanggaranController.php'; // Include PelanggaranController
require_once dirname(__DIR__) . '/partials/app-shell.php';
require_once dirname(__DIR__) . '/components/modals/pelaporan-cancel-modal.php';

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

// Ambil data user dari session
$userData = $_SESSION['user_data'];

$tatibController = new TatibController();
$tatibData = $tatibController->ReadTatib();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pelaporan Pelanggaran Mahasiswa | DiscipLink</title>
    <?php
    app_seo_meta_tags([
        'title' => 'Pelaporan Pelanggaran Mahasiswa | DiscipLink',
        'description' => 'Form pelaporan pelanggaran mahasiswa oleh dosen di DiscipLink untuk proses kedisiplinan kampus yang terstruktur.',
        'canonical_path' => '/',
        'image' => 'img/GRAHA-POLINEMA1-slider-01.webp',
        'robots' => 'noindex, nofollow',
    ]);
    ?>
    <?php app_seo_favicon_tags('../../'); ?>
    <link rel="stylesheet" href="../../css/global.css">
    <link rel="stylesheet" href="../../css/pelaporan.css">
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
    <!-- Select2 CSS -->
    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"
        onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    </noscript>
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
            'title' => 'Pelaporan',
            'showLogin' => false,
            'loginHref' => app_page_url('page.login'),
            'roleLabel' => 'Dosen',
        ]);
        ?>
        <section class="reporting-page">
            <div class="reporting-header">
                <h2>Form Pelaporan Pelanggaran</h2>
                <p>Isi data mahasiswa dan pelanggaran secara lengkap agar proses verifikasi berjalan cepat dan akurat.
                </p>
            </div>

            <div class="reporting-grid">
                <aside class="reporting-info-card" aria-label="Informasi pelapor">
                    <h3>Informasi Dosen</h3>
                    <div class="profile-details">
                        <p><span>Nama</span><strong><?= htmlspecialchars($userData['nama_lengkap']) ?></strong></p>
                        <p><span>NIP/NIDN</span><strong><?= htmlspecialchars($userData['nidn']) ?></strong></p>
                    </div>

                    <div class="reporting-steps">
                        <h4>Langkah Pelaporan</h4>
                        <ol>
                            <li>Masukkan NIM untuk mengambil data mahasiswa otomatis.</li>
                            <li>Pilih tingkat dan jenis pelanggaran sesuai kejadian.</li>
                            <li>Tentukan penanggung jawab tugas khusus (dosen pelapor atau DPA).</li>
                            <li>Simpan untuk mengirim laporan ke sistem.</li>
                        </ol>
                    </div>
                </aside>

                <div class="form-container">
                    <form id="pelanggaranForm" method="POST"
                        action="<?= htmlspecialchars(app_action_url('action.pelanggaran'), ENT_QUOTES, 'UTF-8') ?>"
                        data-lookup-endpoint="<?= htmlspecialchars(app_action_url('action.pelanggaran', ['action' => 'lookup_mahasiswa']), ENT_QUOTES, 'UTF-8') ?>"
                        data-search-endpoint="<?= htmlspecialchars(app_action_url('action.pelanggaran', ['action' => 'search_mahasiswa']), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="form-grid">
                            <div class="form-group form-group-wide">
                                <label for="nim">NIM Mahasiswa</label>
                                <input type="text" id="nim" name="nim" list="nimSuggestions" placeholder="Cari atau ketik NIM mahasiswa" required autocomplete="off">
                                <datalist id="nimSuggestions"></datalist>
                                <small id="nimHelpText">Ketik minimal 2 karakter untuk mencari NIM mahasiswa.</small>
                            </div>

                            <div class="form-group">
                                <label for="nama">Nama</label>
                                <input type="text" id="nama" name="nama" placeholder="Nama Lengkap" readonly>
                            </div>

                            <div class="form-group">
                                <label for="semester">Semester</label>
                                <input type="text" id="semester" name="semester" placeholder="Semester" readonly>
                            </div>

                            <div class="form-group form-group-wide">
                                <label for="prodi">Program Studi</label>
                                <input type="text" id="prodi" name="prodi" placeholder="Program Studi" readonly>
                            </div>

                            <div class="form-group">
                                <label for="tingkat">Tingkat</label>
                                <select id="tingkat" name="tingkat" required>
                                    <option value="">Pilih Tingkat</option>
                                    <option value="I">Tingkat 1</option>
                                    <option value="II">Tingkat 2</option>
                                    <option value="III">Tingkat 3</option>
                                    <option value="IV">Tingkat 4</option>
                                    <option value="V">Tingkat 5</option>
                                </select>
                            </div>

                            <div class="form-group form-group-wide">
                                <label for="jenisPelanggaran">Jenis Pelanggaran</label>
                                <select id="jenisPelanggaran" name="jenisPelanggaran" required>
                                    <option value="" readonly>Pilih Jenis Pelanggaran</option>
                                    <?php foreach ($tatibData as $tatib): ?>
                                        <option
                                            value="<?= htmlspecialchars(app_id_token('tatib', (int) $tatib['id_tata_tertib']), ENT_QUOTES, 'UTF-8') ?>"
                                            data-tingkat="<?= $tatib['tingkat'] ?>">
                                            <?= $tatib['deskripsi'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group form-group-wide" id="deskripsiTugas-container"
                                style="display: none;">
                                <label for="deskripsiTugas">Deskripsi Tugas Khusus</label>
                                <textarea id="deskripsiTugas" name="deskripsiTugas"
                                    placeholder="Jelaskan tugas khusus atau tindak lanjut yang diberikan."></textarea>
                            </div>

                            <div class="form-group form-group-wide" id="penanggungTugas-container"
                                style="display: none;">
                                <label for="penanggungTugas">Penanggung Jawab Tugas Khusus</label>
                                <select id="penanggungTugas" name="penanggungTugas">
                                    <option value="dosen" selected>Dosen Pelapor</option>
                                    <option value="dpa">DPA Mahasiswa</option>
                                </select>
                                <small>Jika memilih DPA, dosen pelapor hanya menerima notifikasi progres.</small>
                            </div>
                        </div>

                        <div class="form-buttons">
                            <button type="submit" name="store" class="btn btn-primary">Simpan Laporan</button>
                            <button type="button" class="btn btn-secondary" data-open-cancel-report-modal>Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <?php
        render_pelaporan_cancel_modal_component([
            'context' => 'nested',
            'redirectHref' => app_page_url('page.pelanggaran_dosen'),
        ]);
        ?>
        <?php
        render_app_footer([
            'context' => 'nested',
        ]);
        ?>
    </div>
    <script defer
        src="<?= htmlspecialchars(app_seo_script_src('js/pelaporan-form.js', '../..') . '?v=20260302-nim-search', ENT_QUOTES, 'UTF-8') ?>"></script>
</body>

</html>
