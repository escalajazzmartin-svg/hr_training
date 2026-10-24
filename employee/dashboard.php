<?php

declare(strict_types=1);
$requiredRole = 'employee';
require_once __DIR__ . '/../includes/page_bootstrap.php';
require_once __DIR__ . '/../includes/renderers/dashboard.php';
require_once __DIR__ . '/../includes/renderers/recommendations.php';

$page = 'Dashboard';
$content = render_dashboard($pdo, $user);
render_app($pdo, $user, $page, $flash, $content);
