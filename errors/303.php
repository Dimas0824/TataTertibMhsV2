<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/error_page_helper.php';

app_render_error_page(303, [
    'canonical_path' => '/errors/303.php',
]);
