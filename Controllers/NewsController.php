<?php
require_once __DIR__ . '/../helpers/path_helper.php';
app_require('Models/News.php');

class NewsController
{
    private $newsModel;
    private $connect;

    public function __construct($connection = null)
    {
        $resolvedConnection = $connection;

        if (!($resolvedConnection instanceof PDO) && isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof PDO) {
            $resolvedConnection = $GLOBALS['connect'];
        }

        if (!($resolvedConnection instanceof PDO)) {
            throw new RuntimeException('Koneksi database tidak tersedia di NewsController. Pastikan config.php memuat PDO pada $connect.');
        }

        $this->connect = $resolvedConnection;
        $this->newsModel = new News($this->connect);
    }

    // Metode untuk mendapatkan berita berdasarkan ID admin
    public function AdminNews($id)
    {
        return $this->newsModel->getNewsAdmin($id);
    }


    public function getNewsById($id)
    {
        return $this->newsModel->getNewsById($id);
    }

    /**
     * Ambil semua berita.
     */
    public function ReadNews()
    {
        return $this->newsModel->getAllNews();
    }

    /**
     * Tambah berita.
     */
    public function store($gambar, $judul, $konten, $penulis_id)
    {
        // Validasi input
        if (empty($judul) || empty($penulis_id) || empty($konten)) {
            throw new Exception("Semua input (judul, penulis, konten) harus diisi.");
        }

        // Proses gambar jika diunggah
        $gambarPath = null;
        if (is_array($gambar) && !empty($gambar['name'])) {
            if (($gambar['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new Exception("Gagal mengunggah gambar.");
            }

            $uploadDir = app_path('document/news');
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                throw new Exception("Direktori upload gambar tidak tersedia.");
            }

            $sanitizedName = (string) preg_replace('/[^a-zA-Z0-9._-]/', '_', basename((string) $gambar['name']));
            $sanitizedName = trim($sanitizedName, '._');
            if ($sanitizedName === '') {
                $sanitizedName = 'news_image';
            }

            $fileName = time() . '_' . $sanitizedName;
            $uploadFile = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

            // Cek tipe file
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array((string) ($gambar['type'] ?? ''), $allowedTypes, true)) {
                throw new Exception("Format gambar tidak didukung.");
            }

            // Pindahkan file yang diunggah
            if (!move_uploaded_file($gambar['tmp_name'], $uploadFile)) {
                throw new Exception("Gagal mengunggah gambar.");
            }

            // Simpan path gambar ke database
            $gambarPath = 'document/news/' . $fileName;
        }

        // Query untuk menyimpan berita
        try {
            $saved = $this->newsModel->insertNews($gambarPath, $judul, $konten, $penulis_id);
            if (!$saved) {
                return ['status' => 'error', 'message' => 'Gagal menyimpan berita.'];
            }

            return ['status' => 'success', 'message' => 'Berita berhasil disimpan.'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Update berita.
     */
    public function update($id, $judul, $konten, $gambarPath = null)
    {
        try {
            // Validasi ID berita
            if (empty($id)) {
                throw new Exception("ID berita tidak valid.");
            }

            // Ambil data lama
            $oldNews = $this->newsModel->getNewsById($id);

            if (!$oldNews) {
                throw new Exception("Data berita tidak ditemukan.");
            }

            // Tentukan gambar yang akan digunakan
            $gambar = $gambarPath ?: $oldNews['gambar'];

            // Update data berita
            $updated = $this->newsModel->updateNews($id, $judul, $konten, null, $gambar);
            if (!$updated) {
                return ['status' => 'error', 'message' => 'Gagal memperbarui data berita.'];
            }

            return ['status' => 'success', 'message' => 'Berita berhasil diperbarui.'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Hapus berita berdasarkan ID.
     */
    public function delete($id)
    {
        try {
            // Ambil path gambar untuk dihapus
            $news = $this->newsModel->getNewsById($id);

            if ($news && !empty($news['gambar'])) {
                $filePath = app_path((string) $news['gambar']);
                if (file_exists($filePath)) {
                    unlink($filePath); // Hapus file gambar
                }
            }

            // Hapus berita dari database
            $deleted = $this->newsModel->deleteNews($id);
            if (!$deleted) {
                return ['status' => 'error', 'message' => 'Gagal menghapus data berita.'];
            }

            return ['status' => 'success', 'message' => 'Berita berhasil dihapus.'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
