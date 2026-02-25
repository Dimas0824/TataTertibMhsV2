<?php

require_once __DIR__ . '/ConnectionResolver.php';
require_once __DIR__ . '/SqlRunner.php';
require_once __DIR__ . '/MigrationService.php';
require_once __DIR__ . '/SeederService.php';

class ConsoleKernel
{
    private $rootPath;

    public function __construct($rootPath)
    {
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
    }

    public function handle(array $argv)
    {
        $command = isset($argv[1]) ? trim((string) $argv[1]) : 'list';

        try {
            $options = $this->parseOptions(array_slice($argv, 2));

            switch ($command) {
                case 'list':
                case 'help':
                case '--help':
                case '-h':
                    $this->printHelp();
                    return 0;

                case 'migrate':
                    $this->ensureAllowedOptions($options, ['path', 'fresh', 'seed', 'force']);
                    return $this->runMigrate($options);

                case 'migrate:fresh':
                    $this->ensureAllowedOptions($options, ['path', 'seed', 'force']);
                    return $this->runMigrateFresh($options);

                case 'db:seed':
                    $this->ensureAllowedOptions($options, ['path', 'file', 'force']);
                    return $this->runSeed($options);

                default:
                    throw new RuntimeException("Perintah tidak dikenali: {$command}");
            }
        } catch (Throwable $exception) {
            fwrite(STDERR, '[error] ' . $exception->getMessage() . PHP_EOL);
            return 1;
        }
    }

    private function runMigrate(array $options)
    {
        $resolved = $this->resolveConnection();

        $path = $this->resolvePath($options['path'] ?? 'Database/migrations');
        $fresh = !empty($options['fresh']);
        $seed = !empty($options['seed']);
        $force = !empty($options['force']);

        $service = new MigrationService($resolved['pdo'], new SqlRunner(), $resolved['app_env']);
        $status = $service->migrate($path, $fresh, $force);
        if ($status !== 0 || !$seed) {
            return $status;
        }

        echo "[migrate] Menjalankan seed (--seed)..." . PHP_EOL;
        $seedService = new SeederService($resolved['pdo'], new SqlRunner(), $resolved['app_env']);
        $seedPath = $this->resolvePath('Database/seeders');

        return $seedService->seed($seedPath, null, $force);
    }

    private function runMigrateFresh(array $options)
    {
        $options['fresh'] = true;
        return $this->runMigrate($options);
    }

    private function runSeed(array $options)
    {
        $resolved = $this->resolveConnection();

        $path = $this->resolvePath($options['path'] ?? 'Database/seeders');
        $file = isset($options['file']) ? $options['file'] : null;
        $force = !empty($options['force']);

        $service = new SeederService($resolved['pdo'], new SqlRunner(), $resolved['app_env']);
        return $service->seed($path, $file, $force);
    }

    private function resolveConnection()
    {
        $resolver = new ConnectionResolver($this->rootPath);
        $resolved = $resolver->resolve();

        echo '[conn] Source: ' . $resolved['source'] . ', APP_ENV=' . $resolved['app_env'] . PHP_EOL;

        return $resolved;
    }

    private function parseOptions(array $args)
    {
        $options = [];

        for ($i = 0, $total = count($args); $i < $total; $i++) {
            $token = trim((string) $args[$i]);
            if ($token === '') {
                continue;
            }

            if (strpos($token, '--') !== 0) {
                throw new RuntimeException('Argumen tidak valid: ' . $token);
            }

            $option = substr($token, 2);
            $value = true;

            if ($option === '') {
                throw new RuntimeException('Format opsi tidak valid: ' . $token);
            }

            if (strpos($option, '=') !== false) {
                list($option, $value) = explode('=', $option, 2);
                $value = trim((string) $value);
                if ($value === '') {
                    throw new RuntimeException('Nilai opsi tidak boleh kosong: --' . $option);
                }
            } elseif (in_array($option, ['path', 'file'], true)) {
                if (!isset($args[$i + 1])) {
                    throw new RuntimeException('Opsi --' . $option . ' membutuhkan nilai.');
                }

                $nextValue = trim((string) $args[$i + 1]);
                if ($nextValue === '' || strpos($nextValue, '--') === 0) {
                    throw new RuntimeException('Nilai opsi --' . $option . ' tidak valid.');
                }

                $value = $nextValue;
                $i++;
            }

            if (!in_array($option, ['path', 'file', 'fresh', 'seed', 'force'], true)) {
                throw new RuntimeException('Opsi tidak dikenali: --' . $option);
            }

            if (in_array($option, ['fresh', 'seed', 'force'], true) && $value !== true) {
                throw new RuntimeException('Opsi --' . $option . ' tidak menerima nilai.');
            }

            $options[$option] = $value;
        }

        return $options;
    }

    private function ensureAllowedOptions(array $options, array $allowed)
    {
        foreach (array_keys($options) as $option) {
            if (!in_array($option, $allowed, true)) {
                throw new RuntimeException('Opsi --' . $option . ' tidak didukung untuk command ini.');
            }
        }
    }

    private function resolvePath($path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            throw new RuntimeException('Path tidak boleh kosong.');
        }

        if ($this->isAbsolutePath($path)) {
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        return rtrim($this->rootPath . DIRECTORY_SEPARATOR . $path, DIRECTORY_SEPARATOR);
    }

    private function isAbsolutePath($path)
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }

    private function printHelp()
    {
        $lines = [
            'DiscipLink Console',
            '',
            'Usage:',
            '  php artisan list',
            '  php artisan help',
            '  php artisan migrate [--fresh] [--seed] [--force] [--path=Database/migrations]',
            '  php artisan migrate:fresh [--seed] [--force] [--path=Database/migrations]',
            '  php artisan db:seed [--force] [--path=Database/seeders] [--file=<filename.sql>]',
            '',
            'Notes:',
            '  - APP_ENV=production memerlukan --force.',
            '  - Nama file harus format: YYYYMMDD_HHMMSS_name.sql',
        ];

        echo implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
