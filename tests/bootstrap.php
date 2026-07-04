<?php
/**
 * PHPUnit Bootstrap
 * 
 * Bootstrap file untuk PHPUnit test suite.
 * Menyiapkan environment dan autoloading untuk testing.
 */

// Define test environment
define('TESTING', true);

// Set error reporting untuk test
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Path setup
$projectRoot = dirname(__DIR__);
define('PROJECT_ROOT', $projectRoot);

// Load konfigurasi test
// Note: Untuk test, kita bisa override .env dengan .env.testing
$envFile = $projectRoot . '/.env.testing';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        
        $separatorPos = strpos($line, '=');
        if ($separatorPos === false) continue;
        
        $key = trim(substr($line, 0, $separatorPos));
        $value = trim(substr($line, $separatorPos + 1));
        
        // Remove quotes
        if (preg_match('/^["\'](.*)["\']\s*$/', $value, $m)) {
            $value = $m[1];
        }
        
        if ($key !== '') {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Simple autoloader untuk classes
spl_autoload_register(function ($class) use ($projectRoot) {
    // Map namespace/class ke file path
    $classFileMap = [
        'User' => $projectRoot . '/models/User.php',
        'Pelanggaran' => $projectRoot . '/models/Pelanggaran.php',
        'News' => $projectRoot . '/models/News.php',
        'Sanksi' => $projectRoot . '/models/Sanksi.php',
        'Tatib' => $projectRoot . '/models/Tatib.php',
        'UserController' => $projectRoot . '/controllers/UserController.php',
        'TatibController' => $projectRoot . '/controllers/TatibController.php',
        'PelanggaranController' => $projectRoot . '/controllers/PelanggaranController.php',
        'NewsController' => $projectRoot . '/controllers/NewsController.php',
    ];
    
    if (isset($classFileMap[$class]) && file_exists($classFileMap[$class])) {
        require_once $classFileMap[$class];
        return true;
    }
    
    return false;
});

// Helper function untuk test
if (!function_exists('test_db_connect')) {
    /**
     * Get PDO connection for testing
     */
    function test_db_connect(): ?PDO
    {
        static $pdo = null;
        
        if ($pdo !== null) {
            return $pdo;
        }
        
        try {
            $dsn = $_ENV['DB_DSN'] ?? 'mysql:host=127.0.0.1;port=3306;dbname=DiscipLink_test;charset=utf8mb4';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';
            
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            
            return $pdo;
        } catch (PDOException $e) {
            echo "Warning: Cannot connect to test database: " . $e->getMessage() . "\n";
            return null;
        }
    }
}

/**
 * Create mock PDO connection for unit tests without database
 */
function create_mock_pdo(): PDO
{
    // Return a mock that simulates PDO behavior
    return new class {
        public function prepare($sql) {
            return new class($sql) {
                private $sql;
                private $params = [];
                
                public function __construct($sql) {
                    $this->sql = $sql;
                }
                
                public function execute($params = null) {
                    $this->params = $params ?? [];
                    return true;
                }
                
                public function fetch($mode = PDO::FETCH_ASSOC) {
                    return false; // Default: no rows
                }
                
                public function fetchAll($mode = PDO::FETCH_ASSOC) {
                    return []; // Default: empty result
                }
                
                public function bindValue($param, $value, $type = null) {
                    return true;
                }
                
                public function bindParam($param, &$var, $type = null, $maxLength = null) {
                    return true;
                }
            };
        }
        
        public function setAttribute($attr, $value) {
            return true;
        }
        
        public function beginTransaction() {
            return true;
        }
        
        public function commit() {
            return true;
        }
        
        public function rollBack() {
            return true;
        }
        
        public function lastInsertId() {
            return '1';
        }
        
        public function inTransaction() {
            return false;
        }
    };
}
