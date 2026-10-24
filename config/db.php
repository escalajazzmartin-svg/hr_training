<?php

declare(strict_types=1);

$host = 'localhost';
$dbname = 'hr_training_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Database setup needed</title><link rel="stylesheet" href="assets/style.css"><main class="login-panel"><section class="login-box"><span class="eyebrow">Initial setup</span><h2>Database connection failed</h2><p class="muted">Import <strong>database.sql</strong> using phpMyAdmin, then check the database username and password in <code>config/db.php</code>.</p><p class="small-muted">Technical detail: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p></section></main></html>';
    exit;
}
