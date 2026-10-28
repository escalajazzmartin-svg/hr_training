<?php

declare(strict_types=1);

function dashboard_program_status(?string $startDate, ?string $endDate, ?string $storedStatus = null): string
{
    $status = trim((string) ($storedStatus ?? ''));

    if (in_array($status, ['Cancelled', 'Draft'], true)) {
        return $status;
    }

    if ($startDate === null || $startDate === '' || $startDate === '0000-00-00') {
        return $status !== '' ? $status : 'Upcoming';
    }

    $today = new DateTimeImmutable('today');
    $start = new DateTimeImmutable((string) $startDate);
    $end = $endDate !== null && $endDate !== '' && $endDate !== '0000-00-00'
        ? new DateTimeImmutable((string) $endDate)
        : null;

    if ($end !== null && $today > $end) {
        return 'Completed';
    }

    if ($today >= $start) {
        return 'Ongoing';
    }

    return 'Upcoming';
}

/**
 * Compact role-specific dashboards.
 * Detailed management features remain available from the sidebar pages.
 */
function render_dashboard(PDO $pdo, array $user): string
{
    $role = (string) $user['role'];
    $name = user_display($user);

    ob_start();
?>
    <section class="welcome dashboard-welcome">
        <div>
            <span class="eyebrow">Academic year 2026–2027</span>
            <h2><?= $role === 'employee' ? 'Welcome back, ' : 'Good morning, ' ?><?= h($name) ?>.</h2>

        </div>
        <div class="welcome-actions">
            <?php if ($role === 'admin'): ?>
                <a class="btn btn-secondary" href="?page=Reports"><?= icon('chart') ?> View reports</a>
                <a class="btn" href="?page=Training Programs"><?= icon('plus') ?> Create training</a>
            <?php elseif ($role === 'employee'): ?>
                <a class="btn btn-secondary" href="?page=My Training History"><?= icon('history') ?> My history</a>
                <a class="btn" href="?page=Recommended Training"><?= icon('plus') ?> Find training</a>
                <a class="btn btn-secondary" href="?page=Scan QR Attendance"><?= icon('qrcode') ?> Scan QR</a>
            <?php else: ?>
                <a class="btn btn-secondary" href="?page=Assessments"><?= icon('chart') ?> Assessments</a>
                <a class="btn" href="?page=Attendance Monitoring"><?= icon('check') ?> Take attendance</a>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($role === 'admin'): ?>
        <?php
        $employees = (int) $pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status = 'Active'")->fetchColumn();
        $programRows = $pdo->query("SELECT id, title, status, start_date, end_date FROM training_programs")->fetchAll();
        $programs = 0;
        foreach ($programRows as $program) {
            $status = dashboard_program_status((string) ($program['start_date'] ?? ''), (string) ($program['end_date'] ?? ''), (string) ($program['status'] ?? ''));
            if ($status === 'Upcoming') {
                $programs++;
            }
        }
        $gaps = (int) $pdo->query('SELECT COUNT(*) FROM employee_competencies WHERE current_score < target_score')->fetchColumn();
        $completed = (int) $pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'Completed'")->fetchColumn();
        ?>
        <div class="metrics four dashboard-key-metrics">
            <?= metric('Active employees', $employees, 'Current staff', 'users') ?>
            <?= metric('Active programs', $programs, 'Upcoming or ongoing', 'book', 'gold') ?>
            <?= metric('Competency gaps', $gaps, 'Scores below target', 'target', 'danger') ?>
            <?= metric('Completed training', $completed, 'Completed registrations', 'check', 'green') ?>
        </div>

        <div class="dashboard-grid dashboard-grid-admin-compact">
            <section class="card dashboard-focus-card">
                <?= section_head(
                    'Faculty needing training',
                    'Highest-priority competency gaps',
                    '<a class="text-action" href="?page=Training Needs Analysis">View all ' . icon('arrow', 13) . '</a>'
                ) ?>
                <?php
                $gapRecords = $pdo->query(
                    'SELECT e.employee_code, e.first_name, e.last_name, e.specialization,
                            ec.current_score, ec.target_score
                     FROM employee_competencies ec
                     JOIN employees e ON e.id = ec.employee_id
                     WHERE ec.current_score < ec.target_score
                       AND e.employment_status = \'Active\'
                     ORDER BY (ec.target_score - ec.current_score) DESC
                     LIMIT 4'
                )->fetchAll();
                $gapRows = [];
                foreach ($gapRecords as $record) {
                    $gapRows[] = [
                        '<div class="dashboard-person"><b>' . h($record['first_name'] . ' ' . $record['last_name']) . '</b><small>' . h($record['employee_code']) . '</small></div>',
                        h($record['specialization']),
                        '<span class="badge badge-danger">' . (int) ($record['target_score'] - $record['current_score']) . ' pt gap</span>',
                    ];
                }
                echo render_table(['Faculty member', 'Specialization', 'Gap'], $gapRows, 'No competency gaps recorded.');
                ?>
            </section>

            <section class="card dashboard-focus-card dashboard-side-focus">
                <?= section_head('Upcoming training', 'Next scheduled programs', '<a class="text-action" href="?page=Training Calendar">Calendar ' . icon('arrow', 13) . '</a>') ?>
                <div class="event-list dashboard-event-list">
                    <?php
                    $upcomingPrograms = $pdo->query(
                        "SELECT p.id, p.title, p.start_date, p.start_time, p.venue, p.status, p.end_date,
                                (SELECT COUNT(*) FROM registrations r WHERE r.program_id = p.id AND r.status <> 'Withdrawn') AS registered
                         FROM training_programs p
                         ORDER BY p.start_date ASC"
                    )->fetchAll();
                    $upcoming = [];
                    foreach ($upcomingPrograms as $program) {
                        $status = dashboard_program_status((string) ($program['start_date'] ?? ''), (string) ($program['end_date'] ?? ''), (string) ($program['status'] ?? ''));
                        if ($status === 'Upcoming') {
                            $program['computed_status'] = $status;
                            $upcoming[] = $program;
                        }
                        if (count($upcoming) >= 3) {
                            break;
                        }
                    }
                    foreach ($upcoming as $program):
                        $date = strtotime((string) $program['start_date']);
                    ?>
                        <div class="event dashboard-compact-event">
                            <div class="date-tile"><b><?= strtoupper(date('M', $date)) ?></b><strong><?= date('d', $date) ?></strong></div>
                            <div class="event-info">
                                <b><?= h($program['title']) ?></b>
                                <span><?= icon('clock', 12) ?> <?= h($program['start_time'] ? date('g:i A', strtotime($program['start_time'])) : 'Time TBA') ?></span>
                                <small><?= (int) $program['registered'] ?> registered</small>
                            </div>
                            <?= badge((string) $program['computed_status']) ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$upcoming): ?><p class="muted">No upcoming programs yet.</p><?php endif; ?>
                </div>
                <div class="dashboard-quick-links">
                    <a href="?page=Employees"><?= icon('users', 15) ?><span>Manage employees</span><?= icon('arrow', 13) ?></a>
                    <a href="?page=Competency Management"><?= icon('target', 15) ?><span>Assess competencies</span><?= icon('arrow', 13) ?></a>
                    <a href="?page=Attendance Monitoring"><?= icon('check', 15) ?><span>Monitor attendance</span><?= icon('arrow', 13) ?></a>
                </div>
            </section>
        </div>

    <?php elseif ($role === 'employee'): ?>
        <?php
        $employeeId = (int) ($user['employee_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE employee_id = ? AND status <> 'Withdrawn'");
        $stmt->execute([$employeeId]);
        $registered = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE employee_id = ? AND status = 'Completed'");
        $stmt->execute([$employeeId]);
        $completed = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM certificates c JOIN registrations r ON r.id = c.registration_id WHERE r.employee_id = ?');
        $stmt->execute([$employeeId]);
        $certificates = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM employee_competencies WHERE employee_id = ? AND current_score < target_score');
        $stmt->execute([$employeeId]);
        $competencyGaps = (int) $stmt->fetchColumn();

        // The donut chart uses saved competency assessments, not placeholder values.
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN current_score >= target_score THEN 1 ELSE 0 END), 0) AS at_target,
                    COALESCE(SUM(CASE WHEN current_score < target_score THEN 1 ELSE 0 END), 0) AS needs_work
             FROM employee_competencies
             WHERE employee_id = ?'
        );
        $stmt->execute([$employeeId]);
        $competencySummary = $stmt->fetch() ?: ['total' => 0, 'at_target' => 0, 'needs_work' => 0];
        $competencyTotal = (int) $competencySummary['total'];
        $competenciesAtTarget = (int) $competencySummary['at_target'];
        $competenciesNeedingWork = (int) $competencySummary['needs_work'];
        $competencyTargetPercent = $competencyTotal > 0
            ? (int) round(($competenciesAtTarget / $competencyTotal) * 100)
            : 0;
        ?>
        <div class="metrics four dashboard-key-metrics">
            <?= metric('My training', $registered, 'Registered sessions', 'book') ?>
            <?= metric('Completed', $completed, 'Training completed', 'check', 'green') ?>
            <?= metric('Certificates', $certificates, 'Issued to you', 'award', 'gold') ?>
            <?= metric('Training needs', $competencyGaps, 'Competencies below target', 'target', 'danger') ?>
        </div>

        <div class="dashboard-grid dashboard-grid-employee-compact">
            <section class="card dashboard-focus-card">
                <?= section_head('My upcoming training', 'Your next registered sessions', '<a class="text-action" href="?page=My Training">All training ' . icon('arrow', 13) . '</a>') ?>
                <?php
                $stmt = $pdo->prepare(
                    "SELECT p.title, p.specialization, p.start_date, p.end_date, p.status
                     FROM registrations r
                     JOIN training_programs p ON p.id = r.program_id
                     WHERE r.employee_id = ?
                       AND r.status <> 'Withdrawn'
                     ORDER BY p.start_date ASC
                     LIMIT 10"
                );
                $stmt->execute([$employeeId]);
                $trainingRows = [];
                foreach ($stmt->fetchAll() as $record) {
                    $computedStatus = dashboard_program_status((string) ($record['start_date'] ?? ''), (string) ($record['end_date'] ?? ''), (string) ($record['status'] ?? ''));
                    if ($computedStatus !== 'Upcoming') {
                        continue;
                    }

                    $trainingRows[] = [
                        h($record['title']),
                        h(date('M j, Y', strtotime($record['start_date']))),
                        badge($computedStatus),
                    ];
                }
                echo render_table(['Training program', 'Date', 'Status'], $trainingRows, 'You have no upcoming registered training.');
                ?>
            </section>

            <section class="card dashboard-focus-card dashboard-side-focus">
                <?= section_head('My competency focus', 'Skills that may need improvement') ?>
                <div class="dashboard-competency-summary">
                    <div
                        class="dashboard-competency-donut<?= $competencyTotal === 0 ? ' is-empty' : '' ?>"
                        style="--target-percent: <?= $competencyTargetPercent ?>%;"
                        role="img"
                        aria-label="<?= $competencyTargetPercent ?> percent of assessed competencies are at target">
                        <span aria-hidden="true" style="position:absolute; inset:.62rem; border-radius:50%; background:#fff;"></span>
                        <div class="dashboard-competency-donut-center" style="position:relative; z-index:1; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; line-height:1.15;">
                            <strong><?= $competencyTotal > 0 ? $competencyTargetPercent . '%' : '—' ?></strong>
                            <span>At target</span>
                        </div>
                    </div>
                    <div class="dashboard-competency-legend">
                        <div>
                            <i class="dashboard-chart-dot at-target"></i>
                            <span>At target</span>
                            <b><?= $competenciesAtTarget ?></b>
                        </div>
                        <div>
                            <i class="dashboard-chart-dot needs-work"></i>
                            <span>Needs improvement</span>
                            <b><?= $competenciesNeedingWork ?></b>
                        </div>
                        <small><?= $competencyTotal ?> competency assessment<?= $competencyTotal === 1 ? '' : 's' ?></small>
                    </div>
                </div>
                <div class="dashboard-quick-links">
                    <a href="?page=Recommended Training"><?= icon('book', 15) ?><span>Find recommended training</span><?= icon('arrow', 13) ?></a>
                    <a href="?page=My Training History"><?= icon('history', 15) ?><span>View training history</span><?= icon('arrow', 13) ?></a>
                    <a href="?page=My Certificates"><?= icon('award', 15) ?><span>View certificates</span><?= icon('arrow', 13) ?></a>
                </div>
            </section>
        </div>

    <?php else: ?>
        <?php
        $trainerId = (int) $user['id'];
        $programs = $pdo->prepare('SELECT id, status, start_date, end_date FROM training_programs WHERE trainer_user_id = ?');
        $programs->execute([$trainerId]);
        $myPrograms = 0;
        foreach ($programs->fetchAll() as $program) {
            $status = dashboard_program_status((string) ($program['start_date'] ?? ''), (string) ($program['end_date'] ?? ''), (string) ($program['status'] ?? ''));
            if ($status === 'Upcoming') {
                $myPrograms++;
            }
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM registrations r JOIN training_programs p ON p.id = r.program_id WHERE p.trainer_user_id = ? AND r.status <> \'Withdrawn\'');
        $stmt->execute([$trainerId]);
        $participants = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations r JOIN training_programs p ON p.id = r.program_id LEFT JOIN attendance a ON a.registration_id = r.id WHERE p.trainer_user_id = ? AND r.status <> 'Withdrawn' AND a.id IS NULL");
        $stmt->execute([$trainerId]);
        $attendancePending = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations r JOIN training_programs p ON p.id = r.program_id LEFT JOIN evaluations ev ON ev.registration_id = r.id WHERE p.trainer_user_id = ? AND r.status <> 'Withdrawn' AND ev.id IS NULL");
        $stmt->execute([$trainerId]);
        $evaluationsPending = (int) $stmt->fetchColumn();
        ?>
        <div class="metrics four dashboard-key-metrics">
            <?= metric('My programs', $myPrograms, 'Upcoming or ongoing', 'book') ?>
            <?= metric('Participants', $participants, 'Registered for my programs', 'users', 'gold') ?>
            <?= metric('Attendance pending', $attendancePending, 'Need attendance status', 'check', 'danger') ?>
            <?= metric('Evaluations pending', $evaluationsPending, 'Need score or feedback', 'chart', 'blue') ?>
        </div>

        <div class="dashboard-grid dashboard-grid-trainer-compact">
            <section class="card dashboard-focus-card">
                <?= section_head('My upcoming sessions', 'Programs assigned to you', '<a class="text-action" href="?page=My Training Programs">All programs ' . icon('arrow', 13) . '</a>') ?>
                <?php
                $stmt = $pdo->prepare(
                    "SELECT p.title, p.specialization, p.start_date, p.end_date, p.start_time, p.status,
                            (SELECT COUNT(*) FROM registrations r WHERE r.program_id = p.id AND r.status <> 'Withdrawn') AS participants
                     FROM training_programs p
                     WHERE p.trainer_user_id = ?
                     ORDER BY p.start_date ASC
                     LIMIT 10"
                );
                $stmt->execute([$trainerId]);
                $sessions = $stmt->fetchAll();
                $sessionRows = [];
                foreach ($sessions as $session) {
                    $computedStatus = dashboard_program_status((string) ($session['start_date'] ?? ''), (string) ($session['end_date'] ?? ''), (string) ($session['status'] ?? ''));
                    if ($computedStatus !== 'Upcoming') {
                        continue;
                    }

                    $sessionRows[] = [
                        h($session['title']),
                        h(date('M j, Y', strtotime($session['start_date']))),
                        h((string) $session['participants']),
                        badge($computedStatus),
                    ];
                }
                echo render_table(['Program', 'Date', 'Participants', 'Status'], $sessionRows, 'No upcoming sessions are assigned to you.');
                ?>
            </section>

            <section class="card dashboard-focus-card dashboard-side-focus">
                <?= section_head('Tasks to complete', 'Keep participant records up to date') ?>
                <div class="dashboard-task-list">
                    <a href="?page=Attendance Monitoring" class="dashboard-task-link">
                        <span class="alert-icon gold"><?= icon('check') ?></span>
                        <span><b>Record attendance</b><small><?= $attendancePending ?> participant record(s) waiting</small></span>
                        <?= icon('arrow', 13) ?>
                    </a>
                    <a href="?page=Assessments" class="dashboard-task-link">
                        <span class="alert-icon blue"><?= icon('chart') ?></span>
                        <span><b>Assess participants</b><small><?= $evaluationsPending ?> evaluation(s) waiting</small></span>
                        <?= icon('arrow', 13) ?>
                    </a>
                    <a href="?page=Attendees" class="dashboard-task-link">
                        <span class="alert-icon red"><?= icon('users') ?></span>
                        <span><b>Review attendees</b><small>See participants in your programs</small></span>
                        <?= icon('arrow', 13) ?>
                    </a>
                </div>
            </section>
        </div>
    <?php endif; ?>
<?php
    return (string) ob_get_clean();
}
