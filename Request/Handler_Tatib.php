<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once '../Controllers/TatibController.php';


$tatibController = new TatibController();



if (isset($_POST['store']) && isset($_POST['admin']) && isset($_POST['deskripsi']) && isset($_POST['tingkat']) && isset($_POST['poin'])) {
    $tatibController->store(
        $_POST['admin'],
        $_POST['deskripsi'],
        $_POST['tingkat'],
        $_POST['poin']
    );
} else if (isset($_POST['update']) && isset($_POST['admin']) && isset($_POST['deskripsi']) && isset($_POST['tingkat']) && isset($_POST['poin'])) {
    $tatibController->update(
        $_POST['id_tatib'],
        $_POST['admin'],
        $_POST['deskripsi'],
        $_POST['tingkat'],
        $_POST['poin']
    );
} else if (isset($_POST['delete']) && isset($_POST['id_tatib'])) {
    $tatibController->delete($_POST['id_tatib']);
}

header("Location: ../views/listTatib-admin.php");
exit();

?>