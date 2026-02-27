<?php
require_once __DIR__ . '/../helpers/path_helper.php';
app_require('config.php');

class News
{
    private $connect;

    public function __construct($connection = null)
    {
        $resolvedConnection = $connection;

        if (!($resolvedConnection instanceof PDO) && isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof PDO) {
            $resolvedConnection = $GLOBALS['connect'];
        }

        if (!($resolvedConnection instanceof PDO)) {
            throw new RuntimeException('Koneksi database tidak tersedia di News model. Pastikan config.php memuat PDO pada $connect.');
        }

        $this->connect = $resolvedConnection;
    }

    public function getNewsById($id)
    {
        try {
            $stmt = $this->connect->prepare(
                "SELECT
                    news.id_news,
                    news.judul,
                    news.konten,
                    news.gambar,
                    news.penulis_id,
                    news.published_at,
                    admin.nama_admin AS penulis_nama
                FROM NEWS news
                JOIN ADMIN admin ON news.penulis_id = admin.id_admin
                WHERE news.id_news = ?"
            );
            $stmt->execute([(int) $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Log or display the error
            echo "Error: " . $e->getMessage();
            return null;
        }
    }

    public function getAllNews()
    {
        try {
            $stmt = $this->connect->prepare("SELECT
                    news.id_news, news.judul, news.konten, news.gambar, news.published_at,
                    admin.nama_admin AS penulis_nama
                FROM NEWS news
                JOIN ADMIN admin ON news.penulis_id = admin.id_admin
                ORDER BY news.published_at DESC, news.id_news DESC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }

    // Dalam model News
    public function getNewsAdmin($adminId)
    {
        $query = "SELECT
                    news.id_news, news.judul, news.konten, news.gambar, news.published_at,
                    admin.nama_admin AS penulis_nama
                FROM NEWS news
                JOIN ADMIN admin ON news.penulis_id = admin.id_admin
                WHERE news.penulis_id = ?
                ORDER BY news.published_at DESC, news.id_news DESC";
        $stmt = $this->connect->prepare($query);
        $stmt->execute([(int) $adminId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLatestNewsExcluding($excludedNewsId, $limit = 8)
    {
        $safeLimit = max(1, min(16, (int) $limit));
        $query = "SELECT
                    news.id_news, news.judul, news.konten, news.gambar, news.published_at,
                    admin.nama_admin AS penulis_nama
                FROM NEWS news
                JOIN ADMIN admin ON news.penulis_id = admin.id_admin
                WHERE news.id_news <> :excluded
                ORDER BY news.published_at DESC, news.id_news DESC
                LIMIT {$safeLimit}";

        $stmt = $this->connect->prepare($query);
        $stmt->bindValue(':excluded', (int) $excludedNewsId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertNews($gambarPath, $judul, $konten, $penulis_id)
    {
        $query = "INSERT INTO NEWS (gambar, judul, konten, penulis_id ) VALUES (?, ?, ?, ?)";
        try {
            $stmt = $this->connect->prepare($query);
            $stmt->execute([$gambarPath, $judul, $konten, $penulis_id]);
            return true;
        } catch (PDOException $e) {
            error_log('Error in insertNews: ' . $e->getMessage());
            return false;
        }
    }

    public function updateNews($id, $judul, $konten, $penulis_id = null, $gambarPath = null)
    {
        $query = "UPDATE NEWS SET judul = ?, konten = ?";
        $params = [$judul, $konten];

        if ($penulis_id !== null) {
            $query .= ", penulis_id = ?";
            $params[] = $penulis_id;
        }

        if ($gambarPath) {
            $query .= ", gambar = ?";
            $params[] = $gambarPath;
        }

        $query .= " WHERE id_news = ?";
        $params[] = $id;

        try {
            $stmt = $this->connect->prepare($query);
            $stmt->execute($params);
            return true;
        } catch (PDOException $e) {
            error_log('Error in updateNews: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteNews($news_id)
    {
        $query = "DELETE FROM NEWS WHERE id_news = ?";
        try {
            $stmt = $this->connect->prepare($query);
            return $stmt->execute([$news_id]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }
}
?>
