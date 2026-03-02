<?php
require_once __DIR__ . '/../helpers/path_helper.php';
app_require('config.php');
app_require('models/Pelanggaran.php');

class PelanggaranController
{
    private $pelanggaranModel;

    public function __construct()
    {
        $this->pelanggaranModel = new Pelanggaran();
    }

    public function getDetailPelanggaranMahasiswa($idMahasiswa)
    {
        return $this->pelanggaranModel->getDetailPelanggaranMahasiswa($idMahasiswa);
    }

    public function getDetailLaporanDosen($idDosen)
    {
        return $this->pelanggaranModel->getDetailLaporanDosen($idDosen);
    }

    public function simpanDetailPelanggaran($nidn, $id_tata_tertib, $nim, $id_sanksi, $detail_pelanggaran, $tugas_khusus, $surat, $status, $status_tugas, $delegasi_tugas_ke_dpa = false)
    {
        // Validate input
        if (!$id_tata_tertib || !$nidn || !$nim) {
            return [
                'success' => false,
                'message' => 'ID Tata Tertib, NIDN, dan NIM harus diisi'
            ];
        }

        // Simpan detail pelanggaran menggunakan model
        $result = $this->pelanggaranModel->simpanDetailPelanggaran(
            $nidn,
            $id_tata_tertib,
            $nim,
            $id_sanksi,
            $detail_pelanggaran,
            $tugas_khusus,
            $surat,
            $status,
            $status_tugas,
            $delegasi_tugas_ke_dpa
        );
        return $result; // Tambahkan return
    }

    public function updateDetailPelanggaran($id_detail, $id_tata_tertib, $nim, $id_sanksi, $detail_pelanggaran, $tugas_khusus, $status, $status_tugas, $delegasi_tugas_ke_dpa = false)
    {
        if (!$id_tata_tertib || !$id_detail || !$nim) {
            return [
                'success' => false,
                'message' => 'ID Tata Tertib, ID detail, dan NIM harus diisi'
            ];
        }

        // Simpan detail pelanggaran menggunakan model
        $result = $this->pelanggaranModel->updateDetailPelanggaran(
            $id_detail,
            $id_tata_tertib,
            $nim,
            $id_sanksi,
            $detail_pelanggaran,
            $tugas_khusus,
            $status,
            $status_tugas,
            $delegasi_tugas_ke_dpa
        );
        return $result; // Tambahkan return
    }

    public function getNotifikasiMahasiswa($idMahasiswa)
    {
        return $this->pelanggaranModel->getNotifikasiMahasiswa($idMahasiswa);
    }

    public function getNotifikasiDosen($idDosen)
    {
        return $this->pelanggaranModel->getNotifikasiDosen($idDosen);
    }

    public function markNotifikasiAsRead(array $sessionData, string $role, int $idNotifikasi): array
    {
        if ($idNotifikasi <= 0) {
            return [
                'success' => false,
                'message' => 'ID notifikasi tidak valid.',
            ];
        }

        if ($role === 'mahasiswa') {
            $nim = trim((string) ($sessionData['nim'] ?? ''));
            if ($nim === '') {
                return [
                    'success' => false,
                    'message' => 'Data mahasiswa tidak ditemukan.',
                ];
            }

            $updated = $this->pelanggaranModel->markNotifikasiAsReadForMahasiswa($nim, $idNotifikasi);
        } elseif ($role === 'dosen') {
            $nidn = trim((string) ($sessionData['nidn'] ?? ''));
            if ($nidn === '') {
                return [
                    'success' => false,
                    'message' => 'Data dosen tidak ditemukan.',
                ];
            }

            $updated = $this->pelanggaranModel->markNotifikasiAsReadForDosen($nidn, $idNotifikasi);
        } else {
            return [
                'success' => false,
                'message' => 'Role pengguna tidak valid.',
            ];
        }

        if (!$updated) {
            return [
                'success' => false,
                'message' => 'Notifikasi tidak ditemukan atau sudah dibaca.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Notifikasi ditandai sebagai dibaca.',
        ];
    }

    public function markAllNotifikasiAsRead(array $sessionData, string $role): array
    {
        if ($role === 'mahasiswa') {
            $nim = trim((string) ($sessionData['nim'] ?? ''));
            if ($nim === '') {
                return [
                    'success' => false,
                    'message' => 'Data mahasiswa tidak ditemukan.',
                ];
            }

            $updatedCount = $this->pelanggaranModel->markAllNotifikasiAsReadForMahasiswa($nim);
        } elseif ($role === 'dosen') {
            $nidn = trim((string) ($sessionData['nidn'] ?? ''));
            if ($nidn === '') {
                return [
                    'success' => false,
                    'message' => 'Data dosen tidak ditemukan.',
                ];
            }

            $updatedCount = $this->pelanggaranModel->markAllNotifikasiAsReadForDosen($nidn);
        } else {
            return [
                'success' => false,
                'message' => 'Role pengguna tidak valid.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Semua notifikasi telah diperbarui.',
            'updated_count' => $updatedCount,
        ];
    }

    public function getDetailPelanggar($id, ?string $nidn = null)
    {
        return $this->pelanggaranModel->getUpdatePelanggar($id, $nidn);
    }

    public function konfirmasiLaporanSelesai(string $nidn, int $idDetail): array
    {
        if (trim($nidn) === '' || $idDetail <= 0) {
            return [
                'success' => false,
                'message' => 'Data konfirmasi tidak valid.',
            ];
        }

        return $this->pelanggaranModel->konfirmasiLaporanSelesaiByDosen($nidn, $idDetail);
    }

    public function hapusDetailPelanggaran(string $nidn, int $idDetail): array
    {
        if (trim($nidn) === '' || $idDetail <= 0) {
            return [
                'success' => false,
                'message' => 'Data penghapusan tidak valid.',
            ];
        }

        return $this->pelanggaranModel->hapusDetailPelanggaranByDosen($nidn, $idDetail);
    }

    public function getMahasiswaByNim($nim)
    {
        if (!$nim) {
            return null;
        }

        return $this->pelanggaranModel->getMahasiswaByNim((string) $nim);
    }

    public function searchMahasiswa($keyword, int $limit = 12): array
    {
        $normalizedKeyword = trim((string) $keyword);
        if ($normalizedKeyword === '') {
            return [];
        }

        return $this->pelanggaranModel->searchMahasiswaByKeyword($normalizedKeyword, $limit);
    }

    public function getDefaultSanksiByTingkat($tingkat)
    {
        if (!$tingkat) {
            return null;
        }

        return $this->pelanggaranModel->getDefaultSanksiByTingkat((string) $tingkat);
    }
}

?>
