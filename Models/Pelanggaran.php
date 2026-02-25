<?php
require_once '../config.php';

class Pelanggaran
{
    private $connect;

    public function __construct()
    {
        global $connect;
        $this->connect = $connect;
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

    public function simpanDetailPelanggaran($nidn_dosen, $id_tata_tertib, $nim_mahasiswa, $id_sanksi, $detail_pelanggaran, $tugas_khusus, $surat, $status, $status_tugas)
    {
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
                $id_tata_tertib,
                (int) $mahasiswa['id_mhs'],
                $id_sanksi,
                $tugas_khusus,
                $detail_pelanggaran,
                $surat,
                $status,
                $status_tugas
            ]);

            $idDetail = (int) $this->connect->lastInsertId();

            $pesanDosen = 'Mahasiswa dengan NIM ' . $mahasiswa['nim'] . ' telah dilaporkan oleh Anda.';
            $stmtNotifDosen = $this->connect->prepare(
                "INSERT INTO NOTIFIKASI (id_dosen, id_mhs, id_detail_pelanggaran, pesan, status, role_penerima)
                 VALUES (?, ?, ?, ?, 'unread', 'dosen')"
            );
            $stmtNotifDosen->execute([(int) $dosen['id_dosen'], (int) $mahasiswa['id_mhs'], $idDetail, $pesanDosen]);

            $pesanMahasiswa = 'Anda telah dilaporkan melakukan pelanggaran oleh ' . $dosen['nama_lengkap'];
            $stmtNotifMhs = $this->connect->prepare(
                "INSERT INTO NOTIFIKASI (id_dosen, id_mhs, id_detail_pelanggaran, pesan, status, role_penerima)
                 VALUES (?, ?, ?, ?, 'unread', 'mahasiswa')"
            );
            $stmtNotifMhs->execute([(int) $dosen['id_dosen'], (int) $mahasiswa['id_mhs'], $idDetail, $pesanMahasiswa]);

            $this->connect->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log('Error in simpanDetailPelanggaran: ' . $e->getMessage());
            return false;
        } catch (Throwable $e) {
            if ($this->connect->inTransaction()) {
                $this->connect->rollBack();
            }
            error_log('Error in simpanDetailPelanggaran: ' . $e->getMessage());
            return false;
        }
    }

    public function updateDetailPelanggaran($id_detail, $id_tata_tertib, $nim_mahasiswa, $id_sanksi, $detail_pelanggaran, $tugas_khusus, $status, $status_tugas)
    {
        try {
            $stmtMhs = $this->connect->prepare("SELECT id_mhs FROM MAHASISWA WHERE nim = ? LIMIT 1");
            $stmtMhs->execute([$nim_mahasiswa]);
            $mahasiswa = $stmtMhs->fetch(PDO::FETCH_ASSOC);

            if (!$mahasiswa) {
                throw new RuntimeException('Mahasiswa tidak ditemukan.');
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
                $id_tata_tertib,
                (int) $mahasiswa['id_mhs'],
                $id_sanksi,
                $tugas_khusus,
                $detail_pelanggaran,
                $status,
                $status_tugas,
                $id_detail
            ]);

            return true;
        } catch (PDOException $e) {
            error_log('Error in updateDetailPelanggaran: ' . $e->getMessage());
            return false;
        } catch (Throwable $e) {
            error_log('Error in updateDetailPelanggaran: ' . $e->getMessage());
            return false;
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
}
?>