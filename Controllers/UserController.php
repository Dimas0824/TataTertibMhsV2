<?php
require_once __DIR__ . '/../helpers/path_helper.php';
app_require('config.php');
app_require('Models/Users.php');
app_require('helpers/flash_modal.php');

class UserController
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new Users();
    }

    public function login($username, $password, $userType = null)
    {
        try {
            $normalizedType = strtolower(trim((string) $userType));

            $authFlows = [
                'mahasiswa' => [
                    'auth' => fn() => $this->userModel->getMahasiswaLogin($username, $password),
                    'redirect' => 'views/pelanggaran/pelanggaranpage.php',
                ],
                'dosen' => [
                    'auth' => fn() => $this->userModel->getDosenLogin($username, $password),
                    'redirect' => 'views/pelanggaran/pelanggaran_dosen.php',
                ],
                'admin' => [
                    'auth' => fn() => $this->userModel->getAdminLogin($username, $password),
                    'redirect' => 'views/admin/home-admin.php',
                ],
            ];

            $typeAlias = [
                'nim' => 'mahasiswa',
                'nidn' => 'dosen',
                'nip' => 'admin',
            ];

            if (isset($typeAlias[$normalizedType])) {
                $normalizedType = $typeAlias[$normalizedType];
            }

            $defaultSequence = ['mahasiswa', 'dosen', 'admin'];
            if (isset($authFlows[$normalizedType])) {
                $sequence = array_merge([$normalizedType], array_values(array_diff($defaultSequence, [$normalizedType])));
            } else {
                $sequence = $defaultSequence;
            }

            foreach ($sequence as $role) {
                $user = ($authFlows[$role]['auth'])();
                if ($user) {
                    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
                    $_SESSION['username'] = $username;
                    $_SESSION['user_type'] = $role;
                    $_SESSION['user_data'] = $user;
                    set_app_flash_modal('success', 'Login berhasil.');
                    app_redirect($authFlows[$role]['redirect']);
                }
            }

            return false;

        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }

    public function logout()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        session_destroy();
        app_redirect('index.php');
    }

    public function getAllMahasiswa()
    {
        try {
            return $this->userModel->getAllMahasiswa();
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }

    public function getAdminName($id_admin)
    {
        try {
            return $this->userModel->getAdminName($id_admin);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            return null;
        }
    }
}
?>
