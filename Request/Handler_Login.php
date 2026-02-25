<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once '../Controllers/UserController.php';

$user = new UserController();
try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $userType = $_POST['user_type'];

        if (!$user->login($username, $password)) {
            $_SESSION['login_error'] = 'Invalid username or password.';
            header("Location: ../views/login.php");
            exit();
        }
    }
} catch (Exception $e) {
    error_log('Pelanggaran Save Error: ' . $e->getMessage());
    $_SESSION['error_messages'] = [$e->getMessage()];
}
?>