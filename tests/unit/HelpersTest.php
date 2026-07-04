<?php
/**
 * Unit Tests untuk Helper Functions
 * 
 * Test helper functions tanpa dependency database
 */

// Load helpers
require_once __DIR__ . '/../../helpers/path_helper.php';
require_once __DIR__ . '/../../helpers/route_helper.php';
require_once __DIR__ . '/../../helpers/token_helper.php';

// Test: app_path() returns correct path
$runner->addTest('app_path returns correct absolute path', function() {
    $path = app_path('config.php');
    assertTrue(is_string($path), 'app_path should return string');
    assertStringContains('config.php', $path, 'Path should contain config.php');
    assertTrue(file_exists($path), 'Path should exist');
});

// Test: app_url() generates correct URL
$runner->addTest('app_url generates correct URL', function() {
    $url = app_url('/test');
    assertTrue(is_string($url), 'app_url should return string');
    assertStringContains('/test', $url, 'URL should contain /test');
});

// Test: app_route_normalize_path() normalizes paths correctly
$runner->addTest('app_route_normalize_path normalizes paths', function() {
    assertEquals('/', app_route_normalize_path('/'), 'Root path should be /');
    assertEquals('/test', app_route_normalize_path('/test'), 'Path should be /test');
    assertEquals('/test', app_route_normalize_path('/test/'), 'Trailing slash should be removed');
    assertEquals('/test', app_route_normalize_path('test'), 'Leading slash should be added');
    assertEquals('/test/path', app_route_normalize_path('/test/path'), 'Nested path should work');
});

// Test: app_route_registry() returns valid structure
$runner->addTest('app_route_registry returns valid structure', function() {
    $registry = app_route_registry();
    assertIsArray($registry, 'Registry should be array');
    assertTrue(count($registry) > 0, 'Registry should not be empty');
    
    // Check required routes exist
    assertArrayHasKey('page.home', $registry, 'Should have page.home route');
    assertArrayHasKey('page.login', $registry, 'Should have page.login route');
    assertArrayHasKey('action.login', $registry, 'Should have action.login route');
});

// Test: app_route_get() returns route data
$runner->addTest('app_route_get returns route data', function() {
    $route = app_route_get('page.home');
    assertNotNull($route, 'Route should exist');
    assertIsArray($route, 'Route should be array');
    assertArrayHasKey('kind', $route, 'Route should have kind');
    assertArrayHasKey('path', $route, 'Route should have path');
    assertArrayHasKey('target', $route, 'Route should have target');
    assertEquals('page', $route['kind'], 'page.home should be page kind');
});

// Test: app_route_path() returns path for valid route
$runner->addTest('app_route_path returns path for valid route', function() {
    $path = app_route_path('page.home');
    assertNotNull($path, 'Path should not be null');
    assertEquals('/', $path, 'page.home path should be /');
    
    $loginPath = app_route_path('page.login');
    assertEquals('/login', $loginPath, 'page.login path should be /login');
});

// Test: app_route_path() returns null for invalid route
$runner->addTest('app_route_path returns null for invalid route', function() {
    $path = app_route_path('invalid.route');
    assertNull($path, 'Invalid route should return null');
});

// Test: app_route_find_by_path() finds route by path
$runner->addTest('app_route_find_by_path finds route by path', function() {
    $routeName = app_route_find_by_path('/', 'page');
    assertEquals('page.home', $routeName, 'Should find page.home for /');
    
    $loginRoute = app_route_find_by_path('/login', 'page');
    assertEquals('page.login', $loginRoute, 'Should find page.login for /login');
});

// Test: app_route_find_by_path() returns null for invalid path
$runner->addTest('app_route_find_by_path returns null for invalid path', function() {
    $routeName = app_route_find_by_path('/invalid/path', 'page');
    assertNull($routeName, 'Invalid path should return null');
});

// Test: app_route_id_entity_map() returns valid mapping
$runner->addTest('app_route_id_entity_map returns valid mapping', function() {
    $map = app_route_id_entity_map();
    assertIsArray($map, 'Map should be array');
    assertArrayHasKey('id_detail', $map, 'Should have id_detail mapping');
    assertArrayHasKey('id_news', $map, 'Should have id_news mapping');
    assertEquals('detail_pelanggaran', $map['id_detail'], 'id_detail should map to detail_pelanggaran');
});

// Test: app_route_id_entity_for_key() returns entity for valid key
$runner->addTest('app_route_id_entity_for_key returns entity for valid key', function() {
    $entity = app_route_id_entity_for_key('id_detail');
    assertEquals('detail_pelanggaran', $entity, 'id_detail should return detail_pelanggaran');
    
    $newsEntity = app_route_id_entity_for_key('id_news');
    assertEquals('news', $newsEntity, 'id_news should return news');
});

// Test: app_route_id_entity_for_key() returns null for invalid key
$runner->addTest('app_route_id_entity_for_key returns null for invalid key', function() {
    $entity = app_route_id_entity_for_key('invalid_key');
    assertNull($entity, 'Invalid key should return null');
});

echo "Loaded " . count($runner->tests ?? []) . " helper tests\n";
