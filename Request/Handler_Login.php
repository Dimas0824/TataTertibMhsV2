<?php
session_start();
require_once __DIR__ . '/../helpers/path_helper.php';
app_require('config.php');
app_require('Controllers/UserController.php');
app_require('helpers/flash_modal.php');

$user = new UserController();
try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = $_POST['username'];
        $password = $_POST['password'];

        if (!$user->login($username, $password)) {
            set_app_flash_modal('error', 'Invalid username or password.');
            app_redirect('views/auth/login.php');
        }
    }
} catch (Exception $e) {
    error_log('Pelanggaran Save Error: ' . $e->getMessage());
    set_app_flash_modal('error', $e->getMessage());
    app_redirect('views/auth/login.php');
}
?>