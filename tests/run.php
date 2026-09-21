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
    // Model/controller behaviour tests (DB-backed): raise coverage of
    // models/Pelanggaran.php and controllers/NewsController.php
    __DIR__ . '/unit/PelanggaranModelTest.php',
    __DIR__ . '/unit/PelanggaranControllerTest.php',
    __DIR__ . '/unit/NewsControllerTest.php',
    __DIR__ . '/unit/ModelCrudTest.php',
    __DIR__ . '/unit/SeoHelperTest.php',
    __DIR__ . '/unit/ControllerCoverageTest.php',
    // Login hardening helpers (NUL/length guard + durable throttle):
    __DIR__ . '/unit/LoginThrottleHelperTest.php',
    __DIR__ . '/integration/DatabaseTest.php',
    __DIR__ . '/security/SourceScanSuite.php',
    __DIR__ . '/security/TokenSuite.php',
    // Upload ownership suite uses the HTTP harness too:
    __DIR__ . '/security/UploadOwnershipSuite.php',
    // Handler coverage (notifikasi/tatib) also uses the HTTP harness:
    __DIR__ . '/security/HandlerCoverageSuite.php',
    // News handler coverage (admin CRUD):
    __DIR__ . '/security/NewsHandlerSuite.php',
    // Pelanggaran report form (POST store/update):
    __DIR__ . '/security/PelanggaranFormSuite.php',
    // Login brute-force + NUL-truncation regression (HTTP):
    __DIR__ . '/security/LoginBruteForceSuite.php',
    // Session cookie + lifecycle regression (HTTP + white-box):
    __DIR__ . '/security/SessionLifecycleSuite.php',
    // AREA 3 access-control regression (lecturer-only page):
    __DIR__ . '/security/Area3AccessSuite.php',
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
