<?php
/**
 * Integration Tests untuk Database
 * 
 * Test database connection dan basic queries
 * Skip jika database tidak tersedia
 */

$db = test_db_connect();
$dbConnected = $db !== null;

if (!$dbConnected) {
    echo "⚠ Database not available, skipping integration tests\n";
    return;
}

// Test: Database connection works
$runner->addTest('Database connection works', function() use ($db) {
    assertNotNull($db, 'Database connection should not be null');
    
    // Test query
    $stmt = $db->query('SELECT 1 as test');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    assertEquals('1', $result['test'], 'Should return 1');
});

// Test: Database has required tables
$runner->addTest('Database has required tables', function() use ($db) {
    $stmt = $db->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredTables = ['MAHASISWA', 'DOSEN', 'ADMIN', 'TATA_TERTIB', 'SANKSI', 'DETAIL_PELANGGARAN'];
    
    foreach ($requiredTables as $table) {
        assertContains($table, $tables, "Table $table should exist");
    }
});

// Test: MAHASISWA table has correct structure
$runner->addTest('MAHASISWA table has correct structure', function() use ($db) {
    $stmt = $db->query('DESCRIBE MAHASISWA');
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = ['id_mhs', 'nim', 'nama_lengkap', 'password', 'email'];
    
    foreach ($requiredColumns as $column) {
        assertContains($column, $columns, "Column $column should exist in MAHASISWA");
    }
});

// Test: DOSEN table has correct structure
$runner->addTest('DOSEN table has correct structure', function() use ($db) {
    $stmt = $db->query('DESCRIBE DOSEN');
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = ['id_dosen', 'nidn', 'nama_lengkap', 'password'];
    
    foreach ($requiredColumns as $column) {
        assertContains($column, $columns, "Column $column should exist in DOSEN");
    }
});

// Test: TATA_TERTIB table has correct structure
$runner->addTest('TATA_TERTIB table has correct structure', function() use ($db) {
    $stmt = $db->query('DESCRIBE TATA_TERTIB');
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = ['id_tata_tertib', 'deskripsi', 'tingkat', 'poin'];
    
    foreach ($requiredColumns as $column) {
        assertContains($column, $columns, "Column $column should exist in TATA_TERTIB");
    }
});

// Test: Can query TATA_TERTIB data
$runner->addTest('Can query TATA_TERTIB data', function() use ($db) {
    $stmt = $db->query('SELECT COUNT(*) as count FROM TATA_TERTIB');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    assertTrue((int)$result['count'] >= 0, 'Should return count >= 0');
});

// Test: Can query SANKSI data
$runner->addTest('Can query SANKSI data', function() use ($db) {
    $stmt = $db->query('SELECT COUNT(*) as count FROM SANKSI');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    assertTrue((int)$result['count'] >= 0, 'Should return count >= 0');
});

echo "Loaded integration tests (requires database)\n";
