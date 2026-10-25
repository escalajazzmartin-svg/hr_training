<?php

declare(strict_types=1);

function render_history(PDO $pdo, array $user): string
{
    $where = '';
    $params = [];

    $isEmployee = ($user['role'] ?? '') === 'employee';

    if ($isEmployee) {
        $where = ' WHERE r.employee_id = ?';
        $params[] = (int) $user['employee_id'];
    }

    $sql = 'SELECT r.id, r.status AS reg_status,
        e.employee_code, e.first_name, e.last_name, e.department,
        p.title, p.specialization, p.start_date,
        a.attendance_status, ev.score, c.certificate_code
        FROM registrations r
        JOIN employees e ON e.id = r.employee_id
        JOIN training_programs p ON p.id = r.program_id
        LEFT JOIN attendance a ON a.registration_id = r.id
        LEFT JOIN evaluations ev ON ev.registration_id = r.id
        LEFT JOIN certificates c ON c.registration_id = r.id'
        . $where
        . ' ORDER BY p.start_date DESC, e.last_name, e.first_name';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // History filters
    $historySearch = trim((string) ($_GET['q'] ?? ''));
    $historyDepartment = trim((string) ($_GET['department'] ?? ''));
    $historyStatus = (string) ($_GET['status'] ?? '');

    if (!in_array(
        $historyStatus,
        ['', 'Registered', 'Completed', 'Withdrawn'],
        true
    )) {
        $historyStatus = '';
    }

    // Collect available departments before filtering records.
    $historyDepartments = [];

    foreach ($records as $record) {
        $department = trim((string) ($record['department'] ?? ''));

        if ($department !== '') {
            $historyDepartments[$department] = $department;
        }
    }

    natcasesort($historyDepartments);

    // Apply search, department, and status filters.
    $records = array_values(array_filter(
        $records,
        function (array $record) use (
            $historySearch,
            $historyDepartment,
            $historyStatus
        ): bool {
            if (
                $historyDepartment !== ''
                && ($record['department'] ?? '') !== $historyDepartment
            ) {
                return false;
            }

            if (
                $historyStatus !== ''
                && ($record['reg_status'] ?? '') !== $historyStatus
            ) {
                return false;
            }

            if ($historySearch !== '') {
                $searchable = implode(' ', [
                    (string) ($record['employee_code'] ?? ''),
                    (string) ($record['first_name'] ?? ''),
                    (string) ($record['last_name'] ?? ''),
                    (string) ($record['department'] ?? ''),
                    (string) ($record['title'] ?? ''),
                    (string) ($record['specialization'] ?? ''),
                ]);

                if (stripos($searchable, $historySearch) === false) {
                    return false;
                }
            }

            return true;
        }
    ));

    $today = date('Y-m-d');
    $rows = [];

    foreach ($records as $record) {
        $attendance = (string) ($record['attendance_status'] ?? '');
        $startDate = (string) ($record['start_date'] ?? '');

        if ($startDate > $today) {
            $attendanceLabel = 'Not due';
        } elseif ($attendance === '') {
            $attendanceLabel = 'Not recorded';
        } else {
            $attendanceLabel = $attendance;
        }

        $firstName = trim((string) ($record['first_name'] ?? ''));
        $lastName = trim((string) ($record['last_name'] ?? ''));
        $fullName = trim($firstName . ' ' . $lastName);

        // Generate initials safely from the employee's name.
        $initials = '';
        if ($firstName !== '') {
            $initials .= function_exists('mb_substr')
                ? mb_strtoupper(mb_substr($firstName, 0, 1, 'UTF-8'), 'UTF-8')
                : strtoupper(substr($firstName, 0, 1));
        }
        if ($lastName !== '') {
            $initials .= function_exists('mb_substr')
                ? mb_strtoupper(mb_substr($lastName, 0, 1, 'UTF-8'), 'UTF-8')
                : strtoupper(substr($lastName, 0, 1));
        }

        $facultyMember = '<div class="history-faculty-member">'
            . '<span class="history-faculty-avatar" aria-hidden="true">'
            . h($initials !== '' ? $initials : '?')
            . '</span>'
            . '<span class="history-faculty-details">'
            . '<strong>' . h($fullName !== '' ? $fullName : 'Unknown employee') . '</strong>'
            . '<small>' . h((string) ($record['employee_code'] ?? '')) . '</small>'
            . '</span>'
            . '</div>';

        $rows[] = [
            $facultyMember,
            h($record['title'] ?? ''),
            h($record['specialization'] ?? ''),
            $startDate !== ''
                ? h(date('M j, Y', strtotime($startDate)))
                : '—',
            badge($record['reg_status'] ?? ''),
            h($attendanceLabel),
            ($record['score'] ?? null) !== null
                ? h(number_format((float) $record['score'], 1) . '%')
                : '—',
            !empty($record['certificate_code'])
                ? '<span class="badge badge-success">'
                    . h($record['certificate_code'])
                    . '</span>'
                : '—',
        ];
    }

    // Automatically submit filters when changed.
    $currentPage = (string) ($_GET['page'] ?? 'History');

    $historyFilters = '<form class="history-filter-form" method="get" action="">'
        . '<input type="hidden" name="page" value="' . h($currentPage) . '">'
        . '<div class="history-search-row">'
        . '<input class="search-input" type="search" name="q" value="'
        . h($historySearch)
        . '" placeholder="Search employee, ID, department, or training program..."'
        . ' oninput="clearTimeout(this.form.searchTimer);'
        . ' this.form.searchTimer=setTimeout(() => this.form.requestSubmit(), 350)">'
        . '</div>'
        . '<div class="history-controls-row">'
        . '<select name="department" aria-label="Filter by department" onchange="this.form.requestSubmit()">'
        . '<option value="">All Departments</option>';

    foreach ($historyDepartments as $department) {
        $historyFilters .= '<option value="'
            . h($department)
            . '"'
            . ($historyDepartment === $department ? ' selected' : '')
            . '>'
            . h($department)
            . '</option>';
    }

    $historyFilters .= '</select><select name="status" aria-label="Filter by status" onchange="this.form.requestSubmit()">';

    foreach (
        [
            '' => 'All Statuses',
            'Registered' => 'Registered',
            'Completed' => 'Completed',
            'Withdrawn' => 'Withdrawn',
        ] as $value => $label
    ) {
        $historyFilters .= '<option value="'
            . h($value)
            . '"'
            . ($historyStatus === $value ? ' selected' : '')
            . '>'
            . h($label)
            . '</option>';
    }

    $historyFilters .= '</select>'
        . '<a class="clear-filters" href="?page='
        . rawurlencode($currentPage)
        . '">Clear filters</a>'
        . '</div></form>';

    return '<div class="history-page">'
        . page_intro('', '')
        . '<section class="card">'
        . $historyFilters
        . '<p class="small-muted">Showing '
        . count($rows)
        . ' registration record(s).</p>'
        . '<div class="history-table-scroll">'
        . '<div class="history-table-inner">'
        . render_table(
            [
                'Faculty member',
                'Training program',
                'Specialization',
                'Date',
                'Status',
                'Attendance',
                'Score',
                'Certificate',
            ],
            $rows,
            'No training history is available yet.'
        )
        . '</div></div></section></div>';
}
