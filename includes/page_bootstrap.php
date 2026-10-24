<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/render_helpers.php';
require_once __DIR__ . '/layout.php';

process_post_request($pdo);
$user = current_user();
if (!$user) {
    header('Location: ' . app_base_url() . '/index.php');
    exit;
}
if (isset($requiredRole) && $user['role'] !== $requiredRole) {
    redirect('Dashboard');
}
$flash = take_flash();
if (isset($_GET['export']) && $_GET['export'] === 'training') {
    handle_training_export($pdo, $user);
}
