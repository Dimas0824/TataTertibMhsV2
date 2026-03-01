<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../helpers/path_helper.php';
require_once __DIR__ . '/../helpers/route_helper.php';
app_require('config.php');
app_require('Controllers/PelanggaranController.php');
app_require('Controllers/TatibController.php');
app_require('helpers/flash_modal.php');

$pelanggaranController = new PelanggaranController();
$tatibController = new TatibController();

function respondJson(array $payload, int $statusCode = 200): void
{
   http_response_code($statusCode);
   header('Content-Type: application/json; charset=utf-8');
   echo json_encode($payload);
   exit();
}

$routeAction = (string) app_route_data('action', '');

if ($routeAction === 'lookup_mahasiswa') {
   if (!isset($_SESSION['username'])) {
      respondJson(['success' => false, 'message' => 'Unauthorized'], 401);
   }

   if (($_SESSION['user_type'] ?? '') !== 'dosen') {
      respondJson(['success' => false, 'message' => 'Forbidden'], 403);
   }

   $nim = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $rawInput = file_get_contents('php://input');
      $decodedInput = json_decode($rawInput ?? '', true);
      $input = is_array($decodedInput) ? $decodedInput : $_POST;
      $nim = trim((string) ($input['nim'] ?? ''));
   } else {
      $nim = trim((string) ($_GET['nim'] ?? ''));
   }
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $routeAction === 'confirm_selesai') {
   if (!isset($_SESSION['username'])) {
      set_app_flash_modal('error', 'Unauthorized.');
      app_redirect('views/auth/login.php');
   }

   if (($_SESSION['user_type'] ?? '') !== 'dosen') {
      set_app_flash_modal('error', 'Hanya dosen yang dapat mengonfirmasi laporan.');
      app_redirect('views/pelanggaran/pelanggaran_dosen.php');
   }

   try {
      $resolvedDetailId = app_id_resolve((string) ($_POST['id_detail'] ?? ''), 'detail_pelanggaran');
      if ($resolvedDetailId === null) {
         throw new RuntimeException('Token detail pelanggaran tidak valid.');
      }

      $nidn = trim((string) ($_SESSION['user_data']['nidn'] ?? ''));
      $result = $pelanggaranController->konfirmasiLaporanSelesai($nidn, (int) $resolvedDetailId);
      set_app_flash_modal(($result['success'] ?? false) ? 'success' : 'error', $result['message'] ?? 'Konfirmasi laporan selesai diproses.');
   } catch (Throwable $e) {
      error_log('Pelanggaran Confirm Error: ' . $e->getMessage());
      set_app_flash_modal('error', $e->getMessage());
   }

   app_redirect('views/pelanggaran/pelanggaran_dosen.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   try {
      if (!isset($_SESSION['username'])) {
         throw new RuntimeException('Unauthorized.');
      }

      if (($_SESSION['user_type'] ?? '') !== 'dosen') {
         throw new RuntimeException('Hanya dosen yang dapat mengubah data pelanggaran.');
      }

      $nidn = trim((string) ($_SESSION['user_data']['nidn'] ?? ''));
      if ($nidn === '') {
         throw new RuntimeException('Data dosen tidak valid.');
      }

      $isUpdate = isset($_POST['update']) || isset($_POST['id_detail']);
      $tatibId = app_id_resolve((string) ($_POST['jenisPelanggaran'] ?? ''), 'tatib');
      if ($tatibId === null) {
         throw new RuntimeException('Token jenis pelanggaran tidak valid.');
      }

      $tatibDetail = $tatibController->getTatibDetail($tatibId);
      $tingkat = $tatibDetail['tingkat'] ?? '';
      $resolvedSanksi = null;
      if (isset($_POST['sanksi']) && trim((string) $_POST['sanksi']) !== '') {
         $resolvedSanksi = app_id_resolve((string) $_POST['sanksi'], 'sanksi');
         if ($resolvedSanksi === null) {
            throw new RuntimeException('Token sanksi tidak valid.');
         }
      }
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

      $resolvedDetailId = null;
      if ($isUpdate) {
         $resolvedDetailId = app_id_resolve((string) ($_POST['id_detail'] ?? ''), 'detail_pelanggaran');
         if ($resolvedDetailId === null) {
            throw new RuntimeException('Token detail pelanggaran tidak valid.');
         }

         $existingDetail = $pelanggaranController->getDetailPelanggar((int) $resolvedDetailId, $nidn);
         if (!$existingDetail) {
            throw new RuntimeException('Data pelanggaran tidak ditemukan atau bukan milik Anda.');
         }

         $existingStatus = strtolower(trim((string) ($existingDetail['status'] ?? '')));
         $existingStatusTugas = strtolower(trim((string) ($existingDetail['status_tugas'] ?? '')));
         $laporanSelesai = in_array($existingStatus, ['selesai', 'done'], true);
         $tugasSelesai = in_array($existingStatusTugas, ['sudah dikumpulkan', 'selesai', 'done'], true);
         if ($laporanSelesai || $tugasSelesai) {
            throw new RuntimeException('Data tidak dapat diedit karena tugas atau laporan sudah selesai.');
         }
      }

      if ($isUpdate) {
         $result = $pelanggaranController->updateDetailPelanggaran(
            $resolvedDetailId,
            $tatibId,
            $_POST['nim'] ?? null,
            $resolvedSanksi,
            $detailPelanggaran,
            $tugas_khusus,
            'pending',
            $status_tugas
         );
      } else {
         $result = $pelanggaranController->simpanDetailPelanggaran(
            $nidn,
            $tatibId,
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
         set_app_flash_modal('success', $result['message'] ?? 'Data pelanggaran berhasil disimpan.');
      } else {
         set_app_flash_modal('error', $result['message'] ?? 'Gagal menyimpan data pelanggaran.');
      }
   } catch (Throwable $e) {
      error_log('Pelanggaran Save/Update Error: ' . $e->getMessage());
      set_app_flash_modal('error', $e->getMessage());
   }
}

app_redirect('views/pelanggaran/pelanggaran_dosen.php');
?>
