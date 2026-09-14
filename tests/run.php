<?php
/**
 * Test Entry Point
 * 
 * Usage: php tests/run.php
 */

// Load test framework
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/bootstrap.php';

// Create test runner
$runner = new TestRunner();

// Load all test files
$testFiles = [
    __DIR__ . '/unit/HelpersTest.php',
    __DIR__ . '/unit/ModelsTest.php',
    __DIR__ . '/integration/DatabaseTest.php',
    __DIR__ . '/security/SourceScanSuite.php',
    __DIR__ . '/security/TokenSuite.php',
    // Upload ownership suite uses the HTTP harness too:
    __DIR__ . '/security/UploadOwnershipSuite.php',
    // HTTP matrix boots its own `php -S` harness; longest-running, last:
    __DIR__ . '/security/HttpMatrixSuite.php',
];

foreach ($testFiles as $testFile) {
    if (file_exists($testFile)) {
        require_once $testFile;
    }
}

// Run tests
exit($runner->run());
