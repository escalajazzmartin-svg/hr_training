<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$_SESSION = [];
session_regenerate_id(true);
flash('You have been signed out.');
header('Location: ' . app_base_url() . '/index.php');
exit;
