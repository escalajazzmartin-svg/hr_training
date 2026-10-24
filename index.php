<?php

declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/actions.php';
require_once __DIR__ . '/includes/render_helpers.php';
require_once __DIR__ . '/includes/login.php';

process_post_request($pdo);
$user = current_user();
if (!$user) {
    $flash = take_flash();
    render_login($flash);
    exit;
}
redirect('Dashboard');
