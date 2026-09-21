<?php

/**
 * Area5XssSuite — regression for the AREA 5 finding.
 *
 * Strix AREA 5 (MEDIUM, CWE-79): the news sanitizer strips inline event handlers
 * with a regex that only matches a handler preceded by whitespace or '/'. A payload
 * that closes a preceding attribute with a quote (<div title="x"onmouseover=...>)
 * bypasses it, and the stored value is emitted raw on the public article page.
 *
 * This suite exercises the sanitizer directly (store path) so a fix is locked in.
 */

require dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/controllers/NewsController.php';

$sanitize = static function (string $html): string {
    $m = new ReflectionMethod(NewsController::class, 'sanitizeNewsContent');
    $m->setAccessible(true);
    return (string) $m->invoke(new NewsController($GLOBALS['connect'] ?? null), $html);
};

$noHandler = static function (string $out): bool {
    return stripos($out, 'onmouseover') === false
        && stripos($out, 'onerror') === false
        && stripos($out, 'onclick') === false
        && stripos($out, 'onfocus') === false
        && stripos($out, 'onload') === false;
};

$runner->addTest('area5: quote-boundary event handler is stripped (CWE-79)', function () use ($sanitize, $noHandler) {
    $out = $sanitize('<div title="x"onmouseover="alert(1)">t</div>');
    assertTrue($noHandler($out), 'handler after a closing quote must be stripped');
});

$runner->addTest('area5: space-preceded handler is stripped (existing behaviour)', function () use ($sanitize, $noHandler) {
    $out = $sanitize('<p onmouseover="alert(1)">t</p>');
    assertTrue($noHandler($out), 'a space-preceded handler must still be stripped');
});

$runner->addTest('area5: autofocus/onfocus variant is stripped', function () use ($sanitize, $noHandler) {
    $out = $sanitize('<p title="a"onfocus="alert(2)" autofocus tabindex="1">t</p>');
    assertTrue($noHandler($out), 'an autofocus/onfocus handler must be stripped');
});

$runner->addTest('area5: script tag and javascript: URI are neutralized', function () use ($sanitize) {
    $a = $sanitize('<script>alert(1)</script>');
    assertTrue(stripos($a, '<script') === false && stripos($a, 'alert(1)') === false, 'script tag removed');

    $b = $sanitize('<a href="javascript:alert(1)">x</a>');
    assertTrue(stripos($b, 'javascript:') === false, 'javascript: URI neutralized');
});

$runner->addTest('area5: benign formatted content is preserved', function () use ($sanitize) {
    $out = $sanitize('<p>Halo <strong>dunia</strong></p>');
    assertStringContains('<strong>dunia</strong>', $out, 'allowed markup must survive sanitization');
});
