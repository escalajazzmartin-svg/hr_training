<?php

declare(strict_types=1);
$requiredRole = 'employee';
require_once __DIR__ . '/../includes/page_bootstrap.php';

header('Location: ' . app_base_url() . '/employee/recommended-training.php');
exit;
