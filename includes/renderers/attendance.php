<?php

declare(strict_types=1);

function render_attendance(PDO $pdo, array $user): string
{
    $role = strtolower((string)($user['role'] ?? ''));
    $userId = (int)($user['id'] ?? 0);

    $sql = 'SELECT r.id AS registration_id, r.status AS registration_status, '
        . 'e.employee_code, e.first_name, e.last_name, e.department, '
        . 'p.title, p.start_date, a.attendance_status, a.remarks '
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

    $html .= '<form method="post" class="attendance-form">'
        . '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'
        . '<input type="hidden" name="action" value="mark_attendance">'
        . '<section class="card attendance-card">'
        . section_head('Participant attendance', 'Use the status selector for each participant.')
        . '<div class="session-banner">'
        . '<div class="date-tile light"><b>' . h(strtoupper(date('M'))) . '</b>'
        . '<strong>' . h(date('d')) . '</strong></div>'
        . '<div class="session-banner-copy"><span class="eyebrow">Attendance workspace</span>'
        . '<h3>Training session participant list</h3><p>'
        . icon('calendar', 13) . ' Save attendance statuses to record presence.</p></div>'
        . '<div class="session-stats"><span><b>' . count($data)
        . '</b><small>Registrations</small></span><span><b>' . $presentCount
        . '</b><small>Marked present</small></span></div></div>'
        . '<div class="table-wrap attendance-table-wrap" data-max-rows="5">'
        . '<table class="attendance-table"><thead><tr>'
        . '<th>Faculty member</th><th>Training program</th><th>Session date</th>'
        . '<th>Attendance status</th><th>Remarks</th></tr></thead><tbody>';

    foreach ($data as $r) {
        $id = (int)$r['registration_id'];
        // Leave unrecorded attendance unselected rather than silently marking Present.
        $status = (string)($r['attendance_status'] ?? '');
        $firstName = (string)($r['first_name'] ?? '');
        $lastName = (string)($r['last_name'] ?? '');
        $fullName = trim($firstName . ' ' . $lastName);
        $startDate = !empty($r['start_date'])
            ? date('M j, Y', strtotime((string)$r['start_date']))
            : '—';

        $html .= '<tr><td><div class="person-cell"><span class="avatar">'
            . h(initials($fullName)) . '</span><span class="person-cell-details"><b>'
            . h($fullName) . '</b><small>'
            . h((string)($r['employee_code'] ?? '') . ' · ' . (string)($r['department'] ?? ''))
            . '</small></span></div></td><td>'
            . h((string)($r['title'] ?? '')) . '</td><td>' . h($startDate)
            . '</td><td><select name="attendance_status[' . $id . ']" class="table-input attendance-status" required>'
            . '<option value=""' . ($status === '' ? ' selected' : '') . '>Select status</option>';

        foreach (['Present', 'Late', 'Absent', 'Excused'] as $value) {
            $selected = $status === $value ? ' selected' : '';
            $html .= '<option value="' . h($value) . '"' . $selected . '>'
                . h($value) . '</option>';
        }

        $html .= '</select></td><td><input name="remarks[' . $id
            . ']" class="table-input attendance-remarks" value="'
            . h((string)($r['remarks'] ?? ''))
            . '" placeholder="Optional note" aria-label="Remarks for '
            . h($fullName) . '"></td></tr>';
    }

    $html .= '</tbody></table></div>'
        . '<div class="save-bar attendance-save-bar"><span>Changes are saved to the database when submitted.</span>'
        . '<button class="btn" type="submit">Save attendance ' . icon('check')
        . '</button></div></section></form></div>';

    return $html;
}
