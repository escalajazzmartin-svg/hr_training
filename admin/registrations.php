<?php

declare(strict_types=1);

$requiredRole = 'admin';
require_once __DIR__ . '/../includes/page_bootstrap.php';

function render_registration(PDO $pdo): string
{
    $emps = employees_for($pdo);
    $progs = program_select($pdo);

    $joined = $pdo->query(
        'SELECT r.id, r.status, r.registered_at, r.employee_id, r.program_id, '
            . 'e.employee_code, e.first_name, e.last_name, e.department, '
            . 'p.title, p.start_date '
            . 'FROM registrations r '
            . 'JOIN employees e ON e.id = r.employee_id '
            . 'JOIN training_programs p ON p.id = r.program_id '
            . 'ORDER BY r.registered_at DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $departments = [];
    foreach ($joined as $r) {
        $department = trim((string)($r['department'] ?? ''));
        if ($department !== '') {
            $departments[$department] = $department;
        }
    }
    natcasesort($departments);

    $statuses = [];
    foreach ($joined as $r) {
        $status = trim((string)($r['status'] ?? ''));
        if ($status !== '') {
            $statuses[$status] = $status;
        }
    }
    natcasesort($statuses);

    $html = '<div class="registration-page">';

    // Page heading and Add button.
    $html .= '<div class="registration-page-heading">'
        . '<div class="registration-page-intro">'
        . page_intro(
            '',
            ''
        )
        . '</div>'
        . '<button type="button" class="registration-add-btn" '
        . 'id="openRegistrationModal">'
        . '<span aria-hidden="true">＋</span> Add Registration'
        . '</button>'
        . '</div>';
    // Main card.
    $html .= '<section class="card registration-history-card">';

    // Search field.
    $html .= '<div class="registration-filters">'
        . '<input type="search" id="registrationSearch" '
        . 'placeholder="Search employee, ID, department, or training program..." '
        . 'aria-label="Search registrations">';

    // Filters.
    $html .= '<div class="registration-filter-row">'
        . '<select id="registrationDepartment" aria-label="Filter by department">'
        . '<option value="">All Departments</option>';

    foreach ($departments as $department) {
        $html .= '<option value="' . h($department) . '">'
            . h($department) . '</option>';
    }

    $html .= '</select>'
        . '<select id="registrationStatus" aria-label="Filter by status">'
        . '<option value="">All Statuses</option>';

    foreach ($statuses as $status) {
        $html .= '<option value="' . h($status) . '">'
            . h($status) . '</option>';
    }

    $html .= '</select>'
        . '<button type="button" class="registration-clear-filters" '
        . 'id="clearRegistrationFilters">Clear filters</button>'
        . '</div></div>';

    // Table rows.
    $html .= '<div class="table-wrap registration-table-wrap" data-max-rows="5">'
        . '<table class="registration-table" id="registrationTable">'
        . '<thead><tr>'
        . '<th>FACULTY MEMBER</th>'
        . '<th>DEPARTMENT</th>'
        . '<th>TRAINING PROGRAM</th>'
        . '<th>TRAINING DATE</th>'
        . '<th>STATUS</th>'
        . '</tr></thead><tbody>';

    foreach ($joined as $r) {
        $employeeCode = (string)($r['employee_code'] ?? '');
        $employeeName = trim(
            (string)($r['first_name'] ?? '') . ' '
                . (string)($r['last_name'] ?? '')
        );
        $firstName = trim((string)($r['first_name'] ?? ''));
        $lastName = trim((string)($r['last_name'] ?? ''));
        $initials = strtoupper(
            (function_exists('mb_substr') ? mb_substr($firstName, 0, 1) : substr($firstName, 0, 1))
                . (function_exists('mb_substr') ? mb_substr($lastName, 0, 1) : substr($lastName, 0, 1))
        );
        $department = (string)($r['department'] ?? '');
        $programTitle = (string)($r['title'] ?? '');
        $status = (string)($r['status'] ?? '');
        $trainingDate = !empty($r['start_date'])
            ? date('Y-m-d', strtotime((string)$r['start_date']))
            : '';

        $searchText = strtolower(
            $employeeCode . ' ' . $employeeName . ' '
                . $department . ' ' . $programTitle . ' ' . $status
        );

        $html .= '<tr data-search="' . h($searchText) . '" '
            . 'data-department="' . h($department) . '" '
            . 'data-status="' . h($status) . '">';

        $html .= '<td>'
            . '<div class="registration-faculty-member">'
            . '<span class="registration-faculty-avatar" aria-hidden="true">'
            . h($initials !== '' ? $initials : '?')
            . '</span>'
            . '<span class="registration-faculty-details">'
            . '<strong>' . h($employeeName !== '' ? $employeeName : 'Unknown employee') . '</strong>'
            . '<small>' . h($employeeCode) . '</small>'
            . '</span>'
            . '</div>'
            . '</td>'
            . '<td>' . h($department !== '' ? $department : '—') . '</td>'
            . '<td>' . h($programTitle) . '</td>'
            . '<td>' . ($trainingDate !== ''
                ? h(date('M j, Y', strtotime($trainingDate)))
                : '—') . '</td>'
            . '<td>' . badge($status) . '</td>'
            . '</tr>';
    }

    if (!$joined) {
        $html .= '<tr class="registration-empty-row">'
            . '<td colspan="5">No registrations have been created yet.</td>'
            . '</tr>';
    }

    $html .= '</tbody></table></div>';

    // Record count.
    $html .= '<div class="registration-table-footer">'
        . '<span id="registrationCount">'
        . count($joined) . ' registration record(s)'
        . '</span></div>';

    $html .= '</section>';

    // Add Registration modal.
    $html .= '<div class="registration-modal" id="registrationModal" '
        . 'aria-hidden="true">'
        . '<div class="registration-modal-backdrop" '
        . 'data-close-registration-modal></div>'
        . '<section class="registration-modal-dialog" role="dialog" '
        . 'aria-modal="true" aria-labelledby="registrationModalTitle">'

        . '<div class="registration-modal-header">'
        . '<div><h2 id="registrationModalTitle">Add Registration</h2>'
        . '<p>Choose an employee and the training program to enroll them in.</p>'
        . '</div>'
        . '<button type="button" class="registration-modal-close" '
        . 'aria-label="Close modal" data-close-registration-modal>×</button>'
        . '</div>'

        . '<form method="post" class="registration-form">'
        . '<input type="hidden" name="csrf_token" value="'
        . h(csrf_token()) . '">'
        . '<input type="hidden" name="action" value="register_training">'

        . '<div class="form-field">'
        . '<label for="registration-employee">Employee *</label>'
        . '<select id="registration-employee" name="employee_id" required>'
        . '<option value="">Select employee</option>';

    foreach ($emps as $e) {
        $employeeName = trim(
            (string)($e['first_name'] ?? '') . ' '
                . (string)($e['last_name'] ?? '')
        );
        $employeeLabel = trim(
            (string)($e['employee_code'] ?? '') . ' · ' . $employeeName,
            ' ·'
        );

        $html .= '<option value="' . (int)$e['id'] . '">'
            . h($employeeLabel) . '</option>';
    }

    $html .= '</select></div>'
        . '<div class="form-field">'
        . '<label for="registration-program">Training program *</label>'
        . '<select id="registration-program" name="program_id" required>'
        . '<option value="">Select program</option>';

    foreach ($progs as $p) {
        $dateLabel = !empty($p['start_date'])
            ? date('M j, Y', strtotime((string)$p['start_date']))
            : 'Date not set';

        $programLabel = trim(
            (string)($p['title'] ?? '') . ' · ' . $dateLabel,
            ' ·'
        );

        $html .= '<option value="' . (int)$p['id'] . '">'
            . h($programLabel) . '</option>';
    }

    $html .= '</select></div>'
        . '<div class="registration-modal-actions">'
        . '<button type="button" class="btn btn-secondary" '
        . 'data-close-registration-modal>Cancel</button>'
        . '<button type="submit" class="btn">Register employee '
        . icon('arrow', 14) . '</button>'
        . '</div></form></section></div>';

    // Search, filters, and modal behavior.
    $html .= <<<'HTML'
<script>
(function () {
    const root = document.querySelector('.registration-page');
    if (!root || root.dataset.registrationReady === 'true') return;
    root.dataset.registrationReady = 'true';

    const modal = root.querySelector('#registrationModal');
    const openButton = root.querySelector('#openRegistrationModal');
    const search = root.querySelector('#registrationSearch');
    const department = root.querySelector('#registrationDepartment');
    const status = root.querySelector('#registrationStatus');
    const clearButton = root.querySelector('#clearRegistrationFilters');
    const table = root.querySelector('#registrationTable');
    const count = root.querySelector('#registrationCount');

    function filterRows() {
        const query = (search.value || '').trim().toLowerCase();
        const selectedDepartment = department.value;
        const selectedStatus = status.value;
        const rows = Array.from(table.tBodies[0].rows);
        let visible = 0;

        rows.forEach(function (row) {
            if (row.classList.contains('registration-empty-row')) return;

            const matchesSearch =
                !query || (row.dataset.search || '').includes(query);
            const matchesDepartment =
                !selectedDepartment ||
                row.dataset.department === selectedDepartment;
            const matchesStatus =
                !selectedStatus || row.dataset.status === selectedStatus;

            const show = matchesSearch && matchesDepartment && matchesStatus;
            row.hidden = !show;
            if (show) visible++;
        });

        let emptyRow = table.tBodies[0].querySelector('.registration-filter-empty');
        if (visible === 0 && rows.length > 0 &&
            !rows.some(row => row.classList.contains('registration-empty-row'))) {
            if (!emptyRow) {
                emptyRow = document.createElement('tr');
                emptyRow.className = 'registration-empty-row registration-filter-empty';
                emptyRow.innerHTML =
                    '<td colspan="5">No matching registrations found.</td>';
                table.tBodies[0].appendChild(emptyRow);
            }
            emptyRow.hidden = false;
        } else if (emptyRow) {
            emptyRow.hidden = true;
        }

        count.textContent = visible + ' registration record(s)';
    }

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('registration-modal-open');

        const firstSelect = modal.querySelector('select');
        if (firstSelect) firstSelect.focus();
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('registration-modal-open');
        if (openButton) openButton.focus();
    }

    openButton.addEventListener('click', openModal);

    modal.querySelectorAll('[data-close-registration-modal]')
        .forEach(function (button) {
            button.addEventListener('click', closeModal);
        });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    search.addEventListener('input', filterRows);
    department.addEventListener('change', filterRows);
    status.addEventListener('change', filterRows);

    clearButton.addEventListener('click', function () {
        search.value = '';
        department.value = '';
        status.value = '';
        filterRows();
    });
})();
</script>
HTML;

    $html .= '</div>';

    return $html;
}

$page = 'Employee Registration';
$content = render_registration($pdo);

$user = $user ?? (function_exists('current_user') ? current_user() : []);
$flash = $flash ?? ($_SESSION['flash'] ?? null);

render_app($pdo, $user, $page, $flash, $content);
