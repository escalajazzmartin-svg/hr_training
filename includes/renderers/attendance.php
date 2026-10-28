<?php

declare(strict_types=1);

function render_attendance(PDO $pdo, array $user): string
{
    $role = strtolower((string)($user['role'] ?? ''));
    $userId = (int)($user['id'] ?? 0);

    $sql = 'SELECT r.id AS registration_id, r.status AS registration_status, p.id AS program_id, '
        . 'e.employee_code, e.first_name, e.last_name, e.department, '
        . 'p.title, p.status AS program_status, p.start_date, a.attendance_status, a.remarks '
        . 'FROM registrations r '
        . 'JOIN employees e ON e.id = r.employee_id '
        . 'JOIN training_programs p ON p.id = r.program_id '
        . 'LEFT JOIN attendance a ON a.registration_id = r.id';

    $params = [];
    if ($role === 'trainer') {
        $sql .= ' WHERE p.trainer_user_id = ?';
        $params[] = $userId;
    }

    $sql .= ' ORDER BY p.start_date DESC, e.last_name, e.first_name';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $data = $st->fetchAll(PDO::FETCH_ASSOC);

    $html = '<div class="attendance-page">';
    $html .= page_intro(
        '',
        ''
    );

    if (!$data) {
        $html .= '<section class="card attendance-empty-card">'
            . '<div class="empty-module"><div class="program-icon">'
            . icon('check', 26)
            . '</div><h3>No attendance records yet</h3>'
            . '<p>Register employees for training programs first, and they will appear here.</p>'
            . '<a class="btn" href="?page='
            . ($role === 'admin' ? 'Employee Registration' : 'My Training Programs')
            . '">View registrations</a></div></section></div>';
        return $html;
    }

    $presentCount = count(array_filter(
        $data,
        static fn(array $row): bool => ($row['attendance_status'] ?? '') === 'Present'
    ));
    $canEditAttendance = $role === 'trainer';

    $eventOptions = [];
    foreach ($data as $row) {
        $eventName = trim((string)($row['title'] ?? ''));
        if ($eventName === '') {
            continue;
        }

        $status = trim((string)($row['program_status'] ?? ''));
        $statusOrder = ['Ongoing' => 0, 'Completed' => 1, 'Upcoming' => 2];
        $order = array_key_exists($status, $statusOrder) ? $statusOrder[$status] : 99;
        $eventOptions[$eventName] = [
            'id' => (int)($row['program_id'] ?? 0),
            'title' => $eventName,
            'status' => $status,
            'order' => $order,
        ];
    }

    uasort($eventOptions, static function (array $a, array $b): int {
        if ($a['order'] !== $b['order']) {
            return $a['order'] <=> $b['order'];
        }

        return strcasecmp($a['title'], $b['title']);
    });

    if ($role === 'trainer') {
        $programOptions = '';
        foreach ($eventOptions as $event) {
            $programOptions .= '<option value="' . h((string)($event['id'] ?? 0)) . '">' . h($event['title']) . '</option>';
        }
        $currentQr = $_SESSION['qr_attendance'] ?? null;
        $qrMarkup = '';
        if (is_array($currentQr) && !empty($currentQr['image'])) {
            $qrMarkup = '<div class="qr-attendance-display"><div class="qr-code-box"><img src="data:image/png;base64,' . base64_encode($currentQr['image']) . '" alt="Attendance QR code"></div>'
                . '<div class="qr-attendance-meta"><strong>' . h(strtoupper(str_replace('_', '-', (string)$currentQr['action']))) . '</strong>'
                . '<p>Expires: ' . h((string)$currentQr['expires_at']) . '</p>'
                . '</div></div>';
            unset($_SESSION['qr_attendance']);
        }

        $html .= '<section class="card qr-attendance-card">'
            . '<div class="qr-attendance-header"><span class="eyebrow">QR attendance</span><h3>Generate session QR code</h3></div>'
            . '<form method="post" class="qr-form"><input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="generate_qr_code">'
            . '<div class="qr-form-grid"><label>Training session<select name="program_id" required>' . $programOptions . '</select></label>'
            . '<label>QR action<select name="qr_action"><option value="time_in">TIME-IN QR</option><option value="time_out">TIME-OUT QR</option></select></label>'
            . '<button type="submit" class="btn">Generate QR</button></div></form>'
            . $qrMarkup
            . '</section>';
    }

    $html .= '<form method="post" class="attendance-form">'
        . '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'
        . '<input type="hidden" name="action" value="mark_attendance">'
        . '<section class="card attendance-card">';

    if (!$canEditAttendance) {
    }

    $html .= '<div class="session-banner">'
        . '<div class="date-tile light"><b>' . h(strtoupper(date('M'))) . '</b>'
        . '<strong>' . h(date('d')) . '</strong></div>'
        . '<div class="session-banner-copy"><span class="eyebrow">Attendance workspace</span>'
        . '<h3>Training session participant list</h3><p>'
        . icon('calendar', 13) . ' Record time in and time out for each participant.</p></div>'
        . '<div class="session-stats"><span><b>' . count($data)
        . '</b><small>Registrations</small></span><span><b>' . $presentCount
        . '</b><small>Present</small></span></div></div>'
        . '<div class="attendance-filter-row">'
        . '<select class="attendance-filter-select" id="attendance-event-filter" aria-label="Filter attendance by event">'
        . '<option value="">Select event</option>';

    foreach ($eventOptions as $event) {
        $label = $event['status'] !== '' ? $event['status'] . ' · ' . $event['title'] : $event['title'];
        $html .= '<option value="' . h($event['title']) . '">' . h($label) . '</option>';
    }

    $html .= '</select>'
        . '<select class="attendance-filter-select attendance-status-filter" id="attendance-status-filter" aria-label="Filter attendance by status">'
        . '<option value="">All status</option>'
        . '<option value="Present">Present</option>'
        . '<option value="Late">Late</option>'
        . '<option value="Absent">Absent</option>'
        . '</select>'
        . '</div>'
        . '<div class="table-wrap attendance-table-wrap" data-max-rows="5">'
        . '<table class="attendance-table"><thead><tr>'
        . '<th>Faculty member</th><th>Training program</th><th>Session date</th>'
        . '<th>Time in</th><th>Time out</th></tr></thead><tbody>';

    foreach ($data as $r) {
        $id = (int)$r['registration_id'];
        // Leave unrecorded attendance unselected rather than silently marking Present.
        $firstName = (string)($r['first_name'] ?? '');
        $lastName = (string)($r['last_name'] ?? '');
        $fullName = trim($firstName . ' ' . $lastName);
        $programTitle = (string)($r['title'] ?? '');
        $timeIn = '';
        $timeOut = '';
        $startDate = !empty($r['start_date'])
            ? date('M j, Y', strtotime((string)$r['start_date']))
            : '—';

        $attendanceStatus = (string)($r['attendance_status'] ?? '');

        $html .= '<tr data-event="' . h($programTitle) . '" data-status="' . h($attendanceStatus) . '"><td><div class="person-cell"><span class="avatar">'
            . h(initials($fullName)) . '</span><span class="person-cell-details"><b>'
            . h($fullName) . '</b><small>'
            . h((string)($r['employee_code'] ?? '') . ' · ' . (string)($r['department'] ?? ''))
            . '</small></span></div></td><td>'
            . h($programTitle) . '</td><td>' . h($startDate)
            . '</td><td><div class="attendance-time-wrap">'
            . '<label class="attendance-time-label" for="time_in_' . $id . '">Time in</label>'
            . '<input id="time_in_' . $id . '" type="time" name="time_in[' . $id . ']" class="table-input attendance-time attendance-time-field" value="' . h($timeIn) . '" aria-label="Time in for ' . h($fullName) . '"' . ($canEditAttendance ? '' : ' readonly disabled') . '></div></td>'
            . '<td><div class="attendance-time-wrap">'
            . '<label class="attendance-time-label" for="time_out_' . $id . '">Time out</label>'
            . '<input id="time_out_' . $id . '" type="time" name="time_out[' . $id . ']" class="table-input attendance-time attendance-time-field" value="' . h($timeOut) . '" aria-label="Time out for ' . h($fullName) . '"' . ($canEditAttendance ? '' : ' readonly disabled') . '></div></td></tr>';
    }

    $html .= '</tbody></table></div>'
        . ($canEditAttendance
            ? '<div class="save-bar attendance-save-bar"><span>Changes are saved to the database when submitted.</span>'
                . '<button class="btn" type="submit">Save attendance ' . icon('check')
                . '</button></div>'
            : '<div class="save-bar attendance-save-bar"><span>Attendance records are view-only for admins.</span></div>')
        . '</section></form>'
        . '<script>'
        . '(function () {'
        . 'var eventFilter = document.getElementById("attendance-event-filter");'
        . 'var statusFilter = document.getElementById("attendance-status-filter");'
        . 'var rows = Array.prototype.slice.call(document.querySelectorAll(".attendance-table tbody tr[data-event]"));'
        . 'if ((!eventFilter && !statusFilter) || rows.length === 0) return;'
        . 'var applyFilters = function () {'
        . 'var selectedEvent = eventFilter ? eventFilter.value.trim() : "";'
        . 'var selectedStatus = statusFilter ? statusFilter.value.trim() : "";'
        . 'rows.forEach(function (row) {'
        . 'var eventName = (row.getAttribute("data-event") || "").trim();'
        . 'var statusName = (row.getAttribute("data-status") || "").trim();'
        . 'var eventMatch = selectedEvent === "" || eventName === selectedEvent;'
        . 'var statusMatch = selectedStatus === "" || statusName === selectedStatus;'
        . 'row.style.display = (eventMatch && statusMatch) ? "" : "none";'
        . '});'
        . '};'
        . 'if (eventFilter) eventFilter.addEventListener("change", applyFilters);'
        . 'if (statusFilter) statusFilter.addEventListener("change", applyFilters);'
        . '})();'
        . '</script>'
        . '</div>';

    return $html;
}
