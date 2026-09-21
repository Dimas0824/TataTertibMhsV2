<?php

/**
 * Area5JsonLdSuite — regression for the finding found during the Strix re-run
 * (2026-09-21 re-scan, MEDIUM, CWE-79).
 *
 * The news title is embedded unescaped into <script type="application/ld+json">.
 * A title containing "</script>" closes the element early and the remainder is
 * parsed as HTML, giving stored XSS. The JSON-LD emitter must hex-escape the tag
 * (JSON_HEX_TAG) so "</script>" cannot break out.
 */

require_once dirname(__DIR__, 2) . '/helpers/seo_helper.php';

$captureJsonLd = static function (array $config): string {
    ob_start();
    app_seo_json_ld_tags($config);
    return (string) ob_get_clean();
};

$runner->addTest('area5-jsonld: a title containing </script> cannot break out (CWE-79)', function () use ($captureJsonLd) {
    $payload = 'zzXSS</script><svg onload=alert(document.domain)>';
    $html = $captureJsonLd([
        'article' => [
            'headline' => $payload,
            'description' => 'body',
            'canonical_path' => '/berita?slug=x',
        ],
    ]);

    // The raw break-out sequence must NOT appear inside the emitted JSON-LD.
    assertTrue(
        strpos($html, '</script><svg') === false,
        'a literal </script><svg...> must not survive into the JSON-LD block'
    );

    // And the JSON-LD script element itself must not be terminated early by the
    // payload: exactly the emitter's own closing tag(s), nothing injected.
    $ldMatches = [];
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $ldMatches);
    assertTrue(count($ldMatches[1]) > 0, 'at least one JSON-LD block must be emitted');
    foreach ($ldMatches[1] as $body) {
        assertTrue(stripos($body, '<svg') === false, 'no injected element inside a JSON-LD body');
    }
});

$runner->addTest('area5-jsonld: benign title is preserved and decodes to valid JSON', function () use ($captureJsonLd) {
    $html = $captureJsonLd([
        'article' => [
            'headline' => 'Berita Biasa <strong>OK</strong>',
            'description' => 'body',
            'canonical_path' => '/berita?slug=y',
        ],
    ]);

    $m = [];
    $n = preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    assertTrue($n > 0, 'JSON-LD block present');

    // The Article block is the one carrying the headline (defaults emit WebSite/
    // Organization/BreadcrumbList first, so pick the block that has a headline).
    $article = null;
    foreach ($m[1] as $body) {
        $d = json_decode($body, true);
        if (is_array($d) && array_key_exists('headline', $d)) {
            $article = $d;
            break;
        }
    }

    assertTrue(is_array($article), 'an Article JSON-LD block must remain valid JSON after escaping');
    assertTrue(
        strpos((string) ($article['headline'] ?? ''), 'Berita Biasa') !== false,
        'benign title must survive escaping'
    );
});
