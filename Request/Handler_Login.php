<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once '../Controllers/UserController.php';
require_once __DIR__ . '/../helpers/flash_modal.php';

$user = new UserController();
try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = $_POST['username'];
        $password = $_POST['password'];

        if (!$user->login($username, $password)) {
            set_app_flash_modal('error', 'Invalid username or password.');
            header("Location: ../views/login.php");
            exit();
        }
    }
} catch (Exception $e) {
    error_log('Pelanggaran Save Error: ' . $e->getMessage());
    set_app_flash_modal('error', $e->getMessage());
    header("Location: ../views/login.php");
    exit();
}
?>
