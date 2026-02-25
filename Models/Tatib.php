<?php
require_once __DIR__ . '/../config.php';

class Tatib
{
    private $connect;

    public function __construct()
    {
        global $connect;
        $this->connect = $connect;
    }

    public function getAllTatib()
    {
        try {
            $stmt = $this->connect->prepare("SELECT * FROM TATA_TERTIB");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }

    public function insertTatib($admin, $deskripsi, $tingkat, $poin)
    {
        $query = "INSERT INTO TATA_TERTIB (id_adminTatib, deskripsi, tingkat, poin) 
              VALUES (?, ?, ?, ?)";

        try {
            $stmt = $this->connect->prepare($query);
            $stmt->execute([$admin, $deskripsi, $tingkat, $poin]);
            return true;
        } catch (PDOException $e) {
            error_log('Error in insertTatib: ' . $e->getMessage());
            return false;
        }
    }

    public function updateTatib($id, $admin, $deskripsi, $tingkat, $poin)
    {
        $query = "UPDATE TATA_TERTIB SET id_adminTatib = ?, deskripsi = ?, tingkat = ?, poin = ? WHERE id_tata_tertib = ?";

        try {
            $stmt = $this->connect->prepare($query);
            $stmt->execute([$admin, $deskripsi, $tingkat, $poin, $id]);
            return true;
        } catch (PDOException $e) {
            error_log('Error in updateTatib: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteTatib($id)
    {
        $query = "DELETE FROM TATA_TERTIB WHERE id_tata_tertib = ?";
        try {
            $stmt = $this->connect->prepare($query);
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function getTatibById($id_tata_tertib)
    {
        $query = "SELECT * FROM TATA_TERTIB WHERE id_tata_tertib = ?";
        $stmt = $this->connect->prepare($query);
        $stmt->bindParam(1, $id_tata_tertib, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>