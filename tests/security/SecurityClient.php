<?php

/**
 * SecurityClient — self-managed `php -S` harness + curl blackbox client
 * for the security regression suites (tests/security/*).
 */

declare(strict_types=1);

if (!class_exists('SecurityClient')) {
    final class SecurityClient
    {
        private static ?string $base = null;
        /** @var resource|null */
        private static $serverProc = null;
        private static string $lastError = '';
        /** @var string[] */
        private static array $tmpFiles = [];

        public static function available(): bool
        {
            if (self::$base !== null) {
                return true;
            }
            try {
                self::boot();
                return true;
            } catch (Throwable $e) {
                self::$lastError = $e->getMessage();
                return false;
            }
        }

        public static function lastError(): string
        {
            return self::$lastError;
        }

        public static function base(): string
        {
            if (!self::available()) {
                throw new RuntimeException('security http server unavailable: ' . self::$lastError);
            }
            return (string) self::$base;
        }

        private static function boot(): void
        {
            $root = dirname(__DIR__, 2);
            $php = PHP_BINARY;
            $port = 0;
            for ($p = 8123; $p <= 8140; $p++) {
                $sock = @fsockopen('127.0.0.1', $p, $errno, $errstr, 0.15);
                if ($sock === false) {
                    $port = $p;
                    break;
                }
                fclose($sock);
            }
            if ($port === 0) {
                throw new RuntimeException('no free test port in 8123..8140');
            }

            // Array-form command (no shell) to avoid Windows escapeshellarg pitfalls.
            // Optional coverage instrumentation (opt-in): SEC_COV_PREPEND sets a router
            // wrapper that starts Xdebug coverage per request; default router otherwise.
            $prepend = (string) (getenv('SEC_COV_PREPEND') ?: '');
            $router = $prepend !== '' ? $prepend : $root . DIRECTORY_SEPARATOR . 'router.php';
            $cmd = [$php];
            $cmd[] = '-S';
            $cmd[] = '127.0.0.1:' . $port;
            $cmd[] = '-t';
            $cmd[] = $root;
            $cmd[] = $router;
            $devNull = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
            // Pass the parent environment through so the child sees COV/XDEBUG vars.
            $childEnv = getenv();
            $proc = @proc_open($cmd, [
                0 => ['file', $devNull, 'r'],
                1 => ['file', $devNull, 'w'],
                2 => ['file', $devNull, 'w'],
            ], $pipes, $root, is_array($childEnv) ? $childEnv : null, ['bypass_shell' => true]);
            if (!is_resource($proc)) {
                throw new RuntimeException('failed to start php -S');
            }
            self::$serverProc = $proc;

            self::$base = 'http://127.0.0.1:' . $port;
            for ($i = 0; $i < 60; $i++) {
                $sock = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $errstr, 0.5);
                if (is_resource($sock)) {
                    stream_set_timeout($sock, 2);
                    fwrite($sock, "GET /login HTTP/1.0\r\nHost: 127.0.0.1\r\n\r\n");
                    $resp = (string) fread($sock, 64);
                    fclose($sock);
                    if (strpos($resp, 'HTTP/1.') === 0) {
                        register_shutdown_function(static function (): void {
                            if (is_resource(self::$serverProc)) {
                                proc_terminate(self::$serverProc);
                            }
                            foreach (self::$tmpFiles as $f) {
                                @unlink($f);
                            }
                        });
                        return;
                    }
                }
                usleep(200000);
            }
            throw new RuntimeException('php -S did not answer within 12s');
        }

        public static function newJar(): string
        {
            $jar = (string) tempnam(sys_get_temp_dir(), 'secjar');
            self::$tmpFiles[] = $jar;
            return $jar;
        }

        /**
         * @return array{status:int, headers:array<string,string>, body:string}
         */
        public static function request(string $method, string $path, array $opts = []): array
        {
            $hFile = (string) tempnam(sys_get_temp_dir(), 'sech');
            $bFile = (string) tempnam(sys_get_temp_dir(), 'secb');
            self::$tmpFiles[] = $hFile;
            self::$tmpFiles[] = $bFile;

            $curl = PHP_OS_FAMILY === 'Windows' ? 'curl.exe' : 'curl';
            $args = [$curl, '-s', '-S', '--max-time', '25', '--dump-header', $hFile, '-o', $bFile, '-w', '%{http_code}'];
            if (!empty($opts['jar'])) {
                $args[] = '-b';
                $args[] = (string) $opts['jar'];
                $args[] = '-c';
                $args[] = (string) $opts['jar'];
            }

            $method = strtoupper($method);
            if (!empty($opts['files'])) {
                $args[] = '-X';
                $args[] = 'POST';
                foreach ((array) $opts['files']['fields'] as $k => $v) {
                    $args[] = '-F';
                    $args[] = $k . '=' . $v;
                }
                foreach ((array) $opts['files']['uploads'] as $k => $spec) {
                    $args[] = '-F';
                    $args[] = $k . '=@' . $spec['path'] . ';type=' . ($spec['type'] ?? 'application/octet-stream');
                }
            } elseif (isset($opts['form'])) {
                $args[] = '-X';
                $args[] = 'POST';
                $args[] = '--data';
                $args[] = http_build_query($opts['form']);
            } else {
                $args[] = '-X';
                $args[] = $method;
            }

            foreach ((array) ($opts['headers'] ?? []) as $h) {
                $args[] = '-H';
                $args[] = $h;
            }
            $args[] = self::base() . $path;

            // Run curl WITHOUT a shell (array form via proc_open). escapeshellarg()+exec()
            // is broken on Windows here: escapeshellarg() mangles `%` (so `%{http_code}`
            // becomes ` {http_code}`) and wraps tokens in double quotes that curl's Windows
            // parser rejects as "URL rejected: Malformed input to a URL function".
            $out = [];
            $rc = 0;
            $proc = @proc_open($args, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, null, null, ['bypass_shell' => true]);
            if (is_resource($proc)) {
                fclose($pipes[0]);
                $stdout = (string) stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $rc = proc_close($proc);
                $out[] = trim($stdout);
            }

            $status = 0;
            foreach ($out as $line) {
                if (preg_match('/\b(\d{3})\b/', (string) $line, $mm) === 1) {
                    $status = (int) $mm[1];
                }
            }

            $rawHeaders = (string) @file_get_contents($hFile);
            // keep final header block (before any redirect following — we never follow)
            $blocks = preg_split("/\r?\n\r?\n/", trim($rawHeaders)) ?: [];
            $final = (string) end($blocks);
            $headers = [];
            foreach (explode("\n", str_replace("\r", "\n", $final)) as $line) {
                if (strpos($line, ':') !== false) {
                    [$k, $v] = explode(':', $line, 2);
                    $headers[strtolower(trim($k))] = trim($v);
                }
            }
            $body = (string) @file_get_contents($bFile);

            return ['status' => $status, 'headers' => $headers, 'body' => $body];
        }

        public static function csrfFrom(string $html): ?string
        {
            $m = [];
            if (preg_match('/name="csrf_token" value="([0-9a-f]{64})"/', $html, $m) === 1) {
                return $m[1];
            }
            if (preg_match('/data-csrf="([0-9a-f]{64})"/', $html, $m) === 1) {
                return $m[1];
            }
            return null;
        }

        public static function login(string $username, string $password, string $type): ?string
        {
            $jar = self::newJar();
            $page = self::request('GET', '/login', ['jar' => $jar]);
            $token = self::csrfFrom($page['body']);
            if ($token === null) {
                return null;
            }
            $res = self::request('POST', '/action/login', [
                'jar' => $jar,
                'form' => ['csrf_token' => $token, 'user_type' => $type, 'username' => $username, 'password' => $password],
            ]);
            $loc = (string) ($res['headers']['location'] ?? '');
            return ($res['status'] === 302 && $loc !== '' && $loc !== '/login') ? $jar : null;
        }

        public static function seedUser(?string $type = 'mahasiswa'): bool
        {
            try {
                require_once dirname(__DIR__, 2) . '/config.php';
                $c = $GLOBALS['connect'] ?? null;
                if (!$c instanceof PDO) {
                    return false;
                }
                $table = ['mahasiswa' => 'MAHASISWA', 'dosen' => 'DOSEN', 'admin' => 'ADMIN'][$type ?? 'mahasiswa'] ?? 'MAHASISWA';
                return (int) $c->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() > 0;
            } catch (Throwable $e) {
                return false;
            }
        }

        /** Small PNG magic bytes for upload tests. */
        public static function tempPng(): string
        {
            // Must carry a real extension: the upload handler validates the client
            // filename extension (pdf/jpg/jpeg/png/doc/docx) before accepting the file.
            $f = (string) tempnam(sys_get_temp_dir(), 'secpng');
            $pngFile = $f . '.png';
            @unlink($f);
            self::$tmpFiles[] = $pngFile;
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
            file_put_contents($pngFile, $png);
            return $pngFile;
        }

        public static function tempPhpLike(): string
        {
            $f = (string) tempnam(sys_get_temp_dir(), 'secrepl');
            $replFile = $f . '.png';
            @unlink($f);
            self::$tmpFiles[] = $replFile;
            file_put_contents($replFile, "<?php echo 'pwned'; ?>\nGIF89a this is not a png at all");
            return $replFile;
        }
    }
}
