<?php
session_start();
require_once __DIR__ . '/../config.php'; // Sertakan file konfigurasi untuk mengakses koneksi database

// Check if the uploads directory exists, if not create it
$uploadDir = '../document/';
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
    mkdir($uploadDir, 0777, true); // Buat direktori dengan izin akses
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($connect) || !($connect instanceof PDO)) {
        echo "Koneksi database tidak tersedia.";
        exit();
    }

    $idDetail = $_POST['id_detail'] ?? null;
    if (!is_numeric($idDetail)) {
        echo "ID detail tidak valid.";
        exit();
    }

    $idDetail = (int) $idDetail;
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
        $file = $_FILES[$fileType];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            echo "Upload file gagal.";
            exit();
        }

        if (($file['size'] ?? 0) > $maxSize) {
            echo "Ukuran file melebihi 2 MB.";
            exit();
        }

        $detectedMime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectedMime = (string) finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            }
        }

        if ($detectedMime === '' && isset($file['type'])) {
            $detectedMime = (string) $file['type'];
        }

        if (!in_array($detectedMime, $allowedMimes, true)) {
            echo "Tipe file tidak diizinkan.";
            exit();
        }

        $originalFileName = basename($file['name']);
        $extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            echo "Ekstensi file tidak diizinkan.";
            exit();
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
                // Perbarui status ke state yang konsisten dengan rule procedure.
                $statusUpdateStmt = $connect->prepare("UPDATE DETAIL_PELANGGARAN SET status = 'proses' WHERE id_detail = :idDetail");
                $statusUpdateStmt->bindValue(':idDetail', $idDetail, PDO::PARAM_INT);
                $statusUpdateStmt->execute();

                echo "File berhasil diunggah dan path tersimpan di database: " . $customFileName;
            } else {
                echo "Gagal menyimpan path file di database.";
            }
        } else {
            echo "Gagal mengunggah file.";
        }
    } else {
        echo "Tidak ada file yang diunggah.";
    }
} else {
    echo "Request tidak valid.";
}
?>