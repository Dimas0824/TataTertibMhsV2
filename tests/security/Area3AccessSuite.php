<?php

/**
 * Area3AccessSuite — regression for the AREA 3 non-security defect.
 *
 * Strix AREA 3 found no vulnerabilities but flagged: an administrator reaching
 * the lecturer-only violation page got an unhandled HTTP 500 because the view
 * reads $userData['nidn'], which an admin account does not have. The fix is a
 * role guard returning 403 (fail-closed, no internal detail).
 */

require_once __DIR__ . '/SecurityClient.php';

if (!SecurityClient::available()) {
    $runner->addTest('area3 access suite skipped', function () {
        echo "\n       (skipped: cannot start php -S test server: " . SecurityClient::lastError() . ')';
    });
    return;
}

require_once dirname(__DIR__, 2) . '/config.php';

$runner->addTest('area3: admin on lecturer-only page gets 403, not 500', function () {
    $jar = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    if ($jar === null) {
        echo "\n       (skipped: admin login unavailable)";
        return;
    }

    $r = SecurityClient::request('GET', '/pelanggaran/dosen', ['jar' => $jar]);
    assertEquals(403, $r['status'], 'a non-dosen role must be refused with 403, not an unhandled 500');
});

$runner->addTest('area3: dosen can still open their own violation page', function () {
    $jar = SecurityClient::login('1234567890', 'password123', 'nidn');
    if ($jar === null) {
        echo "\n       (skipped: dosen login unavailable)";
        return;
    }

    $r = SecurityClient::request('GET', '/pelanggaran/dosen', ['jar' => $jar]);
    assertEquals(200, $r['status'], 'a lecturer must still reach the lecturer page');
});

$runner->addTest('area3: admin on the student violation page gets 403, not 500', function () {
    $jar = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    if ($jar === null) {
        echo "\n       (skipped: admin login unavailable)";
        return;
    }

    $r = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jar]);
    assertEquals(403, $r['status'], 'a non-student role must be refused with 403 on the student page (CWE-754)');
});

$runner->addTest('area3: mahasiswa can still open their own violation page', function () {
    $jar = SecurityClient::login('2341238901', 'password123', 'nim');
    if ($jar === null) {
        echo "\n       (skipped: mahasiswa login unavailable)";
        return;
    }

    $r = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jar]);
    assertEquals(200, $r['status'], 'a student must still reach the student page');
});

