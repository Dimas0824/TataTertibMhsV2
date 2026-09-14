<?php
require_once __DIR__ . '/../config.php';

class Sanksi
{
    private $connect;

    public function __construct()
    {
        global $connect;
        $this->connect = $connect;
    }

    public function getAllSanksi()
    {
        try {
            $stmt = $this->connect->prepare("SELECT * FROM SANKSI ORDER BY id_sanksi DESC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('getAllSanksi DB Error: ' . $e->getMessage());
            return false;
        }
    }
}
?>
