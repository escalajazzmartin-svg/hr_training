<?php

declare(strict_types=1);
$requiredRole = 'employee';
require_once __DIR__ . '/../includes/page_bootstrap.php';
require_once __DIR__ . '/../includes/renderers/employee-training.php';

$page = 'My Training';
$content = render_employee_training($pdo, $user);
render_app($pdo, $user, $page, $flash, $content);
