<?php

class MigrationService
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

    public function migrate($path, $fresh = false, $force = false)
    {
        $this->guardProduction($force, 'migrate');

        if ($fresh) {
            $this->freshDatabase();
        }

        $this->ensureMigrationsTable();

        $files = $this->collectSqlFiles($path);
        if (empty($files)) {
            echo "[migrate] Tidak ada file migrasi di {$path}" . PHP_EOL;
            return 0;
        }

        $applied = $this->getAppliedMigrations();
        $pending = [];

        foreach ($files as $name => $fullPath) {
            $checksum = hash_file('sha256', $fullPath);

            if (isset($applied[$name])) {
                if ($applied[$name] !== $checksum) {
                    throw new RuntimeException("Drift terdeteksi pada migrasi '{$name}'. Checksum file berubah setelah pernah dijalankan.");
                }
                continue;
            }

            $pending[$name] = [
                'path' => $fullPath,
                'checksum' => $checksum,
            ];
        }

        if (empty($pending)) {
            echo "[migrate] Tidak ada migrasi pending" . PHP_EOL;
            return 0;
        }

        $batch = $this->getNextBatchNumber();
        foreach ($pending as $name => $meta) {
            echo "[migrate] Menjalankan {$name} ..." . PHP_EOL;
            $this->sqlRunner->executeFile($this->pdo, $meta['path'], true);
            $this->recordMigration($name, $meta['checksum'], $batch);
            echo "[migrate] Sukses {$name}" . PHP_EOL;
        }

        echo "[migrate] Selesai. Batch {$batch}, total " . count($pending) . " migrasi." . PHP_EOL;
        return 0;
    }

    private function guardProduction($force, $commandName)
    {
        if ($this->appEnv === 'production' && !$force) {
            throw new RuntimeException("Perintah '{$commandName}' ditolak pada APP_ENV=production. Gunakan --force jika yakin.");
        }
    }

    private function collectSqlFiles($path)
    {
        if (!is_dir($path)) {
            throw new RuntimeException("Direktori migrasi tidak ditemukan: {$path}");
        }

        $matches = glob(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql');
        if ($matches === false) {
            throw new RuntimeException("Gagal membaca direktori migrasi: {$path}");
        }

        $files = [];

        foreach ($matches as $fullPath) {
            $filename = basename($fullPath);
            if (!preg_match($this->filenamePattern, $filename)) {
                throw new RuntimeException("Nama file migrasi tidak valid: {$filename}");
            }
            $files[$filename] = $fullPath;
        }

        ksort($files, SORT_STRING);
        return $files;
    }

    private function ensureMigrationsTable()
    {
        $driver = $this->driver();

        if ($driver === 'sqlsrv') {
            $sql = "IF OBJECT_ID('dbo.schema_migrations', 'U') IS NULL
BEGIN
    CREATE TABLE schema_migrations (
        id INT IDENTITY(1,1) PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        checksum VARCHAR(64) NOT NULL,
        batch INT NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT GETDATE()
    );
END";
            $this->pdo->exec($sql);
            return;
        }

        if ($driver === 'mysql') {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                checksum VARCHAR(64) NOT NULL,
                batch INT NOT NULL,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            return;
        }

        if ($driver === 'pgsql') {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
                id SERIAL PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                checksum VARCHAR(64) NOT NULL,
                batch INT NOT NULL,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            return;
        }

        if ($driver === 'sqlite') {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                checksum TEXT NOT NULL,
                batch INTEGER NOT NULL,
                applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
            return;
        }

        throw new RuntimeException('Driver PDO tidak didukung: ' . $driver);
    }

    private function getAppliedMigrations()
    {
        $stmt = $this->pdo->query('SELECT migration, checksum FROM schema_migrations');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $applied = [];
        foreach ($rows as $row) {
            $applied[$row['migration']] = $row['checksum'];
        }

        return $applied;
    }

    private function getNextBatchNumber()
    {
        $stmt = $this->pdo->query('SELECT MAX(batch) AS max_batch FROM schema_migrations');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $maxBatch = $row && $row['max_batch'] !== null ? (int) $row['max_batch'] : 0;

        return $maxBatch + 1;
    }

    private function recordMigration($migration, $checksum, $batch)
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO schema_migrations (migration, checksum, batch, applied_at) VALUES (:migration, :checksum, :batch, :applied_at)'
        );

        $stmt->execute([
            ':migration' => $migration,
            ':checksum' => $checksum,
            ':batch' => (int) $batch,
            ':applied_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function freshDatabase()
    {
        $driver = $this->driver();

        if ($driver === 'sqlsrv') {
            $this->freshSqlsrvDatabase();
            return;
        }

        if ($driver === 'mysql') {
            $this->freshMysqlDatabase();
            return;
        }

        throw new RuntimeException('--fresh saat ini hanya didukung untuk driver sqlsrv dan mysql. Driver aktif: ' . $driver);
    }

    private function freshSqlsrvDatabase()
    {
        echo "[migrate] Menjalankan fresh database (sqlsrv)..." . PHP_EOL;

        $sql = <<<'SQL'
DECLARE @sql NVARCHAR(MAX) = N'';

SELECT @sql += N'ALTER TABLE [' + OBJECT_SCHEMA_NAME(parent_object_id) + N'].[' + OBJECT_NAME(parent_object_id) + N'] DROP CONSTRAINT [' + name + N'];'
FROM sys.foreign_keys;
IF LEN(@sql) > 0 EXEC sp_executesql @sql;

SET @sql = N'';
SELECT @sql += N'DROP VIEW [' + SCHEMA_NAME(schema_id) + N'].[' + name + N'];'
FROM sys.views
WHERE is_ms_shipped = 0;
IF LEN(@sql) > 0 EXEC sp_executesql @sql;

SET @sql = N'';
SELECT @sql += N'DROP PROCEDURE [' + SCHEMA_NAME(schema_id) + N'].[' + name + N'];'
FROM sys.procedures
WHERE is_ms_shipped = 0;
IF LEN(@sql) > 0 EXEC sp_executesql @sql;

SET @sql = N'';
SELECT @sql += N'DROP TABLE [' + SCHEMA_NAME(schema_id) + N'].[' + name + N'];'
FROM sys.tables
WHERE is_ms_shipped = 0;
IF LEN(@sql) > 0 EXEC sp_executesql @sql;
SQL;

        $this->pdo->exec($sql);
    }

    private function freshMysqlDatabase()
    {
        echo "[migrate] Menjalankan fresh database (mysql)..." . PHP_EOL;

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($this->fetchNames("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = DATABASE()") as $view) {
                $this->pdo->exec('DROP VIEW IF EXISTS ' . $this->quoteMysqlIdentifier($view));
            }

            foreach ($this->fetchNames("SELECT TRIGGER_NAME FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()") as $trigger) {
                $this->pdo->exec('DROP TRIGGER IF EXISTS ' . $this->quoteMysqlIdentifier($trigger));
            }

            foreach ($this->fetchNames("SELECT ROUTINE_NAME FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = 'PROCEDURE'") as $procedure) {
                $this->pdo->exec('DROP PROCEDURE IF EXISTS ' . $this->quoteMysqlIdentifier($procedure));
            }

            foreach ($this->fetchNames("SELECT ROUTINE_NAME FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = 'FUNCTION'") as $function) {
                $this->pdo->exec('DROP FUNCTION IF EXISTS ' . $this->quoteMysqlIdentifier($function));
            }

            foreach ($this->fetchNames("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'") as $table) {
                $this->pdo->exec('DROP TABLE IF EXISTS ' . $this->quoteMysqlIdentifier($table));
            }
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function fetchNames($query)
    {
        $stmt = $this->pdo->query($query);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_NUM) : [];
        $names = [];

        foreach ($rows as $row) {
            if (isset($row[0])) {
                $names[] = (string) $row[0];
            }
        }

        return $names;
    }

    private function quoteMysqlIdentifier($identifier)
    {
        return '`' . str_replace('`', '``', (string) $identifier) . '`';
    }

    private function driver()
    {
        return strtolower((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    }
}
