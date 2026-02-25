<?php
require_once __DIR__ . '/../config.php';

class Users
{
    private $connect;
    private const BCRYPT_COST = 12;

    public function __construct()
    {
        global $connect;
        $this->connect = $connect;
    }

    public function getMahasiswaLogin($username, $password)
    {
        return $this->authenticateUser('mahasiswa', 'nim', $username, $password);
    }

    public function getDosenLogin($username, $password)
    {
        return $this->authenticateUser('dosen', 'nidn', $username, $password);
    }

    public function getAdminLogin($username, $password)
    {
        return $this->authenticateUser('admin', 'NIP', $username, $password);
    }

    private function authenticateUser($table, $identifierColumn, $username, $plainPassword)
    {
        try {
            $stmt = $this->connect->prepare("SELECT * FROM {$table} WHERE {$identifierColumn} = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !isset($user['password'])) {
                return false;
            }

            $storedPassword = $user['password'];
            $isLegacyPlaintext = $storedPassword === $plainPassword;
            $isValid = password_verify($plainPassword, $storedPassword) || $isLegacyPlaintext;

            if (!$isValid) {
                return false;
            }

            $needsRehash = $isLegacyPlaintext || password_needs_rehash(
                $storedPassword,
                PASSWORD_BCRYPT,
                ['cost' => self::BCRYPT_COST]
            );

            if ($needsRehash) {
                $newHash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
                $updateStmt = $this->connect->prepare("UPDATE {$table} SET password = ? WHERE {$identifierColumn} = ?");
                $updateStmt->execute([$newHash, $username]);
                $user['password'] = $newHash;
            }

            return $user;
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }

    public function getAllUsers()
    {
        try {
            $stmt = $this->connect->prepare("SELECT * FROM mahasiswa UNION ALL SELECT * FROM dosen");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }
}
?>