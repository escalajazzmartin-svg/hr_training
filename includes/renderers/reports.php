<?php

declare(strict_types=1);
function render_reports(PDO $pdo, array $user): string
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
    $today = date('Y-m-d');
    // ========================================
    // FILTER VALUES
    // ========================================
    $search = trim((string) ($_GET['q'] ?? ''));
    $department = trim((string) ($_GET['department'] ?? ''));
    $status = (string) ($_GET['status'] ?? '');
    $from = (string) ($_GET['from'] ?? '');
    $to = (string) ($_GET['to'] ?? '');
    if (!in_array(
        $status,
        ['', 'Registered', 'Completed', 'Withdrawn'],
        true
    )) {
        $status = '';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = '';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = '';
    }
    // ========================================
    // REPORT METRICS
    // ========================================
    $complete = count(array_filter(
        $records,
        fn($r) => $r['reg_status'] === 'Completed'
    ));
    $certificateCount = count(array_filter(
        $records,
        fn($r) => !empty($r['certificate_code'])
    ));
    $scores = [];
    foreach ($records as $r) {
        if ($r['score'] !== null) {
            $scores[] = (float) $r['score'];
        }
    }
    $scoreCount = count($scores);
    $average = $scoreCount > 0
        ? array_sum($scores) / $scoreCount
        : null;
    $averageLabel = $average !== null
        ? number_format($average, 1) . '%'
        : '—';
    // ========================================
    // DEPARTMENT OPTIONS
    // ========================================
    $departments = [];
    foreach ($records as $r) {
        $name = trim((string) ($r['department'] ?? ''));
        if ($name !== '') {
            $departments[$name] = $name;
        }
    }
    natcasesort($departments);
    // ========================================
    // ATTENDANCE SUMMARY
    // ========================================
    $dueRecords = array_filter(
        $records,
        fn($r) => (string) $r['start_date'] <= $today
    );
    $recordedAttendance = array_filter(
        $dueRecords,
        fn($r) => $r['attendance_status'] !== null
            && $r['attendance_status'] !== ''
    );
    $present = count(array_filter(
        $recordedAttendance,
        fn($r) => $r['attendance_status'] === 'Present'
    ));
    $attendanceCount = count($recordedAttendance);
    // ========================================
    // APPLY REPORT FILTERS
    // ========================================
    $displayRecords = [];
    foreach ($records as $r) {
        if (
            $status !== ''
            && $r['reg_status'] !== $status
        ) {
            continue;
        }
        if (
            $department !== ''
            && $r['department'] !== $department
        ) {
            continue;
        }
        $date = (string) $r['start_date'];
        if ($from !== '' && $date < $from) {
            continue;
        }
        if ($to !== '' && $date > $to) {
            continue;
        }
        if ($search !== '') {
            $searchable = implode(' ', [
                (string) $r['employee_code'],
                (string) $r['first_name'],
                (string) $r['last_name'],
                (string) $r['department'],
                (string) $r['title'],
                (string) $r['specialization'],
            ]);
            if (stripos($searchable, $search) === false) {
                continue;
            }
        }
        $displayRecords[] = $r;
    }
    // ========================================
    // PREPARE TABLE ROWS
    // ========================================
    $filteredRows = [];
    $printRows = [];
    foreach ($displayRecords as $r) {
        $attendance = (string) ($r['attendance_status'] ?? '');
        if ((string) $r['start_date'] > $today) {
            $attendanceLabel = 'Not due';
        } elseif ($attendance === '') {
            $attendanceLabel = 'Not recorded';
        } else {
            $attendanceLabel = $attendance;
        }
        $firstName = trim((string) ($r['first_name'] ?? ''));
        $lastName = trim((string) ($r['last_name'] ?? ''));
        $fullName = trim($firstName . ' ' . $lastName);
        $initials = strtoupper(
            mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1)
        );
        // Screen table: keep employee name and ID together.
        $filteredRows[] = [
            '<div class="faculty-member-cell">'
                . '<span class="faculty-member-avatar">' . h($initials) . '</span>'
                . '<span class="faculty-member-details">'
                . '<strong>' . h($fullName) . '</strong>'
                . '<small>' . h($r['employee_code'] ?? '') . '</small>'
                . '</span>'
                . '</div>',
            h($r['title'] ?? ''),
            h($r['specialization'] ?? ''),
            h(date('M j, Y', strtotime((string) $r['start_date']))),
            badge($r['reg_status']),
            h($attendanceLabel),
            $r['score'] !== null
                ? h(number_format((float) $r['score'], 1) . '%')
                : '—',
            !empty($r['certificate_code'])
                ? '<span class="badge badge-success">'
                . h($r['certificate_code'])
                . '</span>'
                : '—',
        ];
        // Print table: employee ID and employee name are separate columns.
        $printRows[] = [
            h($r['employee_code'] ?? ''),
            h($fullName),
            h($r['title'] ?? ''),
            h($r['specialization'] ?? ''),
            h(date('M j, Y', strtotime((string) $r['start_date']))),
            h($r['reg_status'] ?? ''),
            h($attendanceLabel),
            $r['score'] !== null
                ? h(number_format((float) $r['score'], 1) . '%')
                : '—',
            h($r['certificate_code'] ?? '—'),
        ];
    }
    // ========================================
    // ========================================
    // PRINT-ONLY TABLE
    // ========================================
    $printTableHtml = '<table class="reports-print-table"><thead><tr>';
    foreach (
        [
            'Employee ID',
            'Employee Name',
            'Training program',
            'Specialization',
            'Date',
            'Status',
            'Attendance',
            'Score',
            'Certificate',
        ] as $heading
    ) {
        $printTableHtml .= '<th>' . h($heading) . '</th>';
    }
    $printTableHtml .= '</tr></thead><tbody>';
    if ($printRows === []) {
        $printTableHtml .= '<tr><td colspan="9">'
            . 'No training records match your current filters.'
            . '</td></tr>';
    } else {
        foreach ($printRows as $row) {
            $printTableHtml .= '<tr>';
            foreach ($row as $cell) {
                $printTableHtml .= '<td>' . $cell . '</td>';
            }
            $printTableHtml .= '</tr>';
        }
    }
    $printTableHtml .= '</tbody></table>';
    // PAGE HEADER
    // ========================================
    $html = page_intro(
        '',
        '',
        '<div class="report-header-actions">'
            . '<a class="btn" href="?page=Reports&export=training">'
            . icon('download')
            . ' Export CSV</a>'
            . '<button class="btn" type="button" onclick="window.print()">'
            . 'Print</button>'
            . '</div>'
    );
    // ========================================
    // OUTCOME SUMMARY PERCENTAGES
    // ========================================
    $totalRecords = count($records);
    $completionPercent = $totalRecords > 0
        ? (int) round($complete / $totalRecords * 100)
        : 0;
    $attendancePercent = $attendanceCount > 0
        ? (int) round($present / $attendanceCount * 100)
        : 0;
    $certificatePercent = $totalRecords > 0
        ? (int) round($certificateCount / $totalRecords * 100)
        : 0;
    // ========================================
    // OUTCOME SUMMARY
    // ========================================
    $html .= '<div class="dashboard-grid">'
        . '<section class="card">'
        . section_head('Outcome summary')
        . '<div class="bar-list">'
        // COMPLETION
        . '<div>'
        . '<span><b>Completed</b><small>'
        . $complete . ' / ' . $totalRecords
        . '</small></span>'
        . '<span class="progress"><i style="width:'
        . $completionPercent
        . '%"></i></span>'
        . '</div>'
        // ATTENDANCE
        . '<div>'
        . '<span><b>Present attendance</b><small>'
        . $present . ' / ' . $attendanceCount
        . ' recorded</small></span>'
        . '<span class="progress"><i class="warn" style="width:'
        . $attendancePercent
        . '%"></i></span>'
        . '</div>'
        // CERTIFICATES
        . '<div>'
        . '<span><b>Certificates issued</b><small>'
        . $certificateCount
        . ' total</small></span>'
        . '<span class="progress"><i style="width:'
        . $certificatePercent
        . '%"></i></span>'
        . '</div>'
        . '</div>'
        . '</section>'
        . '</div>';
    // ========================================
    // TRAINING PARTICIPATION
    // ========================================
    $html .= '<div class="dashboard-grid">'
        . '<section class="card span-2">'
        // FILTER FORM
        . '<form class="report-filter-form" method="get" action="?page=Reports">'
        // SEARCH BAR
        . '<div class="report-search-row">'
        . '<input class="search-input" type="search" name="q" value="'
        . h($search)
        . '" placeholder="Search employee, ID, department, program, or specialization..."'
        . ' oninput="clearTimeout(this.form.searchTimer);'
        . ' this.form.searchTimer=setTimeout(() => this.form.requestSubmit(), 350)">'
        . '</div>'
        // DEPARTMENT, STATUS, AND DATE FILTERS
        . '<div class="report-controls-row">'
        . '<select name="department" onchange="this.form.requestSubmit()">'
        . '<option value="">All departments</option>';
    foreach ($departments as $item) {
        $html .= '<option value="'
            . h($item)
            . '"'
            . ($department === $item ? ' selected' : '')
            . '>'
            . h($item)
            . '</option>';
    }
    $html .= '</select>'
        . '<select name="status" onchange="this.form.requestSubmit()">'
        . '<option value="">All statuses</option>';
    foreach (['Registered', 'Completed', 'Withdrawn'] as $item) {
        $html .= '<option value="'
            . h($item)
            . '"'
            . ($status === $item ? ' selected' : '')
            . '>'
            . h($item)
            . '</option>';
    }
    $html .= '</select>'
        . '<label class="report-date-filter">'
        . 'From '
        . '<input type="date" name="from"'
        . ' onchange="this.form.requestSubmit()"'
        . ' value="' . h($from) . '">'
        . '</label>'
        . '<label class="report-date-filter">'
        . 'To '
        . '<input type="date" name="to"'
        . ' onchange="this.form.requestSubmit()"'
        . ' value="' . h($to) . '">'
        . '</label>'
        . '<a class="btn btn-ghost report-clear-filters"'
        . ' href="?page=Reports">Clear filters</a>'
        . '</div>'
        . '</form>'
        // RESULTS INFORMATION
        . '<p class="small-muted">Showing '
        . count($displayRecords)
        . ' of '
        . $totalRecords
        . ' registration record(s). Average assessment uses '
        . $scoreCount
        . ' record(s) with a saved score.</p>'
        // TABLE
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
            $filteredRows,
            'No training records match your current filters.'
        )
        . '</div></div>'
        . $printTableHtml
        . '</section>';
    return '<div class="reports-page">' . $html . '</div>';
}
