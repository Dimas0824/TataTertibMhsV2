<?php
session_start();
require_once __DIR__ . '/../config.php';

require_once "../Controllers/TatibController.php";
require_once '../Controllers/UserController.php';
require_once __DIR__ . '/partials/app-shell.php';

if (isset($_SESSION['username'])) {
    // Redirect based on role
    if ($_SESSION['user_type'] === 'mahasiswa') {
        header("Location: pelanggaranpage.php");
        exit();
    } else if ($_SESSION['user_type'] === 'dosen') {
        header("Location: pelanggaran_dosen.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}

if (isset($_GET['logout'])) {
    $userController = new UserController();
    $userController->logout();
    exit();
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
    <title>Home Page</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Italiana&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/tatib-admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
</head>

<body>
    <?php
    render_app_sidebar([
        'variant' => 'admin',
        'context' => 'views',
        'active' => 'tatib',
    ]);
    ?>
    <div class="content">
        <?php
        render_app_header([
            'title' => 'Tata Tertib Admin',
            'showLogin' => false,
            'loginHref' => 'login.php',
            'roleLabel' => 'Admin',
        ]);
        ?>
        <div class="judul">
            <h1>DISCIPLINE</h1>
        </div>

        <button class="add-button" id="addButton">Tambah</button>
        <div class="table-container">
            <table id="tatib-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>admin</th>
                        <th>Pelanggaran</th>
                        <th>Tingkat</th>
                        <th>Poin</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1 ?>
                    <tr>
                        <?php if ($tatibData): ?>
                            <?php foreach ($tatibData as $tatib): ?>
                            <tr>
                                <td><?= $i ?></td>
                                <td><?= $tatib['id_adminTatib'] ?></td>
                                <td><?= $tatib['deskripsi'] ?></td>
                                <td><?= $tatib['tingkat'] ?></td>
                                <td><?= $tatib['poin'] ?></td>
                                <td class="button-cell">
                                    <!-- <button class="edit-button" id="editbutton"><i class="fa-solid fa-pen-to-square"></i></button>  -->
                                    <!--tombol delete -->
                                    <form action="../Request/Handler_Tatib.php" method="post">
                                        <input type="hidden" name="id_tatib" value="<?= $tatib['id_tata_tertib'] ?>">
                                        <button class="delete" id="delete" name="delete"
                                            onclick="return confirm('Apakah anda yakin ingin menghapus?');"><i
                                                class="fa-solid fa-trash"></i></button>
                                </td>
                                </form>
                            </tr>
                            <?php $i++ ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Modal edit -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Edit Pelanggaran</h2>
                <form id="editForm">
                    <label for="nomor">No.</label>
                    <input type="text" id="nomor" name="nomor" readonly>

                    <label for="editAdmin">Id Admin:</label>
                    <input type="text" id="admin" name="admin" required>

                    <label for="editKonten">Pelanggaran:</label>
                    <textarea id="editKonten" name="konten" rows="4" required></textarea>

                    <label for="editTingkat">Tingkat:</label>
                    <select id="tingkat" name="tingkat" required>
                        <option value="">Pilih Tingkat</option>
                        <option value="I">Tingkat I</option>
                        <option value="II">Tingkat II</option>
                        <option value="III">Tingkat III</option>
                        <option value="IV">Tingkat IV</option>
                        <option value="V">Tingkat V</option>

                    </select>

                    <label for="editPoin">Poin:</label>
                    <input type="text" id="poin" name="poin" readonly>

                    <button type="submit" class="save-button">Simpan</button>
                </form>
            </div>
        </div>

        <!-- Modal insert-->
        <div id="insertModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Tambah Pelanggaran</h2>
                <form id="insertForm" method="POST" action="../Request/Handler_Tatib.php">
                    <label for="insertAdmin">Id Admin:</label>
                    <input type="text" id="admin" name="admin" value="<?= $userData['id_admin'] ?>" required readonly>

                    <label for="insertDeskripsi">Pelanggaran:</label>
                    <textarea id="insertDeskripsi" name="deskripsi" rows="4" required></textarea>

                    <label for="insertTingkat">Tingkat:</label>
                    <select id="tingkat" name="tingkat" required>
                        <option value="">Pilih Tingkat</option>
                        <option value="I">Tingkat I</option>
                        <option value="II">Tingkat II</option>
                        <option value="III">Tingkat III</option>
                        <option value="IV">Tingkat IV</option>
                        <option value="V">Tingkat V</option>

                    </select>

                    <label for="editPoin">Poin:</label>
                    <input type="text" id="poin" name="poin" readonly>

                    <button type="submit" class="save-button" name="store">Simpan</button>
                </form>
            </div>
        </div>

        <!-- javascript -->
        <script src="../js/admin-tatib.js"></script>

        <!-- footer -->

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
