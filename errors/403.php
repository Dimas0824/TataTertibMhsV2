<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/error_page_helper.php';

app_render_error_page(403, [
    'canonical_path' => '/errors/403.php',
]);
