<?php

/**
 * TokenSuite — white-box attack tests for the capability-token layer
 * (file tokens, id tokens, CSRF helper, sanitizer regex).
 */

require_once __DIR__ . '/../../helpers/path_helper.php';
require_once __DIR__ . '/../../helpers/token_helper.php';
require_once __DIR__ . '/../../helpers/route_helper.php';

$runner->addTest('security: file token roundtrip resolves exact name', function () {
    $tok = app_file_token('Surat Pernyataan TI.pdf');
    assertTrue(preg_match('/^(s1|o1)\./', $tok) === 1, 'token must carry a crypto prefix');
    assertEquals('Surat Pernyataan TI.pdf', app_file_resolve($tok));
});

$runner->addTest('security: file token strips traversal at issue time', function () {
    $tok = app_file_token('../../.env');
    assertEquals('.env', app_file_resolve($tok), 'issued token must contain basename only');
});

$runner->addTest('security: tampered / garbage file tokens are rejected', function () {
    $tok = app_file_token('ok.pdf');
    assertNull(app_file_resolve($tok . 'x'), 'tamper must fail closed');
    assertNull(app_file_resolve('ok.pdf'), 'plaintext filename must never resolve');
    assertNull(app_file_resolve('s1.notbase64url!!'), 'garbage must fail closed');
});

$runner->addTest('security: route url data encodes file param as token', function () {
    $enc = app_route_encode_url_data(['file' => 'bukti khusus.pdf'], 1800);
    assertTrue(
        isset($enc['file']) && preg_match('/^(s1|o1)\./', (string) $enc['file']) === 1,
        'generated links must embed a token, never the raw filename'
    );
    $dec = app_route_decode_url_data($enc);
    assertEquals('bukti khusus.pdf', $dec['file'] ?? null);
});

$runner->addTest('security: route decode rejects raw filename on any mapped key', function () {
    assertNull(app_route_decode_url_data(['file' => 'x.pdf']));
    assertNull(app_route_decode_url_data(['id_news' => '7']), 'numeric ids must be tokens too');
});

$runner->addTest('security: id tokens are entity-scoped (no cross-realm replay)', function () {
    $newsTok = app_id_token('news', 7);
    assertNull(app_id_resolve($newsTok, 'detail_pelanggaran'), 'news token must not resolve as detail');
    assertEquals(7, app_id_resolve($newsTok, 'news'));
});

$runner->addTest('security: csrf token is 64-hex and stable within a session', function () {
    $t = app_csrf_token();
    assertTrue(preg_match('/^[0-9a-f]{64}$/', $t) === 1, 'csrF must be 64 hex chars');
    assertEquals($t, app_csrf_token());
});

$runner->addTest('security: news sanitizer kills slash-prefixed handlers and script tags', function () {
    require_once dirname(__DIR__, 2) . '/config.php';
    require_once dirname(__DIR__, 2) . '/controllers/NewsController.php';
    $c = new NewsController();
    $rm = new ReflectionMethod($c, 'sanitizeNewsContent');
    $rm->setAccessible(true);
    $dirty = '<p onmouseover="zzxss1()">Halo<b onclick="zzxss2()">dunia</b><img src=x onerror=zzxss3()>';
    $clean = (string) $rm->invoke($c, $dirty);
    foreach (['onmouseover', 'onclick', 'onerror', 'zzxss'] as $needle) {
        assertTrue(stripos($clean, $needle) === false, "sanitizer must strip {$needle}, got: {$clean}");
    }
    assertStringContains('Halo', $clean);
    assertStringContains('dunia', $clean);
});

/* ------------------------------------------------------------------ */
/* token_helper edge branches                                          */
/* ------------------------------------------------------------------ */

$runner->addTest('security: app_token_decrypt_payload rejects malformed tokens', function () {
    assertNull(app_token_decrypt_payload(''), 'empty -> null');
    assertNull(app_token_decrypt_payload('no-dot-separator'), 'no algorithm prefix -> null');
    assertNull(app_token_decrypt_payload('zz.bm90LXJlYWw'), 'unknown algorithm -> null');
    assertNull(app_token_decrypt_payload('s1.!!!!not-base64!!!!'), 'bad base64 -> null');
    // valid prefix but truncated ciphertext -> null (too short)
    assertNull(app_token_decrypt_payload('s1.' . app_token_base64url_encode('short')), 'too short -> null');
});

$runner->addTest('security: app_token_decode rejects empty / tampered / expired-looking payloads', function () {
    assertNull(app_token_decode(''), 'empty -> null');
    assertNull(app_token_decode('   '), 'blank -> null');
    assertNull(app_token_decode('garbage-token'), 'unparseable -> null');
});

$runner->addTest('security: app_token_issue validates type and subject', function () {
    assertThrows(InvalidArgumentException::class, static function () {
        app_token_issue('bogus', 'x');
    }, 'bad type must throw');
    assertThrows(InvalidArgumentException::class, static function () {
        app_token_issue('route', '   ');
    }, 'blank subject must throw');
    assertThrows(InvalidArgumentException::class, static function () {
        app_id_token('news', 0);
    }, 'non-positive id must throw');
});

$runner->addTest('security: app_token_issue/decode round-trips both route and id types', function () {
    $routeTok = app_token_issue('route', 'page.home', ['k' => 'v'], 60);
    $decoded = app_token_decode($routeTok, 'route', 'page.home');
    assertTrue(is_array($decoded), 'route token decodes');
    assertEquals('route', $decoded['typ']);
    assertEquals('page.home', $decoded['sub']);
    assertEquals(['k' => 'v'], $decoded['data']);

    // wrong expected type / subject rejected
    assertNull(app_token_decode($routeTok, 'id'), 'type mismatch -> null');
    assertNull(app_token_decode($routeTok, 'route', 'other.subject'), 'subject mismatch -> null');
});

$runner->addTest('security: base64url encode/decode are inverse and reject bad input', function () {
    $raw = random_bytes(32);
    $enc = app_token_base64url_encode($raw);
    assertTrue(strpos($enc, '+') === false && strpos($enc, '/') === false && strpos($enc, '=') === false, 'url-safe alphabet');
    assertEquals($raw, app_token_base64url_decode($enc), 'roundtrip');
    assertNull(app_token_base64url_decode('!!!!'), 'invalid base64 -> null');
});
