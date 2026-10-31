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

    // Page heading.
    $html .= '<div class="registration-page-heading">'
        . '<div class="registration-page-intro">'
        . page_intro(
            '',
            ''
        )
        . '</div>'
        . '<button type="button" class="registration-add-btn registration-add-btn-desktop" '
        . 'id="openRegistrationModalDesktop">'
        . '<span aria-hidden="true">＋</span> Add Registration'
        . '</button>'
        . '</div>';
    // Main card.
    $html .= '<section class="card registration-history-card">'
        . '<div class="registration-directory-actions">'
        . '<button type="button" class="registration-add-btn registration-add-btn-compact" '
        . 'id="openRegistrationModalCompact">'
        . '<span aria-hidden="true">＋</span> Add Registration'
        . '</button>'
        . '<div class="registration-header-actions">'
        . '<button type="button" class="registration-header-toggle" '
        . 'id="registration-search-toggle" aria-expanded="false" '
        . 'aria-controls="registration-search-panel" title="Search" '
        . 'aria-label="Search"><i class="fa fa-search" aria-hidden="true"></i>'
        . '</button>'
        . '<button type="button" class="registration-header-toggle" '
        . 'id="registration-filters-toggle" aria-expanded="false" '
        . 'aria-controls="registration-filter-panel" title="Filters" '
        . 'aria-label="Filters"><i class="fa fa-sliders" aria-hidden="true"></i>'
        . '</button>'
        . '</div>'
        . '</div>';

    // Search field.
    $html .= '<div class="registration-filters">'
        . '<div id="registration-search-panel" class="registration-search-panel">'
        . '<input type="search" id="registrationSearch" '
        . 'placeholder="Search employee, ID, department, or training program..." '
        . 'aria-label="Search registrations">'
        . '</div>';

    // Filters.
    $html .= '<div id="registration-filter-panel" class="registration-filter-panel">'
        . '<div class="registration-filter-row">'
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
        . '</div></div></div>';

    // Table rows.
    $html .= '<div class="table-wrap registration-table-wrap" data-max-rows="5">'
        . '<table class="registration-table" id="registrationTable">'
        . '<thead><tr>'
        . '<th>FACULTY MEMBER</th>'
        . '<th>DEPARTMENT</th>'
        . '<th>TRAINING PROGRAM</th>'
        . '<th>TRAINING DATE</th>'
        . '<th>STATUS</th>'
        . '<th>ACTION</th>'
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
            . 'data-status="' . h($status) . '" '
            . 'data-registration-id="' . (int)$r['id'] . '" '
            . 'data-employee-id="' . (int)$r['employee_id'] . '" '
            . 'data-program-id="' . (int)$r['program_id'] . '">';

        $html .= '<td data-label="Faculty member">'
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
            . '<td data-label="Department">' . h($department !== '' ? $department : '—') . '</td>'
            . '<td data-label="Training program">' . h($programTitle) . '</td>'
            . '<td data-label="Training date">' . ($trainingDate !== ''
                ? h(date('M j, Y', strtotime($trainingDate)))
                : '—') . '</td>'
            . '<td data-label="Status">' . badge($status) . '</td>'
            . '<td data-label="Action" class="registration-actions">'
            . '<button type="button" class="registration-action registration-edit-action" '
            . 'data-edit-registration aria-label="Edit registration for '
            . h($employeeName) . '" title="Edit registration">'
            . '<i class="fa fa-pencil" aria-hidden="true"></i>'
            . '</button>'
            . '<form method="post" class="registration-delete-form">'
            . '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'
            . '<input type="hidden" name="action" value="delete_registration">'
            . '<input type="hidden" name="registration_id" value="' . (int)$r['id'] . '">'
            . '<button type="submit" class="registration-action registration-delete-action" '
            . 'aria-label="Delete registration for ' . h($employeeName) . '" '
            . 'title="Delete registration">'
            . '<i class="fa fa-trash" aria-hidden="true"></i>'
            . '</button>'
            . '</form>'
            . '</td>'
            . '</tr>';
    }

    if (!$joined) {
        $html .= '<tr class="registration-empty-row">'
            . '<td colspan="6">No registrations have been created yet.</td>'
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

    $html .= '<div class="registration-modal" id="editRegistrationModal" '
        . 'aria-hidden="true">'
        . '<div class="registration-modal-backdrop" '
        . 'data-close-registration-modal></div>'
        . '<section class="registration-modal-dialog" role="dialog" '
        . 'aria-modal="true" aria-labelledby="editRegistrationModalTitle">'
        . '<div class="registration-modal-header">'
        . '<div><h2 id="editRegistrationModalTitle">Edit Registration</h2>'
        . '<p>Update the employee, training program, or registration status.</p>'
        . '</div>'
        . '<button type="button" class="registration-modal-close" '
        . 'aria-label="Close modal" data-close-registration-modal>×</button>'
        . '</div>'
        . '<form method="post" class="registration-form">'
        . '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'
        . '<input type="hidden" name="action" value="update_registration">'
        . '<input type="hidden" name="registration_id" id="edit-registration-id">'
        . '<div class="form-field">'
        . '<label for="edit-registration-employee">Employee *</label>'
        . '<select id="edit-registration-employee" name="employee_id" required>';

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
        . '<label for="edit-registration-program">Training program *</label>'
        . '<select id="edit-registration-program" name="program_id" required>';

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
        . '<div class="form-field">'
        . '<label for="edit-registration-status">Status *</label>'
        . '<select id="edit-registration-status" name="status" required>'
        . '<option value="Registered">Registered</option>'
        . '<option value="In Progress">In Progress</option>'
        . '<option value="Completed">Completed</option>'
        . '<option value="Withdrawn">Withdrawn</option>'
        . '</select></div>'
        . '<div class="registration-modal-actions">'
        . '<button type="button" class="btn btn-secondary" '
        . 'data-close-registration-modal>Cancel</button>'
        . '<button type="submit" class="btn">Save changes '
        . icon('check', 14) . '</button>'
        . '</div></form></section></div>';

    // Search, filters, and modal behavior.
    $html .= <<<'HTML'
<script>
(function () {
    const root = document.querySelector('.registration-page');
    if (!root || root.dataset.registrationReady === 'true') return;
    root.dataset.registrationReady = 'true';

    const modal = root.querySelector('#registrationModal');
    const editModal = root.querySelector('#editRegistrationModal');
    const openButtons = root.querySelectorAll(
        '[id^="openRegistrationModal"]'
    );
    const editEmployee = root.querySelector('#edit-registration-employee');
    const editProgram = root.querySelector('#edit-registration-program');
    const editStatus = root.querySelector('#edit-registration-status');
    const editId = root.querySelector('#edit-registration-id');
    const search = root.querySelector('#registrationSearch');
    const department = root.querySelector('#registrationDepartment');
    const status = root.querySelector('#registrationStatus');
    const clearButton = root.querySelector('#clearRegistrationFilters');
    const searchToggle = root.querySelector('#registration-search-toggle');
    const filtersToggle = root.querySelector('#registration-filters-toggle');
    const searchPanel = root.querySelector('#registration-search-panel');
    const filterPanel = root.querySelector('#registration-filter-panel');
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
                    '<td colspan="6">No matching registrations found.</td>';
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
        if (openButtons.length > 0) openButtons[0].focus();
    }

    function openEditModal(row) {
        editId.value = row.dataset.registrationId || '';
        editEmployee.value = row.dataset.employeeId || '';
        editProgram.value = row.dataset.programId || '';
        editStatus.value = row.dataset.status || 'Registered';
        editModal.classList.add('is-open');
        editModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('registration-modal-open');
        editEmployee.focus();
    }

    function closeEditModal() {
        editModal.classList.remove('is-open');
        editModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('registration-modal-open');
    }

    openButtons.forEach(function (button) {
        button.addEventListener('click', openModal);
    });

    searchToggle.addEventListener('click', function () {
        const isOpen = root.classList.toggle('registration-search-open');
        searchToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen) search.focus();
    });

    filtersToggle.addEventListener('click', function () {
        const isOpen = root.classList.toggle('registration-filters-open');
        filtersToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    root.querySelectorAll('[data-close-registration-modal]')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                if (button.closest('#editRegistrationModal')) {
                    closeEditModal();
                    return;
                }
                closeModal();
            });
        });

    root.querySelectorAll('[data-edit-registration]')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                openEditModal(button.closest('tr'));
            });
        });

    root.querySelectorAll('.registration-delete-form')
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(
                    'Delete this registration? Related attendance, evaluation, and certificate records will also be deleted.'
                )) {
                    event.preventDefault();
                }
            });
        });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
        if (event.key === 'Escape' && editModal.classList.contains('is-open')) {
            closeEditModal();
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
