<?php
require_once '../Models/Tatib.php';
require_once '../Models/Sanksi.php';

class TatibController {
    private $tatibModel;
    private $sanksiModel;

    public function __construct() {
        $this->tatibModel = new Tatib();
        $this->sanksiModel = new Sanksi();
    }

    public function ReadTatib() {
        return $this->tatibModel->getAllTatib();
    }

    public function ReadSanksi() {
        return $this->sanksiModel->getAllSanksi();
    }

    public function store($admin, $deskripsi, $tingkat, $poin): array {
        $result = $this->tatibModel->insertTatib(
            $admin, 
            $deskripsi, 
            $tingkat, 
            $poin
        );

        return [
            'success' => (bool) $result,
            'message' => $result ? 'Data tata tertib berhasil ditambahkan.' : 'Gagal menambahkan data tata tertib.',
        ];
    }

    public function update($id, $admin, $deskripsi, $tingkat, $poin): array {
        $result = $this->tatibModel->updateTatib(
            $id,
            $admin, 
            $deskripsi, 
            $tingkat, 
            $poin
        );

        return [
            'success' => (bool) $result,
            'message' => $result ? 'Data tata tertib berhasil diperbarui.' : 'Gagal memperbarui data tata tertib.',
        ];
    }

    public function delete($id): array {
        $result = $this->tatibModel->deleteTatib($id);

        return [
            'success' => (bool) $result,
            'message' => $result ? 'Data tata tertib berhasil dihapus.' : 'Gagal menghapus data tata tertib.',
        ];
    }

    public function getTatibDetail($id_tata_tertib) {
        return $this->tatibModel->getTatibById($id_tata_tertib);
    }
}

?>
