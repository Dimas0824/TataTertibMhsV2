<?php

/**
 * PelanggaranControllerTest — unit coverage for the controller's thin
 * pass-through + validation logic (mark notifikasi, getters).
 *
 * DB-backed cases use throwaway rows cleaned up in `finally` and skip
 * gracefully when the seed is unavailable.
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/models/Pelanggaran.php';
require_once dirname(__DIR__, 2) . '/controllers/PelanggaranController.php';
require_once dirname(__DIR__, 2) . '/controllers/TatibController.php';

$pcDb = static function (): ?PDO {
    $c = $GLOBALS['connect'] ?? null;
    return $c instanceof PDO ? $c : null;
};

/* ------------------------------------------------------------------ */
/* 1. Validation branches (no DB)                                       */
/* ------------------------------------------------------------------ */

$runner->addTest('pc: markNotifikasiAsRead rejects id<=0 and bad role', function () {
    $ctrl = new PelanggaranController();
    $r1 = $ctrl->markNotifikasiAsRead(['nim' => 'x'], 'mahasiswa', 0);
    assertFalse($r1['success'], 'id<=0 must be rejected');

    $r2 = $ctrl->markNotifikasiAsRead(['nim' => 'x'], 'zzz-bad-role', 5);
    assertFalse($r2['success'], 'unknown role must be rejected');
});

$runner->addTest('pc: markNotifikasiAsRead rejects missing nim / nidn in session', function () {
    $ctrl = new PelanggaranController();
    $r1 = $ctrl->markNotifikasiAsRead([], 'mahasiswa', 5);
    assertFalse($r1['success'], 'missing nim must be rejected');
    $r2 = $ctrl->markNotifikasiAsRead([], 'dosen', 5);
    assertFalse($r2['success'], 'missing nidn must be rejected');
});

$runner->addTest('pc: markAllNotifikasiAsRead rejects bad role and missing identity', function () {
    $ctrl = new PelanggaranController();
    assertFalse($ctrl->markAllNotifikasiAsRead(['nim' => 'x'], 'zzz')['success'], 'bad role rejected');
    assertFalse($ctrl->markAllNotifikasiAsRead([], 'mahasiswa')['success'], 'missing nim rejected');
    assertFalse($ctrl->markAllNotifikasiAsRead([], 'dosen')['success'], 'missing nidn rejected');
});

/* ------------------------------------------------------------------ */
/* 2. DB-backed pass-throughs                                           */
/* ------------------------------------------------------------------ */

$runner->addTest('pc: markNotifikasiAsRead works for mahasiswa & dosen sessions', function () use ($pcDb) {
    $c = $pcDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $ctrl = new PelanggaranController();

    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1')->fetchColumn();
    if ($nim === '' || $nidn === '') {
        echo "\n       (skipped: seed missing)";
        return;
    }
    $idMhs = (int) $c->query('SELECT id_mhs FROM MAHASISWA WHERE nim = ' . $c->quote($nim))->fetchColumn();
    $idDosen = (int) $c->query('SELECT id_dosen FROM DOSEN WHERE nidn = ' . $c->quote($nidn))->fetchColumn();

    $ins = $c->prepare("INSERT INTO NOTIFIKASI (id_dosen, id_mhs, id_detail_pelanggaran, pesan, status, role_penerima) VALUES (?, ?, NULL, ?, 'unread', ?)");
    $ins->execute([$idDosen, $idMhs, 'ZZPC-mhs', 'mahasiswa']);
    $idMhsNotif = (int) $c->lastInsertId();
    $ins->execute([$idDosen, $idMhs, 'ZZPC-dosen', 'dosen']);
    $idDosenNotif = (int) $c->lastInsertId();

    try {
        $rMhs = $ctrl->markNotifikasiAsRead(['nim' => $nim], 'mahasiswa', $idMhsNotif);
        assertTrue($rMhs['success'], 'mahasiswa mark_read must succeed');

        $rDosen = $ctrl->markNotifikasiAsRead(['nidn' => $nidn], 'dosen', $idDosenNotif);
        assertTrue($rDosen['success'], 'dosen mark_read must succeed');

        // second attempt -> already read
        $again = $ctrl->markNotifikasiAsRead(['nim' => $nim], 'mahasiswa', $idMhsNotif);
        assertFalse($again['success'], 'already-read must report failure');
    } finally {
        foreach ([$idMhsNotif, $idDosenNotif] as $id) {
            $c->prepare('DELETE FROM NOTIFIKASI WHERE id_notifikasi = ?')->execute([$id]);
        }
    }
});

$runner->addTest('pc: markAllNotifikasiAsRead returns updated_count', function () use ($pcDb) {
    $c = $pcDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $ctrl = new PelanggaranController();
    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1')->fetchColumn();
    if ($nim === '' || $nidn === '') {
        echo "\n       (skipped: seed missing)";
        return;
    }

    $rM = $ctrl->markAllNotifikasiAsRead(['nim' => $nim], 'mahasiswa');
    assertTrue($rM['success'], 'markAll mahasiswa must succeed');
    assertTrue(array_key_exists('updated_count', $rM), 'updated_count present');

    $rD = $ctrl->markAllNotifikasiAsRead(['nidn' => $nidn], 'dosen');
    assertTrue($rD['success'], 'markAll dosen must succeed');
});

$runner->addTest('pc: getters pass through to the model', function () use ($pcDb) {
    $c = $pcDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $ctrl = new PelanggaranController();
    $nim = (string) $c->query('SELECT nim FROM MAHASISWA ORDER BY nim LIMIT 1')->fetchColumn();
    $nidn = (string) $c->query('SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1')->fetchColumn();
    if ($nim !== '') {
        assertTrue(is_array($ctrl->getDetailPelanggaranMahasiswa($nim)));
        assertTrue(is_array($ctrl->getNotifikasiMahasiswa($nim)));
        assertTrue(is_array($ctrl->searchMahasiswa(substr($nim, 0, 4), 10)));
    }
    if ($nidn !== '') {
        assertTrue(is_array($ctrl->getDetailLaporanDosen($nidn)));
        assertTrue(is_array($ctrl->getNotifikasiDosen($nidn)));
    }
    // unknown -> null / array
    assertNull($ctrl->getMahasiswaByNim('zz-no-nim'));
    assertNull($ctrl->getDefaultSanksiByTingkat('ZZ'));
});

$runner->addTest('pc: konfirmasi/hapus pass-through reject foreign dosen', function () use ($pcDb) {
    $c = $pcDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $ctrl = new PelanggaranController();
    // Both should return structured failure for a non-existent detail id.
    $k = $ctrl->konfirmasiLaporanSelesai('1234567890', 99999999);
    assertTrue(is_array($k) && ($k['success'] ?? true) === false, 'konfirmasi unknown id must fail');
    $h = $ctrl->hapusDetailPelanggaran('1234567890', 99999999);
    assertTrue(is_array($h) && ($h['success'] ?? true) === false, 'hapus unknown id must fail');
});
