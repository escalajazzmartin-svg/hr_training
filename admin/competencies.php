<?php

declare(strict_types=1);

$requiredRole = 'admin';
require_once __DIR__ . '/../includes/page_bootstrap.php';

function render_competencies(PDO $pdo): string
{
    $employees = employees_for($pdo);

    $competencies = $pdo->query(
        'SELECT id, name, category FROM competencies ORDER BY name'
    )->fetchAll(PDO::FETCH_ASSOC);

    $records = $pdo->query(
        'SELECT ec.*, e.employee_code, e.first_name, e.last_name,
                e.department, c.name AS competency, c.category
         FROM employee_competencies ec
         JOIN employees e ON e.id = ec.employee_id
         JOIN competencies c ON c.id = ec.competency_id
         ORDER BY e.last_name, e.first_name, c.name'
    )->fetchAll(PDO::FETCH_ASSOC);

    $html = '<link rel="stylesheet" href="../assets/css/admin/competencies.css">';

    // Page heading with top-right Add button.
    $html .= '<div class="competency-page-toolbar">'
        . '<div class="competency-page-intro">'
        . page_intro(
            '',
            ''
        )
        . '</div>'
        . '<button type="button" class="btn competency-add-button" '
        . 'data-open-modal="add-competency-modal">'
        . icon('plus') . ' Add competency</button>'
        . '</div>';

    // Search and filters.
    $html .= '<section class="card competency-directory-card">'
        . '<div class="competency-search-row">'
        . '<input type="search" id="competency-search" '
        . 'placeholder="Search faculty, ID, department, or competency..." '
        . 'autocomplete="off" aria-label="Search competency records">'
        . '</div>'
        . '<div class="competency-filter-row">'
        . '<select id="competency-department-filter" aria-label="Filter by department">'
        . '<option value="">All Departments</option>';

    $departments = [];
    foreach ($records as $record) {
        $department = trim((string)($record['department'] ?? ''));
        if ($department !== '') {
            $departments[$department] = $department;
        }
    }
    natcasesort($departments);

    foreach ($departments as $department) {
        $html .= '<option value="' . h($department) . '">'
            . h($department) . '</option>';
    }

    $html .= '</select>'
        . '<select id="competency-status-filter" aria-label="Filter by readiness">'
        . '<option value="">All Readiness</option>'
        . '<option value="on-target">On target</option>'
        . '<option value="below-target">Below target</option>'
        . '</select>'
        . '<select id="competency-name-filter" aria-label="Filter by competency">'
        . '<option value="">All Competencies</option>';

    foreach ($competencies as $competency) {
        $html .= '<option value="' . h($competency['name']) . '">'
            . h($competency['name']) . '</option>';
    }

    $html .= '</select>'
        . '<button type="button" id="clear-competency-filters" '
        . 'class="competency-clear-filters">Clear filters</button>'
        . '</div>';

    // Records table.
    $html .= '<div class="competency-table-scroll" data-max-rows="5">'
        . '<table class="competency-table">'
        . '<thead><tr>'
        . '<th>Faculty member</th>'
        . '<th>Department</th>'
        . '<th>Competency</th>'
        . '<th>Score</th>'
        . '<th>Readiness</th>'
        . '<th>Target</th>'
        . '<th>Last assessed</th>'
        . '</tr></thead><tbody>';

    foreach ($records as $record) {
        $score = max(0, min(100, (int)$record['current_score']));
        $target = max(0, min(100, (int)$record['target_score']));
        $name = trim($record['first_name'] . ' ' . $record['last_name']);
        $onTarget = $score >= $target;

        $html .= '<tr data-department="' . h($record['department'] ?? '') . '"'
            . ' data-competency="' . h($record['competency']) . '"'
            . ' data-readiness="' . ($onTarget ? 'on-target' : 'below-target') . '">';

        $html .= '<td><div class="competency-person-cell">'
            . '<span class="competency-avatar">'
            . h(initials($name))
            . '</span><span class="competency-person-copy">'
            . '<strong>' . h($name) . '</strong>'
            . '<small>' . h($record['employee_code'] ?? '') . '</small>'
            . '</span></div></td>';

        $html .= '<td>' . h($record['department'] ?? '—') . '</td>';
        $html .= '<td>' . h($record['competency']) . '</td>';
        $html .= '<td class="competency-score">' . $score . '%</td>';

        $html .= '<td><span class="competency-progress" role="progressbar"'
            . ' aria-label="' . h($name . ' readiness') . '"'
            . ' aria-valuemin="0" aria-valuemax="100"'
            . ' aria-valuenow="' . $score . '">'
            . '<span class="' . ($onTarget ? 'is-on-target' : '') . '"'
            . ' style="width:' . $score . '%"></span></span></td>';

        $html .= '<td>' . $target . '%</td>';
        $html .= '<td>' . h($record['last_assessed'] ?: 'Not assessed') . '</td>';
        $html .= '</tr>';
    }

    if (!$records) {
        $html .= '<tr><td colspan="7" class="competency-empty">'
            . 'No competency assessments have been recorded yet.'
            . '</td></tr>';
    }

    $html .= '</tbody></table></div>'
        . '<p class="small-muted competency-record-count" id="competency-record-count">'
        . count($records) . ' competency record(s)</p>'
        . '</section>';

    // Add competency assessment modal.
    $html .= '<div class="modal-overlay competency-modal" id="add-competency-modal" '
        . 'role="dialog" aria-modal="true" aria-labelledby="add-competency-title">'
        . '<div class="modal-panel competency-modal-panel">'
        . '<div class="modal-header"><div>'
        . '<h2 id="add-competency-title">Add competency assessment</h2>'
        . '<p>Record a faculty member’s competency score and target.</p>'
        . '</div></div>'
        . '<form method="post" class="form-grid">'
        . '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'
        . '<input type="hidden" name="action" value="save_competency">'

        . '<div class="form-field"><label for="competency-employee">Faculty member *</label>'
        . '<select id="competency-employee" name="employee_id" required>'
        . '<option value="">Select faculty member</option>';

    foreach ($employees as $employee) {
        $employeeName = trim($employee['first_name'] . ' ' . $employee['last_name']);
        $html .= '<option value="' . (int)$employee['id'] . '">'
            . h(($employee['employee_code'] ?? '') . ' · ' . $employeeName)
            . '</option>';
    }

    $html .= '</select></div>'
        . '<div class="form-field"><label for="competency-id">Competency *</label>'
        . '<select id="competency-id" name="competency_id" required>'
        . '<option value="">Select competency</option>';

    foreach ($competencies as $competency) {
        $html .= '<option value="' . (int)$competency['id'] . '">'
            . h($competency['name'] . ' · ' . ($competency['category'] ?? ''))
            . '</option>';
    }

    $html .= '</select></div>'
        . '<div class="form-field"><label for="competency-score-input">Current score (0–100) *</label>'
        . '<input id="competency-score-input" name="current_score" type="number" min="0" max="100" value="70" required></div>'
        . '<div class="form-field"><label for="competency-target-input">Target score (1–100) *</label>'
        . '<input id="competency-target-input" name="target_score" type="number" min="1" max="100" value="80" required></div>'
        . '<div class="form-field full"><label for="competency-notes">Assessment notes</label>'
        . '<textarea id="competency-notes" name="notes" rows="3" placeholder="Describe strengths or training needs..."></textarea></div>'
        . '<div class="form-field full modal-footer">'
        . '<button type="button" class="btn btn-secondary" data-close-competency-modal>Cancel</button>'
        . '<button type="submit" class="btn">Save assessment ' . icon('check') . '</button>'
        . '</div></form></div></div>';

    // Search, filtering, and modal behavior.
    $html .= <<<'HTML'
<script>
(function () {
    const search = document.getElementById('competency-search');
    const department = document.getElementById('competency-department-filter');
    const readiness = document.getElementById('competency-status-filter');
    const competency = document.getElementById('competency-name-filter');
    const clear = document.getElementById('clear-competency-filters');
    const table = document.querySelector('.competency-table');
    const count = document.getElementById('competency-record-count');

    if (search && table) {
        const rows = Array.from(table.querySelectorAll('tbody tr'))
            .filter(row => row.cells.length > 1);

        function applyFilters() {
            const query = search.value.trim().toLocaleLowerCase();
            let visible = 0;

            rows.forEach(row => {
                const matchesSearch = row.textContent.toLocaleLowerCase().includes(query);
                const matchesDepartment = !department.value ||
                    row.dataset.department === department.value;
                const matchesReadiness = !readiness.value ||
                    row.dataset.readiness === readiness.value;
                const matchesCompetency = !competency.value ||
                    row.dataset.competency === competency.value;

                const show = matchesSearch && matchesDepartment &&
                    matchesReadiness && matchesCompetency;

                row.hidden = !show;
                if (show) visible++;
            });

            if (count) count.textContent = visible + ' competency record(s)';
        }

        search.addEventListener('input', applyFilters);
        [department, readiness, competency].forEach(filter => {
            filter.addEventListener('change', applyFilters);
        });

        clear.addEventListener('click', function () {
            search.value = '';
            department.value = '';
            readiness.value = '';
            competency.value = '';
            applyFilters();
            search.focus();
        });
    }

    const modal = document.getElementById('add-competency-modal');

    function setOpen(open) {
        if (!modal) return;
        modal.classList.toggle('is-open', open);
        document.body.style.overflow = open ? 'hidden' : '';
    }

    document.querySelectorAll('[data-open-modal="add-competency-modal"]')
        .forEach(button => button.addEventListener('click', () => setOpen(true)));

    document.querySelectorAll('[data-close-competency-modal]')
        .forEach(button => button.addEventListener('click', () => setOpen(false)));

    if (modal) {
        modal.addEventListener('click', event => {
            if (event.target === modal) setOpen(false);
        });
    }

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal && modal.classList.contains('is-open')) {
            setOpen(false);
        }
    });
})();
</script>
HTML;

    return $html;
}

$page = 'Competency Management';
$content = render_competencies($pdo);
render_app($pdo, $user, $page, $flash, $content);
