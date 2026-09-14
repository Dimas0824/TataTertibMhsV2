<?php

/**
 * ControllerCoverageTest — direct coverage for TatibController (CRUD wrappers)
 * and UserController read-only getters. DB-backed; rows are cleaned up.
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/models/Tatib.php';
require_once dirname(__DIR__, 2) . '/models/Sanksi.php';
require_once dirname(__DIR__, 2) . '/models/User.php';
require_once dirname(__DIR__, 2) . '/controllers/TatibController.php';
require_once dirname(__DIR__, 2) . '/controllers/UserController.php';

$ccDb = static function (): ?PDO {
    $c = $GLOBALS['connect'] ?? null;
    return $c instanceof PDO ? $c : null;
};

$runner->addTest('tatib-ctrl: ReadTatib / ReadSanksi / getTatibDetail', function () use ($ccDb) {
    $c = $ccDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $ctrl = new TatibController();
    assertTrue(is_array($ctrl->ReadTatib()), 'ReadTatib returns array');
    assertTrue(is_array($ctrl->ReadSanksi()), 'ReadSanksi returns array');

    $id = (int) $c->query('SELECT id_tata_tertib FROM TATA_TERTIB LIMIT 1')->fetchColumn();
    if ($id > 0) {
        assertTrue(is_array($ctrl->getTatibDetail($id)), 'getTatibDetail returns row');
    }
});

$runner->addTest('tatib-ctrl: store/update/delete round-trip (success + cleanup)', function () use ($ccDb) {
    $c = $ccDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $idAdmin = (int) $c->query('SELECT id_admin FROM ADMIN LIMIT 1')->fetchColumn();
    if ($idAdmin <= 0) {
        echo "\n       (skipped: no admin)";
        return;
    }

    $ctrl = new TatibController();
    $marker = 'ZZCTRL' . bin2hex(random_bytes(4));
    $createdId = 0;
    try {
        $st = $ctrl->store($idAdmin, $marker, 'V', 1);
        assertTrue($st['success'], 'store success');

        $row = $c->prepare('SELECT id_tata_tertib FROM TATA_TERTIB WHERE deskripsi = ? LIMIT 1');
        $row->execute([$marker]);
        $createdId = (int) $row->fetchColumn();
        assertTrue($createdId > 0, 'row created');

        $up = $ctrl->update($createdId, $idAdmin, $marker . '-u', 'IV', 2);
        assertTrue($up['success'], 'update success');

        $del = $ctrl->delete($createdId);
        assertTrue($del['success'], 'delete success');
        $createdId = 0;
    } finally {
        if ($createdId > 0) {
            $c->prepare('DELETE FROM TATA_TERTIB WHERE id_tata_tertib = ?')->execute([$createdId]);
        }
        $c->prepare('DELETE FROM TATA_TERTIB WHERE deskripsi IN (?,?)')->execute([$marker, $marker . '-u']);
    }
});

$runner->addTest('user-model: getters return arrays for known/unknown input', function () use ($ccDb) {
    $c = $ccDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $model = new User();
    assertTrue(is_array($model->getAllMahasiswa()), 'getAllMahasiswa array');
    $allUsers = $model->getAllUsers();
    assertTrue(is_array($allUsers) || $allUsers === false, 'getAllUsers array/false');

    // unknown admin name should be null (or string) without throwing
    $name = $model->getAdminName(99999999);
    assertTrue($name === null || is_string($name), 'unknown admin name safe');
});

$runner->addTest('user-model: login lookups reject bad credentials', function () use ($ccDb) {
    $c = $ccDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $model = new User();
    assertFalse($model->getMahasiswaLogin('zz-no-nim', 'zz-wrong'), 'unknown nim fails');
    assertFalse($model->getDosenLogin('zz-no-nidn', 'zz-wrong'), 'unknown nidn fails');
    assertFalse($model->getAdminLogin('zz-no-nip', 'zz-wrong'), 'unknown nip fails');
});

$runner->addTest('user-ctrl: getAllMahasiswa / getAdminName pass-through', function () use ($ccDb) {
    $c = $ccDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $ctrl = new UserController();
    assertTrue(is_array($ctrl->getAllMahasiswa()), 'getAllMahasiswa array');

    $idAdmin = (int) $c->query('SELECT id_admin FROM ADMIN LIMIT 1')->fetchColumn();
    if ($idAdmin > 0) {
        $name = $ctrl->getAdminName($idAdmin);
        assertTrue(is_string($name) || $name === null, 'getAdminName returns string/null');
    }
    assertTrue($ctrl->getAdminName(99999999) === null, 'unknown admin name -> null');
});
