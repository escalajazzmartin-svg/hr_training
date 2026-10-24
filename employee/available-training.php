<?php

declare(strict_types=1);
$requiredRole = 'employee';
require_once __DIR__ . '/../includes/page_bootstrap.php';
require_once __DIR__ . '/../includes/renderers/recommendations.php';

$page = 'Available Training';
$content = page_intro('Available Training', 'Browse upcoming training programs recommended for your professional development.')
    . '<section class="card">' . render_recommendations($pdo, $user) . '</section>';
render_app($pdo, $user, $page, $flash, $content);
