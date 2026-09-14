<?php
require_once __DIR__ . '/../helpers/path_helper.php';
require_once __DIR__ . '/../helpers/route_helper.php';
require_once __DIR__ . '/../helpers/token_helper.php';
app_require('config.php');
app_require('controllers/UserController.php');
app_require('helpers/flash_modal.php');

app_session_start_if_needed();

$user = new UserController();
try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        app_verify_csrf();

        // per-session throttle (session dies w/ idle expiry). Add an IP-keyed store if deployed open-internet.
        if ((int) ($_SESSION['__login_until'] ?? 0) > time()) {
            set_app_flash_modal('error', 'Terlalu banyak percobaan gagal. Coba lagi beberapa menit.');
            app_redirect('views/auth/login.php');
        }

        $userType = $_POST['user_type'] ?? null;
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            set_app_flash_modal('error', 'Username dan password wajib diisi.');
            app_redirect('views/auth/login.php');
        }

        if (!$user->login($username, $password, $userType)) {
            app_audit_log('login_fail', ['actor_id' => substr($username, 0, 32)]);
            $_SESSION['__login_fails'] = (int) ($_SESSION['__login_fails'] ?? 0) + 1;
            if ($_SESSION['__login_fails'] >= 5) {
                $_SESSION['__login_until'] = time() + 900;
                unset($_SESSION['__login_fails']);
                app_audit_log('login_locked', ['actor_id' => substr($username, 0, 32)]);
            }
            set_app_flash_modal('error', 'Invalid username or password.');
            app_redirect('views/auth/login.php');
        }
    }
} catch (Exception $e) {
    error_log('Login handler error: ' . $e->getMessage());
    set_app_flash_modal('error', 'Terjadi kesalahan. Silakan coba lagi.');
    app_redirect('views/auth/login.php');
}
?>
