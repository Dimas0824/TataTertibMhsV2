<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Models/Users.php';
require_once __DIR__ . '/../helpers/flash_modal.php';

class UserController {
    private $userModel;

    public function __construct() {
        $this->userModel = new Users();
    }

    public function login($username, $password) {
        try {
            // Check mahasiswa login
            $user = $this->userModel->getMahasiswaLogin($username, $password);
            if ($user) {
                session_start();
                $_SESSION['username'] = $username;
                $_SESSION['user_type'] = 'mahasiswa';
                $_SESSION['user_data'] = $user;
                set_app_flash_modal('success', 'Login berhasil.');
                header("Location: ../views/pelanggaranpage.php");
                exit();
            }

            // Check dosen login
            $user = $this->userModel->getDosenLogin($username, $password);
            if ($user) {
                session_start();
                $_SESSION['username'] = $username;
                $_SESSION['user_type'] = 'dosen';
                $_SESSION['user_data'] = $user;
                set_app_flash_modal('success', 'Login berhasil.');
                header("Location: ../views/pelanggaran_dosen.php");
                exit();
            }

            // Check admin login
            $user = $this->userModel->getAdminLogin($username, $password);
            if ($user) {
                session_start();
                $_SESSION['username'] = $username;
                $_SESSION['user_type'] = 'admin';
                $_SESSION['user_data'] = $user;
                set_app_flash_modal('success', 'Login berhasil.');
                header("Location: ../views/home-admin.php");
                exit();
            }

            return false;

        } catch(Exception $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }

    public function logout() {
        session_start();
        session_destroy();
        header("Location: index.php");
        exit();
    }

    public function getAllMahasiswa() {
        try {
            return $this->userModel->getAllUsers(); // Assuming getAllUsers() returns both mahasiswa and dosen
        } catch(Exception $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }

    public function getAdminName($id_admin) {
        global $connect; // Gunakan koneksi global untuk PDO
        try {
            $stmt = $connect->prepare("SELECT nama_admin FROM admin WHERE id_admin = :id_admin");
            $stmt->bindValue(':id_admin', $id_admin, PDO::PARAM_INT);
            $stmt->execute();
    
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($data) {
                return $data['nama_admin'];
            }
            return null; // Jika admin tidak ditemukan
    
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return null;
        }
    }
}
?>
