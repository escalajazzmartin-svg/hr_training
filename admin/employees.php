<?php
declare(strict_types=1);

if (!function_exists('write_app_log')) {
    function write_app_log(string $message): void
    {
        $logFile = dirname(__DIR__) . '/employee_registration_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        error_log("[{$timestamp}] {$message}" . PHP_EOL, 3, $logFile);
    }
}

function normalize_employee_name(string $value): string
{
    $value = preg_replace('/\s+/', ' ', trim((string) $value));
    $value = preg_replace('/[^\p{L}\s\'\-\.]/u', '', $value ?? '');
    $value = preg_replace('/\s{2,}/', ' ', trim((string) $value));

    return trim((string) $value);
}

function is_valid_employee_name(string $value): bool
{
    $value = preg_replace('/\s+/', ' ', trim($value));

    return $value !== ''
        && preg_match(
            '/^(?!.*\s{2,})[A-Za-z]+(?:[\'\-.][A-Za-z]+)*(?: [A-Za-z]+(?:[\'\-.][A-Za-z]+)*)*$/',
            $value
        ) === 1;
}

function generate_employee_code(PDO $pdo, string $accountRole): string
{
    $rolePrefix = match ($accountRole) {
        'employee' => 'FAC',
        'trainer' => 'TRN',
        default => throw new InvalidArgumentException('Invalid account role.'),
    };

    $registrationYear = (int) date('Y');
    $pdo->prepare(
        'INSERT IGNORE INTO employee_code_counters (role_prefix, registration_year, last_number) VALUES (?, ?, 0)'
    )->execute([$rolePrefix, $registrationYear]);

    $counter = $pdo->prepare(
        'SELECT last_number FROM employee_code_counters WHERE role_prefix = ? AND registration_year = ? FOR UPDATE'
    );
    $counter->execute([$rolePrefix, $registrationYear]);
    $lastNumber = (int) $counter->fetchColumn();

    if ($lastNumber === 0) {
        $like = $rolePrefix . '-' . $registrationYear . '-%';
        $maxStmt = $pdo->prepare(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(employee_code, '-', -1) AS UNSIGNED)), 0) FROM employees WHERE employee_code LIKE ?"
        );
        $maxStmt->execute([$like]);
        $lastNumber = (int) $maxStmt->fetchColumn();
    }

    $sequenceNumber = $lastNumber + 1;
    $employeeCode = sprintf('%s-%d-%03d', $rolePrefix, $registrationYear, $sequenceNumber);
    $pdo->prepare(
        'UPDATE employee_code_counters SET last_number = ? WHERE role_prefix = ? AND registration_year = ?'
    )->execute([$sequenceNumber, $rolePrefix, $registrationYear]);

    $check = $pdo->prepare('SELECT id FROM employees WHERE employee_code = ? LIMIT 1');
    $check->execute([$employeeCode]);
    if ($check->fetchColumn()) {
        throw new RuntimeException('The generated Faculty ID already exists. Please try again.');
    }

    return $employeeCode;
}

function send_employee_credentials_email(
    string $firstName,
    string $lastName,
    string $email,
    string $employeeCode,
    string $messageSubject = 'Your HR Training System login credentials',
    string $accountRole = 'employee'
): void {
    $mailConfig = require __DIR__ . '/../includes/mail_config.php';
    require_once __DIR__ . '/../vendor/autoload.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
    $mail->Debugoutput = static function (string $str, int $level): void {
        write_app_log('PHPMailer [' . $level . ']: ' . trim($str));
    };
    $mail->Host = (string) ($mailConfig['host'] ?? 'smtp.gmail.com');
    $mail->SMTPAuth = true;
    $mail->Username = (string) ($mailConfig['username'] ?? '');
    $mail->Password = (string) ($mailConfig['password'] ?? '');
    $mail->Port = (int) ($mailConfig['port'] ?? 587);
    $encryption = strtolower((string) ($mailConfig['encryption'] ?? 'tls'));
    $mail->SMTPSecure = $encryption === 'ssl'
        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom(
        (string) ($mailConfig['from_email'] ?? $mailConfig['username'] ?? ''),
        (string) ($mailConfig['from_name'] ?? 'HR Training System')
    );
    $mail->addAddress($email, $firstName . ' ' . $lastName);
    $mail->isHTML(true);
    $mail->Subject = $messageSubject;

    $roleLabel = strtolower($accountRole) === 'trainer' ? 'Trainer' : 'Faculty Member';
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safePassword = htmlspecialchars($employeeCode, ENT_QUOTES, 'UTF-8');
    $mail->Body = '<p>Greetings ' . htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p>Welcome to the HR Training System.</p>'
        . '<p>Your account has been created successfully.</p>'
        . '<p><strong>Email:</strong> ' . $safeEmail . '</p>'
        . '<p><strong>Initial password:</strong> ' . $safePassword . '</p>'
        . '<p><strong>Faculty ID:</strong> ' . htmlspecialchars($employeeCode, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>Role:</strong> ' . htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>Use the password above to sign in. We recommend changing it after your first login.</p>';
    $mail->AltBody = "Greetings {$firstName},\n\nWelcome to the HR Training System.\n\nYour account has been created successfully.\n\nEmail: {$email}\nInitial password: {$employeeCode}\nFaculty ID: {$employeeCode}\nRole: {$roleLabel}\n\nUse the password above to sign in. We recommend changing it after your first login.";
    $mail->send();
}

function normalize_employee_phone(string $value): string
{
    $digits = preg_replace('/\D+/', '', (string) $value);
    if (!preg_match('/^09\d{9}$/', $digits)) {
        return '';
    }

    return preg_replace('/^(\d{4})(\d{3})(\d{4})$/', '$1 $2 $3', $digits);
}

$requiredRole = 'admin';
require_once __DIR__ . '/../includes/page_bootstrap.php';
/*
|--------------------------------------------------------------------------
| HANDLE EMPLOYEE REGISTRATION
| Email verification is required before account creation.
|--------------------------------------------------------------------------
*/
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && ($_POST['action'] ?? '') === 'add_employee'
) {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    if (
        $submittedToken === ''
        || !hash_equals((string) csrf_token(), $submittedToken)
    ) {
        http_response_code(403);
        exit('Invalid security token. Reload the page and try again.');
    }
    $firstNameRaw = trim((string)($_POST['first_name'] ?? ''));
    $lastNameRaw = trim((string)($_POST['last_name'] ?? ''));
    $firstName = normalize_employee_name($firstNameRaw);
    $lastName = normalize_employee_name($lastNameRaw);
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phoneRaw = trim((string)($_POST['phone'] ?? ''));
    $phone = normalize_employee_phone($phoneRaw);
    $accountRole = trim((string)($_POST['account_role'] ?? 'employee'));
    $department = trim((string)($_POST['department'] ?? ''));
    $jobTitle = trim((string)($_POST['job_title'] ?? ''));
    $specialization = trim((string)($_POST['specialization'] ?? ''));
    $hireDate = trim((string)($_POST['hire_date'] ?? ''));
    $employmentStatus = trim(
        (string)($_POST['employment_status'] ?? 'Active')
    );
    if (
        $firstNameRaw === ''
        || !is_valid_employee_name($firstNameRaw)
        || $firstName === ''
        || $lastNameRaw === ''
        || !is_valid_employee_name($lastNameRaw)
        || $lastName === ''
        || !preg_match(
            '/^[A-Za-z0-9._%+\-]+@(?:[A-Za-z0-9-]+\.)+com$/i',
            $email
        )
        || ($phoneRaw !== '' && !preg_match('/^09\d{2} \d{3} \d{4}$/', $phoneRaw))
        || ($phoneRaw !== '' && $phone === '')
        || $department === ''
        || $jobTitle === ''
        || $specialization === ''
        || !in_array($accountRole, ['employee', 'trainer'], true)
        || !in_array(
            $employmentStatus,
            ['Active', 'On Leave', 'Inactive'],
            true
        )
        || (
            $hireDate !== ''
            && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hireDate)
        )
    ) {
        header('Location: employees.php?error=invalid_fields#add-employee-modal');
        exit;
    }
    try {
        $check = $pdo->prepare(
            'SELECT email
             FROM employees
             WHERE LOWER(email) = ?
             UNION
             SELECT email
             FROM users
             WHERE LOWER(email) = ?
             LIMIT 1'
        );
        $check->execute([$email, $email]);
        if ($check->fetchColumn()) {
            header(
                'Location: employees.php?error=email_exists#add-employee-modal'
            );
            exit;
        }

        $employeeCode = generate_employee_code($pdo, $accountRole);
        $initialPassword = $employeeCode;

        $pdo->beginTransaction();

        $employeeInsert = $pdo->prepare(
            'INSERT INTO employees
                (employee_code, first_name, last_name, email, phone, department, job_title, specialization, hire_date, employment_status)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $employeeInsert->execute([
            $employeeCode,
            $firstName,
            $lastName,
            $email,
            $phone !== '' ? $phone : null,
            $department,
            $jobTitle,
            $specialization,
            $hireDate !== '' ? $hireDate : null,
            $employmentStatus,
        ]);
        $employeeId = (int) $pdo->lastInsertId();

        $userInsert = $pdo->prepare(
            'INSERT INTO users
                (employee_id, email, password_hash, role, first_name, last_name, active)
             VALUES
                (?, ?, ?, ?, ?, ?, 1)'
        );
        $userInsert->execute([
            $employeeId,
            $email,
            password_hash($initialPassword, PASSWORD_DEFAULT),
            $accountRole,
            $firstName,
            $lastName,
        ]);

        $pdo->commit();

        send_employee_credentials_email(
            $firstName,
            $lastName,
            $email,
            $initialPassword,
            'Your HR Training System login credentials',
            $accountRole
        );

        header('Location: employees.php?credentials_sent=1');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        write_app_log(
            'Employee registration failed: '
                . $e->getMessage()
                . ' | request_uri=' . ($_SERVER['REQUEST_URI'] ?? '')
        );
        header(
            'Location: employees.php?error=email_send_failed#add-employee-modal'
        );
        exit;
    }
}
/*
|--------------------------------------------------------------------------
| HANDLE EMPLOYEE UPDATE
|--------------------------------------------------------------------------
*/
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && ($_POST['action'] ?? '') === 'update_employee'
) {
    $employeeId = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)csrf_token();
    if (!$employeeId || $employeeId < 1) {
        header('Location: employees.php?error=invalid_employee');
        exit;
    }
    if ($submittedToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(403);
        exit('Invalid security token. Please reload the page and try again.');
    }
    $firstNameRaw = trim((string)($_POST['first_name'] ?? ''));
    $lastNameRaw = trim((string)($_POST['last_name'] ?? ''));
    $firstName = normalize_employee_name($firstNameRaw);
    $lastName = normalize_employee_name($lastNameRaw);
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phoneRaw = trim((string)($_POST['phone'] ?? ''));
    $phone = normalize_employee_phone($phoneRaw);
    $department = trim((string)($_POST['department'] ?? ''));
    $jobTitle = trim((string)($_POST['job_title'] ?? ''));
    $specialization = trim((string)($_POST['specialization'] ?? ''));
    $employmentStatus = trim((string)($_POST['employment_status'] ?? 'Active'));
    if (
        $firstNameRaw === '' || !is_valid_employee_name($firstNameRaw)
        || $firstName === ''
        || $lastNameRaw === '' || !is_valid_employee_name($lastNameRaw)
        || $lastName === ''
        || $department === '' || $jobTitle === '' || $specialization === ''
        || !preg_match(
            '/^[A-Za-z0-9._%+\-]+@(?:[A-Za-z0-9-]+\.)+com$/i',
            $email
        )
        || ($phoneRaw !== '' && !preg_match('/^09\d{2} \d{3} \d{4}$/', $phoneRaw))
        || ($phoneRaw !== '' && $phone === '')
        || !in_array($employmentStatus, ['Active', 'On Leave', 'Inactive'], true)
    ) {
        header('Location: employees.php?edit=' . $employeeId . '&error=invalid_fields');
        exit;
    }
    try {
        $current = $pdo->prepare(
            'SELECT e.email AS employee_email,
                    e.employee_code,
                    u.email AS user_email,
                    u.role AS user_role
             FROM employees e
             LEFT JOIN users u ON u.employee_id = e.id
             WHERE e.id = ?
             LIMIT 1'
        );
        $current->execute([$employeeId]);
        $currentRow = $current->fetch(PDO::FETCH_ASSOC) ?: [];
        $previousEmail = strtolower(
            (string) (($currentRow['employee_email'] ?? $currentRow['user_email'] ?? '') ?: '')
        );
        $currentEmployeeCode = (string) ($currentRow['employee_code'] ?? '');
        $currentRole = strtolower((string) ($currentRow['user_role'] ?? 'employee'));
        $emailChanged = $previousEmail !== $email;

        $pdo->beginTransaction();
        $pdo->prepare(
            'UPDATE employees
             SET first_name = :first_name,
                 last_name = :last_name,
                 email = :email,
                 phone = :phone,
                 department = :department,
                 job_title = :job_title,
                 specialization = :specialization,
                 employment_status = :employment_status
             WHERE id = :id'
        )->execute([
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':email' => $email,
            ':phone' => $phone !== '' ? $phone : null,
            ':department' => $department,
            ':job_title' => $jobTitle,
            ':specialization' => $specialization,
            ':employment_status' => $employmentStatus,
            ':id' => $employeeId,
        ]);
        $pdo->prepare(
            'UPDATE users
             SET email = ?, first_name = ?, last_name = ?
             WHERE employee_id = ?'
        )->execute([$email, $firstName, $lastName, $employeeId]);
        $pdo->commit();

        if ($emailChanged && $email !== '') {
            $subject = 'Your HR Training System account email was updated';
            $employeeCodeForNotification = $currentEmployeeCode !== '' ? $currentEmployeeCode : $email;
            send_employee_credentials_email(
                $firstName,
                $lastName,
                $email,
                $employeeCodeForNotification,
                $subject,
                $currentRole
            );
        }
        header('Location: employees.php?updated=1');
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Employee update failed: ' . $e->getMessage());
        header('Location: employees.php?edit=' . $employeeId . '&error=update_failed');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Employee email update notification failed: ' . $e->getMessage());
        header('Location: employees.php?updated=1');
        exit;
    }
}
function render_employee_directory(PDO $pdo): string
{
    $editId = (int)($_GET['edit'] ?? 0);
    $edit = null;
    if ($editId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ?');
        $stmt->execute([$editId]);
        $edit = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $html = page_intro(
        '',
        '',
        '<a class="btn" href="#add-employee-modal" data-open-modal="add-employee-modal">'
            . icon('plus')
            . ' Add employee</a>'
    );
    /*
    |--------------------------------------------------------------------------
    | EDIT EMPLOYEE MODAL
    |--------------------------------------------------------------------------
    */
    if ($edit !== null) {
        $html .= '<div class="modal-overlay is-open" role="dialog" '
            . 'aria-modal="true" aria-labelledby="edit-employee-title">'
            . '<div class="modal-panel" style="width:min(720px, calc(100vw - 32px)); max-width:100%; max-height:calc(100vh - 32px); overflow-y:auto; overscroll-behavior:contain; box-sizing:border-box;">'
            . '<div class="modal-header">'
            . '<div>'
            . '<h2 id="edit-employee-title">Edit employee</h2>'
            . '<p>Update the employee record</p>'
            . '</div>'
            . '</div>'
            . '<form method="post" class="form-grid">'
            . '<input type="hidden" name="csrf_token" value="'
            . h(csrf_token()) . '">'
            . '<input type="hidden" name="action" value="update_employee">'
            . '<input type="hidden" name="employee_id" value="'
            . (int)$edit['id'] . '">';
        foreach (
            [
                'first_name' => 'First name',
                'last_name' => 'Last name',
                'email' => 'Email address',
                'phone' => 'Phone',
                'department' => 'Department',
                'job_title' => 'Job title',
                'specialization' => 'Specialization'
            ] as $key => $label
        ) {
            $required = $key !== 'phone' ? 'required' : '';
            $inputType = $key === 'phone' ? 'tel' : 'text';
            $pattern = $key === 'phone'
                ? ' pattern="^09\\d{2} \\d{3} \\d{4}$"'
                : '';
            $maxlength = in_array($key, ['first_name', 'last_name'], true)
                ? ' maxlength="50"'
                : '';
            $dataAttr = in_array($key, ['first_name', 'last_name'], true)
                ? ' data-name-input="1"'
                : ($key === 'phone' ? ' data-phone-input="1"' : '');
            $html .= '<div class="form-field">'
                . '<label for="edit_' . h($key) . '">'
                . h($label) . '</label>'
                . '<input id="edit_' . h($key) . '" '
                . 'type="' . h($inputType) . '" '
                . 'name="' . h($key) . '" '
                . 'value="' . h($edit[$key] ?? '') . '" '
                . 'title="' . ($key === 'phone' ? 'Use format 09XX XXX XXXX' : 'Use letters only with a single space between words') . '" '
                . $pattern . $maxlength . $dataAttr . ' '
                . $required
                . '></div>';
        }
        $html .= '<div class="form-field">'
            . '<label for="edit_employment_status">Employment status</label>'
            . '<select id="edit_employment_status" name="employment_status">';
        foreach (['Active', 'On Leave', 'Inactive'] as $status) {
            $html .= '<option value="' . h($status) . '" '
                . (($edit['employment_status'] ?? '') === $status
                    ? 'selected'
                    : '')
                . '>' . h($status) . '</option>';
        }
        $html .= '</select></div>'
            . '<div class="form-field full modal-footer">'
            . '<a class="btn btn-secondary" href="employees.php">Cancel</a>'
            . '<button class="btn" type="submit">Save changes</button>'
            . '</div>'
            . '</form></div></div>';
    }
    /*
    |--------------------------------------------------------------------------
    | SEARCH AND EMPLOYEE DIRECTORY
    |--------------------------------------------------------------------------
    */
    $html .= '<section class="card" style="margin-bottom:1rem">'
        . '<div class="filter-row employee-search-wrapper">'
        . '<input type="text" id="employee-search-input" '
        . 'class="search-input" '
        . 'placeholder="Search name, ID, department, or specialization..." '
        . 'autocomplete="off">'
        . '<button type="button" id="clear-employee-search" '
        . 'class="employee-search-clear" aria-label="Clear search">'
        . '<i class="fa fa-times" aria-hidden="true"></i>'
        . '</button>'
        . '</div>';
    $stmt = $pdo->query('SELECT * FROM employees ORDER BY last_name, first_name');
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $departments = [];
    $statuses = [];
    $positions = [];
    foreach ($records as $employee) {
        $department = trim((string)($employee['department'] ?? ''));
        $status = trim((string)($employee['employment_status'] ?? ''));
        $position = trim((string)($employee['job_title'] ?? ''));
        if ($department !== '') {
            $departments[$department] = $department;
        }
        if ($status !== '') {
            $statuses[$status] = $status;
        }
        if ($position !== '') {
            $positions[$position] = $position;
        }
    }
    natcasesort($departments);
    natcasesort($statuses);
    natcasesort($positions);
    $html .= '<div class="employee-filter-row">';
    foreach (
        [
            'employee-department-filter' => ['Department', $departments],
            'employee-status-filter' => ['Statu', $statuses],
            'employee-position-filter' => ['Position', $positions],
        ] as $filterId => [$filterLabel, $options]
    ) {
        $html .= '<label class="employee-filter-field" for="' . h($filterId) . '">'
            . '<select id="' . h($filterId) . '">'
            . '<option value="">All ' . h($filterLabel) . 's</option>';
        foreach ($options as $option) {
            $html .= '<option value="' . h($option) . '">'
                . h($option)
                . '</option>';
        }
        $html .= '</select></label>';
    }
    $html .= '<button type="button" id="clear-employee-filters" '
        . 'class="employee-filter-reset">Clear filters</button>'
        . '</div>';
    /*
    |--------------------------------------------------------------------------
    | EMPLOYEE TABLE
    |--------------------------------------------------------------------------
    */
    $rows = [];
    foreach ($records as $employee) {
        $fullName = $employee['first_name'] . ' ' . $employee['last_name'];
        $employeeId = (int)$employee['id'];
        $rows[] = [
            // First column: three horizontal dots open the employee profile.
            '<div class="table-actions employee-profile-action">'
                . '<a class="btn btn-ghost icon-action" '
                . 'href="employee-profile.php?employee_id=' . $employeeId . '" '
                . 'title="Open employee profile" aria-label="Open profile for '
                . h($fullName) . '">'
                . '<i class="fa fa-ellipsis-h" aria-hidden="true"></i>'
                . '</a>'
                . '</div>',
            // Employee column shifted to the right.
            '<div class="person-cell">'
                . '<span class="avatar">'
                . h(initials($fullName))
                . '</span>'
                . '<span><b>' . h($fullName) . '</b>'
                . '<small>' . h($employee['employee_code'] ?? '') . '</small>'
                . '</span></div>',
            h($employee['department'] ?? ''),
            h($employee['job_title'] ?? ''),
            h($employee['specialization'] ?? ''),
            badge($employee['employment_status'] ?? ''),
            // Keep Edit and Delete at the end of the row.
            '<div class="table-actions">'
                . '<a class="btn btn-ghost icon-action" '
                . 'href="employees.php?edit=' . $employeeId . '" '
                . 'title="Edit employee" aria-label="Edit employee">'
                . '<i class="fa fa-pencil" aria-hidden="true"></i>'
                . '</a>'
                . '<form class="inline-form" method="post" '
                . 'onsubmit="return confirm(\'Delete this employee and their linked records?\')">'
                . '<input type="hidden" name="csrf_token" value="'
                . h(csrf_token()) . '">'
                . '<input type="hidden" name="action" value="delete_employee">'
                . '<input type="hidden" name="employee_id" value="'
                . $employeeId . '">'
                . '<button class="btn btn-ghost delete-btn icon-action" '
                . 'type="submit" title="Delete employee" '
                . 'aria-label="Delete employee">'
                . '<i class="fa fa-trash" aria-hidden="true"></i>'
                . '</button>'
                . '</form>'
                . '</div>'
        ];
    }
    $html .= '<div class="employee-table-scroll" data-max-rows="5">';
    $html .= render_table(
        [
            '',
            'Employee',
            'Department',
            'Job title',
            'Specialization',
            'Status',
            'Actions'
        ],
        $rows,
        'No employees match your search.'
    );
    $html .= '</div>';
    $html .= <<<'HTML'
<style>
.employee-profile-action {
    display: flex;
    justify-content: center;
    align-items: center;
    min-width: 36px;
}
.employee-profile-action .icon-action {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
</style>
HTML;
    /*
    |--------------------------------------------------------------------------
    | SEARCH AND FILTER BEHAVIOR
    |--------------------------------------------------------------------------
    */
    $html .= <<<'HTML'
<script>
(function () {
    function initializeEmployeeSearch() {
        const searchInput = document.getElementById('employee-search-input');
        const wrapper = document.querySelector('.employee-table-scroll');
        const table = wrapper ? wrapper.querySelector('table') : null;
        const tbody = table ? table.querySelector('tbody') : null;
        if (!searchInput || !wrapper || !tbody) return;
        const clearButton = document.getElementById('clear-employee-search');
        function updateClearButton() {
            if (clearButton) {
                clearButton.classList.toggle(
                    'is-visible',
                    searchInput.value.length > 0
                );
            }
        }
        if (clearButton) {
            clearButton.addEventListener('click', function () {
                searchInput.value = '';
                searchInput.dispatchEvent(
                    new Event('input', { bubbles: true })
                );
                searchInput.focus();
            });
        }
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const countLabel = document.getElementById('employee-record-count');
        const departmentFilter = document.getElementById('employee-department-filter');
        const statusFilter = document.getElementById('employee-status-filter');
        const positionFilter = document.getElementById('employee-position-filter');
        const clearFiltersButton = document.getElementById('clear-employee-filters');
        updateClearButton();
        function applyEmployeeFilters() {
            updateClearButton();
            const query = searchInput.value.trim().toLocaleLowerCase();
            const department = departmentFilter ? departmentFilter.value : '';
            const status = statusFilter ? statusFilter.value : '';
            const position = positionFilter ? positionFilter.value : '';
            let visibleCount = 0;
            rows.forEach(function (row) {
                const cells = row.cells;
                // Column indexes account for the new first-column icon.
                const matchesSearch = row.textContent.toLocaleLowerCase().includes(query);
                const matchesDepartment =
                    !department ||
                    (cells[2] && cells[2].textContent.trim() === department);
                const matchesPosition =
                    !position ||
                    (cells[3] && cells[3].textContent.trim() === position);
                const matchesStatus =
                    !status ||
                    (cells[5] && cells[5].textContent.trim() === status);
                const matches =
                    matchesSearch &&
                    matchesDepartment &&
                    matchesPosition &&
                    matchesStatus;
                row.hidden = !matches;
                if (matches) visibleCount++;
            });
            if (countLabel) {
                countLabel.textContent = visibleCount + ' employee record(s)';
            }
            const visibleRows = rows.filter(function (row) {
                return !row.hidden;
            });
            const rowLimit = 5;
            const header = table.querySelector('thead');
            const headerHeight = header
                ? header.getBoundingClientRect().height
                : 0;
            if (visibleRows.length <= rowLimit) {
                wrapper.style.maxHeight = 'none';
                wrapper.style.overflowY = 'visible';
            } else {
                const visibleRowsHeight = visibleRows
                    .slice(0, rowLimit)
                    .reduce(function (total, row) {
                        return total + row.getBoundingClientRect().height;
                    }, 0);
                wrapper.style.maxHeight =
                    Math.ceil(headerHeight + visibleRowsHeight + 2) + 'px';
                wrapper.style.overflowY = 'auto';
            }
            window.dispatchEvent(new Event('resize'));
        }
        searchInput.addEventListener('input', applyEmployeeFilters);
        [departmentFilter, statusFilter, positionFilter].forEach(function (filter) {
            if (filter) {
                filter.addEventListener('change', applyEmployeeFilters);
            }
        });
        if (clearFiltersButton) {
            clearFiltersButton.addEventListener('click', function () {
                searchInput.value = '';
                [departmentFilter, statusFilter, positionFilter].forEach(function (filter) {
                    if (filter) filter.value = '';
                });
                applyEmployeeFilters();
                searchInput.focus();
            });
        }
        applyEmployeeFilters();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeEmployeeSearch);
    } else {
        initializeEmployeeSearch();
    }
})();
</script>
HTML;
    $html .= '<p class="small-muted" id="employee-record-count">'
        . count($records)
        . ' employee record(s)</p></section>';
    /*
    |--------------------------------------------------------------------------
    | ADD EMPLOYEE MODAL
    |--------------------------------------------------------------------------
    */
    $html .= '<div class="modal-overlay" id="add-employee-modal" '
        . 'role="dialog" aria-modal="true" '
        . 'aria-labelledby="add-employee-title" '
        . 'style="padding:16px; box-sizing:border-box; overflow-y:auto;">'
        . '<div class="modal-panel" style="width:min(720px, calc(100vw - 32px)); max-width:100%; max-height:calc(100vh - 32px); overflow-y:auto; overscroll-behavior:contain; box-sizing:border-box;">'
        . '<div class="modal-header">'
        . '<div>'
        . '<h2 id="add-employee-title">Employee Registration</h2>'
        . '</div>'
        . '</div>'
        . '<form method="post" class="form-grid">'
        . '<input type="hidden" name="csrf_token" value="'
        . h(csrf_token()) . '">'
        . '<input type="hidden" name="action" value="add_employee">'
        . '<div class="form-field">'
        . '<label for="add_first_name">First name *</label>'
        . '<input id="add_first_name" name="first_name" '
        . 'placeholder="Enter first name" required '
        . 'autocomplete="given-name" maxlength="50" '
        . 'pattern="[A-Za-z]+(?: [A-Za-z]+)*" '
        . 'data-name-input="1" title="Use letters only with a single space between words"></div>'
        . '<div class="form-field">'
        . '<label for="add_last_name">Last name *</label>'
        . '<input id="add_last_name" name="last_name" '
        . 'placeholder="Enter last name" required '
        . 'autocomplete="family-name" maxlength="50" '
        . 'pattern="[A-Za-z]+(?: [A-Za-z]+)*" '
        . 'data-name-input="1" title="Use letters only with a single space between words"></div>'
        . '<div class="form-field">'
        . '<label for="add_email">Email address *</label>'
        . '<input id="add_email" name="email" type="email" '
        . 'placeholder="Enter email address" required '
        . 'autocomplete="email" pattern="[A-Za-z0-9._%+\-]+@(?:[A-Za-z0-9-]+\.)+com" '
        . 'title="Enter a valid email address ending in .com"></div>'
        . '<div class="form-field">'
        . '<label for="add_phone">Phone number</label>'
        . '<input id="add_phone" name="phone" type="tel" '
        . 'placeholder="09XX XXX XXXX" '
        . 'autocomplete="tel" inputmode="numeric" maxlength="14" '
        . 'pattern="^09\\d{2} \\d{3} \\d{4}$" '
        . 'data-phone-input="1" title="Use format 09XX XXX XXXX"></div>'
        . '<div class="form-field">'
        . '<label for="add_account_role">Account type *</label>'
        . '<select id="add_account_role" name="account_role" required>'
        . '<option value="employee">Faculty Member</option>'
        . '<option value="trainer">Trainer</option>'
        . '</select></div>'
        . '<div class="form-field">'
        . '<label for="add_department">Department *</label>'
        . '<select id="add_department" name="department" required>'
        . '<option value="">Select department</option>';
    foreach (
        [
            'Information Technology',
            'Science',
            'Mathematics',
            'Business',
            'English',
            'Education',
            'Human Resources',
            'Other'
        ] as $department
    ) {
        $html .= '<option value="' . h($department) . '">'
            . h($department)
            . '</option>';
    }
    $html .= '</select></div>'
        . '<div class="form-field">'
        . '<label for="add_job_title">Job title *</label>'
        . '<input id="add_job_title" name="job_title" '
        . 'placeholder="e.g., Faculty Instructor" required></div>'
        . '<div class="form-field">'
        . '<label for="add_specialization">Specialization *</label>'
        . '<input id="add_specialization" name="specialization" '
        . 'placeholder="e.g., Web Development, Mathematics, or Biology" required></div>'
        . '<div class="form-field">'
        . '<label for="add_hire_date">Hire date</label>'
        . '<input id="add_hire_date" name="hire_date" type="date"></div>'
        . '<div class="form-field">'
        . '<label for="add_employment_status">Employment status</label>'
        . '<select id="add_employment_status" name="employment_status">'
        . '<option value="Active">Active</option>'
        . '<option value="On Leave">On Leave</option>'
        . '<option value="Inactive">Inactive</option>'
        . '</select></div>'
        . '<div class="form-field full">'
        . '<p class="small-muted">A verification link will be emailed to the employee. After verification, the employee will receive their login credentials with the Faculty ID as the initial password.</p>'
        . '</div>'
        . '<div class="form-field full modal-footer">'
        . '<a class="btn btn-secondary" href="employees.php">Cancel</a>'
        . '<button class="btn" type="submit">Register employee '
        . icon('arrow', 14)
        . '</button>'
        . '</div>'
        . '</form></div></div>';
    $html .= <<<'HTML'
<script>
(function () {
    function sanitizeNameInput(value) {
        return value
            .replace(/[^A-Za-z\s'-.]/g, '')
            .replace(/\s{2,}/g, ' ')
            .replace(/([\'\-.]){2,}/g, '$1')
            .replace(/^\s+|\s+$/g, '');
    }

    function formatPhoneInput(value) {
        const digits = value.replace(/\D/g, '').slice(0, 11);
        if (digits.length <= 4) {
            return digits;
        }
        if (digits.length <= 7) {
            return digits.replace(/^(\d{4})(\d{0,3})$/, '$1 $2');
        }
        return digits.replace(/^(\d{4})(\d{3})(\d{0,4})$/, '$1 $2 $3');
    }

    document.querySelectorAll('[data-name-input]').forEach(function (input) {
        input.addEventListener('input', function () {
            input.value = sanitizeNameInput(input.value);
        });
    });

    document.querySelectorAll('[data-phone-input]').forEach(function (input) {
        input.addEventListener('input', function () {
            input.value = formatPhoneInput(input.value);
            if (/^09\d{2} \d{3} \d{4}$/.test(input.value)) {
                input.setCustomValidity('');
                return;
            }
            input.setCustomValidity('Use format 09XX XXX XXXX');
        });
    });

    document.querySelectorAll('input[type="email"]').forEach(function (input) {
        input.addEventListener('input', function () {
            input.value = input.value.replace(/\s+/g, '').toLowerCase();
        });
    });

    function initializeEmployeeModals() {
        const addModal = document.getElementById('add-employee-modal');
        if (!addModal) return;
        function setModalOpen(modal, open) {
            modal.classList.toggle('is-open', open);
            document.body.style.overflow = open ? 'hidden' : '';
        }
        document.querySelectorAll('[data-open-modal="add-employee-modal"]').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                setModalOpen(addModal, true);
                history.replaceState(
                    null,
                    '',
                    window.location.pathname + window.location.search + '#add-employee-modal'
                );
            });
        });
        document.querySelectorAll('.modal-overlay').forEach(function (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal && modal.id === 'add-employee-modal') {
                    setModalOpen(modal, false);
                    history.replaceState(
                        null,
                        '',
                        window.location.pathname + window.location.search
                    );
                }
            });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && addModal.classList.contains('is-open')) {
                setModalOpen(addModal, false);
                history.replaceState(
                    null,
                    '',
                    window.location.pathname + window.location.search
                );
            }
        });
        document.querySelectorAll('.modal-close, .modal-footer a').forEach(function (link) {
            link.addEventListener('click', function () {
                document.body.style.overflow = '';
            });
        });
        if (window.location.hash === '#add-employee-modal') {
            setModalOpen(addModal, true);
        } else if (document.querySelector('.modal-overlay.is-open')) {
            document.body.style.overflow = 'hidden';
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeEmployeeModals);
    } else {
        initializeEmployeeModals();
    }
})();
</script>
HTML;
    return $html;
}
$page = 'Employees';
$content = render_employee_directory($pdo);
render_app($pdo, $user, $page, $flash, $content);
