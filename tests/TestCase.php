<?php
/**
 * Base Test Case
 * 
 * Base class untuk semua test case.
 * Menyediakan helper methods dan setup/teardown.
 */

abstract class TestCase
{
    protected PDO $db;
    protected bool $dbConnected = false;
    
    public function __construct()
    {
        $this->db = test_db_connect();
        $this->dbConnected = $this->db !== null;
    }
    
    protected function setUp(): void
    {
        // Override di child class
    }
    
    protected function tearDown(): void
    {
        // Override di child class
    }
    
    /**
     * Skip test jika database tidak tersedia
     */
    protected function skipIfNoDatabase(): void
    {
        if (!$this->dbConnected) {
            $this->markTestSkipped('Database tidak tersedia untuk testing');
        }
    }
    
    /**
     * Assert that a value is an array
     */
    protected function assertIsArray($actual, string $message = ''): void
    {
        $this->assertTrue(is_array($actual), $message ?: 'Expected array, got ' . gettype($actual));
    }
    
    /**
     * Assert that an array has a specific key
     */
    protected function assertArrayHasKeyStrict($key, array $array, string $message = ''): void
    {
        $this->assertTrue(array_key_exists($key, $array), $message ?: "Array does not have key '$key'");
    }
    
    /**
     * Assert response structure for JSON API
     */
    protected function assertApiResponse($response, bool $expectSuccess = true, string $message = null): void
    {
        $this->assertIsArray($response, 'Response should be an array');
        
        if ($expectSuccess) {
            $this->assertArrayHasKeyStrict('success', $response, 'Response should have "success" key');
            $this->assertTrue($response['success'], $message ?? 'API call should succeed');
        }
    }
    
    /**
     * Create a test session array
     */
    protected function createTestSession(array $userData = []): array
    {
        return array_merge([
            'login' => true,
            'user_type' => 'mahasiswa',
            'user_id' => 1,
            'username' => 'test_user',
            'nama_lengkap' => 'Test User',
            'role' => 'mahasiswa',
        ], $userData);
    }
    
    /**
     * Generate random string for testing
     */
    protected function randomString(int $length = 10): string
    {
        return substr(bin2hex(random_bytes(ceil($length / 2))), 0, $length);
    }
}
