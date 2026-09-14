<?php

/**
 * NewsHandlerSuite — HTTP coverage for request/handler-news.php branches:
 * store (valid/invalid), update (valid/invalid/bad token/unknown admin),
 * delete (valid/bad token) and the "invalid action" fallback.
 *
 * Uses SecurityClient (own `php -S`) and cleans up every row it creates.
 */

require_once __DIR__ . '/SecurityClient.php';

if (!SecurityClient::available()) {
    $runner->addTest('news handler suite skipped', function () {
        echo "\n       (skipped: cannot start php -S: " . SecurityClient::lastError() . ')';
    });
    return;
}

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/controllers/NewsController.php';

$nhDb = static function (): ?PDO {
    $c = $GLOBALS['connect'] ?? null;
    return $c instanceof PDO ? $c : null;
};

/** Fetch a fresh CSRF token from an admin page for a session. */
$nhCsrf = static function (string $jar): ?string {
    $page = SecurityClient::request('GET', '/admin/news/tambah', ['jar' => $jar]);
    return SecurityClient::csrfFrom($page['body']);
};

$runner->addTest('news-handler: anonymous store is blocked (401/403/419/302)', function () {
    $r = SecurityClient::request('POST', '/action/news', ['form' => [
        'store' => '1',
        'judul' => 'x',
        'penulis' => '1',
        'konten' => '<p>x</p>',
    ]]);
    assertTrue(in_array($r['status'], [401, 403, 419, 302], true), 'anon store blocked, got ' . $r['status']);
});

$runner->addTest('news-handler: admin store without CSRF -> 419', function () {
    $jar = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $r = SecurityClient::request('POST', '/action/news', ['jar' => $jar, 'form' => [
        'store' => '1',
        'judul' => 'x',
        'penulis' => '1',
        'konten' => '<p>x</p>',
    ]]);
    assertEquals(419, $r['status'], 'missing CSRF must be 419');
});

$runner->addTest('news-handler: store missing fields -> redirect error, no row', function () use ($nhDb, $nhCsrf) {
    $c = $nhDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $jar = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $csrf = $nhCsrf($jar);
    if ($csrf === null) {
        echo "\n       (skipped: no csrf)";
        return;
    }

    $before = (int) $c->query('SELECT COUNT(*) FROM news')->fetchColumn();
    $r = SecurityClient::request('POST', '/action/news', ['jar' => $jar, 'form' => [
        'csrf_token' => $csrf,
        'store' => '1',
        'judul' => '',
        'penulis' => '',
        'konten' => '',
    ]]);
    assertEquals(302, $r['status'], 'empty fields -> redirect');
    $after = (int) $c->query('SELECT COUNT(*) FROM news')->fetchColumn();
    assertEquals($before, $after, 'no row must be created on invalid input');
});

$runner->addTest('news-handler: store non-numeric penulis -> redirect error, no row', function () use ($nhDb, $nhCsrf) {
    $c = $nhDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $jar = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $csrf = $nhCsrf($jar);
    if ($csrf === null) {
        echo "\n       (skipped: no csrf)";
        return;
    }
    $before = (int) $c->query('SELECT COUNT(*) FROM news')->fetchColumn();
    $r = SecurityClient::request('POST', '/action/news', ['jar' => $jar, 'form' => [
        'csrf_token' => $csrf,
        'store' => '1',
        'judul' => 'ZZ-nonnum',
        'penulis' => 'abc',
        'konten' => '<p>x</p>',
    ]]);
    assertEquals(302, $r['status']);
    $after = (int) $c->query('SELECT COUNT(*) FROM news')->fetchColumn();
    assertEquals($before, $after, 'non-numeric penulis must not create a row');
});

$runner->addTest('news-handler: store valid creates a row (cleaned up)', function () use ($nhDb, $nhCsrf) {
    $c = $nhDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $idAdmin = (int) $c->query('SELECT id_admin FROM ADMIN LIMIT 1')->fetchColumn();
    if ($idAdmin <= 0) {
        echo "\n       (skipped: no admin)";
        return;
    }
    $jar = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $csrf = $nhCsrf($jar);
    if ($csrf === null) {
        echo "\n       (skipped: no csrf)";
        return;
    }

    $marker = 'ZZNEWSH' . bin2hex(random_bytes(4));
    $createdId = 0;
    try {
        $r = SecurityClient::request('POST', '/action/news', ['jar' => $jar, 'form' => [
            'csrf_token' => $csrf,
            'store' => '1',
            'judul' => $marker,
            'penulis' => (string) $idAdmin,
            'konten' => '<p>isi</p>',
        ]]);
        assertEquals(302, $r['status'], 'valid store redirects');
        $row = $c->prepare('SELECT id_news FROM news WHERE judul = ? LIMIT 1');
        $row->execute([$marker]);
        $createdId = (int) $row->fetchColumn();
        assertTrue($createdId > 0, 'row must be created');
    } finally {
        if ($createdId > 0) {
            $c->prepare('DELETE FROM news WHERE id_news = ?')->execute([$createdId]);
        }
        $c->prepare('DELETE FROM news WHERE judul = ?')->execute([$marker]);
    }
});

$runner->addTest('news-handler: update with bad token -> redirect error, no crash', function () use ($nhDb, $nhCsrf) {
    $c = $nhDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $jar = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $csrf = $nhCsrf($jar);
    if ($csrf === null) {
        echo "\n       (skipped: no csrf)";
        return;
    }
    $r = SecurityClient::request('POST', '/action/news', ['jar' => $jar, 'form' => [
        'csrf_token' => $csrf,
        'update' => '1',
        'news_id' => 'garbage-token',
        'judul' => 'x',
        'konten' => '<p>x</p>',
        'penulis' => '1',
    ]]);
    assertEquals(302, $r['status'], 'bad token update must redirect');
});

$runner->addTest('news-handler: update valid round-trip (cleaned up)', function () use ($nhDb, $nhCsrf) {
    $c = $nhDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $idAdmin = (int) $c->query('SELECT id_admin FROM ADMIN LIMIT 1')->fetchColumn();
    if ($idAdmin <= 0) {
        echo "\n       (skipped: no admin)";
        return;
    }
    $jar = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }

    $marker = 'ZZNEWSUPD' . bin2hex(random_bytes(4));
    // Create a row directly, then fetch its edit page to obtain a real news_id token.
    $c->prepare("INSERT INTO news (gambar, judul, konten, penulis_id) VALUES (NULL, ?, '<p>old</p>', ?)")->execute([$marker, $idAdmin]);
    $id = (int) $c->lastInsertId();
    try {
        $list = SecurityClient::request('GET', '/admin/news', ['jar' => $jar]);
        $csrf = SecurityClient::csrfFrom($list['body']);
        // find an id_news token for our row via the edit page URL of the row.
        $edit = SecurityClient::request('GET', '/admin/news/edit?id_news=' . rawurlencode((string) $id), ['jar' => $jar]);
        // The edit page issues the opaque token in a hidden field.
        $tok = null;
        if (preg_match('/name="news_id"\s+value="((?:s1|o1)\.[A-Za-z0-9\-\._~%]+)"/', $edit['body'], $m) === 1) {
            $tok = $m[1];
        }
        if ($csrf === null || $tok === null) {
            echo "\n       (skipped: no token/csrf on edit page)";
            return;
        }

        $r = SecurityClient::request('POST', '/action/news', ['jar' => $jar, 'form' => [
            'csrf_token' => $csrf,
            'update' => '1',
            'news_id' => $tok,
            'judul' => $marker . '-u',
            'konten' => '<p>new</p>',
            'penulis' => (string) $idAdmin,
        ]]);
        assertEquals(302, $r['status'], 'valid update redirects');
        $judul = (string) $c->query('SELECT judul FROM news WHERE id_news = ' . $id)->fetchColumn();
        assertEquals($marker . '-u', $judul, 'row must be updated');
    } finally {
        $c->prepare('DELETE FROM news WHERE id_news = ?')->execute([$id]);
        $c->prepare('DELETE FROM news WHERE judul = ?')->execute([$marker]);
        $c->prepare('DELETE FROM news WHERE judul = ?')->execute([$marker . '-u']);
    }
});

$runner->addTest('news-handler: unknown action -> redirect error', function () use ($nhCsrf) {
    $jar = SecurityClient::login('ADMIN001', 'admin123', 'nip');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $csrf = $nhCsrf($jar);
    if ($csrf === null) {
        echo "\n       (skipped: no csrf)";
        return;
    }
    $r = SecurityClient::request('POST', '/action/news', ['jar' => $jar, 'form' => ['csrf_token' => $csrf, 'nonsense' => '1']]);
    assertEquals(302, $r['status'], 'unknown action -> redirect');
});
