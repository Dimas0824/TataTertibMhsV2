<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once '../Controllers/PelanggaranController.php';
require_once '../Controllers/TatibController.php';

$pelanggaranController = new PelanggaranController();
$tatibController = new TatibController();

function respondJson(array $payload, int $statusCode = 200): void
{
   http_response_code($statusCode);
   header('Content-Type: application/json; charset=utf-8');
   echo json_encode($payload);
   exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'lookup_mahasiswa') {
   if (!isset($_SESSION['username'])) {
      respondJson(['success' => false, 'message' => 'Unauthorized'], 401);
   }

   if (($_SESSION['user_type'] ?? '') !== 'dosen') {
      respondJson(['success' => false, 'message' => 'Forbidden'], 403);
   }

   $nim = trim((string) ($_GET['nim'] ?? ''));
   if ($nim === '') {
      respondJson(['success' => false, 'message' => 'NIM wajib diisi.'], 422);
   }

   $mahasiswa = $pelanggaranController->getMahasiswaByNim($nim);
   if (!$mahasiswa) {
      respondJson(['success' => false, 'message' => 'Mahasiswa tidak ditemukan.'], 404);
   }

   respondJson([
      'success' => true,
      'data' => $mahasiswa,
   ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   try {
      $isUpdate = isset($_POST['update']) || isset($_POST['id_detail']);
      $tatibDetail = $tatibController->getTatibDetail($_POST['jenisPelanggaran'] ?? null);
      $tingkat = $tatibDetail['tingkat'] ?? '';
      $resolvedSanksi = $_POST['sanksi'] ?? null;
      $detailPelanggaran = $_POST['deskripsiPelanggaran'] ?? ($tatibDetail['deskripsi'] ?? null);

      if ((!$resolvedSanksi || trim((string) $resolvedSanksi) === '') && $tingkat !== '') {
         $resolvedSanksi = $pelanggaranController->getDefaultSanksiByTingkat($tingkat);
      }

      $tugas_khusus = null;
      $status_tugas = 'Belum Dikumpulkan';

      if (in_array($tingkat, ['I', 'II', 'III'], true)) {
         $tugas_khusus = $_POST['deskripsiTugas'] ?? null;
      } elseif (in_array($tingkat, ['IV', 'V'], true)) {
         $status_tugas = 'Tidak Ada Tugas';
      }

      if ($isUpdate) {
         $result = $pelanggaranController->updateDetailPelanggaran(
            $_POST['id_detail'] ?? null,
            $_POST['jenisPelanggaran'] ?? null,
            $_POST['nim'] ?? null,
            $resolvedSanksi,
            $detailPelanggaran,
            $tugas_khusus,
            'pending',
            $status_tugas
         );
      } else {
         $result = $pelanggaranController->simpanDetailPelanggaran(
            $_SESSION['user_data']['nidn'] ?? null,
            $_POST['jenisPelanggaran'] ?? null,
            $_POST['nim'] ?? null,
            $resolvedSanksi,
            $detailPelanggaran,
            $tugas_khusus,
            null,
            'pending',
            $status_tugas
         );
      }

      if (($result['success'] ?? false) === true) {
         $_SESSION['success_message'] = $result['message'] ?? 'Data pelanggaran berhasil disimpan.';
      } else {
         $_SESSION['error_messages'] = [$result['message'] ?? 'Gagal menyimpan data pelanggaran.'];
      }
   } catch (Throwable $e) {
      error_log('Pelanggaran Save/Update Error: ' . $e->getMessage());
      $_SESSION['error_messages'] = [$e->getMessage()];
   }
}

header("Location: ../views/pelanggaran_dosen.php");
exit();
?>
