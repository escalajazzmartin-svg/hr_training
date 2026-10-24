<?php
declare(strict_types=1);

function process_post_request(PDO $pdo): void {
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

        redirect(post_string('return_page', 'Dashboard'));    }
}

function handle_training_export(PDO $pdo, array $user): void {
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