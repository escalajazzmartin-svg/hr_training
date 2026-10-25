<?php

echo '<h2>PHP Mail Configuration Check</h2>';

echo 'PHP Version: ' . PHP_VERSION . '<br>';

echo 'OpenSSL loaded: ';
echo extension_loaded('openssl') ? 'YES' : 'NO';

echo '<br>OpenSSL version: ';
echo defined('OPENSSL_VERSION_TEXT')
    ? OPENSSL_VERSION_TEXT
    : 'Unavailable';

echo '<br>PHP error log: ';
echo ini_get('error_log');

echo '<br>Error logging enabled: ';
echo ini_get('log_errors') ? 'YES' : 'NO';

echo '<hr><h3>Error Log Write Test</h3>';

$logFile = __DIR__ . '/employee_registration_errors.log';

$result = error_log(
    '[' . date('Y-m-d H:i:s') . '] Test log entry from check_mail.php'
        . PHP_EOL,
    3,
    $logFile
);

echo 'Log path: ' . htmlspecialchars($logFile) . '<br>';
echo 'Write result: ' . ($result ? 'SUCCESS' : 'FAILED') . '<br>';
echo 'File exists: ' . (file_exists($logFile) ? 'YES' : 'NO');