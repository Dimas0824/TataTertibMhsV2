<?php
session_start();
require_once __DIR__ . '/../helpers/path_helper.php';
app_require('config.php');
app_require('Controllers/TatibController.php');
app_require('helpers/flash_modal.php');


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

app_redirect('views/tatib/listTatib-admin.php');

?>