<?php

/**
 * HttpMatrixSuite — blackbox red-team regression against a live `php -S`.
 * Replays the exact payload classes from the 2026-09-08 pentest and asserts
 * that every closed hole stays closed (deny matrix, headers, auth, CSRF,
 * IDOR, upload, logout, brute force, error leak, stored XSS pipeline).
 */

require_once __DIR__ . '/SecurityClient.php';

$secHttp = SecurityClient::available();

if (!$secHttp) {
    $runner->addTest('http suite skipped', function () {
        echo "\n       (skipped: cannot start php -S test server: " . SecurityClient::lastError() . ')';
    });
    return;
}

require_once dirname(__DIR__, 2) . '/config.php';

$secDbReady = static function (): bool {
    return isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof PDO;
};

/* ------------------------------------------------------------------ */
/* 1. Fail-closed static deny matrix                                  */
/* ------------------------------------------------------------------ */

$runner->addTest('http: sensitive secrets never served (deny matrix + encoded traversal)', function () {
    $deny = [
        '/.env',
        '/.env.example',
        '/config.php',
        '/artisan',
        '/storage/keys/app_token.key',
        '/views/auth/login.php',
        '/request/handler-login.php',
        '/models/User.php',
        '/helpers/token_helper.php',
        '/controllers/UserController.php',
        '/database/cli/ConsoleKernel.php',
        '/docs/intern/PENTEST-REPORT-2026-09-08.md',
        '/README.md',
        '/BUG_REPORT.md',
        '/tests/bootstrap.php',
        '/%2e%2e/.env',
        '/..%2f.env',
        '/%2E%2E%2F%2Eenv',
        '/storage/../storage/keys/app_token.key',
    ];
    foreach ($deny as $path) {
        $r = SecurityClient::request('GET', $path);
        if ($r['status'] === 200) {
            throw new AssertionError("200 on sensitive path {$path}");
        }
        foreach (['DB_DSN', 'DB_PASS', 'base64 private key', 'sqlite:', 'mysql:host'] as $leak) {
            if (stripos($r['body'], $leak) !== false) {
                throw new AssertionError("leak '{$leak}' in body of {$path}");
            }
        }
    }
});

$runner->addTest('http: legitimate public surface still works', function () {
    foreach (['/', '/login', '/tatib', '/css/global.css', '/img/logo-icon.png', '/robots.txt', '/sitemap.xml'] as $path) {
        $r = SecurityClient::request('GET', $path);
        assertTrue($r['status'] === 200, "{$path} expected 200, got {$r['status']}");
    }
});

/* ------------------------------------------------------------------ */
/* 2. Header & cookie posture                                         */
/* ------------------------------------------------------------------ */

$runner->addTest('http: security headers present, fingerprint headers absent', function () {
    $r = SecurityClient::request('GET', '/login');
    $h = $r['headers'];
    assertTrue(isset($h['content-security-policy']) && strpos($h['content-security-policy'], "frame-ancestors 'none'") !== false, 'CSP missing frame-ancestors');
    assertTrue(isset($h['content-security-policy']) && strpos($h['content-security-policy'], "default-src 'self'") !== false, 'CSP default-src missing');
    assertEquals('DENY', $h['x-frame-options'] ?? null);
    assertEquals('nosniff', $h['x-content-type-options'] ?? null);
    assertTrue(isset($h['referrer-policy']), 'referrer-policy missing');
    assertTrue(!isset($h['x-powered-by']), 'X-Powered-By must be removed');
    assertStringContains('127.0.0.1', $h['server'] ?? '127.0.0.1');
});

$runner->addTest('http: anonymous session cookie is HttpOnly + SameSite=Lax', function () {
    $r = SecurityClient::request('GET', '/login');
    $set = $r['headers']['set-cookie'] ?? '';
    foreach (['PHPSESSID', 'HttpOnly', 'SameSite=Lax'] as $needle) {
        assertStringContains($needle, $set);
    }
});

/* ------------------------------------------------------------------ */
/* 3. Authentication                                                   */
/* ------------------------------------------------------------------ */

$runner->addTest('http: login POST without CSRF returns 419', function () {
    $r = SecurityClient::request('POST', '/action/login', [
        'form' => ['user_type' => 'nim', 'username' => '2341238901', 'password' => 'password123'],
    ]);
    assertEquals(419, $r['status']);
});

$runner->addTest('http: CSRF token rotates at login (pre-login token never reused)', function () {
    $jar = SecurityClient::newJar();
    $pre = SecurityClient::csrfFrom(SecurityClient::request('GET', '/login', ['jar' => $jar])['body']);
    assertTrue($pre !== null, 'pre-login csrf');
    $r = SecurityClient::request('POST', '/action/login', ['jar' => $jar, 'form' => [
        'csrf_token' => $pre,
        'user_type' => 'nim',
        'username' => '2341238901',
        'password' => 'password123',
    ]]);
    if (($r['headers']['location'] ?? '') !== '/pelanggaran') {
        echo "\n       (skipped: login)";
        return;
    }
    $post = SecurityClient::csrfFrom(SecurityClient::request('GET', '/pelanggaran', ['jar' => $jar])['body']);
    assertTrue($post !== null && $post !== $pre, 'session csrf must change after login');
});

$runner->addTest('http: valid credentials login all three roles', function () use ($secDbReady) {
    if (!$secDbReady()) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    assertTrue(SecurityClient::login('2341238901', 'password123', 'nim') !== null, 'mahasiswa login');
    assertTrue(SecurityClient::login('1234567890', 'password123', 'nidn') !== null, 'dosen login');
    assertTrue(SecurityClient::login('ADMIN001', 'admin123', 'nip') !== null, 'admin login');
});

$runner->addTest('http: invalid credentials rejected; response identical shape (generic message)', function () use ($secDbReady) {
    if (!$secDbReady()) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $jar = SecurityClient::newJar();
    $page = SecurityClient::request('GET', '/login', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    assertTrue($csrf !== null);
    foreach (['wronguser9999', '2341238901'] as $u) {
        $r = SecurityClient::request('POST', '/action/login', [
            'jar' => $jar,
            'form' => ['csrf_token' => $csrf, 'user_type' => 'nim', 'username' => $u, 'password' => 'S0r3WrongPass'],
        ]);
        assertEquals('/login', $r['headers']['location'] ?? '', "user {$u} must not get in");
    }
    $after = SecurityClient::request('GET', '/login', ['jar' => $jar]);
    assertStringContains('Invalid username or password', $after['body']);
});

$runner->addTest('http: 5 failures lock the session even for the correct password', function () use ($secDbReady) {
    if (!$secDbReady()) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $jar = SecurityClient::newJar();
    $page = SecurityClient::request('GET', '/login', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    for ($i = 0; $i < 5; $i++) {
        SecurityClient::request('POST', '/action/login', [
            'jar' => $jar,
            'form' => ['csrf_token' => $csrf, 'user_type' => 'nim', 'username' => '2341238901', 'password' => 'bogus' . $i],
        ]);
    }
    $locked = SecurityClient::request('POST', '/action/login', [
        'jar' => $jar,
        'form' => ['csrf_token' => $csrf, 'user_type' => 'nim', 'username' => '2341238901', 'password' => 'password123'],
    ]);
    assertEquals('/login', $locked['headers']['location'] ?? '', 'locked session must not authenticate');
    $view = SecurityClient::request('GET', '/login', ['jar' => $jar]);
    assertStringContains('Terlalu banyak', $view['body'], 'lockout notice missing');
});

/* ------------------------------------------------------------------ */
/* 4. Role gates & capability tokens                                   */
/* ------------------------------------------------------------------ */

$runner->addTest('http: anonymous cannot reach role pages without redirect to login', function () {
    foreach (['/pelanggaran', '/pelanggaran/dosen', '/admin', '/admin/news', '/notifikasi'] as $p) {
        $r = SecurityClient::request('GET', $p);
        assertTrue(in_array($r['status'], [302, 401, 403], true), "{$p} -> {$r['status']} not blocked");
    }
});

$runner->addTest('http: mahasiswa blocked from every admin page', function () use ($secDbReady) {
    if (!$secDbReady()) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $jar = SecurityClient::login('2341238901', 'password123', 'nim');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    foreach (['/admin', '/admin/news', '/admin/news/tambah', '/admin/tatib'] as $p) {
        $r = SecurityClient::request('GET', $p, ['jar' => $jar]);
        assertTrue($r['status'] !== 200, "{$p} returned 200 to mahasiswa");
    }
});

$runner->addTest('http: admin edit token is session-bound — foreign + anon replay rejected', function () use ($secDbReady) {
    if (!$secDbReady()) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $admin = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    $mhs = SecurityClient::login('2341238901', 'password123', 'nim');
    if ($admin === null || $mhs === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $list = SecurityClient::request('GET', '/admin/news', ['jar' => $admin]);
    $m = [];
    if (preg_match('/id_news=([A-Za-z0-9\-\._~%]{40,})/', $list['body'], $m) !== 1) {
        echo "\n       (skipped: no issued news token)";
        return;
    }
    $tok = $m[1];
    $path = '/admin/news/edit?id_news=' . $tok;
    assertTrue(SecurityClient::request('GET', $path, ['jar' => $admin])['status'] === 200, 'owner must open own edit page');
    foreach ([null, $mhs] as $idx => $jarAtt) {
        $r = SecurityClient::request('GET', $path, $jarAtt ? ['jar' => $jarAtt] : []);
        assertTrue(in_array($r['status'], [401, 403, 302], true), 'replay by ' . ($idx ? 'mahasiswa' : 'anon') . " got {$r['status']}");
    }
});

$runner->addTest('http: download without/with raw filename refused (token required)', function () use ($secDbReady) {
    if ($secDbReady() && ($jar = SecurityClient::login('2341238901', 'password123', 'nim'))) {
        $r = SecurityClient::request('GET', '/action/download?file=x.pdf', ['jar' => $jar]);
        assertEquals(403, $r['status'], 'raw filename must not download');
    }
    $anon = SecurityClient::request('GET', '/action/download?file=x.pdf');
    assertTrue(in_array($anon['status'], [401, 403], true));
});

/* ------------------------------------------------------------------ */
/* 5. Upload + full-chain capability                                  */
/* ------------------------------------------------------------------ */

$runner->addTest('http: upload endpoint enforces CSRF and rejects unknown detail tokens', function () {
    $jar = SecurityClient::login('2341238901', 'password123', 'nim');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $page = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    $r = SecurityClient::request('POST', '/action/upload', ['jar' => $jar, 'files' => [
        'fields' => ['id_detail' => 'garbage'],
        'uploads' => ['suratPernyataan' => ['path' => SecurityClient::tempPng(), 'type' => 'image/png']],
    ]]);
    // login+csrf checked before id resolution; page had no form (no seeded details) -> 419 or 422
    assertTrue(in_array($r['status'], [419, 422], true), "expected 419/422, got {$r['status']}");
    if ($csrf !== null) {
        $r2 = SecurityClient::request('POST', '/action/upload', ['jar' => $jar, 'files' => [
            'fields' => ['csrf_token' => $csrf, 'id_detail' => 'garbage'],
            'uploads' => ['suratPernyataan' => ['path' => SecurityClient::tempPng(), 'type' => 'image/png']],
        ]]);
        assertEquals(422, $r2['status'], 'invalid detail token must be 422');
    }
});

$runner->addTest('http: e2e — fixture violation, upload png, tokenized download, replay blocked, cleanup', function () use ($secDbReady) {
    $connect = $GLOBALS['connect'];
    if (!$secDbReady()) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    assertTrue($connect instanceof PDO);

    $mhs = $connect->query("SELECT id_mhs FROM MAHASISWA WHERE nim='2341238901'")->fetch(PDO::FETCH_ASSOC);
    $dsn = $connect->query("SELECT id_dosen FROM DOSEN WHERE nidn='1234567890'")->fetch(PDO::FETCH_ASSOC);
    if (!$mhs || !$dsn) {
        echo "\n       (skipped: seed accounts missing)";
        return;
    }

    $ins = $connect->prepare('INSERT INTO DETAIL_PELANGGARAN
        (id_dosen, id_tata_tertib, id_mahasiswa, id_sanksi, status, status_tugas, delegasi_tugas_ke_dpa, id_dosen_penanggung_jawab, detail_pelanggaran)
        VALUES (?, 1, ?, 3, ?, ?, 0, ?, ?)');
    $marker = 'ZZSEC' . bin2hex(random_bytes(4));
    $ins->execute([(int) $dsn['id_dosen'], (int) $mhs['id_mhs'], 'Proses Bimbingan', 'Belum Dikerjakan', (int) $dsn['id_dosen'], $marker]);
    $idDetail = (int) $connect->lastInsertId();

    try {
        $jar = SecurityClient::login('2341238901', 'password123', 'nim');
        assertTrue($jar !== null, 'fixture login');
        $page = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jar]);
        $csrf = SecurityClient::csrfFrom($page['body']);
        assertTrue($csrf !== null, 'upload form csrf present');

        // The page renders a token per violation; they are opaque, so we cannot tell
        // which maps to our fixture without trying. Iterate until our row is written
        // (regresses both the HY093 500 and any page/token ordering assumption).
        preg_match_all('/id_detail"?\s+value="((?:s1|o1)\.[A-Za-z0-9\-\._~%]+)"/', $page['body'], $allTok);
        $tokens = array_values(array_unique($allTok[1] ?? []));
        assertTrue($tokens !== [], 'id_detail token rendered');

        $uploadStatus = 0;
        $uploadBody = '';
        $saved = null;
        foreach ($tokens as $tok) {
            $connect->exec("UPDATE DETAIL_PELANGGARAN SET surat=NULL WHERE id_detail={$idDetail}");
            $up = SecurityClient::request('POST', '/action/upload', ['jar' => $jar, 'files' => [
                'fields' => ['csrf_token' => $csrf, 'id_detail' => $tok],
                'uploads' => ['suratPernyataan' => ['path' => SecurityClient::tempPng(), 'type' => 'image/png']],
            ]]);
            $uploadStatus = (int) $up['status'];
            $uploadBody = (string) $up['body'];
            $candidate = $connect->query("SELECT surat FROM DETAIL_PELANGGARAN WHERE id_detail={$idDetail}")->fetchColumn();
            if (is_string($candidate) && $candidate !== '') {
                $saved = $candidate;
                break;
            }
        }

        assertTrue($uploadStatus === 200, 'upload rejected: ' . $uploadStatus . ' body=' . substr($uploadBody, 0, 120));

        assertTrue(is_string($saved) && $saved !== '', 'surat column not set');
        assertTrue(strpos((string) $saved, 'php') === false, 'random name must not keep executable ext');

        // student page now links the file via issued token; pick the link that actually
        // resolves to OUR uploaded file, not merely the first rendered one.
        $page2 = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jar]);
        preg_match_all('/action\/download\?file=((?:s1|o1)\.[A-Za-z0-9\-\._~%]{40,})/', $page2['body'], $allDl);
        $dlTokens = array_values(array_unique($allDl[1] ?? []));
        assertTrue($dlTokens !== [], 'download token not rendered');

        $ownOk = false;
        $ownIdorToken = null;
        foreach ($dlTokens as $dlTok) {
            $own = SecurityClient::request('GET', '/action/download?file=' . $dlTok, ['jar' => $jar]);
            if ($own['status'] === 200 && strlen($own['body']) > 0) {
                $ownOk = true;
                $ownIdorToken = $dlTok;
                break;
            }
        }
        assertTrue($ownOk, 'owner download failed for every issued token');

        $other = SecurityClient::login('1234567890', 'password123', 'nidn');
        $replay = SecurityClient::request('GET', '/action/download?file=' . $ownIdorToken, ['jar' => $other]);
        assertTrue($replay['status'] !== 200, 'foreign session replayed a mahasiswa file token (IDOR!)');
        $anon = SecurityClient::request('GET', '/action/download?file=' . $ownIdorToken);
        assertTrue($anon['status'] !== 200, 'anonymous replay succeeded');

        $connect->exec("UPDATE DETAIL_PELANGGARAN SET surat=NULL WHERE id_detail={$idDetail}");
        @unlink(dirname(__DIR__, 2) . '/storage/uploads/' . $saved);
    } finally {
        $connect->exec("DELETE FROM DETAIL_PELANGGARAN WHERE id_detail={$idDetail} AND detail_pelanggaran=" . $connect->quote($marker));
    }
});

/* ------------------------------------------------------------------ */
/* 6. Query abuse + logout                                             */
/* ------------------------------------------------------------------ */

$runner->addTest('http: dosen student-search neutralizes LIKE wildcards but keeps real hits', function () {
    $jar = SecurityClient::login('1234567890', 'password123', 'nidn');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $wild = SecurityClient::request('GET', '/action/pelanggaran?action=search_mahasiswa&q=%25&limit=25', ['jar' => $jar]);
    $w = json_decode($wild['body'], true);
    assertTrue(($w['success'] ?? false) === true, 'search endpoint broken: ' . $wild['body']);
    assertEquals([], $w['data'] ?? null, "LIKE '%' must match nothing, got " . count($w['data'] ?? []));

    $real = SecurityClient::request('GET', '/action/pelanggaran?action=search_mahasiswa&q=2341&limit=25', ['jar' => $jar]);
    $d = json_decode($real['body'], true);
    assertTrue(!empty($d['data']), 'control search for 2341 must return seeded students');
});

$runner->addTest('http: logout POST needs CSRF; after logout session dies and cookie expires', function () {
    $jar = SecurityClient::login('2341238901', 'password123', 'nim');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }

    assertEquals(405, SecurityClient::request('GET', '/action/logout', ['jar' => $jar])['status'], 'GET logout must be 405');

    $noCsrf = SecurityClient::request('POST', '/action/logout', ['jar' => $jar, 'form' => []]);
    assertEquals(419, $noCsrf['status']);

    $page = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    assertTrue($csrf !== null);
    $ok = SecurityClient::request('POST', '/action/logout', ['jar' => $jar, 'form' => ['csrf_token' => $csrf]]);
    assertEquals(302, $ok['status']);
    $sc = strtolower($ok['headers']['set-cookie'] ?? '');
    assertTrue(strpos($sc, 'phpsessid') !== false && (strpos($sc, '=;') !== false || strpos($sc, 'deleted') !== false || strpos($sc, 'expires=thu') !== false || strpos($sc, 'max-age=') !== false), 'logout must expire PHPSESSID, got: ' . $sc);

    $gone = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jar]);
    assertTrue($gone['status'] === 302 && ($gone['headers']['location'] ?? '') === '/login', 'session must be dead after logout');
});

/* ------------------------------------------------------------------ */
/* 7. Stored-XSS pipeline (create as admin, read as public)            */
/* ------------------------------------------------------------------ */

$runner->addTest('http: admin-created stored XSS payload is neutralized at rest and in render', function () use ($secDbReady) {
    $connect = $GLOBALS['connect'];
    if (!$secDbReady()) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $admin = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    if ($admin === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $form = SecurityClient::request('GET', '/admin/news/tambah', ['jar' => $admin]);
    $csrf = SecurityClient::csrfFrom($form['body']);
    $penulisM = [];
    $penulis = preg_match('/name="penulis"[^>]*value="(\d+)"/', $form['body'], $penulisM) === 1 ? $penulisM[1] : null;
    if ($penulis === null) {
        $penulis = (string) $connect->query('SELECT id_admin FROM ADMIN LIMIT 1')->fetchColumn();
    }
    assertTrue($csrf !== null && $penulis !== '' && $penulis !== null, 'form inputs not found');

    $judul = 'ZZ SEC XSS ' . bin2hex(random_bytes(3));
    $konten = '<p>MarkerHalo zzplain</p><p onmouseover="zzxss1()">x</p><p/onmouseover="zzxss2()">y</p><img src=x onerror="zzxss3()"><script>zzxss4()</script><a href="javascript:zzxss5()">z</a>';
    SecurityClient::request('POST', '/action/news', ['jar' => $admin, 'form' => [
        'csrf_token' => $csrf,
        'store' => '1',
        'judul' => $judul,
        'penulis' => $penulis,
        'konten' => $konten,
    ]]);

    // `news` has no `slug` column — the slug is derived on the fly from judul + id_news
    // (see NewsController::news_build_slug). Read the stored columns only.
    $row = $connect->prepare('SELECT id_news, konten, judul FROM news WHERE judul = ? LIMIT 1');
    $row->execute([$judul]);
    $saved = $row->fetch(PDO::FETCH_ASSOC);
    try {
        assertTrue(is_array($saved), 'news row not created');
        // Assert the ACTIVE vectors are gone (tags/attributes/URL schemes), not bare
        // function-name text: the sanitizer legitimately keeps "zzxss4()" as inert text
        // once <script> is stripped, which is safe (it cannot execute).
        foreach (['<script', '</script', '<img', 'onerror', 'onmouseover', 'javascript:', '<p/', 'p/on'] as $bad) {
            assertTrue(stripos((string) $saved['konten'], $bad) === false, "stored HTML still carries {$bad}");
        }
        assertStringContains('MarkerHalo zzplain', (string) $saved['konten']);

        $idNews = (int) ($saved['id_news'] ?? 0);
        if ($idNews > 0 && class_exists('NewsController')) {
            $slug = NewsController::news_build_slug((string) $saved['judul'], $idNews);
            $pub = SecurityClient::request('GET', '/berita?slug=' . rawurlencode($slug));
            assertTrue($pub['status'] === 200, 'public detail page must render, got ' . $pub['status']);
            // The page legitimately contains its own <script>/<img> scaffolding, so assert
            // on the payload's ACTIVE vectors — not on bare tags which every page has.
            // The detail view re-sanitizes at render (strip_tags allowlist + on*/style
            // stripping), so no event handler or URL scheme survives.
            foreach (['onerror=', 'onmouseover=', 'javascript:'] as $bad) {
                assertTrue(stripos($pub['body'], $bad) === false, "active vector {$bad} survived to public render");
            }
        }
    } finally {
        if (is_array($saved)) {
            $del = $connect->prepare('DELETE FROM news WHERE judul = ?');
            $del->execute([$judul]);
        }
    }
});

/* ------------------------------------------------------------------ */
/* 8. Error-disclosure smoke                                          */
/* ------------------------------------------------------------------ */

$runner->addTest('http: generic error pages never leak internals', function () {
    $probes = ['/nope-does-not-exist', '/berita?slug=zzez-nonexistent', '/action/pelanggaran?action=zzz'];
    foreach ($probes as $p) {
        $r = SecurityClient::request('GET', $p);
        foreach (['SQLSTATE', 'Fatal error', 'Warning:', 'Notice:', 'PDOException', 'Stack trace', 'in /', 'D:\\', '/var/www'] as $leak) {
            assertTrue(stripos($r['body'], $leak) === false, "{$leak} leaked on {$p} (status {$r['status']})");
        }
    }
});
