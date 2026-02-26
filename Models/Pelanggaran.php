<?php
require_once __DIR__ . '/../config.php';

class Pelanggaran
{
    private $connect;

    public function __construct()
    {
        global $connect;
        $this->connect = $connect;
    }

    private function normalizeNullableString($value, ?int $maxLength = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if ($maxLength !== null && $maxLength > 0) {
            $normalized = function_exists('mb_substr')
                ? mb_substr($normalized, 0, $maxLength)
                : substr($normalized, 0, $maxLength);
        }

        return $normalized;
    }

    private function kirimNotifikasi(int $idDosen, int $idMahasiswa, int $idDetail, string $pesan, string $rolePenerima): void
    {
        try {
            $stmtNotif = $this->connect->prepare(
                "INSERT INTO NOTIFIKASI (id_dosen, id_mhs, id_detail_pelanggaran, pesan, status, role_penerima)
                 VALUES (?, ?, ?, ?, 'unread', ?)"
            );
            $stmtNotif->execute([$idDosen, $idMahasiswa, $idDetail, $pesan, $rolePenerima]);
        } catch (Throwable $e) {
            error_log('Warning in kirimNotifikasi: ' . $e->getMessage());
        }
    }

    public function getDetailPelanggaranMahasiswa($nim)
    {
        $query = "SELECT * FROM v_PelanggaranMahasiswa WHERE nim = ?";
        $stmt = $this->connect->prepare($query);
        $stmt->bindParam(1, $nim, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getDetailLaporanDosen($nidn)
    {
        $query = "SELECT * FROM v_DosenMelaporkan WHERE nidn = ?";
        $stmt = $this->connect->prepare($query);
        $stmt->bindParam(1, $nidn, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getUpdatePelanggar($id)
    {
        $query = "SELECT dp.*, m.nim, m.nama_lengkap, m.angkatan
              FROM DETAIL_PELANGGARAN dp
              JOIN MAHASISWA m ON dp.id_mahasiswa = m.id_mhs
              JOIN TATA_TERTIB tt ON dp.id_tata_tertib = tt.id_tata_tertib
              JOIN SANKSI s ON dp.id_sanksi = s.id_sanksi
              WHERE dp.id_detail = ?";

        $stmt = $this->connect->prepare($query);
        $stmt->bindParam(1, $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getMahasiswaByNim(string $nim): ?array
    {
        $query = "SELECT m.nim, m.nama_lengkap, m.angkatan, m.id_prodi, p.nama_prodi
                  FROM MAHASISWA m
                  LEFT JOIN PRODI p ON p.id_prodi = m.id_prodi
                  WHERE m.nim = ?
                  LIMIT 1";
        $stmt = $this->connect->prepare($query);
        $stmt->execute([$nim]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    public function getDefaultSanksiByTingkat(string $tingkat): ?int
    {
        $stmt = $this->connect->prepare(
            "SELECT id_sanksi FROM SANKSI WHERE tingkat = ? ORDER BY id_sanksi ASC LIMIT 1"
        );
        $stmt->execute([$tingkat]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || !isset($result['id_sanksi'])) {
            return null;
        }

        return (int) $result['id_sanksi'];
    }

    public function simpanDetailPelanggaran($nidn_dosen, $id_tata_tertib, $nim_mahasiswa, $id_sanksi, $detail_pelanggaran, $tugas_khusus, $surat, $status, $status_tugas)
    {
        $idTatib = (int) $id_tata_tertib;
        $idSanksi = (int) $id_sanksi;
        $detailPelanggaran = $this->normalizeNullableString($detail_pelanggaran);
        $tugasKhusus = $this->normalizeNullableString($tugas_khusus, 255);
        $suratMahasiswa = $this->normalizeNullableString($surat, 255);
        $statusPelanggaran = $this->normalizeNullableString($status, 50) ?? 'pending';
        $statusTugas = $this->normalizeNullableString($status_tugas, 50) ?? 'Belum Dikumpulkan';

        try {
            $this->connect->beginTransaction();

            $stmtDosen = $this->connect->prepare("SELECT id_dosen, nama_lengkap FROM DOSEN WHERE nidn = ? LIMIT 1");
            $stmtDosen->execute([$nidn_dosen]);
            $dosen = $stmtDosen->fetch(PDO::FETCH_ASSOC);

            $stmtMhs = $this->connect->prepare("SELECT id_mhs, nim FROM MAHASISWA WHERE nim = ? LIMIT 1");
            $stmtMhs->execute([$nim_mahasiswa]);
            $mahasiswa = $stmtMhs->fetch(PDO::FETCH_ASSOC);

            if (!$dosen || !$mahasiswa) {
                throw new RuntimeException('Dosen atau mahasiswa tidak ditemukan.');
            }

            $stmtTatib = $this->connect->prepare("SELECT id_tata_tertib FROM TATA_TERTIB WHERE id_tata_tertib = ? LIMIT 1");
            $stmtTatib->execute([$idTatib]);
            if (!$stmtTatib->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('Jenis pelanggaran tidak valid.');
            }

            $stmtSanksi = $this->connect->prepare("SELECT id_sanksi FROM SANKSI WHERE id_sanksi = ? LIMIT 1");
            $stmtSanksi->execute([$idSanksi]);
            if (!$stmtSanksi->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('Sanksi tidak valid.');
            }

            $stmtInsert = $this->connect->prepare(
                "INSERT INTO DETAIL_PELANGGARAN (
                    id_dosen,
                    id_tata_tertib,
                    id_mahasiswa,
                    id_sanksi,
                    tugas_khusus,
                    detail_pelanggaran,
                    surat,
                    status,
                    status_tugas
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmtInsert->execute([
                (int) $dosen['id_dosen'],
                $idTatib,
                (int) $mahasiswa['id_mhs'],
                $idSanksi,
                $tugasKhusus,
                $detailPelanggaran,
                $suratMahasiswa,
                $statusPelanggaran,
                $statusTugas
            ]);

            $idDetail = (int) $this->connect->lastInsertId();
            $idDosen = (int) $dosen['id_dosen'];
            $idMahasiswa = (int) $mahasiswa['id_mhs'];

            $this->connect->commit();

            $pesanDosen = 'Mahasiswa dengan NIM ' . $mahasiswa['nim'] . ' telah dilaporkan oleh Anda.';
            $this->kirimNotifikasi($idDosen, $idMahasiswa, $idDetail, $pesanDosen, 'dosen');

            $pesanMahasiswa = 'Anda telah dilaporkan melakukan pelanggaran oleh ' . $dosen['nama_lengkap'];
            $this->kirimNotifikasi($idDosen, $idMahasiswa, $idDetail, $pesanMahasiswa, 'mahasiswa');

            return [
                'success' => true,
                'message' => 'Data pelanggaran berhasil disimpan.',
                'id_detail' => $idDetail,
            ];
        } catch (PDOException $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log('Error in simpanDetailPelanggaran: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal menyimpan data pelanggaran.',
            ];
        } catch (Throwable $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log('Error in simpanDetailPelanggaran: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function updateDetailPelanggaran($id_detail, $id_tata_tertib, $nim_mahasiswa, $id_sanksi, $detail_pelanggaran, $tugas_khusus, $status, $status_tugas)
    {
        $idDetail = (int) $id_detail;
        $idTatib = (int) $id_tata_tertib;
        $idSanksi = (int) $id_sanksi;
        $detailPelanggaran = $this->normalizeNullableString($detail_pelanggaran);
        $tugasKhusus = $this->normalizeNullableString($tugas_khusus, 255);
        $statusPelanggaran = $this->normalizeNullableString($status, 50) ?? 'pending';
        $statusTugas = $this->normalizeNullableString($status_tugas, 50) ?? 'Belum Dikumpulkan';

        try {
            $stmtMhs = $this->connect->prepare("SELECT id_mhs FROM MAHASISWA WHERE nim = ? LIMIT 1");
            $stmtMhs->execute([$nim_mahasiswa]);
            $mahasiswa = $stmtMhs->fetch(PDO::FETCH_ASSOC);

            if (!$mahasiswa) {
                throw new RuntimeException('Mahasiswa tidak ditemukan.');
            }

            $stmtTatib = $this->connect->prepare("SELECT id_tata_tertib FROM TATA_TERTIB WHERE id_tata_tertib = ? LIMIT 1");
            $stmtTatib->execute([$idTatib]);
            if (!$stmtTatib->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('Jenis pelanggaran tidak valid.');
            }

            $stmtSanksi = $this->connect->prepare("SELECT id_sanksi FROM SANKSI WHERE id_sanksi = ? LIMIT 1");
            $stmtSanksi->execute([$idSanksi]);
            if (!$stmtSanksi->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('Sanksi tidak valid.');
            }

            $query = "UPDATE DETAIL_PELANGGARAN
                      SET id_tata_tertib = ?,
                          id_mahasiswa = ?,
                          id_sanksi = ?,
                          tugas_khusus = ?,
                          detail_pelanggaran = ?,
                          status = ?,
                          status_tugas = ?
                      WHERE id_detail = ?";

            $stmt = $this->connect->prepare($query);
            $stmt->execute([
                $idTatib,
                (int) $mahasiswa['id_mhs'],
                $idSanksi,
                $tugasKhusus,
                $detailPelanggaran,
                $statusPelanggaran,
                $statusTugas,
                $idDetail
            ]);

            return [
                'success' => true,
                'message' => 'Data pelanggaran berhasil diupdate.',
            ];
        } catch (PDOException $e) {
            error_log('Error in updateDetailPelanggaran: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal mengupdate data pelanggaran.',
            ];
        } catch (Throwable $e) {
            error_log('Error in updateDetailPelanggaran: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getNotifikasiMahasiswa($id)
    {
        $query = "SELECT * FROM v_NotifikasiMahasiswa WHERE nim = ?";
        $stmt = $this->connect->prepare($query);
        $stmt->bindParam(1, $id, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }
    public function getNotifikasiDosen($id)
    {
        $query = "SELECT * FROM v_NotifikasiDosen WHERE nidn = ?";
        $stmt = $this->connect->prepare($query);
        $stmt->bindParam(1, $id, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function markNotifikasiAsReadForMahasiswa(string $nim, int $idNotifikasi): bool
    {
        $query = "UPDATE NOTIFIKASI
                  SET status = 'read'
                  WHERE id_notifikasi = :id_notifikasi
                    AND role_penerima = 'mahasiswa'
                    AND status <> 'read'
                    AND id_mhs = (
                      SELECT id_mhs FROM MAHASISWA WHERE nim = :nim LIMIT 1
                    )";

        try {
            $stmt = $this->connect->prepare($query);
            $stmt->bindValue(':id_notifikasi', $idNotifikasi, PDO::PARAM_INT);
            $stmt->bindValue(':nim', $nim, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('Error in markNotifikasiAsReadForMahasiswa: ' . $e->getMessage());
            return false;
        }
    }

    public function markNotifikasiAsReadForDosen(string $nidn, int $idNotifikasi): bool
    {
        $query = "UPDATE NOTIFIKASI
                  SET status = 'read'
                  WHERE id_notifikasi = :id_notifikasi
                    AND role_penerima = 'dosen'
                    AND status <> 'read'
                    AND id_dosen = (
                      SELECT id_dosen FROM DOSEN WHERE nidn = :nidn LIMIT 1
                    )";

        try {
            $stmt = $this->connect->prepare($query);
            $stmt->bindValue(':id_notifikasi', $idNotifikasi, PDO::PARAM_INT);
            $stmt->bindValue(':nidn', $nidn, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('Error in markNotifikasiAsReadForDosen: ' . $e->getMessage());
            return false;
        }
    }

    public function markAllNotifikasiAsReadForMahasiswa(string $nim): int
    {
        $query = "UPDATE NOTIFIKASI
                  SET status = 'read'
                  WHERE role_penerima = 'mahasiswa'
                    AND status <> 'read'
                    AND id_mhs = (
                      SELECT id_mhs FROM MAHASISWA WHERE nim = :nim LIMIT 1
                    )";

        try {
            $stmt = $this->connect->prepare($query);
            $stmt->bindValue(':nim', $nim, PDO::PARAM_STR);
            $stmt->execute();

            return (int) $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('Error in markAllNotifikasiAsReadForMahasiswa: ' . $e->getMessage());
            return 0;
        }
    }

    public function markAllNotifikasiAsReadForDosen(string $nidn): int
    {
        $query = "UPDATE NOTIFIKASI
                  SET status = 'read'
                  WHERE role_penerima = 'dosen'
                    AND status <> 'read'
                    AND id_dosen = (
                      SELECT id_dosen FROM DOSEN WHERE nidn = :nidn LIMIT 1
                    )";

        try {
            $stmt = $this->connect->prepare($query);
            $stmt->bindValue(':nidn', $nidn, PDO::PARAM_STR);
            $stmt->execute();

            return (int) $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('Error in markAllNotifikasiAsReadForDosen: ' . $e->getMessage());
            return 0;
        }
    }
}
?>
