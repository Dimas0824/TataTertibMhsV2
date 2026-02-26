<?php
require_once '../Controllers/UserController.php';
require_once __DIR__ . '/partials/app-shell.php';

session_start();
if (isset($_SESSION['username'])) {
    // Redirect based on role
    if ($_SESSION['user_type'] === 'mahasiswa') {
        header("Location: pelanggaranpage.php");
        exit();
    } else if ($_SESSION['user_type'] === 'dosen') {
        header("Location: pelanggaran_dosen.php");
        exit();
    }
}



?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Login Mahasiswa</title>
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="../css/global.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">
</head>

<body>
    <?php
    render_app_sidebar([
        'variant' => 'guest',
        'context' => 'views',
        'active' => null,
    ]);
    ?>
    <div class="content">
        <?php
        render_app_header([
            'title' => 'Login',
            'showLogin' => false,
            'loginHref' => 'login.php',
            'roleLabel' => null,
        ]);
        ?>
        <main class="login-page">
            <section class="login-showcase" aria-hidden="true">
                <span class="showcase-chip">DiscipLink Access</span>
                <h2>Kelola tata tertib kampus dengan lebih rapi.</h2>
                <p>Masuk untuk melihat data pelanggaran, notifikasi, dan riwayat pelaporan dalam satu dashboard.</p>
                <ul>
                    <li><i class="fa-solid fa-shield-halved"></i>Login aman untuk setiap user</li>
                    <li><i class="fa-solid fa-chart-line"></i>Data pelanggaran terstruktur</li>
                </ul>
            </section>

            <section class="login-panel">
                <form class="login-form" method="POST" action="../Request/Handler_Login.php">
                    <h3>Selamat Datang</h3>
                    <p class="login-subtitle">Masuk ke akun DiscipLink kamu</p>
                    <input type="hidden" id="user-type" name="user_type" value="nim">

                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <i class="fa-regular fa-user" aria-hidden="true"></i>
                        <input type="text" placeholder="Masukkan Username" id="username" name="username"
                            autocomplete="username" required>
                    </div>

                    <label for="password">Kata Sandi</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input type="password" placeholder="Masukkan Kata Sandi" id="password" name="password"
                            autocomplete="current-password" required>
                    </div>

                    <button type="submit">Masuk</button>
                </form>
            </section>
        </main>
        <?php
        render_app_flash_modal([
            'context' => 'views',
        ]);
        ?>
    </div>
    <script src="../js/login.js">
    </script>

</body>

</html>