<?php
session_start();
// echo realpath(__DIR__ . '/Controllers/UserController.php');
require_once __DIR__ . '/Controllers/UserController.php';
require_once __DIR__ . '/Controllers/NewsController.php';

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
    <title>Home Page</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
    <link rel="stylesheet" href="css/homepage.css">
</head>

<body>

    <?php
    include 'views/homepage.php';
    ?>

    <script src="js/script.js"></script>
</body>

</html>
