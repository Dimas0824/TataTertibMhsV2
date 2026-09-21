<?php

/**
 * Area4SanctionSuite — regression for the AREA 4 finding.
 *
 * Strix AREA 4 (MEDIUM, CWE-20): the sanction chosen for a violation was not
 * validated against the violation's tier, so a lecturer could attach a Tier I
 * (expulsion) sanction to a Tier V (2-point) violation. The fix rejects a
 * sanction whose tier does not match the violation's tier, server-side.
 */

require dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/models/Pelanggaran.php';

$a4Db = static function (): ?PDO {
    $c = $GLOBALS['connect'] ?? null;
    return $c instanceof PDO ? $c : null;
};

$runner->addTest('area4: sanction tier must match the violation tier', function () use ($a4Db) {
    $c = $a4Db();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }

    // Two sanctions of DIFFERENT tiers, and a tatib whose tier differs from one.
    $rows = $c->query("SELECT id_sanksi, tingkat FROM SANKSI ORDER BY id_sanksi")->fetchAll();
    if (count($rows) < 2) {
        echo "\n       (skipped: need >=2 sanctions to test a mismatch)";
        return;
    }

    $tatib = $c->query("SELECT id_tata_tertib, tingkat FROM TATA_TERTIB ORDER BY id_tata_tertib LIMIT 1")->fetch();
    if (!$tatib) {
        echo "\n       (skipped: no tata tertib rows)";
        return;
    }

    // find a sanction whose tier is NOT the tatib's tier
    $mismatchedId = null;
    foreach ($rows as $r) {
        if (strcasecmp(trim((string) $r['tingkat']), trim((string) $tatib['tingkat'])) !== 0) {
            $mismatchedId = (int) $r['id_sanksi'];
            break;
        }
    }
    if ($mismatchedId === null) {
        echo "\n       (skipped: no mismatched sanction tier available)";
        return;
    }

    $model = new Pelanggaran();

    // The guard must be able to tell us the tier of a sanction.
    assertTrue(
        method_exists($model, 'getSanksiTingkatById'),
        'Pelanggaran must expose getSanksiTingkatById() for the tier check'
    );

    $tier = $model->getSanksiTingkatById($mismatchedId);
    assertTrue(
        $tier !== null && strcasecmp(trim($tier), trim((string) $tatib['tingkat'])) !== 0,
        'the chosen sanction tier must be visibly different from the violation tier'
    );
});

$runner->addTest('area4: storing a violation with a mismatched sanction is rejected', function () use ($a4Db) {
    $c = $a4Db();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }

    $tatib = $c->query("SELECT id_tata_tertib, tingkat FROM TATA_TERTIB ORDER BY id_tata_tertib LIMIT 1")->fetch();
    $dosen = $c->query("SELECT nidn FROM DOSEN ORDER BY id_dosen LIMIT 1")->fetch();
    $mhs = $c->query("SELECT nim FROM MAHASISWA ORDER BY id_mhs LIMIT 1")->fetch();
    if (!$tatib || !$dosen || !$mhs) {
        echo "\n       (skipped: seed data missing)";
        return;
    }

    $mismatch = null;
    foreach ($c->query("SELECT id_sanksi, tingkat FROM SANKSI")->fetchAll() as $r) {
        if (strcasecmp(trim((string) $r['tingkat']), trim((string) $tatib['tingkat'])) !== 0) {
            $mismatch = (int) $r['id_sanksi'];
            break;
        }
    }
    if ($mismatch === null) {
        echo "\n       (skipped: no mismatched sanction tier)";
        return;
    }

    $model = new Pelanggaran();
    $countBefore = (int) $c->query("SELECT COUNT(*) FROM DETAIL_PELANGGARAN")->fetchColumn();

    $result = null;
    try {
        $result = $model->simpanDetailPelanggaran(
            (string) $dosen['nidn'],
            (int) $tatib['id_tata_tertib'],
            (string) $mhs['nim'],
            $mismatch,
            'ZZAREA4 mismatch probe',
            null,
            null,
            'Proses Bimbingan',
            'Belum Dikerjakan'
        );
    } catch (Throwable $e) {
        $result = ['success' => false, 'message' => $e->getMessage()];
    }

    $countAfter = (int) $c->query("SELECT COUNT(*) FROM DETAIL_PELANGGARAN")->fetchColumn();

    assertTrue(
        is_array($result) && ($result['success'] ?? true) === false,
        'a mismatched sanction tier must be rejected (success=false)'
    );
    assertEquals($countBefore, $countAfter, 'no row must be written for a mismatched sanction');
});

