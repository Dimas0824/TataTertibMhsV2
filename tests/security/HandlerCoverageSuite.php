<?php

/**
 * HandlerCoverageSuite — HTTP-level coverage for action handlers that had none:
 *   - request/handler-notifikasi.php  (/action/notifikasi, JSON action endpoint)
 *   - request/handler-tatib.php       (/action/tatib, admin-only form handler)
 *
 * Drives the real HTTP surface via SecurityClient (its own `php -S`), with
 * throwaway fixtures that are always cleaned up in `finally`.
 */

require_once __DIR__ . '/SecurityClient.php';

if (!SecurityClient::available()) {
    $runner->addTest('handler coverage suite skipped', function () {
        echo "\n       (skipped: cannot start php -S test server: " . SecurityClient::lastError() . ')';
    });
    return;
}

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/helpers/token_helper.php';

$hDb = static function (): ?PDO {
    $c = $GLOBALS['connect'] ?? null;
    return $c instanceof PDO ? $c : null;
};

$hJson = static function (string $body): ?array {
    $d = json_decode($body, true);
    return is_array($d) ? $d : null;
};

/* ------------------------------------------------------------------ */
/* 1. handler-notifikasi.php                                            */
/* ------------------------------------------------------------------ */

$runner->addTest('notif: POST without CSRF returns 419', function () {
    $jar = SecurityClient::login('2341238901', 'password123', 'nim');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $r = SecurityClient::request('POST', '/action/notifikasi', [
        'jar' => $jar,
        'form' => ['action' => 'fetch_list'],
    ]);
    assertEquals(419, $r['status'], 'missing CSRF must be 419');
});

$runner->addTest('notif: GET is rejected (405)', function () {
    $jar = SecurityClient::login('2341238901', 'password123', 'nim');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $r = SecurityClient::request('GET', '/action/notifikasi', ['jar' => $jar]);
    assertTrue(in_array($r['status'], [405, 419], true), 'GET must be 405 (or 419 if CSRF checked first), got ' . $r['status']);
});

$runner->addTest('notif: admin role is forbidden (403)', function () {
    $jar = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    // Fetch a CSRF token from an admin page then POST.
    $page = SecurityClient::request('GET', '/admin', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    $form = ['action' => 'fetch_list'];
    if ($csrf !== null) {
        $form['csrf_token'] = $csrf;
    }
    $r = SecurityClient::request('POST', '/action/notifikasi', ['jar' => $jar, 'form' => $form]);
    assertEquals(403, $r['status'], 'admin role must be 403');
});

$runner->addTest('notif: missing action -> 422, unknown action -> 422', function () {
    $jar = SecurityClient::login('2341238901', 'password123', 'nim');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $page = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    if ($csrf === null) {
        echo "\n       (skipped: no csrf)";
        return;
    }

    $r1 = SecurityClient::request('POST', '/action/notifikasi', ['jar' => $jar, 'form' => ['csrf_token' => $csrf]]);
    assertEquals(422, $r1['status'], 'missing action must be 422');

    $r2 = SecurityClient::request('POST', '/action/notifikasi', ['jar' => $jar, 'form' => ['csrf_token' => $csrf, 'action' => 'zzz-unknown']]);
    assertEquals(422, $r2['status'], 'unknown action must be 422');
});

$runner->addTest('notif: fetch_list (mahasiswa) returns JSON with opaque ids', function () use ($hJson) {
    $jar = SecurityClient::login('2341238901', 'password123', 'nim');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $page = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    if ($csrf === null) {
        echo "\n       (skipped: no csrf)";
        return;
    }

    $r = SecurityClient::request('POST', '/action/notifikasi', ['jar' => $jar, 'form' => ['csrf_token' => $csrf, 'action' => 'fetch_list']]);
    assertEquals(200, $r['status'], 'fetch_list must be 200');
    $j = $hJson($r['body']);
    assertNotNull($j, 'response must be JSON');
    assertTrue(($j['success'] ?? false) === true, 'success must be true');
    assertTrue(is_array($j['notifications'] ?? null), 'notifications must be an array');
    foreach ($j['notifications'] as $n) {
        // ids must be opaque tokens, never raw integers.
        assertTrue(isset($n['id_notifikasi']), 'notification must carry an id token');
        assertTrue(!is_int($n['id_notifikasi']) && $n['id_notifikasi'] !== '', 'id_notifikasi must be an opaque token string');
    }
});

$runner->addTest('notif: fetch_list (dosen) returns 200 JSON', function () use ($hJson) {
    $jar = SecurityClient::login('1234567890', 'password123', 'nidn');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $page = SecurityClient::request('GET', '/pelanggaran/dosen', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    if ($csrf === null) {
        echo "\n       (skipped: no csrf)";
        return;
    }
    $r = SecurityClient::request('POST', '/action/notifikasi', ['jar' => $jar, 'form' => ['csrf_token' => $csrf, 'action' => 'fetch_list']]);
    assertEquals(200, $r['status']);
    assertNotNull($hJson($r['body']));
});

$runner->addTest('notif: mark_read rejects garbage token (422)', function () {
    $jar = SecurityClient::login('2341238901', 'password123', 'nim');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $page = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    if ($csrf === null) {
        echo "\n       (skipped: no csrf)";
        return;
    }
    $r = SecurityClient::request('POST', '/action/notifikasi', ['jar' => $jar, 'form' => [
        'csrf_token' => $csrf,
        'action' => 'mark_read',
        'id_notifikasi' => 'not-a-token',
    ]]);
    assertEquals(422, $r['status'], 'garbage id token must be 422');
});

$runner->addTest('notif: mark_all_read returns JSON (200/400)', function () use ($hJson) {
    $jar = SecurityClient::login('2341238901', 'password123', 'nim');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $page = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    if ($csrf === null) {
        echo "\n       (skipped: no csrf)";
        return;
    }
    $r = SecurityClient::request('POST', '/action/notifikasi', ['jar' => $jar, 'form' => ['csrf_token' => $csrf, 'action' => 'mark_all_read']]);
    assertTrue(in_array($r['status'], [200, 400], true), 'mark_all_read -> 200/400, got ' . $r['status']);
    assertNotNull($hJson($r['body']));
});

/* ------------------------------------------------------------------ */
/* 2. handler-tatib.php (admin-only)                                    */
/* ------------------------------------------------------------------ */

$runner->addTest('tatib: non-admin is blocked before acting', function () {
    $jar = SecurityClient::login('2341238901', 'password123', 'nim');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $page = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    $form = ['store' => '1', 'admin' => '1', 'deskripsi' => 'ZZ-nope', 'tingkat' => 'V', 'poin' => '1'];
    if ($csrf !== null) {
        $form['csrf_token'] = $csrf;
    }
    $r = SecurityClient::request('POST', '/action/tatib', ['jar' => $jar, 'form' => $form]);
    assertTrue(in_array($r['status'], [403, 302, 419], true), 'non-admin must be blocked, got ' . $r['status']);
});

$runner->addTest('tatib: admin without CSRF -> 419', function () {
    $jar = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $r = SecurityClient::request('POST', '/action/tatib', ['jar' => $jar, 'form' => [
        'store' => '1',
        'admin' => '1',
        'deskripsi' => 'ZZ-nocsrf',
        'tingkat' => 'V',
        'poin' => '1',
    ]]);
    assertEquals(419, $r['status'], 'missing CSRF must be 419');
});

$runner->addTest('tatib: store creates a row then it is cleaned up', function () use ($hDb) {
    $c = $hDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $jar = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }

    $form0 = SecurityClient::request('GET', '/admin/tatib', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($form0['body']);
    if ($csrf === null) {
        echo "\n       (skipped: no csrf)";
        return;
    }

    $idAdmin = (int) $c->query('SELECT id_admin FROM ADMIN LIMIT 1')->fetchColumn();
    $marker = 'ZZTATIB' . bin2hex(random_bytes(4));
    $createdId = 0;
    try {
        $r = SecurityClient::request('POST', '/action/tatib', ['jar' => $jar, 'form' => [
            'csrf_token' => $csrf,
            'store' => '1',
            'admin' => (string) $idAdmin,
            'deskripsi' => $marker,
            'tingkat' => 'V',
            'poin' => '1',
        ]]);
        assertTrue(in_array($r['status'], [200, 302], true), 'store should redirect/succeed, got ' . $r['status']);

        $row = $c->prepare('SELECT id_tata_tertib FROM TATA_TERTIB WHERE deskripsi = ? LIMIT 1');
        $row->execute([$marker]);
        $createdId = (int) $row->fetchColumn();
        assertTrue($createdId > 0, 'tatib row must be created');
    } finally {
        if ($createdId > 0) {
            $c->prepare('DELETE FROM TATA_TERTIB WHERE id_tata_tertib = ?')->execute([$createdId]);
        }
        // safety net: remove any stray marker rows
        $c->prepare('DELETE FROM TATA_TERTIB WHERE deskripsi = ?')->execute([$marker]);
    }
});

$runner->addTest('tatib: update/delete with garbage token -> no mutation, still redirect', function () use ($hDb) {
    $c = $hDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $jar = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $page = SecurityClient::request('GET', '/admin/tatib', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    if ($csrf === null) {
        echo "\n       (skipped: no csrf)";
        return;
    }

    $before = (int) $c->query('SELECT COUNT(*) FROM TATA_TERTIB')->fetchColumn();

    $r = SecurityClient::request('POST', '/action/tatib', ['jar' => $jar, 'form' => [
        'csrf_token' => $csrf,
        'delete' => '1',
        'id_tatib' => 'garbage-token',
    ]]);
    assertTrue(in_array($r['status'], [200, 302], true), 'invalid token handler must redirect, got ' . $r['status']);

    $after = (int) $c->query('SELECT COUNT(*) FROM TATA_TERTIB')->fetchColumn();
    assertEquals($before, $after, 'garbage token must not delete anything');
});

$runner->addTest('tatib: unknown action -> flash error + redirect (302)', function () {
    $jar = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $page = SecurityClient::request('GET', '/admin/tatib', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    if ($csrf === null) {
        echo "\n       (skipped: no csrf)";
        return;
    }
    $r = SecurityClient::request('POST', '/action/tatib', ['jar' => $jar, 'form' => ['csrf_token' => $csrf, 'nonsense' => '1']]);
    assertTrue(in_array($r['status'], [302, 200], true), 'unknown action must redirect, got ' . $r['status']);
});

/* ------------------------------------------------------------------ */
/* 3. handler-pelanggaran.php actions (lookup / confirm / delete)       */
/* ------------------------------------------------------------------ */

$runner->addTest('plg-action: lookup_mahasiswa guards (anon 401, mhs 403, missing nim 422)', function () use ($hJson) {
    // anon
    $anon = SecurityClient::request('GET', '/action/pelanggaran?action=lookup_mahasiswa&nim=2341238901');
    assertTrue(in_array($anon['status'], [401, 302, 403], true), 'anon lookup must be blocked, got ' . $anon['status']);

    // mahasiswa is not allowed (dosen-only)
    $mhsJar = SecurityClient::login('2341238901', 'password123', 'nim');
    if ($mhsJar !== null) {
        $r = SecurityClient::request('GET', '/action/pelanggaran?action=lookup_mahasiswa&nim=2341238901', ['jar' => $mhsJar]);
        assertEquals(403, $r['status'], 'mahasiswa lookup must be 403');
    }

    // dosen, missing nim -> 422
    $dosen = SecurityClient::login('1234567890', 'password123', 'nidn');
    if ($dosen !== null) {
        $r2 = SecurityClient::request('GET', '/action/pelanggaran?action=lookup_mahasiswa', ['jar' => $dosen]);
        assertEquals(422, $r2['status'], 'missing nim must be 422');
    }
});

$runner->addTest('plg-action: lookup_mahasiswa (dosen) returns 200 JSON for a real nim', function () use ($hJson, $hDb) {
    $dosen = SecurityClient::login('1234567890', 'password123', 'nidn');
    if ($dosen === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $c = $hDb();
    $nim = $c ? (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn() : '';
    if ($nim === '') {
        echo "\n       (skipped: no seed nim)";
        return;
    }
    $r = SecurityClient::request('GET', '/action/pelanggaran?action=lookup_mahasiswa&nim=' . rawurlencode($nim), ['jar' => $dosen]);
    assertEquals(200, $r['status'], 'valid lookup must be 200');
    $j = $hJson($r['body']);
    assertNotNull($j, 'lookup must return JSON');
    assertTrue(($j['success'] ?? false) === true, 'lookup success must be true');
});

$runner->addTest('plg-action: confirm_selesai refuses mahasiswa/anon; bad token path exercised', function () use ($hDb) {
    $c = $hDb();
    // anon (no csrf) -> 419
    $anon = SecurityClient::request('POST', '/action/pelanggaran', ['form' => ['action' => 'confirm_selesai']]);
    assertTrue(in_array($anon['status'], [419, 401, 302], true), 'anon confirm blocked, got ' . $anon['status']);

    // mahasiswa: valid csrf but wrong role -> redirect (302) with flash
    $mhs = SecurityClient::login('2341238901', 'password123', 'nim');
    if ($mhs !== null) {
        $page = SecurityClient::request('GET', '/pelanggaran', ['jar' => $mhs]);
        $csrf = SecurityClient::csrfFrom($page['body']);
        if ($csrf !== null) {
            $r = SecurityClient::request('POST', '/action/pelanggaran', ['jar' => $mhs, 'form' => [
                'csrf_token' => $csrf,
                'action' => 'confirm_selesai',
                'id_detail' => 'garbage',
            ]]);
            assertTrue(in_array($r['status'], [302, 403], true), 'mahasiswa confirm must be redirected/forbidden, got ' . $r['status']);
        }
    }

    // dosen with garbage token -> redirect (302), no crash
    $dosen = SecurityClient::login('1234567890', 'password123', 'nidn');
    if ($dosen !== null) {
        $page = SecurityClient::request('GET', '/pelanggaran/dosen', ['jar' => $dosen]);
        $csrf = SecurityClient::csrfFrom($page['body']);
        if ($csrf !== null) {
            $r2 = SecurityClient::request('POST', '/action/pelanggaran', ['jar' => $dosen, 'form' => [
                'csrf_token' => $csrf,
                'action' => 'confirm_selesai',
                'id_detail' => 'garbage-token',
            ]]);
            assertEquals(302, $r2['status'], 'dosen confirm with bad token should redirect');
        }
    }
});

$runner->addTest('plg-action: delete refuses non-dosen and handles bad token safely', function () {
    // anon -> 419/401
    $anon = SecurityClient::request('POST', '/action/pelanggaran', ['form' => ['action' => 'delete']]);
    assertTrue(in_array($anon['status'], [419, 401, 302], true), 'anon delete blocked, got ' . $anon['status']);

    // mahasiswa with csrf -> redirect/forbidden
    $mhs = SecurityClient::login('2341238901', 'password123', 'nim');
    if ($mhs !== null) {
        $page = SecurityClient::request('GET', '/pelanggaran', ['jar' => $mhs]);
        $csrf = SecurityClient::csrfFrom($page['body']);
        if ($csrf !== null) {
            $r = SecurityClient::request('POST', '/action/pelanggaran', ['jar' => $mhs, 'form' => [
                'csrf_token' => $csrf,
                'action' => 'delete',
                'id_detail' => 'garbage',
            ]]);
            assertTrue(in_array($r['status'], [302, 403], true), 'mahasiswa delete must be blocked, got ' . $r['status']);
        }
    }

    // dosen with garbage token -> redirect, no mutation
    $dosen = SecurityClient::login('1234567890', 'password123', 'nidn');
    if ($dosen !== null) {
        $page = SecurityClient::request('GET', '/pelanggaran/dosen', ['jar' => $dosen]);
        $csrf = SecurityClient::csrfFrom($page['body']);
        if ($csrf !== null) {
            $r2 = SecurityClient::request('POST', '/action/pelanggaran', ['jar' => $dosen, 'form' => [
                'csrf_token' => $csrf,
                'action' => 'delete',
                'id_detail' => 'garbage-token',
            ]]);
            assertEquals(302, $r2['status'], 'dosen delete with bad token should redirect');
        }
    }
});
