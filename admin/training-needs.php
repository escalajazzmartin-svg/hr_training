<?php
declare(strict_types=1);

$requiredRole = 'admin';
require_once __DIR__ . '/../includes/page_bootstrap.php';

function render_needs_analysis(PDO $pdo): string {
    $search = trim((string)($_GET['q'] ?? ''));
    $department = trim((string)($_GET['department'] ?? ''));
    $priority = (string)($_GET['priority'] ?? '');

    if (!in_array($priority, ['', 'high', 'standard'], true)) {
        $priority = '';
    }

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

    $records = [];

    foreach ($allRecords as $record) {
        $searchText = implode(' ', [
            (string)$record['employee_code'],
            (string)$record['first_name'],
            (string)$record['last_name'],
            (string)$record['email'],
            (string)$record['department'],
            (string)$record['specialization'],
            (string)$record['competency']
        ]);

        if ($search !== '' && stripos($searchText, $search) === false) {
            continue;
        }

        if (
            $department !== '' &&
            $record['department'] !== $department
        ) {
            continue;
        }

        $gap = (int)$record['gap'];

        if ($priority === 'high' && $gap < 20) {
            continue;
        }

        if ($priority === 'standard' && $gap >= 20) {
            continue;
        }

        $records[] = $record;
    }

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
            ? '<span class="badge badge-danger">'
                . $gap . ' points · High</span>'
            : '<span class="badge">'
                . $gap . ' points</span>';

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

            '<div class="table-actions"><a class="btn btn-ghost" href="'
            . h($profileUrl)
            . '">View profile</a><a class="text-action" href="'
            . h($registrationUrl)
            . '">Assign training '
            . icon('arrow', 14)
            . '</a></div>'
        ];
    }

    $html = page_intro(
        'Training Needs Analysis',
        '',
        '<a class="btn" href="'
        . h(app_page_url('Competency Management', 'admin'))
        . '">'
        . icon('target')
        . ' Assess competencies</a>'
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

    $html .= '<section class="card" style="margin-bottom:1rem">'
        . section_head(
            'Training gap overview',
            'Use competency scores to help prioritize professional development.'
        )
        . '<div class="insight-panel">'
        . '<div class="insight-title"><b>How gaps are identified</b>'
        . '<p>A gap is the difference between the target score and the current score. A gap of 20 points or more is marked high priority.</p>'
        . '</div><div class="insight-grid">'
        . '<div><b>Largest recorded gap</b><small>'
        . h($highestGapLabel)
        . '</small></div>'
        . '<div><b>Average gap</b><small>'
        . $averageGap
        . ' points across the identified gaps</small></div>'
        . '<div><b>Recommended next step</b><small>Review the employee profile and look for training that matches the competency and specialization.</small></div>'
        . '</div></div></section>';

    $html .= '<section class="card">'
        . section_head(
            'Faculty training gaps',
            'Highest-priority gaps appear first. Search or filter to find a specific employee.'
        )
        . '<form class="filter-row" method="get" action="'
        . h($analysisUrl)
        . '">'
        . '<input class="search-input" type="search" name="q" value="'
        . h($search)
        . '" placeholder="Search employee, ID, department, or competency...">'
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

    $html .= '</select>'
        . '<select name="priority">'
        . '<option value=""'
        . ($priority === '' ? ' selected' : '')
        . '>All priorities</option>'
        . '<option value="high"'
        . ($priority === 'high' ? ' selected' : '')
        . '>High priority · 20+ points</option>'
        . '<option value="standard"'
        . ($priority === 'standard' ? ' selected' : '')
        . '>Below 20 points</option>'
        . '</select>'
        . '<button class="btn btn-secondary" type="submit">'
        . icon('search')
        . ' Filter</button>'
        . '<a class="btn btn-ghost" href="'
        . h($analysisUrl)
        . '">Clear</a>'
        . '</form>';

    $html .= '<p class="small-muted">'
        . count($records)
        . ' gap record(s) shown out of '
        . $gapCount
        . ' identified gap record(s).</p>';

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

    $html .= '</section>';

    return $html;
}

$page = 'Training Needs Analysis';
$content = render_needs_analysis($pdo);
render_app($pdo, $user, $page, $flash, $content);
