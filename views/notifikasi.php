<?php
session_start();
require_once '../Controllers/UserController.php';
require_once '../Controllers/PelanggaranController.php'; // Include PelanggaranController
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

// Ambil data user dari session
$userData = $_SESSION['user_data'];

// Initialize PelanggaranController
$pelanggaranController = new PelanggaranController();

// Get notifications based on user type
if ($_SESSION['user_type'] === 'mahasiswa') {
    $notifications = $pelanggaranController->getNotifikasiMahasiswa($userData['nim']);
} elseif ($_SESSION['user_type'] === 'dosen') {
    $notifications = $pelanggaranController->getNotifikasiDosen($userData['nidn']);
} else {
    $notifications = []; // Default to empty if user type is unknown
}

$notificationRole = $_SESSION['user_type'] === 'dosen' ? 'Dosen' : 'Mahasiswa';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Italiana&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
    <link rel="stylesheet" href="../css/notifikasi.css">
</head>
<body>
<?php
render_app_sidebar([
    'variant' => 'student',
    'context' => 'views',
    'active' => 'notifikasi',
]);
?>
    <div class="content">
        <?php
        render_app_header([
            'title' => 'Notifikasi',
            'showLogin' => false,
            'loginHref' => 'login.php',
            'roleLabel' => $notificationRole,
        ]);
        ?>
            <!-- Notifications Section -->
            <div class="notifications">
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-item">
                    <div class="icon">
                    <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="notification-content">
                        <p><strong><?= htmlspecialchars($notification['pesan']); ?></strong></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
</body>
<footer class="footer">
    <div class="footer-left">
    <img class="footer-logo" src="../img/logo aja.png" alt="Logo">
        <img class ="footer-logo" src="../img/logo.png" alt="logo polinema">
    </div>
    <div class="footer-center">
        <p>Jl. Soekarno Hatta No.9, Jatimulyo, Kec. Lowokwaru, <br>Kota Malang, Jawa Timur 65141</p>
        <p><a href="tel:(0341)404424" class="footer-link">(0341) 404424</a></p>
    </div>
    <div class="footer-right">
        <a href="https://instagram.com" class="social-link"><i class="fa-brands fa-instagram" alt="Instagram" class="social-icon"></i></a>
        <a href="https://wa.me/1234567890" class="social-link"><i class="fa-brands fa-whatsapp" alt="WhatsApp" class="social-icon"></i></a>
        <a href="https://wa.me/1234567890" class="social-link"><i class="fa-solid fa-envelope" alt="Email" class="social-icon"></i></a>
    </div>
    <div class="footer-bottom">
        <p>© Copyright 2024 web Tatib. All Rights Reserved.</p>
    </div>
</footer>
</html>
