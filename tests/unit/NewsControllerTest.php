<?php

/**
 * NewsControllerTest — unit coverage for the static slug helpers and the
 * DB-backed CRUD paths of controllers/NewsController.php.
 *
 * Slug helpers are pure and fast; CRUD cases use throwaway rows cleaned up in
 * `finally`, and skip gracefully when the DB/seed is unavailable.
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/controllers/NewsController.php';

/* ------------------------------------------------------------------ */
/* 1. Pure slug helpers (no DB)                                         */
/* ------------------------------------------------------------------ */

$runner->addTest('news: news_slugify_title basic + empty + symbols', function () {
    assertEquals('hello-world', NewsController::news_slugify_title('Hello World'));
    assertEquals('berita', NewsController::news_slugify_title(''), 'empty -> berita');
    assertEquals('berita', NewsController::news_slugify_title('   '), 'blank -> berita');
    assertEquals('berita', NewsController::news_slugify_title('!!!'), 'symbols-only -> berita');
    assertEquals('a-b-c', NewsController::news_slugify_title('  A---B___C  '));
});

$runner->addTest('news: news_build_slug appends id and handles non-positive id', function () {
    assertEquals('judul-berita-42', NewsController::news_build_slug('Judul Berita', 42));
    assertEquals('judul-berita-0', NewsController::news_build_slug('Judul Berita', 0), 'id<=0 -> suffix -0');
});

$runner->addTest('news: news_extract_id_from_slug parses trailing id', function () {
    assertEquals(42, NewsController::news_extract_id_from_slug('judul-berita-42'));
    assertEquals(7, NewsController::news_extract_id_from_slug('x-7'));
    assertNull(NewsController::news_extract_id_from_slug('no-id-here'), 'no trailing digits -> null');
    assertNull(NewsController::news_extract_id_from_slug(''), 'empty -> null');
    assertNull(NewsController::news_extract_id_from_slug('abc-0'), 'zero id -> null');
});

$runner->addTest('news: slug build/extract round-trips', function () {
    $slug = NewsController::news_build_slug('Peringatan Disiplin!', 123);
    assertEquals(123, NewsController::news_extract_id_from_slug($slug));
});

/* ------------------------------------------------------------------ */
/* 2. DB-backed CRUD                                                    */
/* ------------------------------------------------------------------ */

$newsDb = static function (): ?PDO {
    $c = $GLOBALS['connect'] ?? null;
    return $c instanceof PDO ? $c : null;
};

$runner->addTest('news: store + read + update + delete round-trip (cleanup guaranteed)', function () use ($newsDb) {
    $c = $newsDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $idAdmin = (int) $c->query('SELECT id_admin FROM ADMIN LIMIT 1')->fetchColumn();
    if ($idAdmin <= 0) {
        echo "\n       (skipped: no seed admin)";
        return;
    }

    $ctrl = new NewsController($c);
    $marker = 'ZZNEWS' . bin2hex(random_bytes(4));
    $createdId = 0;
    try {
        $store = $ctrl->store('', $marker, '<p>konten uji</p>', $idAdmin);
        assertEquals('success', $store['status'] ?? null, 'store must succeed: ' . ($store['message'] ?? ''));

        $row = $c->prepare('SELECT id_news FROM NEWS WHERE judul = ? LIMIT 1');
        $row->execute([$marker]);
        $createdId = (int) $row->fetchColumn();
        assertTrue($createdId > 0, 'row must exist');

        // getNewsById
        $byId = $ctrl->getNewsById($createdId);
        assertTrue(is_array($byId) && !empty($byId), 'getNewsById returns the row');

        // ReadNews + getLatestNewsExcluding
        assertTrue(is_array($ctrl->ReadNews()));
        assertTrue(is_array($ctrl->getLatestNewsExcluding($createdId, 5)));

        // update
        $upd = $ctrl->update($createdId, $marker . '-u', '<p>baru</p>', null);
        assertEquals('success', $upd['status'] ?? null, 'update must succeed: ' . ($upd['message'] ?? ''));

        // delete
        $del = $ctrl->delete($createdId);
        assertEquals('success', $del['status'] ?? null, 'delete must succeed: ' . ($del['message'] ?? ''));
        $createdId = 0; // deleted already
    } finally {
        if ($createdId > 0) {
            $c->prepare('DELETE FROM NEWS WHERE id_news = ?')->execute([$createdId]);
        }
        $c->prepare('DELETE FROM NEWS WHERE judul = ?')->execute([$marker]);
        $c->prepare('DELETE FROM NEWS WHERE judul = ?')->execute([$marker . '-u']);
    }
});

$runner->addTest('news: update/delete on unknown id do not throw', function () use ($newsDb) {
    $c = $newsDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $ctrl = new NewsController($c);
    $u = $ctrl->update(99999999, 'zz-no-such', '<p>x</p>', null);
    assertTrue(is_array($u), 'update returns array for unknown id');
    $d = $ctrl->delete(99999999);
    assertTrue(is_array($d), 'delete returns array for unknown id');
});

$runner->addTest('news: store sanitizes dangerous HTML in content', function () use ($newsDb) {
    $c = $newsDb();
    if ($c === null) {
        echo "\n       (skipped: DB unavailable)";
        return;
    }
    $idAdmin = (int) $c->query('SELECT id_admin FROM ADMIN LIMIT 1')->fetchColumn();
    if ($idAdmin <= 0) {
        echo "\n       (skipped: no seed admin)";
        return;
    }

    $ctrl = new NewsController($c);
    $marker = 'ZZNEWSXSS' . bin2hex(random_bytes(3));
    $payload = '<p>ok</p><script>zzx()</script><img src=x onerror="zzy()">';
    $createdId = 0;
    try {
        $store = $ctrl->store('', $marker, $payload, $idAdmin);
        assertEquals('success', $store['status'] ?? null, 'store must succeed');
        $row = $c->prepare('SELECT id_news, konten FROM NEWS WHERE judul = ? LIMIT 1');
        $row->execute([$marker]);
        $saved = $row->fetch(PDO::FETCH_ASSOC);
        $createdId = (int) ($saved['id_news'] ?? 0);
        assertTrue($createdId > 0, 'row must exist');
        $konten = (string) $saved['konten'];
        foreach (['<script', '</script', '<img', 'onerror'] as $bad) {
            assertTrue(stripos($konten, $bad) === false, "sanitizer left {$bad} in stored content");
        }
    } finally {
        if ($createdId > 0) {
            $c->prepare('DELETE FROM NEWS WHERE id_news = ?')->execute([$createdId]);
        }
        $c->prepare('DELETE FROM NEWS WHERE judul = ?')->execute([$marker]);
    }
});
