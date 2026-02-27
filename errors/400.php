<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/error_page_helper.php';

app_render_error_page(400, [
    'canonical_path' => '/errors/400.php',
]);
