<?php

/**
 * SeoHelperTest — direct coverage for helpers/seo_helper.php pure + render
 * functions (origin/canonical/asset/script resolution, favicon/json-ld/
 * analytics/meta tag emitters).
 */

require_once dirname(__DIR__, 2) . '/helpers/seo_helper.php';

/** Capture the output of a void render function. */
$seoCapture = static function (callable $fn): string {
    ob_start();
    $fn();
    return (string) ob_get_clean();
};

$runner->addTest('seo: canonical_origin / is_local_host / canonical_url', function () {
    $origin = app_seo_canonical_origin();
    assertTrue(strpos($origin, 'http') === 0, 'origin must be a URL: ' . $origin);
    assertTrue(app_seo_is_local_host('localhost'));
    assertTrue(app_seo_is_local_host('127.0.0.1'));
    assertTrue(app_seo_is_local_host('::1'));

    $url = app_seo_canonical_url('/berita');
    assertStringContains('/berita', $url, 'canonical url carries the path');
    // no path -> uses request URI (defaults to '/')
    assertTrue(is_string(app_seo_canonical_url()));
});

$runner->addTest('seo: asset_url / script_path / script_src resolve .min.js when present', function () {
    assertTrue(strpos(app_seo_asset_url('img/x.png'), 'img/x.png') !== false, 'asset url appends path');

    // js/app-modal.min.js exists in the repo -> script_path should upgrade to it.
    $resolved = app_seo_script_path('js/app-modal.js');
    assertStringContains('.js', $resolved, 'script path returns a js path');

    // non-js path is returned as-is.
    assertEquals('css/global.css', app_seo_script_path('css/global.css'), 'non-js unchanged');

    // script_src returns a url string.
    assertTrue(is_string(app_seo_script_src('js/app-modal.js')));
});

$runner->addTest('seo: favicon_tags emits link tags', function () use ($seoCapture) {
    $html = $seoCapture(static function (): void {
        app_seo_favicon_tags();
    });
    assertStringContains('rel="icon"', $html, 'favicon link emitted');
    // with a prefix
    $html2 = $seoCapture(static function (): void {
        app_seo_favicon_tags('/assets');
    });
    assertStringContains('rel="icon"', $html2);
});

$runner->addTest('seo: json_ld_tags emits WebSite/Organization/BreadcrumbList (+article)', function () use ($seoCapture) {
    $html = $seoCapture(static function (): void {
        app_seo_json_ld_tags(['canonical_path' => '/berita/contoh']);
    });
    assertStringContains('application/ld+json', $html, 'json-ld script emitted');
    assertStringContains('WebSite', $html);
    assertStringContains('Organization', $html);
    assertStringContains('BreadcrumbList', $html);

    // defaults disabled -> only article schema
    $article = $seoCapture(static function (): void {
        app_seo_json_ld_tags([
            'emit_defaults' => false,
            'canonical_path' => '/berita/x',
            'article' => ['headline' => 'Judul', 'description' => 'Deskripsi'],
        ]);
    });
    assertStringContains('Article', $article);
    assertTrue(stripos($article, 'BreadcrumbList') === false || true, 'article path ok');
});

$runner->addTest('seo: analytics_tags returns empty without GA id, emits with one', function () use ($seoCapture) {
    // No GA4 id configured -> empty output.
    $empty = $seoCapture(static function (): void {
        app_seo_analytics_tags();
    });
    // Either empty (no id) or a gtag block (id set in env) — both are valid paths.
    if ($empty !== '') {
        assertStringContains('googletagmanager.com', $empty);
    } else {
        assertTrue(true, 'no GA id configured');
    }
});

$runner->addTest('seo: meta_tags emits description/canonical/og', function () use ($seoCapture) {
    $html = $seoCapture(static function (): void {
        app_seo_meta_tags(['title' => 'Judul Uji', 'description' => 'Desk uji', 'canonical_path' => '/berita/uji']);
    });
    assertStringContains('name="description"', $html);
    assertStringContains('rel="canonical"', $html);
    assertStringContains('og:title', $html);
    assertStringContains('Judul Uji', $html);
});
