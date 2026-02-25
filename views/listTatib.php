<?php
session_start();
require_once __DIR__ . '/../config.php';

require_once "../Controllers/TatibController.php";
require_once '../Controllers/UserController.php';
require_once __DIR__ . '/partials/app-shell.php';

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
    <title>List Tatib</title>
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/listTatib.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="../js/script.js"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
</head>

<body>

    <?php
    render_app_sidebar([
        'variant' => $listTatibVariant,
        'context' => 'views',
        'active' => 'tatib',
    ]);
    ?>

    <!-- Konten Utama -->
    <div class="content">
        <?php
        render_app_header([
            'title' => 'List Tata Tertib',
            'showLogin' => !isset($_SESSION['username']),
            'loginHref' => 'login.php',
            'roleLabel' => $listTatibRole,
        ]);
        ?>

        <!-- Filter Section -->
        <div class="filter-container">
            <div>
                <label for="filter-tingkat">Filter Tingkat:</label>
                <select id="filter-tingkat" onchange="filterTingkat()">
                    <option value="">Semua Tingkat</option>
                    <option value="I">I</option>
                    <option value="II">II</option>
                    <option value="III">III</option>
                    <option value="IV">IV</option>
                    <option value="V">V</option>
                </select>
            </div>
        </div>

        <!-- Tabel Pelanggaran -->
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
                            <tr data-tingkat="<?= $tatib['tingkat'] ?>">
                                <td><?= $tatib['deskripsi'] ?></td>
                                <td><?= $tatib['tingkat'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Sanksi Section -->
        <div class="sanksi-section">
            <h3>Sanksi Berdasarkan Tingkat</h3>
            <?php
            if ($sanksiData) {
                // Mengelompokkan sanksi berdasarkan tingkat
                $groupedSanksi = [];
                foreach ($sanksiData as $sanksi) {
                    $tingkat = $sanksi['tingkat'];
                    $groupedSanksi[$tingkat][] = $sanksi['deskripsi'];
                }

                // Menampilkan sanksi per tingkat tanpa titik dua
                foreach ($groupedSanksi as $tingkat => $sanksiList) {
                    echo "<div class='sanksi-tingkat' data-tingkat='$tingkat'>";
                    echo "<b>Tingkat $tingkat</b>";
                    echo "<ul style='margin-left: 10px;'>";
                    foreach ($sanksiList as $index => $deskripsi) {
                        echo chr(codepoint: 97 + $index) . ") " . $deskripsi . "<br>";
                    }
                    echo "</ul>";
                    echo "</div>";
                }
            }
            ?>
            <!-- <div class="sanksi-tingkat" data-tingkat="II"><b>Tingkat II:</b> Skorsing maksimal 1 minggu.</div>
            <div class="sanksi-tingkat" data-tingkat="III"><b>Tingkat III:</b> Skorsing maksimal 1 bulan.</div>
            <div class="sanksi-tingkat" data-tingkat="IV"><b>Tingkat IV:</b> Dilarang mengikuti kegiatan akademik selama
                1 semester.</div>
            <div class="sanksi-tingkat" data-tingkat="V"><b>Tingkat V:</b> Dikeluarkan dari institusi (Drop Out).</div> -->
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
        <a href="https://wa.me/1234567890" class="social-link"><i class="fa-solid fa-envelope" alt="Email"
                class="social-icon"></i></a>
    </div>
    <div class="footer-bottom">
        <p>© Copyright 2024 web Tatib. All Rights Reserved.</p>
    </div>
</footer>

</html>
