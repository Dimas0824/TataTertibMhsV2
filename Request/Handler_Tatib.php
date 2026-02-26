<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once '../Controllers/TatibController.php';
require_once __DIR__ . '/../helpers/flash_modal.php';


$tatibController = new TatibController();



if (isset($_POST['store']) && isset($_POST['admin']) && isset($_POST['deskripsi']) && isset($_POST['tingkat']) && isset($_POST['poin'])) {
    $result = $tatibController->store(
        $_POST['admin'],
        $_POST['deskripsi'],
        $_POST['tingkat'],
        $_POST['poin']
    );
    set_app_flash_modal(($result['success'] ?? false) ? 'success' : 'error', $result['message'] ?? 'Operasi selesai.');
} else if (isset($_POST['update']) && isset($_POST['admin']) && isset($_POST['deskripsi']) && isset($_POST['tingkat']) && isset($_POST['poin'])) {
    $result = $tatibController->update(
        $_POST['id_tatib'],
        $_POST['admin'],
        $_POST['deskripsi'],
        $_POST['tingkat'],
        $_POST['poin']
    );
    set_app_flash_modal(($result['success'] ?? false) ? 'success' : 'error', $result['message'] ?? 'Operasi selesai.');
} else if (isset($_POST['delete']) && isset($_POST['id_tatib'])) {
    $result = $tatibController->delete($_POST['id_tatib']);
    set_app_flash_modal(($result['success'] ?? false) ? 'success' : 'error', $result['message'] ?? 'Operasi selesai.');
} else {
    set_app_flash_modal('error', 'Aksi tata tertib tidak valid.');
}

header("Location: ../views/listTatib-admin.php");
exit();

?>
