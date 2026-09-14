<?php
require_once __DIR__ . '/../config.php';

class User
{
    private $connect;
    private const BCRYPT_COST = 12;
    // Fixed bcrypt(cost-12) hash of a random throwaway string — used only to equalize
    // response time between "user not found" and "wrong password" (kills timing enumeration).
    private const DUMMY_PASSWORD_HASH = '$2y$12$vV55GxE6aRjijhPFpkROSey1DdGgATDMuoSOtRHoK6B400Ao0YSRy';

    public function __construct()
    {
        global $connect;
        $this->connect = $connect;
    }

    public function getMahasiswaLogin($username, $password)
    {
        return $this->authenticateUser('MAHASISWA', 'nim', $username, $password);
    }

    public function getDosenLogin($username, $password)
    {
        return $this->authenticateUser('DOSEN', 'nidn', $username, $password);
    }

    public function getAdminLogin($username, $password)
    {
        return $this->authenticateUser('ADMIN', 'NIP', $username, $password);
    }

    private function authenticateUser($table, $identifierColumn, $username, $plainPassword)
    {
        try {
            $stmt = $this->connect->prepare("SELECT * FROM {$table} WHERE {$identifierColumn} = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !isset($user['password'])) {
                password_verify($plainPassword, self::DUMMY_PASSWORD_HASH);
                return false;
            }

            $storedPassword = (string) $user['password'];

            // Hashed credentials only; legacy plaintext rows are rejected here and must be
            // migrated once via: php database/cli/hash-plaintext-passwords.php
            $isValid = str_starts_with($storedPassword, '$2') && password_verify($plainPassword, $storedPassword);

            if (!$isValid) {
                return false;
            }

            if (password_needs_rehash(
                $storedPassword,
                PASSWORD_BCRYPT,
                ['cost' => self::BCRYPT_COST]
            )) {
                $newHash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
                $updateStmt = $this->connect->prepare("UPDATE {$table} SET password = ? WHERE {$identifierColumn} = ?");
                $updateStmt->execute([$newHash, $username]);
                $user['password'] = $newHash;
            }

            return $user;
        } catch (PDOException $e) {
            error_log('Login DB Error [' . $table . '.' . $identifierColumn . ']: ' . $e->getMessage());
            return false;
        }
    }

    public function getAllUsers()
    {
        try {
            $stmt = $this->connect->prepare(
                "SELECT
                    id_mhs AS id_user,
                    nim AS identitas,
                    nama_lengkap,
                    email,
                    id_prodi,
                    role,
                    'mahasiswa' AS tipe_user,
                    angkatan,
                    id_dpa,
                    NULL AS jabatan
                 FROM MAHASISWA
                 UNION ALL
                 SELECT
                    id_dosen AS id_user,
                    nidn AS identitas,
                    nama_lengkap,
                    email,
                    id_prodi,
                    role,
                    'dosen' AS tipe_user,
                    NULL AS angkatan,
                    NULL AS id_dpa,
                    jabatan
                 FROM DOSEN
                 ORDER BY tipe_user ASC, nama_lengkap ASC"
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Get users DB Error: ' . $e->getMessage());
            return false;
        }
    }

    public function getAllMahasiswa()
    {
        try {
            $stmt = $this->connect->prepare(
                "SELECT
                    id_mhs,
                    nim,
                    nama_lengkap,
                    email,
                    angkatan,
                    id_prodi,
                    id_dpa,
                    role
                 FROM MAHASISWA
                 ORDER BY nama_lengkap ASC"
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Get mahasiswa DB Error: ' . $e->getMessage());
            return false;
        }
    }

    public function getAdminName($idAdmin): ?string
    {
        try {
            $stmt = $this->connect->prepare("SELECT nama_admin FROM ADMIN WHERE id_admin = :id_admin LIMIT 1");
            $stmt->bindValue(':id_admin', (int) $idAdmin, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || !isset($result['nama_admin'])) {
                return null;
            }

            return (string) $result['nama_admin'];
        } catch (PDOException $e) {
            error_log('Get admin name DB Error: ' . $e->getMessage());
            return null;
        }
    }
}
?>