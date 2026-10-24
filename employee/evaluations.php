<?php
declare(strict_types=1);
$requiredRole = 'employee';
require_once __DIR__ . '/../includes/page_bootstrap.php';
require_once __DIR__ . '/../includes/renderers/assessments.php';

$page = 'My Assessments';
$content = render_assessments($pdo, $user);
render_app($pdo, $user, $page, $flash, $content);
