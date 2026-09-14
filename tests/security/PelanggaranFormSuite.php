<?php

/**
 * PelanggaranFormSuite — HTTP coverage for the POST branch of
 * request/handler-pelanggaran.php (store + update), which builds a report from
 * the /pelaporan form (teacher role).
 *
 * The form issues opaque tokens for `jenisPelanggaran` (tatib) and `sanksi`;
 * we scrape them from the rendered page, submit, and clean up any row created.
 */

require_once __DIR__ . '/SecurityClient.php';

if (!SecurityClient::available()) {
    $runner->addTest('pelanggaran form suite skipped', function () {
        echo "\n       (skipped: cannot start php -S: " . SecurityClient::lastError() . ')';
    });
    return;
}

require_once dirname(__DIR__, 2) . '/config.php';

$pfDb = static function (): ?PDO {
    $c = $GLOBALS['connect'] ?? null;
    return $c instanceof PDO ? $c : null;
};

/** Scrape the first tatib token + its tingkat from the /pelaporan page. */
$pfScrapeTatib = static function (string $body): array {
    // Option carries the token + data-tingkat attribute.
    if (preg_match('/value="((?:s1|o1)\.[A-Za-z0-9\-\._~%]+)"\s+data-tingkat="([IVX]+)"/', $body, $m) === 1) {
        return [$m[1], $m[2]];
    }
    return [null, null];
};

/** Scrape the first sanksi token. */
$pfScrapeSanksi = static function (string $body): ?string {
    if (preg_match('/name="sanksi"[^>]*>(.*?)<\/select>/s', $body, $m) === 1) {
        if (preg_match('/value="((?:s1|o1)\.[A-Za-z0-9\-\._~%]+)"/', $m[1], $mm) === 1) {
            return $mm[1];
        }
    }
    return null;
};

$runner->addTest('plg-form: anonymous POST /action/pelanggaran is blocked', function () {
    $r = SecurityClient::request('POST', '/action/pelanggaran', ['form' => ['store' => '1']]);
    assertTrue(in_array($r['status'], [401, 403, 419, 302], true), 'anon blocked, got ' . $r['status']);
});

$runner->addTest('plg-form: mahasiswa POST is forbidden', function () {
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
    $r = SecurityClient::request('POST', '/action/pelanggaran', ['jar' => $jar, 'form' => [
        'csrf_token' => $csrf,
        'store' => '1',
        'jenisPelanggaran' => 'x',
        'nim' => '2341238901',
    ]]);
    assertTrue(in_array($r['status'], [302, 403], true), 'mahasiswa must be blocked, got ' . $r['status']);
});

$runner->addTest('plg-form: dosen invalid tatib token -> flash error redirect, no row', function () use ($pfDb) {
    $c = $pfDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $jar = SecurityClient::login('1234567890', 'password123', 'nidn');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $page = SecurityClient::request('GET', '/pelaporan', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    if ($csrf === null) {
        echo "\n       (skipped: no csrf)";
        return;
    }

    $before = (int) $c->query('SELECT COUNT(*) FROM DETAIL_PELANGGARAN')->fetchColumn();
    $r = SecurityClient::request('POST', '/action/pelanggaran', ['jar' => $jar, 'form' => [
        'csrf_token' => $csrf,
        'store' => '1',
        'jenisPelanggaran' => 'garbage-token',
        'nim' => '2341238901',
    ]]);
    assertEquals(302, $r['status'], 'invalid tatib token must redirect');
    $after = (int) $c->query('SELECT COUNT(*) FROM DETAIL_PELANGGARAN')->fetchColumn();
    assertEquals($before, $after, 'no row must be created on invalid token');
});

$runner->addTest('plg-form: dosen valid store creates a report then it is cleaned up', function () use ($pfDb, $pfScrapeTatib, $pfScrapeSanksi) {
    $c = $pfDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $jar = SecurityClient::login('1234567890', 'password123', 'nidn');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $page = SecurityClient::request('GET', '/pelaporan', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    [$tatibTok, $tingkat] = $pfScrapeTatib($page['body']);
    $sanksiTok = $pfScrapeSanksi($page['body']);
    if ($csrf === null || $tatibTok === null) {
        echo "\n       (skipped: no csrf/tatib token on /pelaporan)";
        return;
    }

    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    if ($nim === '') {
        echo "\n       (skipped: no seed nim)";
        return;
    }

    $marker = 'ZZFORM' . bin2hex(random_bytes(4));
    $createdId = 0;
    try {
        $form = [
            'csrf_token' => $csrf,
            'store' => '1',
            'jenisPelanggaran' => $tatibTok,
            'nim' => $nim,
            'deskripsiPelanggaran' => $marker,
            'penanggungTugas' => 'dosen',
        ];
        if ($sanksiTok !== null) {
            $form['sanksi'] = $sanksiTok;
        }
        if (in_array($tingkat, ['I', 'II', 'III'], true)) {
            $form['deskripsiTugas'] = 'tugas uji';
        }

        $r = SecurityClient::request('POST', '/action/pelanggaran', ['jar' => $jar, 'form' => $form]);
        assertEquals(302, $r['status'], 'valid store redirects');

        $row = $c->prepare('SELECT id_detail FROM DETAIL_PELANGGARAN WHERE detail_pelanggaran = ? LIMIT 1');
        $row->execute([$marker]);
        $createdId = (int) $row->fetchColumn();
        assertTrue($createdId > 0, 'report row must be created by the handler');
    } finally {
        if ($createdId > 0) {
            $c->exec('DELETE FROM NOTIFIKASI WHERE id_detail_pelanggaran = ' . $createdId);
            $c->prepare('DELETE FROM DETAIL_PELANGGARAN WHERE id_detail = ?')->execute([$createdId]);
        }
        $c->prepare('DELETE FROM DETAIL_PELANGGARAN WHERE detail_pelanggaran = ?')->execute([$marker]);
    }
});

$runner->addTest('plg-form: update with bad id_detail token -> flash error, no mutation', function () use ($pfDb, $pfScrapeTatib) {
    $c = $pfDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $jar = SecurityClient::login('1234567890', 'password123', 'nidn');
    if ($jar === null) {
        echo "\n       (skipped: login)";
        return;
    }
    $page = SecurityClient::request('GET', '/pelaporan', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    [$tatibTok] = $pfScrapeTatib($page['body']);
    if ($csrf === null || $tatibTok === null) {
        echo "\n       (skipped: no csrf/tatib token)";
        return;
    }

    $before = (int) $c->query('SELECT COUNT(*) FROM DETAIL_PELANGGARAN')->fetchColumn();
    $r = SecurityClient::request('POST', '/action/pelanggaran', ['jar' => $jar, 'form' => [
        'csrf_token' => $csrf,
        'update' => '1',
        'id_detail' => 'garbage-token',
        'jenisPelanggaran' => $tatibTok,
        'nim' => '2341238901',
    ]]);
    assertEquals(302, $r['status'], 'bad update token must redirect');
    $after = (int) $c->query('SELECT COUNT(*) FROM DETAIL_PELANGGARAN')->fetchColumn();
    assertEquals($before, $after, 'no mutation on bad update token');
});
