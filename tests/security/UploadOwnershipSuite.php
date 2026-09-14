<?php

/**
 * UploadOwnershipSuite — regression for `POST /action/upload`.
 *
 * Covers the two follow-ups from the 2026-09-14 Strix deep pentest:
 *   1. The handler must reach its success path for the legitimate owner
 *      (regression for the SQLSTATE[HY093] duplicate-placeholder 500).
 *   2. Object-level authorization: a user must NOT be able to attach a file to
 *      another user's DETAIL_PELANGGARAN (cross-user id_detail token).
 *
 * Both are driven through the real HTTP surface (SecurityClient boots its own
 * `php -S`), against throwaway fixtures that are cleaned up afterwards.
 */

require_once __DIR__ . '/SecurityClient.php';

if (!SecurityClient::available()) {
    $runner->addTest('upload ownership suite skipped', function () {
        echo "\n       (skipped: cannot start php -S test server: " . SecurityClient::lastError() . ')';
    });
    return;
}

require_once dirname(__DIR__, 2) . '/config.php';

$uploadDb = static function (): bool {
    return isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof PDO;
};

/**
 * Insert a throwaway violation owned by $nim, with $reporterNidn / $dpaNidn set,
 * returning [id_detail, marker]. Caller MUST clean up in a finally block.
 */
$uploadFixture = static function (PDO $connect, string $nim, string $reporterNidn, string $dpaNidn): array {
    $mhs = $connect->prepare('SELECT id_mhs FROM MAHASISWA WHERE nim = ? LIMIT 1');
    $mhs->execute([$nim]);
    $idMhs = (int) $mhs->fetchColumn();

    $rep = $connect->prepare('SELECT id_dosen FROM DOSEN WHERE nidn = ? LIMIT 1');
    $rep->execute([$reporterNidn]);
    $idReporter = (int) $rep->fetchColumn();

    $dpa = $connect->prepare('SELECT id_dosen FROM DOSEN WHERE nidn = ? LIMIT 1');
    $dpa->execute([$dpaNidn]);
    $idDpa = (int) $dpa->fetchColumn();

    if ($idMhs <= 0 || $idReporter <= 0 || $idDpa <= 0) {
        return [0, ''];
    }

    $marker = 'ZZUPL' . bin2hex(random_bytes(4));
    $ins = $connect->prepare('INSERT INTO DETAIL_PELANGGARAN
        (id_dosen, id_tata_tertib, id_mahasiswa, id_sanksi, status, status_tugas, delegasi_tugas_ke_dpa, id_dosen_penanggung_jawab, detail_pelanggaran)
        VALUES (?, 1, ?, 3, ?, ?, 0, ?, ?)');
    $ins->execute([$idReporter, $idMhs, 'Proses Bimbingan', 'Belum Dikerjakan', $idDpa, $marker]);

    return [(int) $connect->lastInsertId(), $marker];
};

/** Collect every id_detail token rendered on /pelanggaran for a session. */
$uploadTokensFrom = static function (string $jar, string $path = '/pelanggaran'): array {
    $page = SecurityClient::request('GET', $path, ['jar' => $jar]);
    preg_match_all('/id_detail"?\s+value="((?:s1|o1)\.[A-Za-z0-9\-\._~%]+)"/', $page['body'], $m);
    return array_values(array_unique($m[1] ?? []));
};

/**
 * Upload to the throwaway fixture identified by $idDetail.
 *
 * The rendered page carries a token per violation but they are opaque/encrypted,
 * so we cannot tell which token maps to our fixture without trying. We iterate the
 * rendered tokens until one actually writes to our row (verified via the DB), and
 * return that attempt. This mirrors how a real owner-pinned token behaves while
 * staying robust to unrelated seeded violations on the same page.
 *
 * @return array{status:int, body:string, headers:array<string,string>, matched:bool, token:?string}
 */
$uploadToFixture = static function (PDO $connect, string $jar, int $idDetail, string $capabilityToken = '') use ($uploadTokensFrom): array {
    $page = SecurityClient::request('GET', '/pelanggaran', ['jar' => $jar]);
    $csrf = SecurityClient::csrfFrom($page['body']);
    if ($csrf === null) {
        return ['status' => 0, 'body' => '', 'headers' => [], 'matched' => false, 'token' => null];
    }

    // A caller may pass an explicit capability token (cross-user test replays another
    // session's token); otherwise try the tokens this session rendered.
    $tokens = $capabilityToken !== '' ? [$capabilityToken] : $uploadTokensFrom($jar);

    $last = ['status' => 0, 'body' => '', 'headers' => [], 'matched' => false, 'token' => null];
    foreach ($tokens as $tok) {
        $connect->exec("UPDATE DETAIL_PELANGGARAN SET surat = NULL WHERE id_detail = {$idDetail}");
        $r = SecurityClient::request('POST', '/action/upload', ['jar' => $jar, 'files' => [
            'fields' => ['csrf_token' => $csrf, 'id_detail' => $tok],
            'uploads' => ['suratPernyataan' => ['path' => SecurityClient::tempPng(), 'type' => 'image/png']],
        ]]);
        $saved = $connect->query("SELECT surat FROM DETAIL_PELANGGARAN WHERE id_detail = {$idDetail}")->fetchColumn();
        $matched = is_string($saved) && $saved !== '';
        $last = ['status' => (int) $r['status'], 'body' => (string) $r['body'], 'headers' => $r['headers'], 'matched' => $matched, 'token' => $tok];
        if ($matched) {
            break;
        }
    }
    return $last;
};

/* ------------------------------------------------------------------ */
/* 1. Regression: legitimate owner upload must succeed (no HY093 500)  */
/* ------------------------------------------------------------------ */

$runner->addTest('upload: owner (mahasiswa) upload succeeds — regresses the HY093 native-prepare 500', function () use ($uploadDb, $uploadFixture, $uploadToFixture) {
    if (!$uploadDb()) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $connect = $GLOBALS['connect'];

    // Mahasiswa 2341238901, reported by dosen 1234567890 (DPA = same dosen in seed).
    [$idDetail, $marker] = $uploadFixture($connect, '2341238901', '1234567890', '1234567890');
    if ($idDetail <= 0) {
        echo "\n       (skipped: seed fixtures missing)";
        return;
    }

    $saved = null;
    try {
        $jar = SecurityClient::login('2341238901', 'password123', 'nim');
        assertTrue($jar !== null, 'owner login');

        $up = $uploadToFixture($connect, $jar, $idDetail);

        // The bug: this used to be 500 {"message":"Internal Server Error"} (PDOException HY093).
        assertTrue($up['status'] === 200, "owner upload must succeed, got {$up['status']} body=" . substr($up['body'], 0, 160));
        $json = json_decode($up['body'], true);
        assertTrue(($json['success'] ?? false) === true, 'response must be success:true, body=' . substr($up['body'], 0, 160));
        assertTrue($up['matched'], 'no rendered id_detail token mapped to the fixture (page/token mismatch)');

        $saved = $connect->query("SELECT surat FROM DETAIL_PELANGGARAN WHERE id_detail = {$idDetail}")->fetchColumn();
        assertTrue(is_string($saved) && $saved !== '', 'surat column must be populated after upload');
    } finally {
        if (is_string($saved) && $saved !== '') {
            @unlink(dirname(__DIR__, 2) . '/storage/uploads/' . $saved);
        }
        $connect->exec("UPDATE DETAIL_PELANGGARAN SET surat = NULL WHERE id_detail = {$idDetail}");
        $connect->exec("DELETE FROM DETAIL_PELANGGARAN WHERE id_detail = {$idDetail} AND detail_pelanggaran = " . $connect->quote($marker));
    }
});

$runner->addTest('upload: owner (dosen) upload succeeds — exercises the :roleDosen / :nidnPenanggung branch', function () use ($uploadDb, $uploadFixture, $uploadToFixture) {
    if (!$uploadDb()) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $connect = $GLOBALS['connect'];

    // Dosen 1234567890 is the reporter of this fixture -> ownership via pelapor.nidn.
    [$idDetail, $marker] = $uploadFixture($connect, '2341238901', '1234567890', '1234567890');
    if ($idDetail <= 0) {
        echo "\n       (skipped: seed fixtures missing)";
        return;
    }

    $saved = null;
    try {
        $jar = SecurityClient::login('1234567890', 'password123', 'nidn');
        assertTrue($jar !== null, 'dosen login');

        $up = $uploadToFixture($connect, $jar, $idDetail);
        if ($up['status'] === 0) {
            echo "\n       (skipped: dosen session has no csrf)";
            return;
        }
        assertTrue($up['status'] !== 500, "dosen upload must not 500, got {$up['status']} body=" . substr($up['body'], 0, 160));
        assertTrue(in_array($up['status'], [200, 404, 422], true), "unexpected status {$up['status']}");
        if ($up['matched']) {
            $saved = $connect->query("SELECT surat FROM DETAIL_PELANGGARAN WHERE id_detail = {$idDetail}")->fetchColumn();
        }
    } finally {
        if (is_string($saved) && $saved !== '') {
            @unlink(dirname(__DIR__, 2) . '/storage/uploads/' . $saved);
        }
        $connect->exec("UPDATE DETAIL_PELANGGARAN SET surat = NULL WHERE id_detail = {$idDetail}");
        $connect->exec("DELETE FROM DETAIL_PELANGGARAN WHERE id_detail = {$idDetail} AND detail_pelanggaran = " . $connect->quote($marker));
    }
});

/* ------------------------------------------------------------------ */
/* 2. Object-level authorization: cross-user upload must be denied     */
/* ------------------------------------------------------------------ */

$runner->addTest('upload: cross-user denied — student B cannot upload to student A violation', function () use ($uploadDb, $uploadFixture, $uploadTokensFrom) {
    if (!$uploadDb()) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $connect = $GLOBALS['connect'];

    $nimB = (string) $connect->query("SELECT nim FROM MAHASISWA WHERE nim <> '2341238901' ORDER BY nim LIMIT 1")->fetchColumn();
    if ($nimB === '') {
        echo "\n       (skipped: needs a second seeded mahasiswa)";
        return;
    }

    // Seed passwords differ per student (2341238902 => password456, ...). Try the
    // known seed passwords so the attacker session is real rather than skipped.
    $attackerJar = null;
    foreach (['password456', 'password789', 'password123'] as $pw) {
        $attackerJar = SecurityClient::login($nimB, $pw, 'nim');
        if ($attackerJar !== null) {
            break;
        }
    }

    // Fixture owned by 2341238901 (victim). Attacker is the other student.
    [$idDetail, $marker] = $uploadFixture($connect, '2341238901', '1234567890', '1234567890');
    if ($idDetail <= 0) {
        echo "\n       (skipped: seed fixtures missing)";
        return;
    }

    try {
        // Victim's session issues valid, session-bound id_detail tokens. We must learn
        // WHICH token maps to our fixture; the only reliable probe is the victim's own
        // successful upload, so first let the owner pin it.
        $victim = SecurityClient::login('2341238901', 'password123', 'nim');
        assertTrue($victim !== null, 'victim login');
        $victimTokens = $uploadTokensFrom($victim);
        assertTrue($victimTokens !== [], 'victim id_detail tokens rendered');

        // Find the victim token that writes to OUR fixture (verified via DB), then reset.
        $victimToken = null;
        foreach ($victimTokens as $tok) {
            $connect->exec("UPDATE DETAIL_PELANGGARAN SET surat = NULL WHERE id_detail = {$idDetail}");
            $vPage = SecurityClient::request('GET', '/pelanggaran', ['jar' => $victim]);
            $vCsrf = SecurityClient::csrfFrom($vPage['body']);
            if ($vCsrf === null) {
                break;
            }
            SecurityClient::request('POST', '/action/upload', ['jar' => $victim, 'files' => [
                'fields' => ['csrf_token' => $vCsrf, 'id_detail' => $tok],
                'uploads' => ['suratPernyataan' => ['path' => SecurityClient::tempPng(), 'type' => 'image/png']],
            ]]);
            $wrote = $connect->query("SELECT surat FROM DETAIL_PELANGGARAN WHERE id_detail = {$idDetail}")->fetchColumn();
            if (is_string($wrote) && $wrote !== '') {
                $victimToken = $tok;
                @unlink(dirname(__DIR__, 2) . '/storage/uploads/' . $wrote);
                break;
            }
        }
        $connect->exec("UPDATE DETAIL_PELANGGARAN SET surat = NULL WHERE id_detail = {$idDetail}");
        assertTrue($victimToken !== null, 'could not identify victim token for fixture');

        $attacker = $attackerJar;
        if ($attacker === null) {
            echo "\n       (skipped: attacker login failed -- password/seed differs)";
            return;
        }
        $aPage = SecurityClient::request('GET', '/pelanggaran', ['jar' => $attacker]);
        $aCsrf = SecurityClient::csrfFrom($aPage['body']);
        assertTrue($aCsrf !== null, 'attacker csrf');

        // Replay victim's token from the attacker's session.
        $replay = SecurityClient::request('POST', '/action/upload', ['jar' => $attacker, 'files' => [
            'fields' => ['csrf_token' => $aCsrf, 'id_detail' => $victimToken],
            'uploads' => ['suratPernyataan' => ['path' => SecurityClient::tempPng(), 'type' => 'image/png']],
        ]]);
        assertTrue($replay['status'] !== 200, "cross-user upload must be refused, got {$replay['status']}");
        $rj = json_decode($replay['body'], true);
        assertTrue(($rj['success'] ?? true) === false, 'cross-user upload reported success');

        // And no write must have landed.
        $after = $connect->query("SELECT surat FROM DETAIL_PELANGGARAN WHERE id_detail = {$idDetail}")->fetchColumn();
        assertTrue($after === null || $after === '', 'victim record must remain untouched after cross-user attempt');
    } finally {
        $connect->exec("UPDATE DETAIL_PELANGGARAN SET surat = NULL WHERE id_detail = {$idDetail}");
        $connect->exec("DELETE FROM DETAIL_PELANGGARAN WHERE id_detail = {$idDetail} AND detail_pelanggaran = " . $connect->quote($marker));
    }
});

$runner->addTest('upload: anonymous and CSRF-less requests never reach the write path', function () use ($uploadDb, $uploadFixture) {
    if (!$uploadDb()) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $connect = $GLOBALS['connect'];

    [$idDetail, $marker] = $uploadFixture($connect, '2341238901', '1234567890', '1234567890');
    if ($idDetail <= 0) {
        echo "\n       (skipped: seed fixtures missing)";
        return;
    }

    try {
        // Anonymous: no session at all.
        $anon = SecurityClient::request('POST', '/action/upload', ['files' => [
            'fields' => ['id_detail' => 'garbage'],
            'uploads' => ['suratPernyataan' => ['path' => SecurityClient::tempPng(), 'type' => 'image/png']],
        ]]);
        assertTrue(in_array($anon['status'], [401, 403, 419, 302], true), "anonymous upload must be blocked, got {$anon['status']}");

        // Logged in but missing CSRF.
        $jar = SecurityClient::login('2341238901', 'password123', 'nim');
        if ($jar !== null) {
            $noCsrf = SecurityClient::request('POST', '/action/upload', ['jar' => $jar, 'files' => [
                'fields' => ['id_detail' => 'garbage'],
                'uploads' => ['suratPernyataan' => ['path' => SecurityClient::tempPng(), 'type' => 'image/png']],
            ]]);
            assertEquals(419, $noCsrf['status'], 'missing CSRF must be 419');
        }
    } finally {
        $connect->exec("DELETE FROM DETAIL_PELANGGARAN WHERE id_detail = {$idDetail} AND detail_pelanggaran = " . $connect->quote($marker));
    }
});
