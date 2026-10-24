<?php
declare(strict_types=1);
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/page_bootstrap.php';
require_once __DIR__ . '/../includes/renderers/assessments.php';

$page = 'Assessments & Evaluations';
$content = render_assessments($pdo, $user);
render_app($pdo, $user, $page, $flash, $content);
