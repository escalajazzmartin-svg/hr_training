<?php

declare(strict_types=1);

function process_post_request(PDO $pdo): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $action = post_string('action');

    if ($action === 'login') {
        verify_csrf();

        $email = strtolower(post_string('email'));
        $password = (string)($_POST['password'] ?? '');

        $stmt = $pdo->prepare(
            'SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            flash('Invalid email or password. Please try again.', 'error');
            header('Location: ' . app_base_url() . '/index.php');
            exit;
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'employee_id' => $user['employee_id'] ? (int)$user['employee_id'] : null,
            'email' => $user['email'],
            'role' => $user['role'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name']
        ];

        csrf_token();
        redirect('Dashboard');
    }

    if ($action === 'add_employee') {
        // The admin employee registration page handles this route with email verification.
        // Do not run the legacy direct-create employee flow here, as it conflicts with
        // the verified registration sequence and requires different fields.
        return;
    }

    verify_csrf();

    if ($action === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('You have been signed out.');
        header('Location: ' . app_base_url() . '/index.php');
        exit;
    }

    $user = require_login();

    try {
        switch ($action) {
            case 'add_employee':
                signed_in_user($pdo, 'admin');

                $first = post_string('first_name');
                $last = post_string('last_name');
                $email = strtolower(post_string('email'));
                $code = strtoupper(post_string('employee_code'));
                $dept = post_string('department');
                $job = post_string('job_title');
                $spec = post_string('specialization');
                $role = post_string('account_role');
                $password = (string)($_POST['initial_password'] ?? '');
                $confirmPassword = (string)($_POST['confirm_password'] ?? '');

                if (
                    !$first ||
                    !$last ||
                    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                    !$code ||
                    !$dept ||
                    !$job ||
                    !$spec
                ) {
                    throw new RuntimeException(
                        'Please complete all required employee fields using a valid email address.'
                    );
                }

                if (!in_array($role, ['employee', 'trainer'], true)) {
                    throw new RuntimeException('Choose a valid account type.');
                }

                if (strlen($password) < 8) {
                    throw new RuntimeException(
                        'The initial password must contain at least 8 characters.'
                    );
                }

                if ($password !== $confirmPassword) {
                    throw new RuntimeException('The passwords do not match.');
                }

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO employees
                    (employee_code, first_name, last_name, email, phone, department, job_title, specialization, hire_date, employment_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $code,
                    $first,
                    $last,
                    $email,
                    post_string('phone') ?: null,
                    $dept,
                    $job,
                    $spec,
                    post_string('hire_date') ?: null,
                    post_string('employment_status', 'Active')
                ]);

                $employeeId = (int)$pdo->lastInsertId();

                $stmt = $pdo->prepare(
                    'INSERT INTO users
                    (employee_id, email, password_hash, role, first_name, last_name)
                    VALUES (?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $employeeId,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    $role,
                    $first,
                    $last
                ]);

                $pdo->commit();

                flash(
                    $role === 'trainer'
                        ? 'Trainer account created successfully.'
                        : 'Faculty Member account created successfully.'
                );

                redirect('Employees');

            case 'update_employee':
                signed_in_user($pdo, 'admin');

                $id = (int)($_POST['employee_id'] ?? 0);
                $email = strtolower(post_string('email'));

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid email address.');
                }

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'UPDATE employees
                    SET first_name = ?, last_name = ?, email = ?, phone = ?, department = ?, job_title = ?, specialization = ?, employment_status = ?
                    WHERE id = ?'
                );

                $stmt->execute([
                    post_string('first_name'),
                    post_string('last_name'),
                    $email,
                    post_string('phone') ?: null,
                    post_string('department'),
                    post_string('job_title'),
                    post_string('specialization'),
                    post_string('employment_status', 'Active'),
                    $id
                ]);

                $stmt = $pdo->prepare(
                    'UPDATE users
                    SET email = ?, first_name = ?, last_name = ?
                    WHERE employee_id = ?'
                );

                $stmt->execute([
                    $email,
                    post_string('first_name'),
                    post_string('last_name'),
                    $id
                ]);

                $pdo->commit();

                flash('Employee details updated.');
                redirect('Employees');

            case 'delete_employee':
                signed_in_user($pdo, 'admin');

                $id = (int)($_POST['employee_id'] ?? 0);

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'DELETE FROM users WHERE employee_id = ?'
                );
                $stmt->execute([$id]);

                $stmt = $pdo->prepare(
                    'DELETE FROM employees WHERE id = ?'
                );
                $stmt->execute([$id]);

                $pdo->commit();

                flash('Employee record deleted.');
                redirect('Employees');

            case 'add_program':
                signed_in_user($pdo, 'admin');

                $title = post_string('title');
                $category = post_string('category');
                $spec = post_string('specialization');
                $start = post_string('start_date');

                if (!$title || !$category || !$spec || !$start) {
                    throw new RuntimeException(
                        'Enter the title, category, specialization, and start date.'
                    );
                }

                $trainer = post_string('trainer_user_id');

                $stmt = $pdo->prepare(
                    'INSERT INTO training_programs
                    (title, category, specialization, description, trainer_user_id, start_date, end_date, start_time, venue, capacity, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $title,
                    $category,
                    $spec,
                    post_string('description') ?: null,
                    $trainer !== '' ? (int)$trainer : null,
                    $start,
                    post_string('end_date') ?: null,
                    post_string('start_time') ?: null,
                    post_string('venue') ?: null,
                    max(1, (int)post_string('capacity', '30')),
                    post_string('status', 'Upcoming'),
                    $user['id']
                ]);

                flash('Training program created.');
                redirect('Training Programs');

            case 'register_training':
                $programId = (int)($_POST['program_id'] ?? 0);

                if ($user['role'] === 'employee') {
                    $employeeId = (int)$user['employee_id'];
                } else {
                    signed_in_user($pdo, 'admin');
                    $employeeId = (int)($_POST['employee_id'] ?? 0);
                }

                if (!$employeeId || !$programId) {
                    throw new RuntimeException(
                        'Choose an employee and training program.'
                    );
                }

                $check = $pdo->prepare(
                    'SELECT
                        p.capacity,
                        p.status,
                        (SELECT COUNT(*)
                            FROM registrations r
                            WHERE r.program_id = p.id
                            AND r.status <> \'Withdrawn\') AS seats,
                        (SELECT status
                            FROM registrations
                            WHERE employee_id = ?
                            AND program_id = p.id
                            LIMIT 1) AS existing_status
                    FROM training_programs p
                    WHERE p.id = ?'
                );

                $check->execute([$employeeId, $programId]);
                $program = $check->fetch();

                if (!$program) {
                    throw new RuntimeException(
                        'That training program does not exist.'
                    );
                }

                if (!in_array($program['status'], ['Upcoming', 'Ongoing'], true)) {
                    throw new RuntimeException(
                        'Registration is closed for this training program.'
                    );
                }

                if (
                    $program['existing_status'] &&
                    $program['existing_status'] !== 'Withdrawn'
                ) {
                    flash('This employee is already registered for that program.');
                    redirect(
                        $user['role'] === 'employee'
                            ? 'My Training'
                            : 'Employee Registration'
                    );
                }

                if ((int)$program['seats'] >= (int)$program['capacity']) {
                    throw new RuntimeException(
                        'This training program has reached its registration capacity.'
                    );
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO registrations (employee_id, program_id, status)
                    VALUES (?, ?, \'Registered\')
                    ON DUPLICATE KEY UPDATE
                    status = IF(status = \'Withdrawn\', \'Registered\', status)'
                );

                $stmt->execute([$employeeId, $programId]);

                flash('Training registration saved.');
                redirect(
                    $user['role'] === 'employee'
                        ? 'My Training'
                        : 'Employee Registration'
                );

            case 'mark_attendance':
                if (!in_array($user['role'], ['admin', 'trainer'], true)) {
                    http_response_code(403);
                    exit('Permission denied.');
                }

                foreach (($_POST['attendance_status'] ?? []) as $registrationId => $status) {
                    if (!in_array($status, ['Present', 'Late', 'Absent', 'Excused'], true)) {
                        continue;
                    }

                    if ($user['role'] === 'trainer') {
                        $check = $pdo->prepare(
                            'SELECT COUNT(*)
                            FROM registrations r
                            JOIN training_programs p ON p.id = r.program_id
                            WHERE r.id = ? AND p.trainer_user_id = ?'
                        );

                        $check->execute([
                            (int)$registrationId,
                            $user['id']
                        ]);

                        if (!(int)$check->fetchColumn()) {
                            continue;
                        }
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO attendance
                        (registration_id, attendance_status, remarks, marked_by)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        attendance_status = VALUES(attendance_status),
                        remarks = VALUES(remarks),
                        marked_by = VALUES(marked_by)'
                    );

                    $stmt->execute([
                        (int)$registrationId,
                        $status,
                        trim((string)($_POST['remarks'][$registrationId] ?? '')) ?: null,
                        $user['id']
                    ]);
                }

                flash('Attendance records saved.');
                redirect('Attendance Monitoring');

            case 'save_competency':
                signed_in_user($pdo, 'admin');

                $emp = (int)($_POST['employee_id'] ?? 0);
                $comp = (int)($_POST['competency_id'] ?? 0);
                $score = max(0, min(100, (int)($_POST['current_score'] ?? 0)));
                $target = max(1, min(100, (int)($_POST['target_score'] ?? 80)));

                $stmt = $pdo->prepare(
                    'INSERT INTO employee_competencies
                    (employee_id, competency_id, current_score, target_score, last_assessed, notes)
                    VALUES (?, ?, ?, ?, CURDATE(), ?)
                    ON DUPLICATE KEY UPDATE
                    current_score = VALUES(current_score),
                    target_score = VALUES(target_score),
                    last_assessed = CURDATE(),
                    notes = VALUES(notes)'
                );

                $stmt->execute([
                    $emp,
                    $comp,
                    $score,
                    $target,
                    post_string('notes') ?: null
                ]);

                flash('Competency assessment saved.');
                redirect('Competency Management');

            case 'save_evaluation':
                if (!in_array($user['role'], ['admin', 'trainer'], true)) {
                    http_response_code(403);
                    exit('Permission denied.');
                }

                $reg = (int)($_POST['registration_id'] ?? 0);
                $score = max(0, min(100, (float)($_POST['score'] ?? 0)));

                if ($user['role'] === 'trainer') {
                    $check = $pdo->prepare(
                        'SELECT COUNT(*)
                        FROM registrations r
                        JOIN training_programs p ON p.id = r.program_id
                        WHERE r.id = ? AND p.trainer_user_id = ?'
                    );

                    $check->execute([$reg, $user['id']]);

                    if (!(int)$check->fetchColumn()) {
                        throw new RuntimeException(
                            'This registration is not part of your assigned training programs.'
                        );
                    }
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO evaluations
                    (registration_id, score, feedback, assessed_by)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    score = VALUES(score),
                    feedback = VALUES(feedback),
                    assessed_by = VALUES(assessed_by),
                    assessed_at = CURRENT_TIMESTAMP'
                );

                $stmt->execute([
                    $reg,
                    $score,
                    post_string('feedback') ?: null,
                    $user['id']
                ]);

                if ($score >= 75) {
                    $stmt = $pdo->prepare(
                        "UPDATE registrations SET status = 'Completed' WHERE id = ?"
                    );
                    $stmt->execute([$reg]);
                }

                flash('Assessment saved.');
                redirect(
                    $user['role'] === 'trainer'
                        ? 'Training Results'
                        : 'Assessments & Evaluations'
                );

            case 'issue_certificate':
                signed_in_user($pdo, 'admin');

                $reg = (int)($_POST['registration_id'] ?? 0);

                $stmt = $pdo->prepare(
                    "SELECT r.id
                    FROM registrations r
                    JOIN evaluations e ON e.registration_id = r.id
                    WHERE r.id = ?
                    AND e.score >= 75
                    AND r.status = 'Completed'"
                );

                $stmt->execute([$reg]);

                if (!$stmt->fetch()) {
                    throw new RuntimeException(
                        'A certificate can only be issued after completion and a passing evaluation (75 or above).'
                    );
                }

                $code = 'WIS-TRN-' . date('Y') . '-' . str_pad(
                    (string)$reg,
                    5,
                    '0',
                    STR_PAD_LEFT
                );

                $stmt = $pdo->prepare(
                    'INSERT INTO certificates
                    (registration_id, certificate_code, issued_at, approved_by)
                    VALUES (?, ?, CURDATE(), ?)
                    ON DUPLICATE KEY UPDATE
                    issued_at = VALUES(issued_at),
                    approved_by = VALUES(approved_by)'
                );

                $stmt->execute([$reg, $code, $user['id']]);

                flash('Certificate issued.');
                redirect('Certificates');

            case 'send_certificate_email':
                signed_in_user($pdo, 'admin');

                $reg = (int)($_POST['registration_id'] ?? 0);
                if ($reg < 1) {
                    throw new RuntimeException('Choose a valid certificate to send.');
                }

                // Only send certificates that have been issued for a completed,
                // passing training registration.
                $stmt = $pdo->prepare(
                    "SELECT
                        r.id AS registration_id,
                        e.first_name,
                        e.last_name,
                        e.employee_code,
                        e.email AS employee_email,
                        p.title AS program_title,
                        ev.score,
                        c.certificate_code,
                        c.issued_at
                    FROM registrations r
                    JOIN employees e ON e.id = r.employee_id
                    JOIN training_programs p ON p.id = r.program_id
                    JOIN evaluations ev ON ev.registration_id = r.id
                    JOIN certificates c ON c.registration_id = r.id
                    WHERE r.id = ?
                      AND r.status = 'Completed'
                      AND ev.score >= 75
                    LIMIT 1"
                );
                $stmt->execute([$reg]);
                $certificate = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$certificate) {
                    throw new RuntimeException(
                        'No issued certificate was found for this completed training registration.'
                    );
                }

                $recipient = trim((string)($certificate['employee_email'] ?? ''));
                if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException(
                        'The employee does not have a valid email address. Update the employee record first.'
                    );
                }

                $fullName = trim(
                    (string)$certificate['first_name'] . ' ' .
                        (string)$certificate['last_name']
                );
                $programTitle = (string)$certificate['program_title'];
                $certificateCode = (string)$certificate['certificate_code'];
                $score = number_format((float)$certificate['score'], 1);
                $issuedAt = !empty($certificate['issued_at'])
                    ? date('F j, Y', strtotime((string)$certificate['issued_at']))
                    : date('F j, Y');

                $escape = static function ($value): string {
                    return htmlspecialchars(
                        (string)$value,
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    );
                };

                // Create a portable HTML certificate attachment. This does not
                // claim to be a PDF; the recipient can open and print the HTML.
                $certificateHtml = $html = '
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Certificate of Completion - ' . $escape($fullName) . '</title>
<style>
@page { size: A4 landscape; margin: 0; }
* { box-sizing: border-box; }

html, body {
    margin: 0;
    padding: 0;
    width: 297mm;
    height: 210mm;
    font-family: Georgia, "Times New Roman", serif;
    color: #262626;
    background: #ffffff;
}

body {
    position: relative;
}

.certificate {
    position: absolute;

    margin: 6mm 0 0 5mm;
    width: 260mm;
    height: 170mm;
    padding: 12mm;

    border: 1.2mm double #8B1E1E;
    text-align: center;
    overflow: hidden;
}

.certificate::before {
    content: "";
    position: absolute;
    left: 3mm;
    top: 3mm;
    right: 3mm;
    bottom: 3mm;
    border: 0.45mm solid #F2C300;
    pointer-events: none;
}

.organization {
    margin: 1mm 0 0;
    color: #8B1E1E;
    font-family: Arial, sans-serif;
    font-size: 10pt;
    font-weight: bold;
    letter-spacing: 2.5pt;
    text-transform: uppercase;
}

h1 {
    margin: 7mm 0 4mm;
    color: #8B1E1E;
    font-size: 25pt;
    line-height: 1.15;
    letter-spacing: 1.3pt;
    text-transform: uppercase;
}

.intro {
    margin: 0;
    font-size: 12pt;
}

.recipient {
    width: 82%;
    margin: 4mm auto;
    padding: 0 0 2.5mm;
    border-bottom: 0.3mm solid #999999;
    font-size: 23pt;
    font-weight: bold;
    overflow-wrap: anywhere;
}

.description {
    margin: 3.5mm auto 0;
    font-size: 12pt;
    line-height: 1.35;
}

.program {
    margin: 3mm 0 0;
    color: #8B1E1E;
    font-size: 19pt;
    font-weight: bold;
    overflow-wrap: anywhere;
}

.score {
    margin: 3mm 0 0;
    font-size: 11pt;
}

.details {
    width: 78%;
    margin: 8mm auto 0;
    border-collapse: collapse;
    table-layout: fixed;
    font-family: Arial, sans-serif;
    font-size: 9pt;
}

.details td {
    width: 50%;
    padding: 0 8mm;
    text-align: center;
    vertical-align: top;
}

.label {
    display: block;
    margin-bottom: 1.5mm;
    color: #666666;
    font-size: 8pt;
    letter-spacing: 1pt;
    text-transform: uppercase;
}

.value {
    display: block;
    font-size: 9pt;
    font-weight: bold;
    overflow-wrap: anywhere;
}

.code {
    margin-top: 6mm;
    font-family: Arial, sans-serif;
    font-size: 9pt;
    overflow-wrap: anywhere;
}

/* Shift all certificate content downward slightly */
.certificate > .organization,
.certificate > h1,
.certificate > .intro,
.certificate > .recipient,
.certificate > .description,
.certificate > .program,
.certificate > .score,
.certificate > .details,
.certificate > .code {
    position: relative;
    top: 30mm;
}

@media print {
    html, body {
        width: 297mm;
        height: 210mm;
        margin: 0;
        padding: 0;
        overflow: hidden;
        background: #ffffff;
    }

    .certificate {
        position: absolute;
        left: 10mm;
        top: 10mm;
        width: 277mm;
        height: 190mm;
        margin: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
</style>
</head>
<body>
<main class="certificate">

<div class="organization">WIS HR Training and Development</div>

<h1>Certificate of Completion</h1>

<p class="intro">This certificate is proudly presented to</p>

<div class="recipient">' . $escape($fullName) . '</div>

<p class="description">
In recognition of successfully completing the training program
</p>

<div class="program">' . $escape($programTitle) . '</div>

<p class="score">
with an assessment score of <strong>' . $escape($score) . '%</strong>.
</p>

<table class="details" cellspacing="0" cellpadding="0">
<tr>
<td>
<span class="label">Employee ID</span>
<span class="value">' . $escape($certificate['employee_code']) . '</span>
</td>
<td>
<span class="label">Date Issued</span>
<span class="value">' . $escape($issuedAt) . '</span>
</td>
</tr>
</table>

<div class="code">
Certificate Code: <strong>' . $escape($certificateCode) . '</strong>
</div>

</main>
</body>
</html>';

                // Send through Gmail SMTP using PHPMailer.
                $autoloadPath = __DIR__ . '/../vendor/autoload.php';
                $mailConfigPath = __DIR__ . '/mail_config.php';

                if (!is_file($autoloadPath)) {
                    throw new RuntimeException(
                        'PHPMailer is not installed. Run composer require phpmailer/phpmailer in the project root.'
                    );
                }

                if (!is_file($mailConfigPath)) {
                    throw new RuntimeException(
                        'Mail configuration was not found at includes/mail_config.php.'
                    );
                }

                require_once $autoloadPath;
                $mailConfig = require $mailConfigPath;

                if (!is_array($mailConfig)) {
                    throw new RuntimeException('The mail configuration is invalid.');
                }

                $smtpHost = trim((string)($mailConfig['host'] ?? ''));
                $smtpUsername = trim((string)($mailConfig['username'] ?? ''));
                $smtpPassword = trim((string)($mailConfig['password'] ?? ''));
                $fromAddress = trim((string)($mailConfig['from_email'] ?? ''));
                $fromName = trim((string)($mailConfig['from_name'] ?? 'HR Training System'));
                $smtpPort = (int)($mailConfig['port'] ?? 587);
                $encryption = strtolower(trim((string)($mailConfig['encryption'] ?? 'tls')));

                if (
                    $smtpHost === '' ||
                    !filter_var($smtpUsername, FILTER_VALIDATE_EMAIL) ||
                    $smtpPassword === '' ||
                    !filter_var($fromAddress, FILTER_VALIDATE_EMAIL) ||
                    !in_array($encryption, ['tls', 'ssl', ''], true) ||
                    $smtpPort < 1 ||
                    $smtpPort > 65535
                ) {
                    throw new RuntimeException(
                        'Mail configuration is incomplete. Check host, port, encryption, username, password, and from_email in includes/mail_config.php.'
                    );
                }

                if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
                    throw new RuntimeException(
                        'PHPMailer could not be loaded. Run composer require phpmailer/phpmailer in the project root.'
                    );
                }

                $subject = 'Your Certificate of Completion - ' . $programTitle;
                $bodyHtml = '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:24px;background:#f5f5f5;font-family:Arial,sans-serif;color:#262626">
  <div style="max-width:640px;margin:0 auto;padding:28px;background:#ffffff;border-top:5px solid #8B1E1E">
    <h2 style="margin-top:0;color:#8B1E1E">Congratulations, ' . $escape($fullName) . '!</h2>
    <p>You have successfully completed the training program <strong>' . $escape($programTitle) . '</strong>.</p>
    <p><strong>Assessment score:</strong> ' . $escape($score) . '%<br>
       <strong>Certificate code:</strong> ' . $escape($certificateCode) . '<br>
       <strong>Date issued:</strong> ' . $escape($issuedAt) . '</p>
    <p>Your Certificate of Completion is attached to this email as a PDF document.</p>
    <p>Thank you,<br><strong>Training and Development</strong></p>
  </div>
</body>
</html>';
                if (!class_exists(\Dompdf\Dompdf::class)) {
                    throw new RuntimeException(
                        'PDF generator is not installed. Run composer require dompdf/dompdf in the project root.'
                    );
                }

                $pdfOptions = new \Dompdf\Options();
                $pdfOptions->set('isRemoteEnabled', false);
                $pdfOptions->set('isPhpEnabled', false);

                $dompdf = new \Dompdf\Dompdf($pdfOptions);
                $dompdf->loadHtml($certificateHtml, 'UTF-8');
                $dompdf->setPaper('A4', 'landscape');
                $dompdf->render();
                $certificatePdf = $dompdf->output();

                $safeCertificateCode = preg_replace('/[^A-Za-z0-9_-]/', '-', $certificateCode);
                $attachmentName = 'certificate-' . $safeCertificateCode . '.pdf';

                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host = $smtpHost;
                    $mail->SMTPAuth = true;
                    $mail->Username = $smtpUsername;
                    $mail->Password = $smtpPassword;
                    $mail->Port = $smtpPort;

                    if ($encryption === 'ssl') {
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                    } elseif ($encryption === 'tls') {
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    } else {
                        $mail->SMTPSecure = '';
                        $mail->SMTPAutoTLS = false;
                    }

                    $mail->CharSet = 'UTF-8';
                    $mail->setFrom($fromAddress, $fromName);
                    $mail->addAddress($recipient, $fullName);
                    $mail->Subject = $subject;
                    $mail->isHTML(true);
                    $mail->Body = $bodyHtml;
                    $mail->AltBody = "Hello {$fullName},\n\n"
                        . "Congratulations on completing {$programTitle}.\n"
                        . "Assessment score: {$score}%\n"
                        . "Certificate code: {$certificateCode}\n"
                        . "Date issued: {$issuedAt}\n\n"
                        . "Your Certificate of Completion is attached as a PDF document.\n";

                    $mail->addStringAttachment(
                        $certificatePdf,
                        $attachmentName,
                        \PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64,
                        'application/pdf'
                    );

                    $mail->send();
                } catch (\PHPMailer\PHPMailer\Exception $mailException) {
                    error_log('Certificate email failed: ' . $mailException->getMessage());
                    throw new RuntimeException(
                        'The certificate email could not be sent. Check your Gmail SMTP settings and App Password, then try again.'
                    );
                }

                flash('Certificate email sent successfully to ' . $recipient . '.');
                redirect('Certificates');

            case 'change_password':
                $currentPassword = (string)($_POST['current_password'] ?? '');
                $newPassword = (string)($_POST['new_password'] ?? '');
                $confirmPassword = (string)($_POST['confirm_password'] ?? '');

                if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                    throw new RuntimeException('Please complete all password fields.');
                }

                if (strlen($newPassword) < 8) {
                    throw new RuntimeException('Your new password must contain at least 8 characters.');
                }

                if ($newPassword !== $confirmPassword) {
                    throw new RuntimeException('The new passwords do not match.');
                }

                if ($newPassword === $currentPassword) {
                    throw new RuntimeException('Your new password must be different from your current password.');
                }

                $stmt = $pdo->prepare(
                    'SELECT password_hash
                     FROM users
                     WHERE id = ? AND active = 1
                     LIMIT 1'
                );
                $stmt->execute([(int)$user['id']]);
                $account = $stmt->fetch();

                if (
                    !$account ||
                    !password_verify($currentPassword, $account['password_hash'])
                ) {
                    throw new RuntimeException('Your current password is incorrect.');
                }

                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare(
                    'UPDATE users
                     SET password_hash = ?
                     WHERE id = ? AND active = 1'
                );
                $stmt->execute([$newPasswordHash, (int)$user['id']]);

                session_regenerate_id(true);
                csrf_token();

                flash('Your password has been changed successfully.');
                header('Location: ' . app_base_url() . '/change-password.php');
                exit;

            case 'update_profile':
                $name = explode(' ', post_string('display_name'), 2);
                $first = trim($name[0] ?? '');
                $last = trim($name[1] ?? '');

                $email = strtolower(trim(
                    post_string('email', (string)$user['email'])
                ));

                if (!$first || !$last) {
                    throw new RuntimeException(
                        'Enter your first and last name.'
                    );
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException(
                        'Enter a valid email address.'
                    );
                }

                $stmt = $pdo->prepare(
                    'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1'
                );
                $stmt->execute([$email, (int)$user['id']]);

                if ($stmt->fetch()) {
                    throw new RuntimeException(
                        'That email address is already used by another account.'
                    );
                }

                $stmt = $pdo->prepare(
                    'UPDATE users
                SET first_name = ?, last_name = ?, email = ?
                WHERE id = ?'
                );

                $stmt->execute([
                    $first,
                    $last,
                    $email,
                    (int)$user['id']
                ]);

                $employeeId = (int)($user['employee_id'] ?? 0);

                if ($employeeId > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE employees SET email = ? WHERE id = ?'
                    );
                    $stmt->execute([$email, $employeeId]);
                }

                $_SESSION['user']['first_name'] = $first;
                $_SESSION['user']['last_name'] = $last;
                $_SESSION['user']['email'] = $email;

                flash('Profile details updated.');

                redirect(
                    $user['role'] === 'employee'
                        ? 'My Profile'
                        : 'Settings'
                );

            default:
                throw new RuntimeException('That action is not available.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        flash(
            $e instanceof PDOException && $e->getCode() === '23000'
                ? 'That email, employee code, or registration already exists. Check the information and try again.'
                : $e->getMessage(),
            'error'
        );

        if ($action === 'change_password') {
            header('Location: ' . app_base_url() . '/change-password.php');
            exit;
        }

        redirect(post_string('return_page', 'Dashboard'));
    }
}

function handle_training_export(PDO $pdo, array $user): void
{
    if (isset($_GET['export']) && $_GET['export'] === 'training') {
        if (!in_array($user['role'], ['admin', 'trainer'], true)) {
            http_response_code(403);
            exit('Permission denied.');
        }

        $q = 'SELECT e.employee_code, CONCAT(e.first_name, \' \', e.last_name) AS faculty, e.department, p.title, p.specialization, p.start_date, r.status, a.attendance_status, ev.score, c.certificate_code
            FROM registrations r
            JOIN employees e ON e.id = r.employee_id
            JOIN training_programs p ON p.id = r.program_id
            LEFT JOIN attendance a ON a.registration_id = r.id
            LEFT JOIN evaluations ev ON ev.registration_id = r.id
            LEFT JOIN certificates c ON c.registration_id = r.id
            ORDER BY p.start_date DESC';

        if ($user['role'] === 'trainer') {
            $q = str_replace(
                'ORDER BY p.start_date DESC',
                'WHERE p.trainer_user_id = ' . (int)$user['id'] . ' ORDER BY p.start_date DESC',
                $q
            );
        }

        $rows = $pdo->query($q)->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="training-report.csv"');

        $out = fopen('php://output', 'w');

        fputcsv($out, [
            'Employee ID',
            'Faculty member',
            'Department',
            'Training program',
            'Specialization',
            'Date',
            'Registration status',
            'Attendance',
            'Score',
            'Certificate'
        ]);

        foreach ($rows as $r) {
            fputcsv($out, $r);
        }

        fclose($out);
        exit;
    }
}
