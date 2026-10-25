<?php
declare(strict_types=1);
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
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $accountRole = trim((string)($_POST['account_role'] ?? 'employee'));
    $department = trim((string)($_POST['department'] ?? ''));
    $jobTitle = trim((string)($_POST['job_title'] ?? ''));
    $specialization = trim((string)($_POST['specialization'] ?? ''));
    $hireDate = trim((string)($_POST['hire_date'] ?? ''));
    $employmentStatus = trim(
        (string)($_POST['employment_status'] ?? 'Active')
    );
    if (
        $firstName === ''
        || $lastName === ''
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
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
        // Prevent duplicate employee or user email addresses.
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
        // Remove expired verification requests.
        $pdo->exec(
            'DELETE FROM pending_employee_verifications
             WHERE expires_at <= NOW()'
        );
        // Generate a secure, single-use verification token.
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $payload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'account_role' => $accountRole,
            'department' => $department,
            'job_title' => $jobTitle,
            'specialization' => $specialization,
            'hire_date' => $hireDate !== '' ? $hireDate : null,
            'employment_status' => $employmentStatus,
        ];
        // Replace any previous pending request for this email.
        $deletePending = $pdo->prepare(
            'DELETE FROM pending_employee_verifications
             WHERE email = ?'
        );
        $deletePending->execute([$email]);
        $insertPending = $pdo->prepare(
            'INSERT INTO pending_employee_verifications
                (email, token_hash, payload_json, expires_at)
             VALUES
                (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
        );
        $insertPending->execute([
            $email,
            $tokenHash,
            json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
        // Determine the application URL.
        $baseUrl = function_exists('app_base_url')
            ? rtrim((string) app_base_url(), '/')
            : '';
        if ($baseUrl === '') {
            $scriptDir = str_replace(
                '\\\\',
                '/',
                dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))
            );
            $scriptDir = preg_replace('~/admin$~', '', $scriptDir);
            $baseUrl = rtrim(
                (string) ($_SERVER['REQUEST_SCHEME'] ?? 'http')
                    . '://'
                    . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')
                    . $scriptDir,
                '/'
            );
        }
        $verifyUrl = $baseUrl
            . '/verify-employee-email.php?token='
            . urlencode($token);
        // Load SMTP configuration and PHPMailer.
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
        $encryption = strtolower(
            (string) ($mailConfig['encryption'] ?? 'tls')
        );
        $mail->SMTPSecure = $encryption === 'ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(
            (string) (
                $mailConfig['from_email']
                ?? $mailConfig['username']
                ?? ''
            ),
            (string) (
                $mailConfig['from_name']
                ?? 'HR Training System'
            )
        );
        $mail->addAddress($email, $firstName . ' ' . $lastName);
        $safeName = htmlspecialchars(
            $firstName,
            ENT_QUOTES,
            'UTF-8'
        );
        $safeUrl = htmlspecialchars(
            $verifyUrl,
            ENT_QUOTES,
            'UTF-8'
        );
        $mail->isHTML(true);
        $mail->Subject = 'Verify your HR Training System email';
        $mail->Body =
            '<p>Hello ' . $safeName . ',</p>'
            . '<p>An administrator has started creating your HR Training System account.</p>'
            . '<p>Click the link below to verify your email address. '
            . 'The link expires in 30 minutes.</p>'
            . '<p><a href="' . $safeUrl . '">Verify my email address</a></p>'
            . '<p>Your employee account will be created after verification.</p>'
            . '<p>If you were not expecting this email, you can ignore it.</p>';
        $mail->AltBody =
            "Hello {$firstName},\n\n"
            . "Verify your email address using this link:\n"
            . "{$verifyUrl}\n\n"
            . "This link expires in 30 minutes.";
        $mail->send();
        header('Location: employees.php?verification_sent=1');
        exit;
    } catch (Throwable $e) {
        write_app_log(
            'Employee registration verification failed: '
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
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $department = trim((string)($_POST['department'] ?? ''));
    $jobTitle = trim((string)($_POST['job_title'] ?? ''));
    $specialization = trim((string)($_POST['specialization'] ?? ''));
    $employmentStatus = trim((string)($_POST['employment_status'] ?? 'Active'));
    if (
        $firstName === '' || $lastName === '' || $department === ''
        || $jobTitle === '' || $specialization === ''
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !in_array($employmentStatus, ['Active', 'On Leave', 'Inactive'], true)
    ) {
        header('Location: employees.php?edit=' . $employeeId . '&error=invalid_fields');
        exit;
    }
    try {
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
        header('Location: employees.php?updated=1');
        exit;
    } catch (PDOException $e) {
        error_log('Employee update failed: ' . $e->getMessage());
        header('Location: employees.php?edit=' . $employeeId . '&error=update_failed');
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
            $html .= '<div class="form-field">'
                . '<label for="edit_' . h($key) . '">'
                . h($label) . '</label>'
                . '<input id="edit_' . h($key) . '" '
                . 'name="' . h($key) . '" '
                . 'value="' . h($edit[$key] ?? '') . '" '
                . ($key !== 'phone' ? 'required' : '')
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
        . 'autocomplete="given-name"></div>'
        . '<div class="form-field">'
        . '<label for="add_last_name">Last name *</label>'
        . '<input id="add_last_name" name="last_name" '
        . 'placeholder="Enter last name" required '
        . 'autocomplete="family-name"></div>'
        . '<div class="form-field">'
        . '<label for="add_email">Email address *</label>'
        . '<input id="add_email" name="email" type="email" '
        . 'placeholder="Enter email address" required '
        . 'autocomplete="email"></div>'
        . '<div class="form-field">'
        . '<label for="add_phone">Phone number</label>'
        . '<input id="add_phone" name="phone" type="tel" '
        . 'placeholder="Enter phone number" '
        . 'autocomplete="tel"></div>'
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
        . '<div class="form-field full">'
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
        . '<p class="small-muted">A verification link will be emailed to the employee. After verifying the address, the employee will receive a secure link to set their initial password.</p>'
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
