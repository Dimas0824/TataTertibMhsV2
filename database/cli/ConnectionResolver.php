<?php

class ConnectionResolver
{
    private $rootPath;

    public function __construct($rootPath)
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
    }

    public function resolve()
    {
        $envValues = $this->loadEnvFile($this->rootPath . '/.env');
        $appEnv = isset($envValues['APP_ENV']) && $envValues['APP_ENV'] !== ''
            ? $envValues['APP_ENV']
            : (getenv('APP_ENV') ?: 'local');

        if (!empty($envValues['DB_DSN'])) {
            $pdo = $this->createFromEnv($envValues['DB_DSN'], $envValues['DB_USER'] ?? null, $envValues['DB_PASS'] ?? null);
            return [
                'pdo' => $pdo,
                'app_env' => $appEnv,
                'source' => '.env',
            ];
        }

        $configPath = $this->rootPath . '/config.php';
        if (is_file($configPath)) {
            require_once $configPath;

            if (isset($connect) && $connect instanceof PDO) {
                $this->configurePdo($connect);
                return [
                    'pdo' => $connect,
                    'app_env' => $appEnv,
                    'source' => 'config.php',
                ];
            }
        }

        throw new RuntimeException(
            'Koneksi DB tidak ditemukan. Isi .env (DB_DSN, DB_USER, DB_PASS) atau sediakan $connect di config.php'
        );
    }

    private function createFromEnv($dsn, $user, $pass)
    {
        $username = $user === '' ? null : $user;
        $password = $pass === '' ? null : $pass;

        $pdo = new PDO($dsn, $username, $password);
        $this->configurePdo($pdo);
        return $pdo;
    }

    private function configurePdo(PDO $pdo)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        try {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (Throwable $exception) {
            // Some PDO drivers do not support emulate prepares.
        }
    }

    private function loadEnvFile($path)
    {
        if (!is_file($path)) {
            return [];
        }

        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return $values;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }

            $separatorPos = strpos($line, '=');
            if ($separatorPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separatorPos));
            $value = trim(substr($line, $separatorPos + 1));

            if ($key === '') {
                continue;
            }

            if ($value !== '') {
                $first = substr($value, 0, 1);
                $last = substr($value, -1);
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            $values[$key] = $value;
        }

        return $values;
    }
}
