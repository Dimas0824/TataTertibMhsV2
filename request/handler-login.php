<?php
require_once __DIR__ . '/../helpers/path_helper.php';
require_once __DIR__ . '/../helpers/route_helper.php';
require_once __DIR__ . '/../helpers/token_helper.php';
require_once __DIR__ . '/../helpers/login_throttle_helper.php';
app_require('config.php');
app_require('controllers/UserController.php');
app_require('helpers/flash_modal.php');

app_session_start_if_needed();

$user = new UserController();
try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        app_verify_csrf();

        $userType = $_POST['user_type'] ?? null;
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        // Reject malformed credentials BEFORE the password verifier:
        // a NUL byte would let bcrypt truncate the compare (CWE-230), and an
        // over-long password would be silently truncated at 72 bytes.
        $invalidReason = app_login_input_invalid($username, $password);
        if ($invalidReason !== null) {
            app_audit_log('login_reject_input', [
                'actor_id' => substr($username, 0, 32),
                'detail'   => $invalidReason,
            ]);
            set_app_flash_modal('error', 'Invalid username or password.');
            app_redirect('views/auth/login.php');
        }

        if ($username === '' || $password === '') {
            set_app_flash_modal('error', 'Username dan password wajib diisi.');
            app_redirect('views/auth/login.php');
        }

        // Lockout is enforced from a server-side store (audit log), keyed on the
        // account identifier and the client IP — so discarding the session cookie
        // no longer resets it (CWE-307). The session flag is kept as a fast path.
        $sessionLocked = (int) ($_SESSION['__login_until'] ?? 0) > time();
        $throttle = app_login_throttle_status($username, app_login_client_ip());
        if ($sessionLocked || $throttle['locked']) {
            app_audit_log('login_locked', [
                'actor_id' => substr($username, 0, 32),
                'detail'   => 'durable check (' . ($throttle['reason'] !== '' ? $throttle['reason'] : 'session') . ')',
            ]);
            set_app_flash_modal('error', 'Terlalu banyak percobaan gagal. Coba lagi beberapa menit.');
            app_redirect('views/auth/login.php');
        }

        if (!$user->login($username, $password, $userType)) {
            app_audit_log('login_fail', ['actor_id' => substr($username, 0, 32)]);

            $_SESSION['__login_fails'] = (int) ($_SESSION['__login_fails'] ?? 0) + 1;
            if ($_SESSION['__login_fails'] >= APP_LOGIN_MAX_FAILS_ACTOR) {
                $_SESSION['__login_until'] = time() + APP_LOGIN_LOCK_WINDOW;
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

