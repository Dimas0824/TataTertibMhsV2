<?php
session_start();
require_once '../Controllers/UserController.php';
require_once '../Controllers/PelanggaranController.php';
require_once __DIR__ . '/partials/app-shell.php';
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['logout'])) {
    $userController = new UserController();
    $userController->logout();
    exit();
}

if ($_SESSION['user_type'] === 'dosen') {
    header("Location: pelanggaran_dosen.php");
    exit();
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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pelanggaran</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/perlanggaranPage.css">
    <link rel="stylesheet" href="../css/modal.css">
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
        'variant' => 'student',
        'context' => 'views',
        'active' => 'pelanggaran',
    ]);
    ?>

    <!-- Main Content -->
    <div class="content">
        <?php
        render_app_header([
            'title' => 'Pelanggaran',
            'showLogin' => false,
            'loginHref' => 'login.php',
            'roleLabel' => 'Mahasiswa',
        ]);
        ?>
        <div class="profile">
            <p><strong>Nama: <?= $userData['nama_lengkap'] ?></strong></p>
            <p><strong>NIM: <?= $userData['nim'] ?></strong></p>
            <p><strong>Semester: <?= $semester ?></strong></p>
        </div>

        <h3>Tabel Pelanggaran</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Pelanggaran</th>
                        <th>Tingkat Pelanggaran</th>
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
                                <td><?= htmlspecialchars($detail['tingkat']) ?></td>
                                <td><?= htmlspecialchars($detail['sanksi']) ?></td>
                                <td><?= htmlspecialchars($detail['nama_lengkap']) ?></td>
                                <td><?= htmlspecialchars($detail['tugas_khusus'] ?? 'Tidak Ada Tugas') ?></td>
                                <td><a href="<?= htmlspecialchars('../document/SURAT PERNYATAAN TI.pdf') ?>"
                                        target="_blank">Unduh File</a></td>
                                <td><?= htmlspecialchars($detail['poin']) ?></td>
                                <td><?= htmlspecialchars($detail['status']) ?></td>
                                <td>
                                    <form class="uploadForm" enctype="multipart/form-data">
                                        <input type="hidden" name="id_detail" value="<?= $detail['id_detail'] ?>">
                                        <input type="file" name="suratPernyataan" required>
                                        <button type="button" class="submit-btn uploadButton">Upload Surat Pernyataan</button>
                                    </form>
                                    <?php if (in_array($detail['tingkat'], ['I', 'II', 'III'])):  // Check for Roman numerals ?>
                                        <form class="uploadForm" enctype="multipart/form-data">
                                            <input type="hidden" name="id_detail" value="<?= $detail['id_detail'] ?>">
                                            <input type="file" name="tugasKhusus" required>
                                            <button type="button" class="submit-btn uploadButton">Upload Tugas Khusus</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php }
                    } else {
                        echo "<tr><td colspan='9'>Data pelanggaran tidak ditemukan.</td></tr>";
                    } ?>
                </tbody>
            </table>
        </div>
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

    </div>

    <!-- JavaScript -->
    <script src="../js/script-pelanggaran.js"></script>
    <script>
        document.querySelectorAll('.uploadButton').forEach(button => {
            button.addEventListener('click', function () {
                const form = this.closest('form');
                const formData = new FormData(form);

                // Send the AJAX request
                fetch('../Request/Handler_uploads.php', {
                    method: 'POST',
                    body: formData,
                })
                    .then(response => response.text())
                    .then(result => {
                        alert(result); // Show success message
                        form.reset(); // Reset the form fields
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Gagal mengunggah file.');
                    });
            });
        });
    </script>
</body>
<footer class="footer">
    <div class="footer-left">
        <img class="footer-logo" src="../img/logo aja.png" alt="Logo">
        <img class="footer-logo" src="../img/logo.png" alt="logo polinema">
    </div>
    <div class="footer-center">
        <p>Jl. Soekarno Hatta No.9, Jatimulyo, Kec. Lowokwaru, <br>Kota Malang, Jawa Timur 65141</p>
        <p><a href="tel:(0341)404424" class="footer-link">(0341) 404424</a></p>
    </div>
    <div class="footer-right">
        <a href="https://instagram.com" class="social-link"><i class="fa-brands fa-instagram" alt="Instagram"
                class="social-icon"></i></a>
        <a href="https://wa.me/1234567890" class="social-link"><i class="fa-brands fa-whatsapp" alt="WhatsApp"
                class="social-icon"></i></a>
        <a href="https://wa.me/1234567890" class="social-link"><i class="fa-solid fa-envelope" alt="Email"
                class="social-icon"></i></a>
    </div>
    <div class="footer-bottom">
        <p>© Copyright 2024 web Tatib. All Rights Reserved.</p>
    </div>
</footer>

</html>