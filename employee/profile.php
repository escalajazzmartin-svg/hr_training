<?php

declare(strict_types=1);
$requiredRole = 'employee';
require_once __DIR__ . '/../includes/page_bootstrap.php';
require_once __DIR__ . '/../includes/renderers/profile.php';

$page = 'My Profile';
$content = render_profile($pdo, $user);
render_app($pdo, $user, $page, $flash, $content);
