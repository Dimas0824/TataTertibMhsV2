<?php

/**
 * Area4WorkflowSuite — regression for the finding found during the Strix re-run
 * (2026-09-21 re-scan, HIGH, CWE-863).
 *
 * The delete path enforced reporter ownership but not the workflow state, so a
 * lecturer could permanently delete a violation already confirmed 'selesai'
 * (finalized) — even though the edit path refuses it and the UI disables it.
 */

require dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/models/Pelanggaran.php';

$a4wDb = static function (): ?PDO {
    $c = $GLOBALS['connect'] ?? null;
    return $c instanceof PDO ? $c : null;
};

$runner->addTest('area4-workflow: a finalized violation cannot be deleted (CWE-863)', function () use ($a4wDb) {
    $c = $a4wDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }

    $dosen = $c->query("SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1")->fetch();
    $mhs = $c->query("SELECT nim FROM MAHASISWA ORDER BY id_mhs LIMIT 1")->fetch();
    $tatib = $c->query("SELECT id_tata_tertib FROM TATA_TERTIB ORDER BY id_tata_tertib LIMIT 1")->fetch();
    if (!$dosen || !$mhs || !$tatib) {
        echo "\n       (skipped: seed data missing)";
        return;
    }

    $dosenRow = $c->query("SELECT id_dosen, nama_lengkap FROM DOSEN ORDER BY id_dosen LIMIT 1")->fetch();
    $mhsRow = $c->query("SELECT id_mhs FROM MAHASISWA ORDER BY id_mhs LIMIT 1")->fetch();

    // Insert a FIXTURE already in the finalized state.
    $ins = $c->prepare('INSERT INTO DETAIL_PELANGGARAN
        (id_dosen, id_tata_tertib, id_mahasiswa, id_sanksi, status, status_tugas,
         delegasi_tugas_ke_dpa, id_dosen_penanggung_jawab, detail_pelanggaran)
        VALUES (?, ?, ?, 3, ?, ?, 0, ?, ?)');
    $marker = 'ZZWF' . bin2hex(random_bytes(4));
    $ins->execute([
        (int) $dosenRow['id_dosen'], (int) $tatib['id_tata_tertib'], (int) $mhsRow['id_mhs'],
        'selesai', 'Sudah Dikerjakan', (int) $dosenRow['id_dosen'], $marker,
    ]);
    $idDetail = (int) $c->lastInsertId();

    try {
        $model = new Pelanggaran();
        $result = $model->hapusDetailPelanggaranByDosen((string) $dosen['nidn'], $idDetail);

        $stillThere = (int) $c->query("SELECT COUNT(*) FROM DETAIL_PELANGGARAN WHERE id_detail = {$idDetail}")->fetchColumn();

        assertTrue(
            is_array($result) && ($result['success'] ?? true) === false,
            'deleting a finalized violation must be refused (success=false)'
        );
        assertEquals(1, $stillThere, 'the finalized row must still exist after the refused delete');
    } finally {
        $c->prepare("DELETE FROM NOTIFIKASI WHERE id_detail_pelanggaran = ?")->execute([$idDetail]);
        $c->prepare("DELETE FROM DETAIL_PELANGGARAN WHERE id_detail = ?")->execute([$idDetail]);
    }
});

$runner->addTest('area4-workflow: an unfinished violation can still be deleted (control)', function () use ($a4wDb) {
    $c = $a4wDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }

    $dosenRow = $c->query("SELECT id_dosen, nidn FROM DOSEN ORDER BY id_dosen LIMIT 1")->fetch();
    $mhsRow = $c->query("SELECT id_mhs FROM MAHASISWA ORDER BY id_mhs LIMIT 1")->fetch();
    $tatib = $c->query("SELECT id_tata_tertib FROM TATA_TERTIB ORDER BY id_tata_tertib LIMIT 1")->fetch();
    if (!$dosenRow || !$mhsRow || !$tatib) {
        echo "\n       (skipped: seed data missing)";
        return;
    }

    $ins = $c->prepare('INSERT INTO DETAIL_PELANGGARAN
        (id_dosen, id_tata_tertib, id_mahasiswa, id_sanksi, status, status_tugas,
         delegasi_tugas_ke_dpa, id_dosen_penanggung_jawab, detail_pelanggaran)
        VALUES (?, ?, ?, 3, ?, ?, 0, ?, ?)');
    $marker = 'ZZWF' . bin2hex(random_bytes(4));
    $ins->execute([
        (int) $dosenRow['id_dosen'], (int) $tatib['id_tata_tertib'], (int) $mhsRow['id_mhs'],
        'Proses Bimbingan', 'Belum Dikerjakan', (int) $dosenRow['id_dosen'], $marker,
    ]);
    $idDetail = (int) $c->lastInsertId();

    try {
        $model = new Pelanggaran();
        $result = $model->hapusDetailPelanggaranByDosen((string) $dosenRow['nidn'], $idDetail);
        assertTrue(
            is_array($result) && ($result['success'] ?? false) === true,
            'an unfinished violation must still be deletable by its reporter'
        );
    } finally {
        $c->prepare("DELETE FROM NOTIFIKASI WHERE id_detail_pelanggaran = ?")->execute([$idDetail]);
        $c->prepare("DELETE FROM DETAIL_PELANGGARAN WHERE id_detail = ?")->execute([$idDetail]);
    }
});
