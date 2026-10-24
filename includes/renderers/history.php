<?php
declare(strict_types=1);

function render_history(PDO $pdo, array $user, bool $report = false): string {
    $where = '';
    $params = [];
    $isEmployee = $user['role'] === 'employee';

    if ($isEmployee) {
        $where = ' WHERE r.employee_id = ?';
        $params[] = (int)$user['employee_id'];
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
    $records = $stmt->fetchAll();

    $today = date('Y-m-d');
    $rows = [];

    foreach ($records as $r) {
        $attendance = (string)$r['attendance_status'];

        if ((string)$r['start_date'] > $today) {
            $attendanceLabel = 'Not due';
        } elseif ($attendance === '') {
            $attendanceLabel = 'Not recorded';
        } else {
            $attendanceLabel = $attendance;
        }

        $rows[] = [
            '<span style="white-space:nowrap">'
            . h($r['employee_code'])
            . '</span>',
            h($r['first_name'] . ' ' . $r['last_name']),
            h($r['title']),
            h($r['specialization']),
            h(date('M j, Y', strtotime($r['start_date']))),
            badge($r['reg_status']),
            h($attendanceLabel),
            $r['score'] !== null
                ? h(number_format((float)$r['score'], 1) . '%')
                : '—',
            !empty($r['certificate_code'])
                ? '<span class="badge badge-success">'
                    . h($r['certificate_code'])
                    . '</span>'
                : '—'
        ];
    }

    if ($report) {
        $metricFilter = (string)($_GET['metric'] ?? 'all');

        if (!in_array(
            $metricFilter,
            ['all', 'completed', 'assessed', 'certificates'],
            true
        )) {
            $metricFilter = 'all';
        }

        $search = trim((string)($_GET['q'] ?? ''));
        $department = trim((string)($_GET['department'] ?? ''));
        $status = (string)($_GET['status'] ?? '');
        $from = (string)($_GET['from'] ?? '');
        $to = (string)($_GET['to'] ?? '');

        if (!in_array($status, ['', 'Registered', 'Completed', 'Withdrawn'], true)) {
            $status = '';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = '';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = '';
        }

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
                $scores[] = (float)$r['score'];
            }
        }

        $scoreCount = count($scores);
        $average = $scoreCount > 0
            ? array_sum($scores) / $scoreCount
            : null;

        $averageLabel = $average !== null
            ? number_format($average, 1) . '%'
            : '—';

        $departments = [];

        foreach ($records as $r) {
            $name = trim((string)$r['department']);

            if ($name !== '') {
                $departments[$name] = $name;
            }
        }

        natcasesort($departments);

        $dueRecords = array_filter(
            $records,
            fn($r) => (string)$r['start_date'] <= $today
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

        $displayRecords = [];

        foreach ($records as $r) {
            if (
                $metricFilter === 'completed' &&
                $r['reg_status'] !== 'Completed'
            ) {
                continue;
            }

            if (
                $metricFilter === 'assessed' &&
                $r['score'] === null
            ) {
                continue;
            }

            if (
                $metricFilter === 'certificates' &&
                empty($r['certificate_code'])
            ) {
                continue;
            }

            if (
                $status !== '' &&
                $r['reg_status'] !== $status
            ) {
                continue;
            }

            if (
                $department !== '' &&
                $r['department'] !== $department
            ) {
                continue;
            }

            $date = (string)$r['start_date'];

            if ($from !== '' && $date < $from) {
                continue;
            }

            if ($to !== '' && $date > $to) {
                continue;
            }

            if ($search !== '') {
                $searchable = implode(' ', [
                    (string)$r['employee_code'],
                    (string)$r['first_name'],
                    (string)$r['last_name'],
                    (string)$r['department'],
                    (string)$r['title'],
                    (string)$r['specialization']
                ]);

                if (stripos($searchable, $search) === false) {
                    continue;
                }
            }

            $displayRecords[] = $r;
        }

        $filteredRows = [];

        foreach ($displayRecords as $r) {
            $attendance = (string)$r['attendance_status'];

            if ((string)$r['start_date'] > $today) {
                $attendanceLabel = 'Not due';
            } elseif ($attendance === '') {
                $attendanceLabel = 'Not recorded';
            } else {
                $attendanceLabel = $attendance;
            }

            $filteredRows[] = [
                '<span style="white-space:nowrap">'
                . h($r['employee_code'])
                . '</span>',
                h($r['first_name'] . ' ' . $r['last_name']),
                h($r['title']),
                h($r['specialization']),
                h(date('M j, Y', strtotime($r['start_date']))),
                badge($r['reg_status']),
                h($attendanceLabel),
                $r['score'] !== null
                    ? h(number_format((float)$r['score'], 1) . '%')
                    : '—',
                !empty($r['certificate_code'])
                    ? '<span class="badge badge-success">'
                        . h($r['certificate_code'])
                        . '</span>'
                    : '—'
            ];
        }

        $html = page_intro(
            'Reports & Analytics',
            'Review training participation, attendance, assessments, and certification outcomes.',
            '<a class="btn" href="?page=Reports&export=training">'
            . icon('download')
            . ' Export CSV</a>'
        );

        $html .= '<form class="report-tiles" method="get" action="?page=Reports">'
            . '<button type="submit" name="metric" value="all" class="'
            . ($metricFilter === 'all' ? 'active' : '')
            . '"><span class="report-icon">'
            . icon('users')
            . '</span><b>Total registrations</b><small>'
            . count($records)
            . ' training enrollments</small></button>'

            . '<button type="submit" name="metric" value="completed" class="'
            . ($metricFilter === 'completed' ? 'active' : '')
            . '"><span class="report-icon">'
            . icon('check')
            . '</span><b>Completed training</b><small>'
            . $complete
            . ' marked complete</small></button>'

            . '<button type="submit" name="metric" value="assessed" class="'
            . ($metricFilter === 'assessed' ? 'active' : '')
            . '"><span class="report-icon">'
            . icon('chart')
            . '</span><b>Average assessment</b><small>'
            . h($averageLabel)
            . ' · '
            . $scoreCount
            . ' scored record(s)</small></button>'

            . '<button type="submit" name="metric" value="certificates" class="'
            . ($metricFilter === 'certificates' ? 'active' : '')
            . '"><span class="report-icon">'
            . icon('award')
            . '</span><b>Certificates issued</b><small>'
            . $certificateCount
            . ' certificate(s)</small></button>'
            . '</form>';

        $completionPercent = count($records) > 0
            ? (int)round($complete / count($records) * 100)
            : 0;

        $attendancePercent = $attendanceCount > 0
            ? (int)round($present / $attendanceCount * 100)
            : 0;

        $certificatePercent = count($records) > 0
            ? (int)round($certificateCount / count($records) * 100)
            : 0;

        $html .= '<div class="dashboard-grid">'
            . '<section class="card span-2">'
            . section_head(
                'Training participation',
                'Registration records with attendance and assessment results'
            )
            . '<form class="filter-row" method="get" action="?page=Reports">'
            . '<input type="hidden" name="metric" value="'
            . h($metricFilter)
            . '">'
            . '<input class="search-input" type="search" name="q" value="'
            . h($search)
            . '" placeholder="Search employee, ID, program, or specialization...">'
            . '<select name="department">'
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

        $html .= '</select><select name="status">'
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
            . '<label class="small-muted">From <input type="date" name="from" value="'
            . h($from)
            . '"></label>'
            . '<label class="small-muted">To <input type="date" name="to" value="'
            . h($to)
            . '"></label>'
            . '<button class="btn btn-secondary" type="submit">'
            . icon('search')
            . ' Filter</button>'
            . '<a class="btn btn-ghost" href="?page=Reports">Clear</a>'
            . '</form>'
            . '<p class="small-muted">Showing '
            . count($displayRecords)
            . ' of '
            . count($records)
            . ' registration record(s). Average assessment uses '
            . $scoreCount
            . ' record(s) with a saved score.</p>'
            . '<div style="width:100%;max-width:100%;overflow-x:auto">'
            . '<div style="min-width:1050px">'
            . render_table(
                [
                    'Employee ID',
                    'Faculty member',
                    'Training program',
                    'Specialization',
                    'Date',
                    'Status',
                    'Attendance',
                    'Score',
                    'Certificate'
                ],
                $filteredRows,
                'No training records match your current filters.'
            )
            . '</div></div></section>'

            . '<section class="card">'
            . section_head(
                'Outcome summary',
                'Completion, recorded attendance, and certification'
            )
            . '<div class="bar-list">'
            . '<div><span><b>Completed</b><small>'
            . $complete . ' / ' . count($records)
            . '</small></span><span class="progress"><i style="width:'
            . $completionPercent
            . '%"></i></span></div>'

            . '<div><span><b>Present attendance</b><small>'
            . $present . ' / ' . $attendanceCount
            . ' recorded</small></span><span class="progress"><i class="warn" style="width:'
            . $attendancePercent
            . '%"></i></span></div>'

            . '<div><span><b>Certificates issued</b><small>'
            . $certificateCount
            . ' total</small></span><span class="progress"><i style="width:'
            . $certificatePercent
            . '%"></i></span></div>'
            . '</div>'
            . '<p class="small-muted" style="margin-top:1rem">Future training sessions are excluded from the attendance percentage. Sessions without a saved attendance status are not counted as present or absent.</p>'
            . '</section></div>';

        return $html;
    }

    return page_intro(
        $isEmployee ? 'My Training History' : 'Training History',
        'A record of training participation, completion, attendance, assessments, and certificates.'
    )
        . '<section class="card">'
        . section_head(
            'Training records',
            'Records are based on saved training registrations.'
        )
        . '<div style="width:100%;max-width:100%;overflow-x:auto">'
        . '<div style="min-width:1050px">'
        . render_table(
            [
                'Employee ID',
                'Faculty member',
                'Training program',
                'Specialization',
                'Date',
                'Status',
                'Attendance',
                'Score',
                'Certificate'
            ],
            $rows,
            'No training history is available yet.'
        )
        . '</div></div></section>';
}
