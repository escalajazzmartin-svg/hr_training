<?php
declare(strict_types=1);
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/page_bootstrap.php';
require_once __DIR__ . '/../includes/renderers/history.php';

$page = 'Training History';
$content = render_history($pdo, $user, false);
render_app($pdo, $user, $page, $flash, $content);
