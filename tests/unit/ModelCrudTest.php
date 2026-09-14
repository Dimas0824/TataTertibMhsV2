<?php

/**
 * ModelCrudTest — direct unit coverage for models/News.php and models/Tatib.php
 * CRUD methods (raises coverage of two models that were mostly uncovered).
 *
 * Uses the application DB via config.php; rows are throwaway and removed in
 * `finally`. Skips gracefully when the seed is unavailable.
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/models/News.php';
require_once dirname(__DIR__, 2) . '/models/Tatib.php';

$mcDb = static function (): ?PDO {
    $c = $GLOBALS['connect'] ?? null;
    return $c instanceof PDO ? $c : null;
};

/* ------------------------------------------------------------------ */
/* News model                                                           */
/* ------------------------------------------------------------------ */

$runner->addTest('news-model: insert/getAll/getAdmin/getLatest/update/delete', function () use ($mcDb) {
    $c = $mcDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $idAdmin = (int) $c->query('SELECT id_admin FROM ADMIN LIMIT 1')->fetchColumn();
    if ($idAdmin <= 0) {
        echo "\n       (skipped: no admin)";
        return;
    }

    $model = new News($c);
    $marker = 'ZZMNEWS' . bin2hex(random_bytes(4));
    $createdId = 0;
    try {
        assertTrue($model->insertNews(null, $marker, '<p>x</p>', $idAdmin), 'insertNews true');

        $row = $c->prepare('SELECT id_news FROM NEWS WHERE judul = ? LIMIT 1');
        $row->execute([$marker]);
        $createdId = (int) $row->fetchColumn();
        assertTrue($createdId > 0, 'row created');

        $byId = $model->getNewsById($createdId);
        assertTrue(is_array($byId) && (int) $byId['id_news'] === $createdId, 'getNewsById returns row');

        assertTrue(is_array($model->getAllNews()), 'getAllNews array');
        assertTrue(is_array($model->getNewsAdmin($idAdmin)), 'getNewsAdmin array');
        assertTrue(is_array($model->getLatestNewsExcluding($createdId, 3)), 'getLatestNewsExcluding array');

        // update with penulis + gambar path branches
        assertTrue($model->updateNews($createdId, $marker . '-u', '<p>y</p>', $idAdmin, 'img/x.jpg'), 'updateNews true');
        $judul = (string) $c->query('SELECT judul FROM NEWS WHERE id_news = ' . $createdId)->fetchColumn();
        assertEquals($marker . '-u', $judul, 'update applied');

        assertTrue($model->deleteNews($createdId), 'deleteNews true');
        $createdId = 0;
    } finally {
        if ($createdId > 0) {
            $c->prepare('DELETE FROM NEWS WHERE id_news = ?')->execute([$createdId]);
        }
        $c->prepare('DELETE FROM NEWS WHERE judul IN (?,?)')->execute([$marker, $marker . '-u']);
    }
});

$runner->addTest('news-model: getNewsById on unknown id returns false/null safely', function () use ($mcDb) {
    $c = $mcDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $model = new News($c);
    $res = $model->getNewsById(99999999);
    assertTrue($res === false || $res === null || $res === [], 'unknown id -> empty-ish');
});

/* ------------------------------------------------------------------ */
/* Tatib model                                                          */
/* ------------------------------------------------------------------ */

$runner->addTest('tatib-model: insert/getAll/update/getById/delete round-trip', function () use ($mcDb) {
    $c = $mcDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $idAdmin = (int) $c->query('SELECT id_admin FROM ADMIN LIMIT 1')->fetchColumn();
    if ($idAdmin <= 0) {
        echo "\n       (skipped: no admin)";
        return;
    }

    $model = new Tatib();
    $marker = 'ZZMTATIB' . bin2hex(random_bytes(4));
    $createdId = 0;
    try {
        assertTrue($model->insertTatib($idAdmin, $marker, 'V', 1), 'insertTatib true');
        $row = $c->prepare('SELECT id_tata_tertib FROM TATA_TERTIB WHERE deskripsi = ? LIMIT 1');
        $row->execute([$marker]);
        $createdId = (int) $row->fetchColumn();
        assertTrue($createdId > 0, 'tatib row created');

        assertTrue(is_array($model->getAllTatib()), 'getAllTatib array');
        assertTrue(is_array($model->getTatibById($createdId)), 'getTatibById array');

        assertTrue($model->updateTatib($createdId, $idAdmin, $marker . '-u', 'IV', 2), 'updateTatib true');
        $desk = (string) $c->query('SELECT deskripsi FROM TATA_TERTIB WHERE id_tata_tertib = ' . $createdId)->fetchColumn();
        assertEquals($marker . '-u', $desk, 'update applied');

        assertTrue($model->deleteTatib($createdId), 'deleteTatib true');
        $createdId = 0;
    } finally {
        if ($createdId > 0) {
            $c->prepare('DELETE FROM TATA_TERTIB WHERE id_tata_tertib = ?')->execute([$createdId]);
        }
        $c->prepare('DELETE FROM TATA_TERTIB WHERE deskripsi IN (?,?)')->execute([$marker, $marker . '-u']);
    }
});
