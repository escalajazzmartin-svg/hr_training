<?php
declare(strict_types=1);
$requiredRole = 'employee';
require_once __DIR__ . '/../includes/page_bootstrap.php';
require_once __DIR__ . '/../includes/renderers/certificates.php';

$page = 'My Certificates';
$content = render_certificates($pdo, $user);
render_app($pdo, $user, $page, $flash, $content);
