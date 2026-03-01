<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../config.php'; // Sertakan file konfigurasi untuk mengakses koneksi database
require_once __DIR__ . '/../helpers/token_helper.php';
require_once __DIR__ . '/../helpers/path_helper.php';

function respondJson(bool $success, string $message, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
    ]);
    exit();
}

// Simpan dokumen upload di folder storage/uploads milik aplikasi (path absolut).
$uploadDir = app_path('storage/uploads') . DIRECTORY_SEPARATOR;
// Hard limit 2 MB
$maxSize = 2 * 1024 * 1024;
$allowedMimes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        respondJson(false, 'Direktori upload tidak tersedia.', 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($connect) || !($connect instanceof PDO)) {
        respondJson(false, 'Koneksi database tidak tersedia.', 500);
    }

    $idDetail = app_id_resolve((string) ($_POST['id_detail'] ?? ''), 'detail_pelanggaran');
    if ($idDetail === null) {
        respondJson(false, 'ID detail tidak valid.', 422);
    }

    $detailStmt = $connect->prepare(
        "SELECT dp.id_detail, dp.surat, dp.pengumpulan_tgsKhusus, dp.delegasi_tugas_ke_dpa, tt.tingkat
         FROM DETAIL_PELANGGARAN dp
         JOIN TATA_TERTIB tt ON tt.id_tata_tertib = dp.id_tata_tertib
         WHERE dp.id_detail = :idDetail
         LIMIT 1"
    );
    $detailStmt->bindValue(':idDetail', $idDetail, PDO::PARAM_INT);
    $detailStmt->execute();
    $detailData = $detailStmt->fetch(PDO::FETCH_ASSOC);
    if (!$detailData) {
        respondJson(false, 'Data pelanggaran tidak ditemukan.', 404);
    }

    $tingkat = strtoupper(trim((string) ($detailData['tingkat'] ?? '')));
    $requiresTugas = in_array($tingkat, ['I', 'II', 'III'], true);

    $fileType = '';
    $filePath = ''; // Untuk menyimpan jalur file

    // Proses upload Surat Pernyataan
    if (isset($_FILES['suratPernyataan'])) {
        $fileType = 'suratPernyataan';
    }
    // Proses upload Tugas Khusus
    if (isset($_FILES['tugasKhusus'])) {
        $fileType = 'tugasKhusus';
    }

    if ($fileType) {
        if ($fileType === 'tugasKhusus' && !$requiresTugas) {
            respondJson(false, 'Pelanggaran ini tidak memerlukan pengumpulan tugas khusus.', 422);
        }

        $file = $_FILES[$fileType];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            respondJson(false, 'Upload file gagal.', 422);
        }

        if (($file['size'] ?? 0) > $maxSize) {
            respondJson(false, 'Ukuran file melebihi 2 MB.', 422);
        }

        $detectedMime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectedMime = (string) finfo_file($finfo, $file['tmp_name']);
            }
        }

        if ($detectedMime === '' && isset($file['type'])) {
            $detectedMime = (string) $file['type'];
        }

        if (!in_array($detectedMime, $allowedMimes, true)) {
            respondJson(false, 'Tipe file tidak diizinkan.', 422);
        }

        $originalFileName = basename($file['name']);
        $extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            respondJson(false, 'Ekstensi file tidak diizinkan.', 422);
        }

        $customFileName = $idDetail . '_' . $fileType . '_' . uniqid() . '.' . $extension;
        $targetFilePath = $uploadDir . $customFileName;

        // Pindahkan file yang diunggah ke direktori uploads
        if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
            // Simpan jalur file di database berdasarkan jenis file
            $filePath = $customFileName; // Simpan nama file khusus secara langsung
            if ($fileType == 'suratPernyataan') {
                $updateColumn = 'surat';
            } elseif ($fileType == 'tugasKhusus') {
                $updateColumn = 'pengumpulan_tgsKhusus';
            }

            // Masukkan jalur file ke dalam database
            $stmt = $connect->prepare("UPDATE DETAIL_PELANGGARAN SET $updateColumn = :filePath WHERE id_detail = :idDetail");
            $stmt->bindValue(':filePath', $filePath);
            $stmt->bindValue(':idDetail', $idDetail, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $currentSurat = trim((string) ($detailData['surat'] ?? ''));
                $currentTugas = trim((string) ($detailData['pengumpulan_tgsKhusus'] ?? ''));
                if ($fileType === 'suratPernyataan') {
                    $currentSurat = $filePath;
                } elseif ($fileType === 'tugasKhusus') {
                    $currentTugas = $filePath;
                }

                $statusTugas = 'Tidak Ada Tugas';
                if ($requiresTugas) {
                    $delegasiKeDpa = ((int) ($detailData['delegasi_tugas_ke_dpa'] ?? 0)) === 1;
                    if ($currentTugas !== '') {
                        $statusTugas = 'Sudah Dikumpulkan';
                    } else {
                        $statusTugas = $delegasiKeDpa ? 'Menunggu Penugasan DPA' : 'Belum Diberikan';
                    }
                }

                // Perbarui status proses tanpa menurunkan laporan yang sudah selesai.
                $statusUpdateStmt = $connect->prepare(
                    "UPDATE DETAIL_PELANGGARAN
                     SET status = CASE WHEN LOWER(TRIM(status)) = 'selesai' THEN status ELSE 'proses' END,
                         status_tugas = :status_tugas
                     WHERE id_detail = :idDetail"
                );
                $statusUpdateStmt->bindValue(':status_tugas', $statusTugas, PDO::PARAM_STR);
                $statusUpdateStmt->bindValue(':idDetail', $idDetail, PDO::PARAM_INT);
                $statusUpdateStmt->execute();

                respondJson(true, 'File berhasil diunggah.');
            } else {
                respondJson(false, 'Gagal menyimpan path file di database.', 500);
            }
        } else {
            respondJson(false, 'Gagal mengunggah file.', 500);
        }
    } else {
        respondJson(false, 'Tidak ada file yang diunggah.', 422);
    }
} else {
    respondJson(false, 'Request tidak valid.', 405);
}
?>