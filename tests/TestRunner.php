<?php
/**
 * Custom Test Runner untuk PHP Native Project
 * 
 * Simple test framework tanpa dependency PHPUnit
 * Usage: php tests/run.php
 */

class TestRunner
{
    private array $tests = [];
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;
    private int $errors = 0;
    private array $failures = [];
    
    public function addTest(string $name, callable $test): void
    {
        $this->tests[$name] = $test;
    }
    
    public function run(): int
    {
        echo "\n";
        echo "===========================================\n";
        echo "  DiscipLink Test Suite\n";
        echo "  " . date('Y-m-d H:i:s') . "\n";
        echo "===========================================\n\n";
        
        $total = count($this->tests);
        $current = 0;
        
        foreach ($this->tests as $name => $test) {
            $current++;
            echo "[$current/$total] $name ... ";
            
            try {
                $test();
                echo "\033[32mPASS\033[0m\n";
                $this->passed++;
                $this->results[] = ['name' => $name, 'status' => 'PASS'];
            } catch (AssertionError $e) {
                echo "\033[31mFAIL\033[0m\n";
                echo "       " . $e->getMessage() . "\n";
                $this->failed++;
                $this->failures[] = ['name' => $name, 'message' => $e->getMessage()];
                $this->results[] = ['name' => $name, 'status' => 'FAIL', 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                echo "\033[33mERROR\033[0m\n";
                echo "       " . get_class($e) . ": " . $e->getMessage() . "\n";
                $this->errors++;
                $this->failures[] = ['name' => $name, 'message' => get_class($e) . ': ' . $e->getMessage()];
                $this->results[] = ['name' => $name, 'status' => 'ERROR', 'message' => $e->getMessage()];
            }
        }
        
        echo "\n";
        echo "===========================================\n";
        echo "  Test Results\n";
        echo "===========================================\n";
        echo "  Total:  $total\n";
        echo "  \033[32mPassed: {$this->passed}\033[0m\n";
        echo "  \033[31mFailed: {$this->failed}\033[0m\n";
        echo "  \033[33mErrors: {$this->errors}\033[0m\n";
        echo "===========================================\n\n";
        
        if (!empty($this->failures)) {
            echo "Failures:\n";
            foreach ($this->failures as $failure) {
                echo "  - {$failure['name']}\n";
                echo "    {$failure['message']}\n";
            }
            echo "\n";
        }
        
        return ($this->failed + $this->errors) > 0 ? 1 : 0;
    }
    
    public function getResults(): array
    {
        return [
            'total' => count($this->tests),
            'passed' => $this->passed,
            'failed' => $this->failed,
            'errors' => $this->errors,
            'failures' => $this->failures,
        ];
    }
}

/**
 * Assertion helper functions
 */
function assertTrue($condition, string $message = ''): void
{
    if (!$condition) {
        throw new AssertionError($message ?: 'Assertion failed: expected true');
    }
}

function assertFalse($condition, string $message = ''): void
{
    if ($condition) {
        throw new AssertionError($message ?: 'Assertion failed: expected false');
    }
}

function assertEquals($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $msg = $message ?: "Expected " . var_export($expected, true) . ", got " . var_export($actual, true);
        throw new AssertionError($msg);
    }
}

function assertNotNull($value, string $message = ''): void
{
    if ($value === null) {
        throw new AssertionError($message ?: 'Assertion failed: expected not null');
    }
}

function assertNull($value, string $message = ''): void
{
    if ($value !== null) {
        throw new AssertionError($message ?: 'Assertion failed: expected null');
    }
}

function assertIsArray($value, string $message = ''): void
{
    if (!is_array($value)) {
        throw new AssertionError($message ?: 'Expected array, got ' . gettype($value));
    }
}

function assertArrayHasKey($key, array $array, string $message = ''): void
{
    if (!array_key_exists($key, $array)) {
        throw new AssertionError($message ?: "Array does not have key '$key'");
    }
}

function assertContains($needle, array $haystack, string $message = ''): void
{
    if (!in_array($needle, $haystack, true)) {
        throw new AssertionError($message ?: "Array does not contain expected value");
    }
}

function assertCount(int $expected, array $array, string $message = ''): void
{
    $actual = count($array);
    if ($actual !== $expected) {
        throw new AssertionError($message ?: "Expected count $expected, got $actual");
    }
}

function assertStringContains(string $needle, string $haystack, string $message = ''): void
{
    if (strpos($haystack, $needle) === false) {
        throw new AssertionError($message ?: "String does not contain '$needle'");
    }
}

function assertInstanceOf(string $expected, $actual, string $message = ''): void
{
    if (!($actual instanceof $expected)) {
        throw new AssertionError($message ?: "Expected instance of $expected, got " . get_class($actual));
    }
}

function assertThrows(string $exceptionClass, callable $callback, string $message = ''): void
{
    try {
        $callback();
        throw new AssertionError($message ?: "Expected exception $exceptionClass was not thrown");
    } catch (Throwable $e) {
        if (!($e instanceof $exceptionClass)) {
            throw new AssertionError($message ?: "Expected $exceptionClass, got " . get_class($e));
        }
    }
}
