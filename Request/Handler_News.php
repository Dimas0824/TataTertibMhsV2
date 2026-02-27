<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../helpers/path_helper.php';
require_once __DIR__ . '/../helpers/route_helper.php';
app_require('config.php');
app_require('Controllers/NewsController.php');
app_require('helpers/flash_modal.php');

// Instansiasi controller
$newsController = new NewsController();

try {
    // Validasi jika tombol "store" diklik
    if (isset($_POST['store'])) {
        // Validasi input
        $judul = $_POST['judul'] ?? '';
        $penulis = $_POST['penulis'] ?? '';
        $konten = $_POST['konten'] ?? '';
        $gambar = $_FILES['gambar'] ?? null;

        // Input tidak boleh kosong
        if (empty($judul) || empty($penulis) || empty($konten)) {
            throw new Exception("Semua input (judul, penulis, konten) harus diisi.");
        }

        // Validasi ID admin
        if (!is_numeric($penulis)) {
            throw new Exception("ID Admin tidak valid.");
        }

        // Panggil method store()
        $result = $newsController->store($gambar, $judul, $konten, $penulis);
        set_app_flash_modal(
            ($result['status'] ?? 'success') === 'success' ? 'success' : 'error',
            $result['message'] ?? 'Berita berhasil disimpan.'
        );
    }

    // Validasi jika tombol "update" diklik
    elseif (isset($_POST['update'])) {
        $newsId = app_id_resolve((string) ($_POST['news_id'] ?? ''), 'news');
        $judul = $_POST['judul'] ?? '';
        $konten = $_POST['konten'] ?? '';
        $penulis = $_POST['penulis'] ?? '';
        $gambar = $_FILES['gambar'] ?? null;

        // Input tidak boleh kosong
        if ($newsId === null || empty($judul) || empty($konten) || empty($penulis)) {
            throw new Exception("Semua input wajib diisi.");
        }

        // Validasi ID admin
        if (!is_numeric($penulis)) {
            throw new Exception("ID Admin tidak valid.");
        }

        // Cek ID admin di database (opsional untuk validasi tambahan)
        if (!isset($connect) || !($connect instanceof PDO)) {
            throw new Exception("Koneksi database tidak tersedia.");
        }

        $stmt = $connect->prepare("SELECT id_admin FROM ADMIN WHERE id_admin = ?");
        $stmt->execute([$penulis]);
        $penulis_id = $stmt->fetchColumn();

        if (!$penulis_id) {
            throw new Exception("Admin tidak ditemukan. Pastikan ID Admin benar.");
        }

        $gambarPath = null;
        if (!empty($gambar['name']) && ($gambar['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploadDir = '../document/news/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($gambar['name']));
            $fileName = time() . '_' . $sanitizedName;
            $uploadFile = $uploadDir . $fileName;
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];

            if (!in_array($gambar['type'], $allowedTypes, true)) {
                throw new Exception("Format gambar tidak didukung.");
            }

            if (!move_uploaded_file($gambar['tmp_name'], $uploadFile)) {
                throw new Exception("Gagal mengunggah gambar.");
            }

            $gambarPath = 'document/news/' . $fileName;
        }

        // Panggil method update()
        $result = $newsController->update($newsId, $judul, $konten, $gambarPath);
        set_app_flash_modal(
            ($result['status'] ?? 'success') === 'success' ? 'success' : 'error',
            $result['message'] ?? 'Berita berhasil diperbarui.'
        );
    }

    // Validasi jika tombol "delete" diklik
    elseif (isset($_POST['delete'])) {
        $newsId = app_id_resolve((string) ($_POST['news_id'] ?? ''), 'news');

        if ($newsId === null) {
            throw new Exception("ID berita tidak boleh kosong.");
        }

        // Panggil method delete()
        $result = $newsController->delete($newsId);
        set_app_flash_modal(
            ($result['status'] ?? 'success') === 'success' ? 'success' : 'error',
            $result['message'] ?? 'Berita berhasil dihapus.'
        );
    } else {
        throw new Exception("Aksi tidak valid.");
    }
} catch (Exception $e) {
    // Tangkap semua error
    set_app_flash_modal('error', 'Error: ' . $e->getMessage());
}

app_redirect('views/admin/news-admin.php');
