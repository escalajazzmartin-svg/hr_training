<?php

declare(strict_types=1);

$requiredRole = 'admin';
require_once __DIR__ . '/../includes/page_bootstrap.php';

function render_needs_analysis(PDO $pdo): string
{
    $stmt = $pdo->query(
        'SELECT e.*, c.name AS competency, ec.current_score,
                ec.target_score, ec.last_assessed,
                (ec.target_score - ec.current_score) AS gap
         FROM employee_competencies ec
         JOIN employees e ON e.id = ec.employee_id
         JOIN competencies c ON c.id = ec.competency_id
         WHERE ec.current_score < ec.target_score
         ORDER BY (ec.target_score - ec.current_score) DESC,
                  e.last_name, e.first_name'
    );

    $allRecords = $stmt->fetchAll();
    $records = $allRecords;

    $gapCount = count($allRecords);

    $highCount = count(array_filter(
        $allRecords,
        fn($r) => (int)$r['gap'] >= 20
    ));

    $assessedCount = (int)db_count($pdo, 'employee_competencies');
    $employeeCount = (int)db_count($pdo, 'employees');

    $averageGap = $gapCount > 0
        ? (int)round(
            array_sum(array_map(
                fn($r) => (int)$r['gap'],
                $allRecords
            )) / $gapCount
        )
        : 0;

    $highestGap = $allRecords[0] ?? null;

    $highestGapLabel = $highestGap
        ? (int)$highestGap['gap'] . '-point gap · '
        . $highestGap['first_name'] . ' '
        . $highestGap['last_name']
        : 'No current competency gaps';

    $departmentStmt = $pdo->query(
        "SELECT DISTINCT department
         FROM employees
         WHERE department IS NOT NULL
           AND TRIM(department) <> ''
         ORDER BY department"
    );

    $departments = $departmentStmt->fetchAll(PDO::FETCH_COLUMN);

    $analysisUrl = app_page_url('Training Needs Analysis', 'admin');
    $rows = [];

    foreach ($records as $r) {
        $employeeName = $r['first_name'] . ' ' . $r['last_name'];
        $gap = (int)$r['target_score'] - (int)$r['current_score'];
        $employeeId = (int)$r['id'];

        $profileUrl = app_page_url(
            'Employee Profiles',
            'admin',
            ['employee_id' => $employeeId]
        );

        $registrationUrl = app_page_url(
            'Employee Registration',
            'admin',
            ['employee_id' => $employeeId]
        );

        $gapBadge = $gap >= 20
            ? '<span class="badge badge-danger" data-gap="' . $gap . '">'
            . $gap . ' pts · High</span>'
            : '<span class="badge" data-gap="' . $gap . '">'
            . $gap . ' pts</span>';

        $rows[] = [
            '<div class="person-cell"><span class="avatar">'
                . h(initials($employeeName))
                . '</span><span><b>'
                . h($employeeName)
                . '</b><small>'
                . h($r['employee_code'])
                . '</small></span></div>',

            h($r['department']),
            h($r['specialization']),
            h($r['competency']),

            '<div><span class="gap-value">'
                . (int)$r['current_score']
                . '%</span><small class="small-muted">Target: '
                . (int)$r['target_score']
                . '%</small></div>',

            $gapBadge,

            '<div class="table-actions tna-icon-actions">
                <a class="tna-icon-action tna-view-action"
                href="' . h($profileUrl) . '"
                aria-label="View profile of ' . h($employeeName) . '"
                title="View profile">'
                    . icon('eye', 17) .
                '</a>

                <a class="tna-icon-action tna-assign-action"
                href="' . h($registrationUrl) . '"
                aria-label="Assign training to ' . h($employeeName) . '"
                title="Assign training">'
                    . icon('graduation-cap', 17) .
                '</a>
            </div>'
        ];
    }

    $html = '<link rel="stylesheet" href="/hr_training/assets/css/admin/training-needs.css">';

    $html .= page_intro(
        'Training & Development',
        '',
        '<div class="tna-header-actions">
            <button type="button"
                    class="tna-help-button"
                    data-tna-help-open
                    aria-label="Help"
                    title="Training gap overview">'
            . icon('alert-triangle', 18) .
            '</button>

            <a class="btn" href="'
            . h(app_page_url('Competency Management', 'admin')) . '">'
            . icon('target') . '
                Assess competencies
            </a>
        </div>'
    );

    $html .= '<div class="metrics four">'
        . metric(
            'Identified competency gaps',
            $gapCount,
            'Assessment records below target',
            'target',
            'danger'
        )
        . metric(
            'High-priority gaps',
            $highCount,
            '20 or more points below target',
            'alert-triangle',
            'gold'
        )
        . metric(
            'Competencies assessed',
            $assessedCount,
            'Recorded competency assessments',
            'chart',
            'blue'
        )
        . metric(
            'Employees in directory',
            $employeeCount,
            'Registered staff profiles',
            'users'
        )
        . '</div>';

    $html .= '
    <div class="tna-modal" id="tna-help-modal" aria-hidden="true">
        <div class="tna-modal-backdrop" data-tna-help-close></div>

        <section class="tna-modal-dialog"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="tna-modal-title">

            <div class="tna-modal-header">
                <h2 id="tna-modal-title">Training gap overview</h2>

                <button type="button"
                        class="tna-modal-close"
                        data-tna-help-close
                        aria-label="Close help">&times;</button>
            </div>

            <p class="tna-modal-description">
                Use competency scores to help prioritize professional development.
            </p>

            <div class="insight-panel">
                <div class="insight-title">
                    <b>How gaps are identified</b>
                    <p>
                        A gap is the difference between the target score and
                        the current score. A gap of 20 points or more is
                        marked high priority.
                    </p>
                </div>

                <div class="insight-grid">
                    <div>
                        <b>Largest recorded gap</b>
                        <small>' . h($highestGapLabel) . '</small>
                    </div>

                    <div>
                        <b>Average gap</b>
                        <small>' . $averageGap . ' points across the
                        identified gaps</small>
                    </div>

                    <div>
                        <b>Recommended next step</b>
                        <small>
                            Review the employee profile and look for training
                            that matches the competency and specialization.
                        </small>
                    </div>
                </div>
            </div>
        </section>
    </div>';

    $html .= '<script src="/hr_training/assets/js/admin/training-needs-help.js" defer></script>';

    $html .= '<section class="card tna-table-card">
        <form class="filter-row tna-auto-filters"
              method="get"
              action="' . h($analysisUrl) . '"
              role="search">

            <input class="search-input"
                   type="search"
                   name="q"
                   placeholder="Search employee, ID, department, or competency..."
                   autocomplete="off"
                   aria-label="Search training gaps">

            <select name="department" aria-label="Filter by department">
                <option value="">All departments</option>';

    foreach ($departments as $item) {
        $html .= '<option value="' . h($item) . '">' . h($item) . '</option>';
    }

    $html .= '
            </select>

            <select name="priority" aria-label="Filter by priority">
                <option value="">All priorities</option>
                <option value="high">High priority · 20+ points</option>
                <option value="standard">Below 20 points</option>
            </select>

            <button type="button" class="tna-clear-button">
                Clear
            </button>
        </form>';

    $html .= '<div class="tna-table-results table-wrap" data-max-rows="5">';

    $html .= render_table(
        [
            'Faculty member',
            'Department',
            'Specialization',
            'Competency',
            'Score',
            'Gap',
            'Action'
        ],
        $rows,
        'No competency gaps match your current search or filters.'
    );

    $html .= '</div>';
    $html .= '<p class="small-muted tna-result-count" aria-live="polite"></p>';
    $html .= '</section>';

    $html .= '<script src="/hr_training/assets/js/admin/training-needs-filters.js" defer></script>';

    return $html;
}

$page = 'Training Needs Analysis';
$content = render_needs_analysis($pdo);
render_app($pdo, $user, $page, $flash, $content);
