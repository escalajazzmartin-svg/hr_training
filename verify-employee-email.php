<?php

declare(strict_types=1);

if (!function_exists('write_app_log')) {
    function write_app_log(string $message): void
    {
        $logFile = __DIR__ . '/employee_registration_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        error_log("[{$timestamp}] {$message}" . PHP_EOL, 3, $logFile);
    }
}

require_once __DIR__ . '/includes/page_bootstrap.php';

function verification_page(string $title, string $message, bool $success = false): never
{
    http_response_code($success ? 200 : 400);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
        . '<link rel="stylesheet" href="assets/style.css"></head>'
        . '<body><main class="login-panel"><section class="login-box">'
        . '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
        . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><a href="index.php">Go to login</a></p>'
        . '</section></main></body></html>';
    exit;
}

$token = trim((string)($_GET['token'] ?? ''));
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    verification_page('Invalid verification link', 'Please check the link or ask the HR administrator to send a new verification email.');
}

$stmt = $pdo->prepare(
    'SELECT id, email, payload_json FROM pending_employee_verifications
     WHERE token_hash = ? AND expires_at > NOW() LIMIT 1'
);
$stmt->execute([hash('sha256', $token)]);
$pending = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pending) {
    verification_page('Link expired or already used', 'This verification link is invalid, expired, or already used. Ask the HR administrator to start the email verification again.');
}

try {
    $data = json_decode((string)$pending['payload_json'], true, 512, JSON_THROW_ON_ERROR);
    $email = strtolower((string)$pending['email']);
    if (!is_array($data) || strtolower((string)($data['email'] ?? '')) !== $email) {
        throw new RuntimeException('The pending registration data is invalid.');
    }

    $pdo->beginTransaction();

    $check = $pdo->prepare('SELECT id FROM employees WHERE LOWER(email) = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetchColumn()) {
        throw new RuntimeException('An employee with this email already exists. Contact the HR administrator.');
    }

    // Assign a unique sequence only after the email is verified.
    // employee = faculty member (FAC); trainer = TRN.
    $rolePrefix = match ((string)($data['account_role'] ?? 'employee')) {
        'employee' => 'FAC',
        'trainer' => 'TRN',
        default => throw new RuntimeException('The selected account role is invalid.'),
    };
    $registrationYear = (int)date('Y');
    $counterInsert = $pdo->prepare(
        'INSERT IGNORE INTO employee_code_counters (role_prefix, registration_year, last_number) VALUES (?, ?, 0)'
    );
    $counterInsert->execute([$rolePrefix, $registrationYear]);
    $counterStmt = $pdo->prepare(
        'SELECT last_number FROM employee_code_counters WHERE role_prefix = ? AND registration_year = ? FOR UPDATE'
    );
    $counterStmt->execute([$rolePrefix, $registrationYear]);
    $lastNumber = (int)$counterStmt->fetchColumn();

    // Seed a new/unused counter from existing IDs so legacy records are not duplicated.
    if ($lastNumber === 0) {
        $like = $rolePrefix . '-' . $registrationYear . '-%';
        $maxStmt = $pdo->prepare(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(employee_code, '-', -1) AS UNSIGNED)), 0) FROM employees WHERE employee_code LIKE ?"
        );
        $maxStmt->execute([$like]);
        $lastNumber = (int)$maxStmt->fetchColumn();
    }
    $sequenceNumber = $lastNumber + 1;
    $employeeCode = sprintf('%s-%d-%03d', $rolePrefix, $registrationYear, $sequenceNumber);
    $pdo->prepare(
        'UPDATE employee_code_counters SET last_number = ? WHERE role_prefix = ? AND registration_year = ?'
    )->execute([$sequenceNumber, $rolePrefix, $registrationYear]);

    $check = $pdo->prepare('SELECT id FROM employees WHERE employee_code = ? LIMIT 1');
    $check->execute([$employeeCode]);
    if ($check->fetchColumn()) {
        throw new RuntimeException('The generated employee ID already exists. Contact the HR administrator.');
    }
    $check = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetchColumn()) {
        throw new RuntimeException('An account with this email already exists. Contact the HR administrator.');
    }

    $pdo->prepare(
        'INSERT INTO employees
         (employee_code, first_name, last_name, email, phone, department, job_title, specialization, hire_date, employment_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $employeeCode,
        $data['first_name'],
        $data['last_name'],
        $email,
        $data['phone'] ?? null,
        $data['department'],
        $data['job_title'],
        $data['specialization'],
        $data['hire_date'] ?? null,
        $data['employment_status'] ?? 'Active'
    ]);
    $employeeId = (int)$pdo->lastInsertId();

    $initialPassword = $employeeCode;
    $pdo->prepare(
        'INSERT INTO users (employee_id, email, password_hash, role, first_name, last_name, active)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    )->execute([
        $employeeId,
        $email,
        password_hash($initialPassword, PASSWORD_DEFAULT),
        $data['account_role'],
        $data['first_name'],
        $data['last_name']
    ]);

    $pdo->prepare('DELETE FROM pending_employee_verifications WHERE id = ?')->execute([(int)$pending['id']]);
    $pdo->commit();

    $mailConfig = require __DIR__ . '/includes/mail_config.php';
    require_once __DIR__ . '/vendor/autoload.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
    $mail->Debugoutput = static function (string $str, int $level): void {
        write_app_log('PHPMailer [' . $level . ']: ' . trim($str));
    };
    $mail->Host = (string)($mailConfig['host'] ?? '');
    $mail->SMTPAuth = true;
    $mail->Username = (string)($mailConfig['username'] ?? '');
    $mail->Password = (string)($mailConfig['password'] ?? '');
    $mail->Port = (int)($mailConfig['port'] ?? 587);
    $mail->SMTPSecure = strtolower((string)($mailConfig['encryption'] ?? 'tls')) === 'ssl'
        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom((string)($mailConfig['from_email'] ?? $mailConfig['username']), (string)($mailConfig['from_name'] ?? 'HR Training System'));
    $mail->addAddress($email, (string)$data['first_name'] . ' ' . (string)$data['last_name']);
    $mail->isHTML(true);
    $mail->Subject = 'Your HR Training System login credentials';
    $safeName = htmlspecialchars((string)$data['first_name'], ENT_QUOTES, 'UTF-8');
    $mail->Body = '<p>Greetings ' . $safeName . ',</p>'
        . '<p>Welcome to the HR Training System.</p>'
        . '<p>Your account has been created successfully.</p>'
        . '<p><strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Initial password:</strong> ' . htmlspecialchars($initialPassword, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Faculty ID:</strong> ' . htmlspecialchars($initialPassword, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Role:</strong> ' . htmlspecialchars((string) ($data['account_role'] === 'employee' ? 'Faculty Member' : 'Trainer'), ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>Use the password above to sign in. We recommend changing it after your first login.</p>';
    $mail->AltBody = "Greetings {$data['first_name']},\n\nWelcome to the HR Training System.\n\nYour account has been created successfully.\n\nEmail: {$email}\nInitial password: {$initialPassword}\nFaculty ID: {$initialPassword}\nRole: " . ($data['account_role'] === 'employee' ? 'Faculty Member' : 'Trainer') . "\n\nUse the password above to sign in. We recommend changing it after your first login.";
    $mail->send();

    verification_page('Email verified', 'Your email address is verified. The employee credentials email has been sent.', true);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    write_app_log('Employee email verification failed: ' . $e->getMessage() . ' | request_uri=' . ($_SERVER['REQUEST_URI'] ?? ''));
    verification_page('Could not complete registration', 'The system could not finish creating the account or sending the password setup email. Please contact the HR administrator.');
}
