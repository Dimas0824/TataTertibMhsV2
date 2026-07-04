<?php
/**
 * Unit Tests untuk Models
 * 
 * Test model methods tanpa dependency database (mock)
 */

// Load models
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Pelanggaran.php';
require_once __DIR__ . '/../../models/News.php';
require_once __DIR__ . '/../../models/Tatib.php';
require_once __DIR__ . '/../../models/Sanksi.php';

// Test: User model class exists
$runner->addTest('User model class exists', function() {
    assertTrue(class_exists('User'), 'User class should exist');
});

// Test: Pelanggaran model class exists
$runner->addTest('Pelanggaran model class exists', function() {
    assertTrue(class_exists('Pelanggaran'), 'Pelanggaran class should exist');
});

// Test: News model class exists
$runner->addTest('News model class exists', function() {
    assertTrue(class_exists('News'), 'News class should exist');
});

// Test: Tatib model class exists
$runner->addTest('Tatib model class exists', function() {
    assertTrue(class_exists('Tatib'), 'Tatib class should exist');
});

// Test: Sanksi model class exists
$runner->addTest('Sanksi model class exists', function() {
    assertTrue(class_exists('Sanksi'), 'Sanksi class should exist');
});

// Test: User model has required methods
$runner->addTest('User model has required methods', function() {
    $methods = get_class_methods('User');
    assertContains('getMahasiswaLogin', $methods, 'Should have getMahasiswaLogin method');
    assertContains('getDosenLogin', $methods, 'Should have getDosenLogin method');
    assertContains('getAdminLogin', $methods, 'Should have getAdminLogin method');
    assertContains('getAllUsers', $methods, 'Should have getAllUsers method');
    assertContains('getAllMahasiswa', $methods, 'Should have getAllMahasiswa method');
});

// Test: Pelanggaran model has required methods
$runner->addTest('Pelanggaran model has required methods', function() {
    $methods = get_class_methods('Pelanggaran');
    assertContains('getDetailPelanggaranMahasiswa', $methods, 'Should have getDetailPelanggaranMahasiswa');
    assertContains('getDetailLaporanDosen', $methods, 'Should have getDetailLaporanDosen');
    assertContains('simpanDetailPelanggaran', $methods, 'Should have simpanDetailPelanggaran');
    assertContains('updateDetailPelanggaran', $methods, 'Should have updateDetailPelanggaran');
    assertContains('getMahasiswaByNim', $methods, 'Should have getMahasiswaByNim');
    assertContains('searchMahasiswaByKeyword', $methods, 'Should have searchMahasiswaByKeyword');
});

// Test: News model has required methods
$runner->addTest('News model has required methods', function() {
    $methods = get_class_methods('News');
    assertContains('getAllNews', $methods, 'Should have getAllNews method');
    assertContains('getNewsById', $methods, 'Should have getNewsById method');
    assertContains('insertNews', $methods, 'Should have insertNews method');
    assertContains('updateNews', $methods, 'Should have updateNews method');
    assertContains('deleteNews', $methods, 'Should have deleteNews method');
});

// Test: Tatib model has required methods
$runner->addTest('Tatib model has required methods', function() {
    $methods = get_class_methods('Tatib');
    assertContains('getAllTatib', $methods, 'Should have getAllTatib method');
    assertContains('getTatibById', $methods, 'Should have getTatibById method');
});

echo "Loaded " . count($runner->tests ?? []) . " model tests\n";
