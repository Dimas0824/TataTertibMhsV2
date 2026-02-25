<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once '../Controllers/PelanggaranController.php';
require_once '../Controllers/TatibController.php';

$pelanggaranController = new PelanggaranController();
$tatibController = new TatibController();

if (isset($_POST['store'])) {
   try {
      $result = $pelanggaranController->simpanDetailPelanggaran(
         $_SESSION['user_data']['nidn'],
         $_POST['jenisPelanggaran'],
         $_POST['nim'],
         $_POST['sanksi'],
         $_POST['deskripsiPelanggaran'],
         $_POST['deskripsiTugas'] ?? null,
         null,
         'pending',
         'Belum Dikumpulkan'
      );

   } catch (Exception $e) {
      error_log('Pelanggaran Save Error: ' . $e->getMessage());
      $_SESSION['error_messages'] = [$e->getMessage()];
   }
} else if (isset($_POST['update'])) {
   try {
      $tatibDetail = $tatibController->getTatibDetail($_POST['jenisPelanggaran']);
      $tingkat = $tatibDetail['tingkat'] ?? '';

      // Set tugas_khusus and status_tugas based on tingkat
      $tugas_khusus = null;
      $status_tugas = null;

      if (in_array($tingkat, ['I', 'II', 'III'])) {
         // For levels I, II, III - allow task description input
         $tugas_khusus = $_POST['deskripsiTugas'] ?? null;
         $status_tugas = 'Belum Dikumpulkan';
      } else if (in_array($tingkat, ['IV', 'V'])) {
         // For levels IV and V - set to null
         $tugas_khusus = null;
         $status_tugas = 'Belum Dikumpulkan';
      }
      // Get the ID from the form
      $id_detail = $_POST['id_detail'];

      $result = $pelanggaranController->updateDetailPelanggaran(
         $id_detail,
         $_POST['jenisPelanggaran'],
         $_POST['nim'],
         $_POST['sanksi'],
         $_POST['deskripsiPelanggaran'],
         $tugas_khusus,
         'pending',
         $status_tugas   // Use the conditional status_tugas
      );

      if ($result) {
         $_SESSION['success_message'] = "Data pelanggaran berhasil diupdate";
      } else {
         $_SESSION['error_messages'] = ["Gagal mengupdate data pelanggaran"];
      }

   } catch (Exception $e) {
      error_log('Pelanggaran Update Error: ' . $e->getMessage());
      $_SESSION['error_messages'] = [$e->getMessage()];
   }
}

header("Location: ../views/pelanggaran_dosen.php");
exit();
?>