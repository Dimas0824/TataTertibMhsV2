<?php
session_start();
// echo realpath(__DIR__ . '/Controllers/UserController.php');
require_once __DIR__ . '/Controllers/UserController.php';
require_once __DIR__ . '/Controllers/NewsController.php';
require_once __DIR__ . '/helpers/seo_helper.php';

app_seo_enforce_canonical_host();

if (isset($_GET['logout'])) {
    $userController = new UserController();
    $userController->logout();
    exit();
}
$newsController = new NewsController();
$newsData = $newsController->ReadNews();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tata Tertib Mahasiswa Polinema - Aturan &amp; Sanksi | DiscipLink</title>
    <?php
    app_seo_meta_tags([
        'title' => 'Tata Tertib Mahasiswa Polinema - Aturan & Sanksi | DiscipLink',
        'description' => 'Pusat tata tertib mahasiswa Polinema: aturan kampus, pelanggaran, sanksi, dan berita kedisiplinan terbaru dalam satu platform DiscipLink.',
        'canonical_path' => '/',
        'image' => 'img/GRAHA-POLINEMA1-slider-01.webp',
    ]);
    ?>
    <link rel="icon" type="image/png" href="img/logo aja.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preload" as="image" href="img/GRAHA-POLINEMA1-slider-01.webp" fetchpriority="high">
    <link rel="preload" as="style" href="css/global.css">
    <link rel="preload" as="style" href="css/homepage.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
    <link rel="stylesheet" href="css/homepage.css">
</head>

<body>

    <?php
    include 'views/public/homepage.php';
    ?>

    <script defer src="js/homepage.js"></script>
</body>

</html>
