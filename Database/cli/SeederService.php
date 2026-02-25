<?php

class SeederService
{
    private $pdo;
    private $sqlRunner;
    private $appEnv;
    private $filenamePattern = '/^\d{8}_\d{6}_[a-z0-9_]+\.sql$/';

    public function __construct(PDO $pdo, SqlRunner $sqlRunner, $appEnv = 'local')
    {
        $this->pdo = $pdo;
        $this->sqlRunner = $sqlRunner;
        $this->appEnv = strtolower((string) $appEnv);
    }

    public function seed($path, $fileName = null, $force = false)
    {
        $this->guardProduction($force, 'db:seed');
        $this->ensureSeedsTable();

        $files = $this->collectSqlFiles($path, $fileName);
        if (empty($files)) {
            echo "[db:seed] Tidak ada file seed di {$path}" . PHP_EOL;
            return 0;
        }

        $applied = $this->getAppliedSeeds();
        $pending = [];

        foreach ($files as $name => $fullPath) {
            $checksum = hash_file('sha256', $fullPath);

            if (isset($applied[$name])) {
                if ($applied[$name] !== $checksum) {
                    throw new RuntimeException("Drift terdeteksi pada seed '{$name}'. Checksum file berubah setelah pernah dijalankan.");
                }
                continue;
            }

            $pending[$name] = [
                'path' => $fullPath,
                'checksum' => $checksum,
            ];
        }

        if (empty($pending)) {
            if ($fileName !== null) {
                echo "[db:seed] File {$fileName} sudah pernah dijalankan, skip." . PHP_EOL;
            } else {
                echo "[db:seed] Tidak ada seed pending" . PHP_EOL;
            }
            return 0;
        }

        foreach ($pending as $name => $meta) {
            echo "[db:seed] Menjalankan {$name} ..." . PHP_EOL;
            $this->sqlRunner->executeFile($this->pdo, $meta['path'], true);
            $this->recordSeed($name, $meta['checksum']);
            echo "[db:seed] Sukses {$name}" . PHP_EOL;
        }

        echo "[db:seed] Selesai. Total " . count($pending) . " seed dieksekusi." . PHP_EOL;
        return 0;
    }

    private function guardProduction($force, $commandName)
    {
        if ($this->appEnv === 'production' && !$force) {
            throw new RuntimeException("Perintah '{$commandName}' ditolak pada APP_ENV=production. Gunakan --force jika yakin.");
        }
    }

    private function collectSqlFiles($path, $singleFile)
    {
        if (!is_dir($path)) {
            throw new RuntimeException("Direktori seed tidak ditemukan: {$path}");
        }

        $files = [];

        if ($singleFile !== null) {
            $filename = basename($singleFile);
            if ($filename !== $singleFile) {
                throw new RuntimeException('--file harus berupa nama file, tanpa path.');
            }

            if (!preg_match($this->filenamePattern, $filename)) {
                throw new RuntimeException("Nama file seed tidak valid: {$filename}");
            }

            $fullPath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
            if (!is_file($fullPath)) {
                throw new RuntimeException("File seed tidak ditemukan: {$fullPath}");
            }

            $files[$filename] = $fullPath;
            return $files;
        }

        $matches = glob(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql');
        if ($matches === false) {
            throw new RuntimeException("Gagal membaca direktori seed: {$path}");
        }

        foreach ($matches as $fullPath) {
            $filename = basename($fullPath);
            if (!preg_match($this->filenamePattern, $filename)) {
                throw new RuntimeException("Nama file seed tidak valid: {$filename}");
            }
            $files[$filename] = $fullPath;
        }

        ksort($files, SORT_STRING);
        return $files;
    }

    private function ensureSeedsTable()
    {
        $driver = $this->driver();

        if ($driver === 'sqlsrv') {
            $sql = "IF OBJECT_ID('dbo.schema_seeds', 'U') IS NULL
BEGIN
    CREATE TABLE schema_seeds (
        id INT IDENTITY(1,1) PRIMARY KEY,
        seeder VARCHAR(255) NOT NULL UNIQUE,
        checksum VARCHAR(64) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT GETDATE()
    );
END";
            $this->pdo->exec($sql);
            return;
        }

        if ($driver === 'mysql') {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_seeds (
                id INT AUTO_INCREMENT PRIMARY KEY,
                seeder VARCHAR(255) NOT NULL UNIQUE,
                checksum VARCHAR(64) NOT NULL,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            return;
        }

        if ($driver === 'pgsql') {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_seeds (
                id SERIAL PRIMARY KEY,
                seeder VARCHAR(255) NOT NULL UNIQUE,
                checksum VARCHAR(64) NOT NULL,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            return;
        }

        if ($driver === 'sqlite') {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_seeds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                seeder TEXT NOT NULL UNIQUE,
                checksum TEXT NOT NULL,
                applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            return;
        }

        throw new RuntimeException('Driver PDO tidak didukung: ' . $driver);
    }

    private function getAppliedSeeds()
    {
        $stmt = $this->pdo->query('SELECT seeder, checksum FROM schema_seeds');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $applied = [];
        foreach ($rows as $row) {
            $applied[$row['seeder']] = $row['checksum'];
        }

        return $applied;
    }

    private function recordSeed($seeder, $checksum)
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO schema_seeds (seeder, checksum, applied_at) VALUES (:seeder, :checksum, :applied_at)'
        );

        $stmt->execute([
            ':seeder' => $seeder,
            ':checksum' => $checksum,
            ':applied_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function driver()
    {
        return strtolower((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    }
}
