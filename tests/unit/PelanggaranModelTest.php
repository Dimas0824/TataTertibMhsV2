<?php

/**
 * PelanggaranModelTest — raises coverage of `models/Pelanggaran.php`.
 *
 * Mix of:
 *  - guard/invalid-input branches (no DB fixture needed)
 *  - real DB paths against the application database (via config.php), using
 *    throwaway fixtures that are always removed in `finally`.
 *
 * Kept deterministic: real ids are read from the DB, never hardcoded, and every
 * test skips gracefully when the required seed rows are missing.
 */

require_once dirname(__DIR__, 2) . '/config.php';

$plgDb = static function (): ?PDO {
    $c = $GLOBALS['connect'] ?? null;
    return $c instanceof PDO ? $c : null;
};

/** First id_mhs for a nim (0 when not found). */
$plgIdMhs = static function (PDO $c, string $nim): int {
    $s = $c->prepare('SELECT id_mhs FROM MAHASISWA WHERE nim = ? LIMIT 1');
    $s->execute([$nim]);
    return (int) $s->fetchColumn();
};

/** First id_dosen for a nidn (0 when not found). */
$plgIdDosen = static function (PDO $c, string $nidn): int {
    $s = $c->prepare('SELECT id_dosen FROM DOSEN WHERE nidn = ? LIMIT 1');
    $s->execute([$nidn]);
    return (int) $s->fetchColumn();
};

/** A valid (id_tata_tertib, tingkat) row, preferring a non-tugas (tingkat IV/V) one. */
$plgTatib = static function (PDO $c, bool $requiresTugas = false): ?array {
    $sql = $requiresTugas
        ? "SELECT id_tata_tertib, tingkat FROM TATA_TERTIB WHERE tingkat IN ('I','II','III') LIMIT 1"
        : "SELECT id_tata_tertib, tingkat FROM TATA_TERTIB LIMIT 1";
    $row = $c->query($sql)->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
};

/** A sanksi id, optionally matching a tingkat. */
$plgSanksi = static function (PDO $c, ?string $tingkat = null): int {
    if ($tingkat !== null) {
        $s = $c->prepare('SELECT id_sanksi FROM SANKSI WHERE tingkat = ? LIMIT 1');
        $s->execute([$tingkat]);
        $found = (int) $s->fetchColumn();
        if ($found > 0) {
            return $found;
        }
    }
    return (int) $c->query('SELECT id_sanksi FROM SANKSI LIMIT 1')->fetchColumn();
};

$plgNewModel = static function (): Pelanggaran {
    return new Pelanggaran();
};

/* ------------------------------------------------------------------ */
/* 1. Guard / invalid-input branches (no DB needed)                    */
/* ------------------------------------------------------------------ */

$runner->addTest('plg: getMahasiswaByNim returns null for unknown nim', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    assertNull($m->getMahasiswaByNim('nope-zz-' . bin2hex(random_bytes(3))));
});

$runner->addTest('plg: getDefaultSanksiByTingkat null for unknown tingkat', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    assertNull($m->getDefaultSanksiByTingkat('ZZ-' . bin2hex(random_bytes(3))));
});

$runner->addTest('plg: searchMahasiswaByKeyword returns [] for blank keyword', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    assertEquals([], $m->searchMahasiswaByKeyword(''));
    assertEquals([], $m->searchMahasiswaByKeyword('   '));
});

$runner->addTest('plg: searchMahasiswaByKeyword neutralizes LIKE wildcards', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    // '%' and '_' must NOT match everything (escaped in the query).
    assertEquals([], $m->searchMahasiswaByKeyword('%'), "'%' must be neutralized");
    assertEquals([], $m->searchMahasiswaByKeyword('_'), "'_' must be neutralized");
});

$runner->addTest('plg: searchMahasiswaByKeyword finds real prefix and clamps limit', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    if ($nim === '') {
        echo "\n       (skipped: no seed mahasiswa)";
        return;
    }
    $hits = $m->searchMahasiswaByKeyword(substr($nim, 0, 4), 25);
    assertTrue(count($hits) >= 1, 'seeded prefix must return at least one row');
    // limit clamp: 0 -> min 1, 999 -> max 25 (cannot exceed row count anyway)
    $small = $m->searchMahasiswaByKeyword(substr($nim, 0, 4), 0);
    assertTrue(count($small) <= 1, 'limit 0 clamps to 1');
});

$runner->addTest('plg: konfirmasiLaporanSelesaiByDosen rejects invalid input', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    $r1 = $m->konfirmasiLaporanSelesaiByDosen('', 1);
    assertFalse($r1['success'], 'empty nidn must be rejected');
    $r2 = $m->konfirmasiLaporanSelesaiByDosen('1234567890', 0);
    assertFalse($r2['success'], 'id <= 0 must be rejected');
});

$runner->addTest('plg: hapusDetailPelanggaranByDosen rejects invalid input', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    assertFalse($m->hapusDetailPelanggaranByDosen('', 1)['success']);
    assertFalse($m->hapusDetailPelanggaranByDosen('1234567890', 0)['success']);
});

$runner->addTest('plg: simpanDetailPelanggaran fails for unknown dosen/mhs/tatib', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    // unknown dosen
    $r = $m->simpanDetailPelanggaran('0000000000', 1, '2341238901', 1, 'x', '', '', 'pending', 'Belum Dikerjakan', false);
    assertFalse($r['success'], 'unknown dosen must fail');
    // unknown nim (valid nidn)
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN LIMIT 1')->fetchColumn();
    if ($nidn !== '') {
        $r2 = $m->simpanDetailPelanggaran($nidn, 1, 'zz-not-a-nim', 1, 'x', '', '', 'pending', 'Belum Dikerjakan', false);
        assertFalse($r2['success'], 'unknown nim must fail');
    }
});

$runner->addTest('plg: updateDetailPelanggaran fails for unknown id', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    $r = $m->updateDetailPelanggaran(99999999, 1, '2341238901', 1, 'x', '', 'pending', 'Belum Dikerjakan', false);
    assertFalse($r['success'], 'unknown id must fail');
});

$runner->addTest('plg: mark read helpers return false for garbage ids', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    assertFalse($m->markNotifikasiAsReadForMahasiswa('2341238901', 99999999));
    assertFalse($m->markNotifikasiAsReadForDosen('1234567890', 99999999));
});

/* ------------------------------------------------------------------ */
/* 2. Real DB paths with fixtures                                      */
/* ------------------------------------------------------------------ */

$runner->addTest('plg: simpan + update + konfirmasi + hapus round-trip (cleanup guaranteed)', function () use ($plgDb, $plgIdMhs, $plgIdDosen, $plgTatib, $plgSanksi, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();

    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1')->fetchColumn();
    $tatib = $plgTatib($c, false);
    if ($nim === '' || $nidn === '' || $tatib === null) {
        echo "\n       (skipped: seed missing)";
        return;
    }

    $idTatib = (int) $tatib['id_tata_tertib'];
    $idSanksi = $plgSanksi($c, (string) $tatib['tingkat']);
    $marker = 'ZZPLG' . bin2hex(random_bytes(4));

    $createdId = 0;
    try {
        $r = $m->simpanDetailPelanggaran($nidn, $idTatib, $nim, $idSanksi, $marker, '', '', 'pending', 'Belum Dikerjakan', false);
        assertTrue($r['success'], 'simpan should succeed: ' . ($r['message'] ?? ''));
        $createdId = (int) $r['id_detail'];
        assertTrue($createdId > 0, 'id_detail must be returned');

        // verify row exists with our marker
        $chk = $c->prepare('SELECT detail_pelanggaran FROM DETAIL_PELANGGARAN WHERE id_detail = ?');
        $chk->execute([$createdId]);
        assertEquals($marker, (string) $chk->fetchColumn());

        // dosen view should include it
        $rows = $m->getDetailLaporanDosen($nidn);
        assertTrue(is_array($rows), 'getDetailLaporanDosen returns array');

        // getUpdatePelanggar resolves it
        $upd = $m->getUpdatePelanggar($createdId, $nidn);
        assertTrue(is_array($upd) && !empty($upd), 'getUpdatePelanggar returns the row');

        // update it (still not 'selesai')
        $r2 = $m->updateDetailPelanggaran($createdId, $idTatib, $nim, $idSanksi, $marker . '-upd', 'note', 'pending', 'Belum Dikerjakan', false);
        assertTrue($r2['success'], 'update should succeed: ' . ($r2['message'] ?? ''));

        // once status is 'selesai', editing must be refused
        $c->prepare("UPDATE DETAIL_PELANGGARAN SET status='selesai' WHERE id_detail=?")->execute([$createdId]);
        $r3 = $m->updateDetailPelanggaran($createdId, $idTatib, $nim, $idSanksi, 'x', '', 'pending', 'Belum Dikerjakan', false);
        assertFalse($r3['success'], 'edit of a finished report must be refused');
    } finally {
        if ($createdId > 0) {
            $c->exec('DELETE FROM NOTIFIKASI WHERE id_detail_pelanggaran = ' . (int) $createdId);
            $c->prepare('DELETE FROM DETAIL_PELANGGARAN WHERE id_detail = ?')->execute([$createdId]);
        }
    }
});

$runner->addTest('plg: simpan with DPA delegation sets responsible dosen (cleanup guaranteed)', function () use ($plgDb, $plgTatib, $plgSanksi, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();

    // need a mahasiswa that has an id_dpa, and a tingkat I/II/III tatib
    $mhs = $c->query('SELECT nim, id_dpa FROM MAHASISWA WHERE id_dpa IS NOT NULL AND id_dpa > 0 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1')->fetchColumn();
    $tatib = $plgTatib($c, true);
    if (!$mhs || $nidn === '' || $tatib === null) {
        echo "\n       (skipped: seed (dpa/tingkat I-III) missing)";
        return;
    }

    $idTatib = (int) $tatib['id_tata_tertib'];
    $idSanksi = $plgSanksi($c, (string) $tatib['tingkat']);
    $marker = 'ZZPLGDPA' . bin2hex(random_bytes(4));

    $createdId = 0;
    try {
        $r = $m->simpanDetailPelanggaran($nidn, $idTatib, (string) $mhs['nim'], $idSanksi, $marker, 'tugas', '', 'pending', 'Belum Dikerjakan', true);
        assertTrue($r['success'], 'simpan with delegation should succeed: ' . ($r['message'] ?? ''));
        $createdId = (int) $r['id_detail'];
        $row = $c->query('SELECT delegasi_tugas_ke_dpa, id_dosen_penanggung_jawab FROM DETAIL_PELANGGARAN WHERE id_detail = ' . $createdId)->fetch(PDO::FETCH_ASSOC);
        assertEquals(1, (int) $row['delegasi_tugas_ke_dpa'], 'delegation flag must be set');
        assertEquals((int) $mhs['id_dpa'], (int) $row['id_dosen_penanggung_jawab'], 'responsible dosen = DPA');
    } finally {
        if ($createdId > 0) {
            $c->exec('DELETE FROM NOTIFIKASI WHERE id_detail_pelanggaran = ' . (int) $createdId);
            $c->prepare('DELETE FROM DETAIL_PELANGGARAN WHERE id_detail = ?')->execute([$createdId]);
        }
    }
});

$runner->addTest('plg: konfirmasi selesai requires uploaded surat (cleanup guaranteed)', function () use ($plgDb, $plgIdMhs, $plgIdDosen, $plgTatib, $plgSanksi, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();

    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1')->fetchColumn();
    $tatib = $plgTatib($c, false);
    if ($nim === '' || $nidn === '' || $tatib === null) {
        echo "\n       (skipped: seed missing)";
        return;
    }

    $idMhs = $plgIdMhs($c, $nim);
    $idDosen = $plgIdDosen($c, $nidn);
    $idTatib = (int) $tatib['id_tata_tertib'];
    $idSanksi = $plgSanksi($c, (string) $tatib['tingkat']);
    $marker = 'ZZPLGCONF' . bin2hex(random_bytes(4));

    $createdId = 0;
    try {
        // fixture: this dosen is the penanggung_jawab, surat empty -> confirm must fail
        $ins = $c->prepare('INSERT INTO DETAIL_PELANGGARAN
            (id_dosen, id_tata_tertib, id_mahasiswa, id_sanksi, status, status_tugas, delegasi_tugas_ke_dpa, id_dosen_penanggung_jawab, detail_pelanggaran)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)');
        $ins->execute([$idDosen, $idTatib, $idMhs, $idSanksi, 'proses', 'Belum Dikerjakan', $idDosen, $marker]);
        $createdId = (int) $c->lastInsertId();

        $r = $m->konfirmasiLaporanSelesaiByDosen($nidn, $createdId);
        assertFalse($r['success'], 'confirm without surat must fail');
        assertStringContains('surat', strtolower($r['message'] ?? ''), 'message should mention surat');

        // now set a surat -> confirm succeeds
        $c->prepare("UPDATE DETAIL_PELANGGARAN SET surat='x.pdf' WHERE id_detail=?")->execute([$createdId]);
        $r2 = $m->konfirmasiLaporanSelesaiByDosen($nidn, $createdId);
        assertTrue($r2['success'], 'confirm with surat should succeed: ' . ($r2['message'] ?? ''));
        $status = (string) $c->query('SELECT status FROM DETAIL_PELANGGARAN WHERE id_detail = ' . $createdId)->fetchColumn();
        assertEquals('selesai', strtolower(trim($status)), 'status must become selesai');

        // confirming again is idempotent success
        $r3 = $m->konfirmasiLaporanSelesaiByDosen($nidn, $createdId);
        assertTrue($r3['success'], 're-confirm should be a no-op success');
    } finally {
        if ($createdId > 0) {
            $c->exec('DELETE FROM NOTIFIKASI WHERE id_detail_pelanggaran = ' . (int) $createdId);
            $c->prepare('DELETE FROM DETAIL_PELANGGARAN WHERE id_detail = ?')->execute([$createdId]);
        }
    }
});

$runner->addTest('plg: hapus detail removes row and its notifications (cleanup guaranteed)', function () use ($plgDb, $plgIdMhs, $plgIdDosen, $plgTatib, $plgSanksi, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();

    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1')->fetchColumn();
    $tatib = $plgTatib($c, false);
    if ($nim === '' || $nidn === '' || $tatib === null) {
        echo "\n       (skipped: seed missing)";
        return;
    }

    $idMhs = $plgIdMhs($c, $nim);
    $idDosen = $plgIdDosen($c, $nidn);
    $idTatib = (int) $tatib['id_tata_tertib'];
    $idSanksi = $plgSanksi($c, (string) $tatib['tingkat']);
    $marker = 'ZZPLGDEL' . bin2hex(random_bytes(4));

    $createdId = 0;
    try {
        $ins = $c->prepare('INSERT INTO DETAIL_PELANGGARAN
            (id_dosen, id_tata_tertib, id_mahasiswa, id_sanksi, status, status_tugas, delegasi_tugas_ke_dpa, id_dosen_penanggung_jawab, detail_pelanggaran)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)');
        $ins->execute([$idDosen, $idTatib, $idMhs, $idSanksi, 'proses', 'Belum Dikerjakan', $idDosen, $marker]);
        $createdId = (int) $c->lastInsertId();

        $r = $m->hapusDetailPelanggaranByDosen($nidn, $createdId);
        assertTrue($r['success'], 'delete by owner dosen should succeed: ' . ($r['message'] ?? ''));
        $gone = (int) $c->query('SELECT COUNT(*) FROM DETAIL_PELANGGARAN WHERE id_detail = ' . $createdId)->fetchColumn();
        assertEquals(0, $gone, 'row must be gone');
        $createdId = 0; // already deleted

        // foreign dosen cannot delete
        $otherNidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen DESC LIMIT 1')->fetchColumn();
        if ($otherNidn !== '' && $otherNidn !== $nidn) {
            $ins->execute([$idDosen, $idTatib, $idMhs, $idSanksi, 'proses', 'Belum Dikerjakan', $idDosen, $marker . '-2']);
            $createdId = (int) $c->lastInsertId();
            $r2 = $m->hapusDetailPelanggaranByDosen($otherNidn, $createdId);
            assertFalse($r2['success'], 'foreign dosen must not delete');
        }
    } finally {
        if ($createdId > 0) {
            $c->exec('DELETE FROM NOTIFIKASI WHERE id_detail_pelanggaran = ' . (int) $createdId);
            $c->prepare('DELETE FROM DETAIL_PELANGGARAN WHERE id_detail = ?')->execute([$createdId]);
        }
    }
});

$runner->addTest('plg: notification read helpers mark own notification (cleanup guaranteed)', function () use ($plgDb, $plgIdMhs, $plgIdDosen, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();

    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1')->fetchColumn();
    if ($nim === '' || $nidn === '') {
        echo "\n       (skipped: seed missing)";
        return;
    }
    $idMhs = $plgIdMhs($c, $nim);
    $idDosen = $plgIdDosen($c, $nidn);

    $createdIds = [];
    try {
        // mahasiswa notification
        $ins = $c->prepare("INSERT INTO NOTIFIKASI (id_dosen, id_mhs, id_detail_pelanggaran, pesan, status, role_penerima) VALUES (?, ?, NULL, '', 'unread', 'mahasiswa')");
        $ins->execute([$idDosen, $idMhs]);
        $idNotifMhs = (int) $c->lastInsertId();
        $createdIds[] = $idNotifMhs;
        assertTrue($m->markNotifikasiAsReadForMahasiswa($nim, $idNotifMhs), 'own mahasiswa notif must be markable');
        // second time -> already read -> false
        assertFalse($m->markNotifikasiAsReadForMahasiswa($nim, $idNotifMhs), 'already-read returns false');
        // foreign nim cannot mark it (new unread notif)
        $ins->execute([$idDosen, $idMhs]);
        $idNotifMhs2 = (int) $c->lastInsertId();
        $createdIds[] = $idNotifMhs2;
        assertFalse($m->markNotifikasiAsReadForMahasiswa('zz-not-a-nim', $idNotifMhs2), 'foreign nim must not mark');

        // dosen notification
        $insD = $c->prepare("INSERT INTO NOTIFIKASI (id_dosen, id_mhs, id_detail_pelanggaran, pesan, status, role_penerima) VALUES (?, NULL, NULL, '', 'unread', 'dosen')");
        $insD->execute([$idDosen]);
        $idNotifDosen = (int) $c->lastInsertId();
        $createdIds[] = $idNotifDosen;
        assertTrue($m->markNotifikasiAsReadForDosen($nidn, $idNotifDosen), 'own dosen notif must be markable');

        // mark all for a role returns int >= 0
        $n = $m->markAllNotifikasiAsReadForMahasiswa($nim);
        assertTrue(is_int($n) && $n >= 0, 'markAll (mahasiswa) returns int');
        $n2 = $m->markAllNotifikasiAsReadForDosen($nidn);
        assertTrue(is_int($n2) && $n2 >= 0, 'markAll (dosen) returns int');

        // list helpers still work
        assertTrue(is_array($m->getNotifikasiMahasiswa($nim)));
        assertTrue(is_array($m->getNotifikasiDosen($nidn)));
    } finally {
        foreach ($createdIds as $id) {
            $c->prepare('DELETE FROM NOTIFIKASI WHERE id_notifikasi = ?')->execute([$id]);
        }
    }
});

$runner->addTest('plg: getDetailPelanggaranMahasiswa returns array for known/unknown nim', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    if ($nim !== '') {
        assertTrue(is_array($m->getDetailPelanggaranMahasiswa($nim)));
    }
    assertTrue(is_array($m->getDetailPelanggaranMahasiswa('zz-no-such-nim')));
});

$runner->addTest('plg: getDetailLaporanDosen returns [] for unknown dosen', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    assertEquals([], $m->getDetailLaporanDosen('zz-no-such-nidn'));
});

/* ------------------------------------------------------------------ */
/* 3. Error branches of the mutating methods (raise model coverage)    */
/* ------------------------------------------------------------------ */

$runner->addTest('plg: getDetailLaporanDosen returns rows for a real dosen', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1')->fetchColumn();
    if ($nidn === '') {
        echo "\n       (skipped: no seed dosen)";
        return;
    }
    assertTrue(is_array($m->getDetailLaporanDosen($nidn)));
});

$runner->addTest('plg: simpan fails when tatib id is invalid (RuntimeException branch)', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1')->fetchColumn();
    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    if ($nidn === '' || $nim === '') {
        echo "\n       (skipped: seed missing)";
        return;
    }
    $r = $m->simpanDetailPelanggaran($nidn, 99999999, $nim, 1, 'x', '', '', 'pending', 'Belum Dikerjakan', false);
    assertFalse($r['success'], 'invalid tatib must fail');
});

$runner->addTest('plg: simpan fails when sanksi id is invalid', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1')->fetchColumn();
    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    $tatib = (int) $c->query('SELECT id_tata_tertib FROM TATA_TERTIB LIMIT 1')->fetchColumn();
    if ($nidn === '' || $nim === '' || $tatib <= 0) {
        echo "\n       (skipped: seed missing)";
        return;
    }
    $r = $m->simpanDetailPelanggaran($nidn, $tatib, $nim, 99999999, 'x', '', '', 'pending', 'Belum Dikerjakan', false);
    assertFalse($r['success'], 'invalid sanksi must fail');
});

$runner->addTest('plg: update fails when tatib/mhs/sanksi invalid', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    $tatib = (int) $c->query('SELECT id_tata_tertib FROM TATA_TERTIB LIMIT 1')->fetchColumn();
    $sanksi = (int) $c->query('SELECT id_sanksi FROM SANKSI LIMIT 1')->fetchColumn();
    if ($nim === '' || $tatib <= 0 || $sanksi <= 0) {
        echo "\n       (skipped: seed missing)";
        return;
    }

    // Create a real row to target (so we don't trip the "not found" branch first).
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1')->fetchColumn();
    if ($nidn === '') {
        echo "\n       (skipped: no dosen)";
        return;
    }
    $marker = 'ZZUPD' . bin2hex(random_bytes(4));
    $created = $m->simpanDetailPelanggaran($nidn, $tatib, $nim, $sanksi, $marker, '', '', 'pending', 'Belum Dikerjakan', false);
    if (!($created['success'] ?? false)) {
        echo "\n       (skipped: fixture create failed)";
        return;
    }
    $id = (int) $created['id_detail'];
    try {
        // invalid tatib -> error branch
        assertFalse($m->updateDetailPelanggaran($id, 99999999, $nim, $sanksi, 'x', '', 'pending', 'Belum Dikerjakan', false)['success']);
        // invalid mahasiswa -> error branch
        assertFalse($m->updateDetailPelanggaran($id, $tatib, 'zz-no-nim', $sanksi, 'x', '', 'pending', 'Belum Dikerjakan', false)['success']);
        // invalid sanksi -> error branch
        assertFalse($m->updateDetailPelanggaran($id, $tatib, $nim, 99999999, 'x', '', 'pending', 'Belum Dikerjakan', false)['success']);
    } finally {
        $c->exec('DELETE FROM NOTIFIKASI WHERE id_detail_pelanggaran = ' . $id);
        $c->prepare('DELETE FROM DETAIL_PELANGGARAN WHERE id_detail = ?')->execute([$id]);
    }
});

$runner->addTest('plg: konfirmasi/hapus reject wrong dosen (not penanggung)', function () use ($plgDb, $plgTatib, $plgSanksi, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    $dosenRows = $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
    $tatib = $plgTatib($c, false);
    if ($nim === '' || count($dosenRows) < 2 || $tatib === null) {
        echo "\n       (skipped: need 2 dosen + seed)";
        return;
    }
    $reporter = (string) $dosenRows[0];
    $other = (string) $dosenRows[1];
    $idTatib = (int) $tatib['id_tata_tertib'];
    $idSanksi = $plgSanksi($c, (string) $tatib['tingkat']);

    $marker = 'ZZOWN' . bin2hex(random_bytes(4));
    $created = $m->simpanDetailPelanggaran($reporter, $idTatib, $nim, $idSanksi, $marker, '', 'x.pdf', 'proses', 'Belum Dikerjakan', false);
    if (!($created['success'] ?? false)) {
        echo "\n       (skipped: fixture create failed)";
        return;
    }
    $id = (int) $created['id_detail'];
    try {
        // A different dosen is not the owner -> both must refuse.
        assertFalse($m->konfirmasiLaporanSelesaiByDosen($other, $id)['success'], 'foreign dosen cannot confirm');
        assertFalse($m->hapusDetailPelanggaranByDosen($other, $id)['success'], 'foreign dosen cannot delete');
        // Row must still be there.
        $chk = $c->prepare('SELECT COUNT(*) FROM DETAIL_PELANGGARAN WHERE id_detail = ?');
        $chk->execute([$id]);
        assertEquals(1, (int) $chk->fetchColumn(), 'foreign ops must not mutate');
    } finally {
        $c->exec('DELETE FROM NOTIFIKASI WHERE id_detail_pelanggaran = ' . $id);
        $c->prepare('DELETE FROM DETAIL_PELANGGARAN WHERE id_detail = ?')->execute([$id]);
    }
});

$runner->addTest('plg: notification read helpers succeed on a real notification', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1')->fetchColumn();
    if ($nim === '' || $nidn === '') {
        echo "\n       (skipped: seed missing)";
        return;
    }
    $idMhs = (int) $c->query("SELECT id_mhs FROM MAHASISWA WHERE nim = " . $c->quote($nim))->fetchColumn();
    $idDosen = (int) $c->query("SELECT id_dosen FROM DOSEN WHERE nidn = " . $c->quote($nidn))->fetchColumn();
    if ($idMhs <= 0 || $idDosen <= 0) {
        echo "\n       (skipped: ids missing)";
        return;
    }

    // Insert one unread notification per role, then mark them read individually.
    // id_detail_pelanggaran is nullable; NULL avoids the FK constraint.
    $ins = $c->prepare("INSERT INTO NOTIFIKASI (id_dosen, id_mhs, id_detail_pelanggaran, pesan, status, role_penerima) VALUES (?, ?, NULL, ?, 'unread', ?)");
    $ins->execute([$idDosen, $idMhs, 'ZZ-notif-mhs', 'mahasiswa']);
    $idNotifMhs = (int) $c->lastInsertId();
    $ins->execute([$idDosen, $idMhs, 'ZZ-notif-dosen', 'dosen']);
    $idNotifDosen = (int) $c->lastInsertId();

    try {
        assertTrue($m->markNotifikasiAsReadForMahasiswa($nim, $idNotifMhs), 'mark read (mahasiswa) must succeed');
        assertFalse($m->markNotifikasiAsReadForMahasiswa($nim, $idNotifMhs), 'second mark is a no-op (already read)');
        assertTrue($m->markNotifikasiAsReadForDosen($nidn, $idNotifDosen), 'mark read (dosen) must succeed');
        assertFalse($m->markNotifikasiAsReadForDosen($nidn, $idNotifDosen), 'second mark (dosen) is a no-op');
    } finally {
        foreach ([$idNotifMhs, $idNotifDosen] as $id) {
            $c->prepare('DELETE FROM NOTIFIKASI WHERE id_notifikasi = ?')->execute([$id]);
        }
    }
});

$runner->addTest('plg: simpan with delegation to DPA without id_dpa fails gracefully', function () use ($plgDb, $plgTatib, $plgSanksi, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    $tatib = $plgTatib($c, true); // tingkat I/II/III (requires tugas)
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1')->fetchColumn();
    if ($tatib === null || $nidn === '') {
        echo "\n       (skipped: no tingkat I-III tatib / dosen)";
        return;
    }
    // Pick a mahasiswa with NO dpa (id_dpa null/0) to hit the guard.
    $nimNoDpa = (string) $c->query('SELECT nim FROM MAHASISWA WHERE id_dpa IS NULL OR id_dpa = 0 ORDER BY nim LIMIT 1')->fetchColumn();
    if ($nimNoDpa === '') {
        echo "\n       (skipped: all mahasiswa already have a DPA)";
        return;
    }
    $idTatib = (int) $tatib['id_tata_tertib'];
    $idSanksi = $plgSanksi($c, (string) $tatib['tingkat']);
    $r = $m->simpanDetailPelanggaran($nidn, $idTatib, $nimNoDpa, $idSanksi, 'ZZNODPA', '', '', 'pending', 'Belum Dikerjakan', true);
    assertFalse($r['success'], 'delegation without id_dpa must fail');
});

$runner->addTest('plg: konfirmasi of a tingkat I-III report requires tugas khusus', function () use ($plgDb, $plgTatib, $plgSanksi, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    $mhs = $c->query('SELECT nim, id_dpa FROM MAHASISWA WHERE id_dpa IS NOT NULL AND id_dpa > 0 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $tatib = $plgTatib($c, true);
    if (!$mhs || $tatib === null) {
        echo "\n       (skipped: need dpa mahasiswa + tingkat I-III)";
        return;
    }
    // DPA dosen (the penanggung) must differ from the reporter for the notification branch.
    $dpaNidn = (string) $c->query('SELECT nidn FROM DOSEN WHERE id_dosen = ' . (int) $mhs['id_dpa'])->fetchColumn();
    $reporterNidn = (string) $c->query('SELECT nidn FROM DOSEN WHERE id_dosen <> ' . (int) $mhs['id_dpa'] . ' ORDER BY id_dosen LIMIT 1')->fetchColumn();
    if ($dpaNidn === '' || $reporterNidn === '') {
        echo "\n       (skipped: need distinct dpa/reporter dosen)";
        return;
    }

    $idTatib = (int) $tatib['id_tata_tertib'];
    $idSanksi = $plgSanksi($c, (string) $tatib['tingkat']);
    $marker = 'ZZCONFTGS' . bin2hex(random_bytes(4));

    // Reporter creates it with DPA delegation + surat but NO tugas -> confirm should fail on tugas.
    $created = $m->simpanDetailPelanggaran($reporterNidn, $idTatib, (string) $mhs['nim'], $idSanksi, $marker, '', 'x.pdf', 'proses', 'Belum Dikerjakan', true);
    if (!($created['success'] ?? false)) {
        echo "\n       (skipped: fixture create failed)";
        return;
    }
    $id = (int) $created['id_detail'];
    try {
        $r1 = $m->konfirmasiLaporanSelesaiByDosen($dpaNidn, $id);
        assertFalse($r1['success'], 'confirm without tugas khusus must fail');
        assertStringContains('tugas', strtolower($r1['message'] ?? ''), 'message should mention tugas');

        // Upload the tugas -> confirm succeeds, and the "reporter != actor" notification branch runs.
        $c->prepare("UPDATE DETAIL_PELANGGARAN SET pengumpulan_tgsKhusus='t.pdf' WHERE id_detail=?")->execute([$id]);
        $r2 = $m->konfirmasiLaporanSelesaiByDosen($dpaNidn, $id);
        assertTrue($r2['success'], 'confirm with tugas should succeed: ' . ($r2['message'] ?? ''));

        // Already done -> idempotent success branch.
        $r3 = $m->konfirmasiLaporanSelesaiByDosen($dpaNidn, $id);
        assertTrue($r3['success'], 're-confirm is idempotent success');

        // A non-penanggung dosen is refused ("not found / not responsible" branch).
        $otherNidn = (string) $c->query("SELECT nidn FROM DOSEN WHERE nidn NOT IN (" . $c->quote($dpaNidn) . ',' . $c->quote($reporterNidn) . ") ORDER BY id_dosen LIMIT 1")->fetchColumn();
        if ($otherNidn !== '') {
            assertFalse($m->konfirmasiLaporanSelesaiByDosen($otherNidn, $id)['success'], 'unrelated dosen cannot confirm');
        }
    } finally {
        $c->exec('DELETE FROM NOTIFIKASI WHERE id_detail_pelanggaran = ' . $id);
        $c->prepare('DELETE FROM DETAIL_PELANGGARAN WHERE id_detail = ?')->execute([$id]);
    }
});

$runner->addTest('plg: hapus by penanggung succeeds and removes notifications', function () use ($plgDb, $plgTatib, $plgSanksi, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1')->fetchColumn();
    $tatib = $plgTatib($c, false);
    if ($nim === '' || $nidn === '' || $tatib === null) {
        echo "\n       (skipped: seed missing)";
        return;
    }
    $idTatib = (int) $tatib['id_tata_tertib'];
    $idSanksi = $plgSanksi($c, (string) $tatib['tingkat']);
    $marker = 'ZZDELOK' . bin2hex(random_bytes(4));

    // Reporter == penanggung (no delegation), so the reporter may delete.
    $created = $m->simpanDetailPelanggaran($nidn, $idTatib, $nim, $idSanksi, $marker, '', '', 'proses', 'Belum Dikerjakan', false);
    if (!($created['success'] ?? false)) {
        echo "\n       (skipped: fixture create failed)";
        return;
    }
    $id = (int) $created['id_detail'];
    try {
        $del = $m->hapusDetailPelanggaranByDosen($nidn, $id);
        assertTrue($del['success'], 'owner dosen delete must succeed: ' . ($del['message'] ?? ''));
        $chk = $c->prepare('SELECT COUNT(*) FROM DETAIL_PELANGGARAN WHERE id_detail = ?');
        $chk->execute([$id]);
        assertEquals(0, (int) $chk->fetchColumn(), 'row must be gone');
    } finally {
        $c->exec('DELETE FROM NOTIFIKASI WHERE id_detail_pelanggaran = ' . $id);
        $c->prepare('DELETE FROM DETAIL_PELANGGARAN WHERE id_detail = ?')->execute([$id]);
    }
});

$runner->addTest('plg: konfirmasi/hapus with empty nidn or id<=0 return structured error', function () use ($plgDb, $plgNewModel) {
    $c = $plgDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $m = $plgNewModel();
    foreach ([['', 1], ['1234567890', 0], ['', 0]] as [$nidn, $id]) {
        $rk = $m->konfirmasiLaporanSelesaiByDosen($nidn, $id);
        assertTrue(is_array($rk) && ($rk['success'] ?? true) === false, 'invalid confirm input must fail cleanly');
        $rh = $m->hapusDetailPelanggaranByDosen($nidn, $id);
        assertTrue(is_array($rh) && ($rh['success'] ?? true) === false, 'invalid delete input must fail cleanly');
    }
});
