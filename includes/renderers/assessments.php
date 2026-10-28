<?php

declare(strict_types=1);

function render_assessments(PDO $pdo, array $user): string
{
    $role = strtolower(trim((string)($user['role'] ?? '')));
    $userId = (int)($user['id'] ?? 0);
    $employeeId = (int)($user['employee_id'] ?? 0);

    $sql = 'SELECT '
        . 'r.id AS registration_id, '
        . 'r.status AS registration_status, '
        . 'e.employee_code, e.first_name, e.last_name, e.department, '
        . 'p.title, p.start_date, '
        . 'ev.score, ev.feedback '
        . 'FROM registrations r '
        . 'JOIN employees e ON e.id = r.employee_id '
        . 'JOIN training_programs p ON p.id = r.program_id '
        . 'LEFT JOIN evaluations ev ON ev.registration_id = r.id';

    $params = [];

    if ($role === 'trainer') {
        $sql .= ' WHERE p.trainer_user_id = ?';
        $params[] = $userId;
    } elseif ($role === 'employee') {
        if ($employeeId <= 0) {
            return '<div class="assessment-page">'
                . page_intro(
                    '',
                    ''
                )
                . '<section class="card assessment-empty-card">'
                . '<div class="empty-module">'
                . '<h3>Employee profile unavailable</h3>'
                . '<p>Your account is not linked to an employee record.</p>'
                . '</div></section></div>';
        }

        $sql .= ' WHERE r.employee_id = ?';
        $params[] = $employeeId;
    }

    $sql .= ' ORDER BY p.start_date DESC, e.last_name, e.first_name';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $isEmployee = $role === 'employee';

    $html = '<div class="assessment-page">';

    $html .= page_intro(
        $isEmployee ? '' : '',
        $isEmployee
            ? ''
            : ''
    );

    /*
     * Employee view: read-only results table.
     */
    if ($isEmployee) {
        $rows = [];

        foreach ($data as $r) {
            $score = $r['score'] ?? null;
            $hasScore = $score !== null && $score !== '';
            $feedback = trim((string)($r['feedback'] ?? ''));

            $rows[] = [
                h((string)($r['title'] ?? '—')),
                badge($hasScore ? 'Scored' : 'Pending'),
                $hasScore
                    ? h(number_format((float)$score, 1) . '%')
                    : '—',
                h($feedback !== '' ? $feedback : 'No feedback recorded')
            ];
        }

        $html .= '<section class="card assessment-results-card">'
            . '<div class="assessment-table-wrap">'
            . render_table(
                ['Training program', 'Status', 'Score', 'Feedback'],
                $rows,
                'No assessment results have been recorded yet.'
            )
            . '</div></section></div>';

        return $html;
    }

    /*
     * Trainer/admin view.
     */
    if (!$data) {
        $html .= '<section class="card assessment-empty-card">'
            . '<div class="empty-module">'
            . '<div class="program-icon">' . icon('chart', 26) . '</div>'
            . '<h3>No participants to assess</h3>'
            . '<p>Training registrations will appear here when employees are registered for a training program.</p>'
            . '</div></section></div>';

        return $html;
    }

    $scoredCount = count(array_filter(
        $data,
        static fn(array $r): bool => isset($r['score']) && $r['score'] !== ''
    ));

    $departments = [];
    foreach ($data as $item) {
        $department = trim((string)($item['department'] ?? ''));
        if ($department !== '') {
            $departments[$department] = $department;
        }
    }
    natcasesort($departments);

    $html .= '<section class="card assessment-table-card">'
        . '<div class="assessment-toolbar">'
        . '<label class="assessment-search-wrap">'
        . '<span class="sr-only">Search assessments</span>'
        . '<input type="search" class="assessment-search" '
        . 'placeholder="Search employee, ID, department, or training program..." '
        . 'aria-label="Search employee, ID, department, or training program" '
        . 'data-assessment-search></label>'
        . '<div class="assessment-filter-row">'
        . '<label><span class="sr-only">Filter by department</span>'
        . '<select data-assessment-department aria-label="Filter by department">'
        . '<option value="">All Departments</option>';

    foreach ($departments as $department) {
        $html .= '<option value="' . h($department) . '">' . h($department) . '</option>';
    }

    $html .= '</select></label>'
        . '<label><span class="sr-only">Filter by status</span>'
        . '<select data-assessment-status aria-label="Filter by status">'
        . '<option value="">All Statuses</option>'
        . '<option value="scored">Scored</option>'
        . '<option value="pending">Pending</option>'
        . '</select></label>'
        . '<button type="button" class="assessment-clear-filters" data-assessment-clear>Clear filters</button>'
        . '</div></div>'
        . '<div class="assessment-table-wrap">'
        . '<table class="assessment-table">'
        . '<thead><tr>'
        . '<th class="assessment-actions-heading">Actions</th>'
        . '<th>Employee ID</th>'
        . '<th>Faculty Member</th>'
        . '<th>Department</th>'
        . '<th>Training Program</th>'
        . '<th>Training Date</th>'
        . '<th>Status</th>'
        . '</tr></thead><tbody>';

    foreach ($data as $r) {
        $registrationId = (int)$r['registration_id'];
        $score = $r['score'] ?? null;
        $hasScore = $score !== null && $score !== '';
        $firstName = (string)($r['first_name'] ?? '');
        $lastName = (string)($r['last_name'] ?? '');
        $fullName = trim($firstName . ' ' . $lastName);
        $title = (string)($r['title'] ?? 'Untitled training');
        $employeeCode = (string)($r['employee_code'] ?? '—');
        $department = trim((string)($r['department'] ?? ''));
        $department = $department !== '' ? $department : '—';
        $feedback = (string)($r['feedback'] ?? '');
        $startDate = !empty($r['start_date'])
            ? date('M j, Y', strtotime((string)$r['start_date']))
            : '—';

        $detailsId = 'assessment-details-' . $registrationId;
        $scoreId = 'assessment-score-' . $registrationId;
        $feedbackId = 'assessment-feedback-' . $registrationId;
        $searchText = strtolower($employeeCode . ' ' . $fullName . ' ' . $department . ' ' . $title);
        $statusKey = $hasScore ? 'scored' : 'pending';

        $html .= '<tr class="assessment-data-row" '
            . 'data-assessment-row data-search-text="' . h($searchText) . '" '
            . 'data-department="' . h($department) . '" '
            . 'data-status="' . $statusKey . '">';

        // First column: Actions icon
        $html .= '<td class="assessment-actions-cell">'
            . '<button type="button" class="assessment-row-action" '
            . 'aria-label="Toggle assessment details for ' . h($fullName) . '" '
            . 'aria-expanded="false" aria-controls="' . $detailsId . '" '
            . 'data-assessment-toggle="' . $detailsId . '">'
            . '<svg viewBox="0 0 24 24" aria-hidden="true">'
            . '<circle cx="5" cy="12" r="1.7" fill="currentColor"/>'
            . '<circle cx="12" cy="12" r="1.7" fill="currentColor"/>'
            . '<circle cx="19" cy="12" r="1.7" fill="currentColor"/>'
            . '</svg></button></td>';

        // Remaining columns
        $html .= '<td>' . h($employeeCode) . '</td>';

        $html .= '<td><strong class="assessment-faculty-name">'
            . h($fullName !== '' ? $fullName : 'Unknown employee') . '</strong></td>';

        $html .= '<td>' . h($department) . '</td>';

        $html .= '<td><span class="assessment-program-title">'
            . h($title) . '</span></td>';

        $html .= '<td>' . h($startDate) . '</td>';

        $html .= '<td><span class="assessment-status '
            . ($hasScore ? 'is-scored' : 'is-pending') . '">'
            . ($hasScore ? 'Scored' : 'Pending') . '</span></td></tr>';

        $html .= '<tr class="assessment-details-row" id="' . $detailsId . '" hidden>'
            . '<td colspan="7"><div class="assessment-details-panel">'
            . '<div class="assessment-details-heading"><div><h3>Assessment details</h3>'
            . '<p>' . h($fullName) . ' · ' . h($title) . '</p></div>'
            . '<span class="assessment-details-current-status">'
            . ($hasScore ? 'Existing assessment' : 'Not yet assessed')
            . '</span></div>'
            . '<form method="post" class="assessment-inline-form">'
            . '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'
            . '<input type="hidden" name="action" value="save_evaluation">'
            . '<input type="hidden" name="registration_id" value="' . $registrationId . '">'
            . '<div class="assessment-inline-fields">'
            . '<div class="assessment-form-field"><label for="' . $scoreId . '">Assessment score (0–100)</label>'
            . '<div class="assessment-score-input"><input id="' . $scoreId . '" type="number" name="score" '
            . 'min="0" max="100" step="0.5" value="' . h($hasScore ? (string)$score : '') . '" '
            . 'placeholder="Enter score" required><span>%</span></div></div>'
            . '<div class="assessment-form-field assessment-feedback-field">'
            . '<label for="' . $feedbackId . '">Feedback (optional)</label>'
            . '<textarea id="' . $feedbackId . '" name="feedback" rows="3" '
            . 'placeholder="Enter participant feedback...">' . h($feedback) . '</textarea></div></div>'
            . '<div class="assessment-details-footer">'
            . '<button type="button" class="btn assessment-cancel-button" data-assessment-toggle="' . $detailsId . '">Close details</button>'
            . '<button type="submit" class="btn assessment-save-button">' . icon('check', 14)
            . ($hasScore ? ' Update assessment' : ' Save assessment')
            . '</button></div></form></div></td></tr>';
    }

    $html .= '<tr class="assessment-no-results" data-assessment-no-results hidden>'
        . '<td colspan="7">No assessments match your search or filters.</td></tr>';
    $html .= '</tbody></table></div>'
        . '<div class="assessment-table-footer"><span data-assessment-count>'
        . count($data) . ' assessment record(s)</span>'
        . '<span class="assessment-summary-inline">Participants: ' . count($data)
        . ' · Scored: ' . $scoredCount
        . ' · Pending: ' . (count($data) - $scoredCount)
        . '</span></div></section></div>';

    $html .= <<<'HTML'
<script>
(function () {
    const root = document.querySelector('.assessment-page');
    if (!root || root.dataset.assessmentReady === '1') return;
    root.dataset.assessmentReady = '1';

    const search = root.querySelector('[data-assessment-search]');
    const department = root.querySelector('[data-assessment-department]');
    const status = root.querySelector('[data-assessment-status]');
    const clear = root.querySelector('[data-assessment-clear]');
    const rows = Array.from(root.querySelectorAll('[data-assessment-row]'));
    const noResults = root.querySelector('[data-assessment-no-results]');
    const count = root.querySelector('[data-assessment-count]');

    function filterRows() {
        const query = (search.value || '').trim().toLowerCase();
        const selectedDepartment = department.value;
        const selectedStatus = status.value;
        let visible = 0;

        rows.forEach(function (row) {
            const matches = (!query || (row.dataset.searchText || '').includes(query))
                && (!selectedDepartment || row.dataset.department === selectedDepartment)
                && (!selectedStatus || row.dataset.status === selectedStatus);
            row.hidden = !matches;
            const details = document.getElementById(row.querySelector('[data-assessment-toggle]').dataset.assessmentToggle);
            if (!matches && details) details.hidden = true;
            if (matches) visible++;
        });

        noResults.hidden = visible !== 0;
        count.textContent = visible + ' assessment record(s)';
    }

    [search, department, status].forEach(function (control) {
        control.addEventListener('input', filterRows);
        control.addEventListener('change', filterRows);
    });

    clear.addEventListener('click', function () {
        search.value = '';
        department.value = '';
        status.value = '';
        filterRows();
    });
})();
</script>
HTML;

    return $html;
}
