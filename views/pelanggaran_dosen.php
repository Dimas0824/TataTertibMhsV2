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

if ($_SESSION['user_type'] === 'mahasiswa') {
    header("Location: pelanggaranpage.php");
    exit();
}

// Ambil data user dari session
$userData = $_SESSION['user_data'];

// tabel
$pelanggaranController = new PelanggaranController();
$nidn = $userData['nidn'];
$pelanggaranDetail = $pelanggaranController->getDetailLaporanDosen($nidn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pelanggaran</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/perlanggaranPage.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
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
        'context' => 'views',
        'active' => 'pelanggaran',
    ]);
    ?>

    <div class="content">
        <?php
        render_app_header([
            'title' => 'Pelanggaran',
            'showLogin' => false,
            'loginHref' => 'login.php',
            'roleLabel' => 'Dosen',
        ]);
        ?>
        <div class="profile">
            <p><strong>Nama: <?= $userData['nama_lengkap'] ?></strong></p>
            <p><strong>NIP: <?= $userData['nidn'] ?></strong></p>
        </div>

        <h3>Tabel Pelanggaran</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Pelanggar</th>
                        <th>Pelanggaran</th>
                        <th>Tingkat Pelanggaran</th>
                        <th>Dosen Pelapor</th>
                        <th>Tugas Khusus</th>
                        <th>Surat</th>
                        <th>Poin</th>
                        <th>Status</th>
                        <th>Status Tugas</th>
                        <th>Edit laporan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pelanggaranDetail)) {
                        foreach ($pelanggaranDetail as $detail) { ?>
                            <tr>
                                <td><?= htmlspecialchars($detail['nama_mahasiswa']) ?></td>
                                <td><?= htmlspecialchars($detail['pelanggaran']) ?></td>
                                <td><?= htmlspecialchars($detail['tingkat']) ?></td>
                                <td><?= htmlspecialchars(string: $detail['dosen_pelapor']) ?></td>
                                <td><?= htmlspecialchars($detail['tugas_khusus'] ?? 'Tidak Ada Tugas') ?></td>
                                <td>
                                    <?php if (!empty($detail['surat'])): ?>
                                        <a href="<?= htmlspecialchars('../document/' . $detail['surat']) ?>" target="_blank">Unduh
                                            Surat Pernyataan</a>
                                    <?php else: ?>
                                        <span>Tidak ada file surat yang diunggah.</span>
                                    <?php endif; ?>
                                    <?php if (!empty($detail['pengumpulan_tgsKhusus'])): ?>
                                        <a href="<?= htmlspecialchars('../document/' . $detail['pengumpulan_tgsKhusus']) ?>"
                                            target="_blank">Unduh Tugas Khusus</a>
                                    <?php else: ?>
                                        <?php echo $detail['pengumpulan_tgsKhusus'] ?>
                                        <span>Tidak ada file tugas yang diunggah.</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($detail['poin']) ?></td>
                                <td><?= htmlspecialchars($detail['status_pelanggaran']) ?></td>
                                <?php if ($detail['tingkat'] === 'IV' || $detail['tingkat'] === 'V'): ?>
                                    <td>Tidak ada tugas</td>
                                <?php else: ?>
                                    <td><?= htmlspecialchars($detail['status_tugas']) ?></td>
                                <?php endif; ?>
                                <!-- tombol edit laporan -->
                                <td><button class="edit-laporan"><a href="edit-pelaporan.php?id=<?= $detail['id_detail'] ?>"><i
                                                class="fa-solid fa-pen-to-square"></i></a></button></td>
                            </tr>
                        <?php }
                    } else {
                        echo "<tr><td colspan='10'>Data pelanggaran tidak ditemukan.</td></tr>";
                    } ?>
                </tbody>
            </table>
            <div class="statement-button">
                <button onclick="window.location.href='pelaporan.php'">Laporkan</button>
            </div>
        </div>
    </div>
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
        <a href="mailto:someone@example.com" class="social-link"><i class="fa-solid fa-envelope" alt="Email"
                class="social-icon"></i></a>
    </div>
    <div class="footer-bottom">
        <p>© Copyright 2024 web Tatib. All Rights Reserved.</p>
    </div>
</footer>

</html>