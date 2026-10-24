<?php

declare(strict_types=1);
$requiredRole = 'trainer';
require_once __DIR__ . '/../includes/page_bootstrap.php';
require_once __DIR__ . '/../includes/renderers/programs.php';

$page = 'My Training Programs';
$content = render_programs($pdo, $user);
render_app($pdo, $user, $page, $flash, $content);
