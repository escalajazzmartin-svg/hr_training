<?php

declare(strict_types=1);

/**
 * Render the profile page for employees/trainers or the admin employee directory.
 * Existing query parameters and POST form field names are retained for compatibility.
 */
function render_profile(PDO $pdo, array $user): string
{
    // Employees and trainers can view only their own profile and change their password.
    if (in_array($user['role'], ['employee', 'trainer'], true)) {
        $id = (int)($user['employee_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ?');
        $stmt->execute([$id]);
        $employee = $stmt->fetch();

        if (!$employee) {
            return '<div class="empty-module"><h3>No employee profile is linked to this login.</h3></div>';
        }

        $stmt = $pdo->prepare(
            'SELECT c.name, ec.current_score, ec.target_score, ec.last_assessed
            FROM employee_competencies ec
            JOIN competencies c ON c.id = ec.competency_id
            WHERE ec.employee_id = ?
            ORDER BY c.name'
        );
        $stmt->execute([$id]);
        $skills = $stmt->fetchAll();

        $profileLabel = $user['role'] === 'trainer'
            ? 'Trainer profile'
            : 'Faculty profile';

        $passwordFormOpen = (string)($_GET['password_form'] ?? '') === 'open';

        $html = '<div class="profile-hero"><span class="avatar avatar-gold profile-avatar">'
            . h(initials($employee['first_name'] . ' ' . $employee['last_name']))
            . '</span><div><span class="eyebrow">'
            . h($profileLabel)
            . '</span><h2>'
            . h($employee['first_name'] . ' ' . $employee['last_name'])
            . '</h2><p>'
            . h($employee['job_title']) . ' · ' . h($employee['department'])
            . '</p></div>'
            . badge($employee['employment_status'])
            . '</div><div class="profile-grid"><section class="card profile-info-card">'
            . section_head('Contact & employment', 'Personal and workplace details')
            . '<div class="sample-records">';

        foreach (
            [
                'Employee ID' => $employee['employee_code'],
                'Email' => $employee['email'],
                'Phone' => $employee['phone'],
                'Department' => $employee['department'],
                'Job title' => $employee['job_title'],
                'Specialization' => $employee['specialization'],
                'Hire date' => $employee['hire_date'],
                'Employment status' => $employee['employment_status']
            ] as $label => $value
        ) {
            $html .= '<div><span class="small-muted">'
                . h($label)
                . '</span><span><b>'
                . h($value ?: 'Not provided')
                . '</b></span></div>';
        }

        $html .= '</div></section><div class="profile-side-stack"><section class="card profile-competency-card">'
            . section_head('Competency summary', 'Your latest assessed competencies')
            . '<div class="skill-list">';

        foreach ($skills as $skill) {
            $score = (int)$skill['current_score'];
            $target = (int)$skill['target_score'];

            $html .= '<div><span><b>'
                . h($skill['name'])
                . '</b><small>'
                . $score . '% / ' . $target . '% target'
                . '</small></span><span class="progress"><i class="'
                . ($score < $target ? 'warn' : '')
                . '" style="width:' . max(0, min(100, $score))
                . '%"></i></span></div>';
        }

        if (!$skills) {
            $html .= '<p class="small-muted">No competency assessments have been recorded yet.</p>';
        }

        $html .= '</div></section>'
            . '<section class="card profile-security-card">'
            . '<div class="profile-security-heading">'
            . '<div class="profile-security-title">'
            . icon('shield', 18)
            . '<div><h2>Account security</h2>'
            . '<p>Change your password to keep your account secure.</p></div>'
            . '</div>'
            . '<button class="btn profile-password-toggle" type="button" id="profile-password-toggle" aria-controls="profile-password-form" aria-expanded="'
            . ($passwordFormOpen ? 'true' : 'false')
            . '">Change password</button>'
            . '</div>'
            . '<form class="profile-password-form" id="profile-password-form" method="post"'
            . ($passwordFormOpen ? '' : ' hidden')
            . '>'
            . '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'
            . '<input type="hidden" name="action" value="change_password">'
            . '<input type="hidden" name="return_page" value="My Profile">'
            . '<div class="profile-password-fields">'
            . '<div class="form-field"><label for="profile-current-password">Current password</label>'
            . '<div class="profile-password-input-wrap">'
            . '<span class="profile-password-field-icon" aria-hidden="true">' . icon('shield', 18) . '</span>'
            . '<input id="profile-current-password" name="current_password" type="password" autocomplete="current-password" required>'
            . '<button class="profile-password-eye" type="button" data-password-toggle="profile-current-password" aria-label="Show password" title="Show password">'
            . icon('eye', 18)
            . '</button></div></div>'
            . '<div class="form-field"><label for="profile-new-password">New password</label>'
            . '<div class="profile-password-input-wrap">'
            . '<span class="profile-password-field-icon" aria-hidden="true">' . icon('shield', 18) . '</span>'
            . '<input id="profile-new-password" name="new_password" type="password" minlength="8" autocomplete="new-password" required>'
            . '<button class="profile-password-eye" type="button" data-password-toggle="profile-new-password" aria-label="Show password" title="Show password">'
            . icon('eye', 18)
            . '</button></div>'
            . '<small class="small-muted">At least 8 characters.</small></div>'
            . '<div class="form-field"><label for="profile-confirm-password">Confirm new password</label>'
            . '<div class="profile-password-input-wrap">'
            . '<span class="profile-password-field-icon" aria-hidden="true">' . icon('shield', 18) . '</span>'
            . '<input id="profile-confirm-password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>'
            . '<button class="profile-password-eye" type="button" data-password-toggle="profile-confirm-password" aria-label="Show password" title="Show password">'
            . icon('eye', 18)
            . '</button></div></div>'
            . '</div>'
            . '<div class="profile-password-actions">'
            . '<button class="btn btn-secondary" type="button" id="profile-password-cancel">Cancel</button>'
            . '<button class="btn" type="submit">Update password ' . icon('shield-check', 15) . '</button>'
            . '</div>'
            . '</form></section></div></div>';



        $html .= '<script>
(function () {
    const toggle = document.getElementById("profile-password-toggle");
    const form = document.getElementById("profile-password-form");
    const cancel = document.getElementById("profile-password-cancel");

    if (!toggle || !form || !cancel) {
        return;
    }

    function setFormOpen(open) {
        form.hidden = !open;
        toggle.setAttribute("aria-expanded", String(open));

        if (open) {
            form.querySelector("input[name=current_password]")?.focus();
        } else {
            form.reset();
        }
    }

    toggle.addEventListener("click", function () {
        setFormOpen(form.hidden);
    });

    cancel.addEventListener("click", function () {
        setFormOpen(false);
    });

    form.querySelectorAll("[data-password-toggle]").forEach(button => {
        button.addEventListener("click", function () {
            const input = document.getElementById(this.dataset.passwordToggle);

            if (!input) {
                return;
            }

            const showPassword = input.type === "password";
            input.type = showPassword ? "text" : "password";

            this.setAttribute(
                "aria-label",
                showPassword ? "Hide password" : "Show password"
            );

            this.setAttribute(
                "title",
                showPassword ? "Hide password" : "Show password"
            );
        });
    });
})();
</script>';

        return $html;
    }

    // Admin profile view: a selected employee is shown when employee_id is provided.
    $selectedId = (int)($_GET['employee_id'] ?? 0);

    if ($selectedId > 0) {
        $stmt = $pdo->prepare(
            'SELECT e.*, u.role AS account_role
            FROM employees e
            LEFT JOIN users u ON u.employee_id = e.id
            WHERE e.id = ?
            LIMIT 1'
        );
        $stmt->execute([$selectedId]);
        $employee = $stmt->fetch();

        if (!$employee) {
            return page_intro(
                'Employee Profiles',
                'The selected employee could not be found.',
                '<a class="btn btn-secondary" href="employees.php">Back to directory</a>'
            ) . '<div class="empty-module"><h3>No matching employee profile was found.</h3></div>';
        }

        $roleLabel = match ($employee['account_role'] ?? '') {
            'employee' => 'Faculty Member',
            'trainer' => 'Trainer',
            'admin' => 'HR Officer',
            default => 'No login account'
        };

        $html = '<div class="profile-hero"><span class="avatar avatar-gold profile-avatar">'
            . h(initials($employee['first_name'] . ' ' . $employee['last_name']))
            . '</span><div><span class="eyebrow">'
            . h($roleLabel)
            . '</span><h2>'
            . h($employee['first_name'] . ' ' . $employee['last_name'])
            . '</h2><p>'
            . h($employee['job_title'])
            . ' · '
            . h($employee['department'])
            . '</p></div>'
            . badge($employee['employment_status'])
            . '<a class="btn btn-secondary profile-back-button" href="employees.php">'
            . icon('arrow', 14)
            . ' Back to directory</a></div>';

        $html .= '<div class="profile-grid"><section class="card profile-info-card">'
            . section_head('Contact & employment', 'Personal and workplace details')
            . '<div class="sample-records">';

        foreach (
            [
                'Employee ID' => $employee['employee_code'],
                'Email' => $employee['email'],
                'Phone' => $employee['phone'],
                'Department' => $employee['department'],
                'Job title' => $employee['job_title'],
                'Specialization' => $employee['specialization'],
                'Hire date' => $employee['hire_date'],
                'Employment status' => $employee['employment_status']
            ] as $label => $value
        ) {
            $html .= '<div><span class="small-muted">'
                . h($label)
                . '</span><span><b>'
                . h($value ?: 'Not provided')
                . '</b></span></div>';
        }

        $html .= '</div></section><div class="profile-side-stack"><section class="card profile-competency-card">'
            . section_head('Competency summary', 'Current scores compared with target levels');

        $stmt = $pdo->prepare(
            'SELECT c.name, ec.current_score, ec.target_score, ec.last_assessed
    FROM employee_competencies ec
    JOIN competencies c ON c.id = ec.competency_id
    WHERE ec.employee_id = ?
    ORDER BY c.name'
        );
        $stmt->execute([$selectedId]);
        $skills = $stmt->fetchAll();

        $html .= '<div class="profile-competency-list">';

        foreach ($skills as $skill) {
            $score = (int)$skill['current_score'];
            $target = (int)$skill['target_score'];
            $gap = max(0, $target - $score);
            $percentage = max(0, min(100, $score));
            $targetPercentage = max(0, min(100, $target));

            $html .= '<article class="profile-competency-item">'
                . '<div class="profile-competency-top">'
                . '<span class="profile-competency-name">' . h($skill['name']) . '</span>'
                . '<strong class="profile-competency-score ' . ($score < $target ? 'is-below-target' : 'is-at-target') . '">'
                . $score . '%</strong>'
                . '</div>'
                . '<div class="profile-competency-track">'
                . '<span class="profile-competency-fill ' . ($score < $target ? 'is-below-target' : 'is-at-target') . '" style="width:' . $percentage . '%"></span>'
                . '</div>'
                . '<div class="profile-competency-meta">'
                . '<span>Target: ' . $target . '%</span>'
                . ($gap > 0
                    ? '<span class="profile-competency-gap">' . $gap . ' points to target</span>'
                    : '<span class="profile-competency-achieved">Target achieved</span>')
                . '</div>'
                . '<small class="profile-competency-assessed">Last assessed: '
                . h($skill['last_assessed'] ?: 'Not assessed')
                . '</small>'
                . '</article>';
        }

        if (!$skills) {
            $html .= '<p class="small-muted">No competency assessments have been recorded for this employee.</p>';
        }

        $html .= '</div></section>';

        if (($employee['account_role'] ?? '') === 'trainer') {
            $stmt = $pdo->prepare(
                "SELECT id
                 FROM users
                 WHERE employee_id = ? AND role = 'trainer'
                 LIMIT 1"
            );
            $stmt->execute([$selectedId]);
            $trainerUserId = (int)($stmt->fetchColumn() ?: 0);

            $programRecords = [];

            if ($trainerUserId > 0) {
                $stmt = $pdo->prepare(
                    "SELECT
                    p.title,
                    p.specialization,
                    p.start_date,
                    p.end_date,
                    p.status,
                    (
                        SELECT COUNT(*)
                        FROM registrations r
                        WHERE r.program_id = p.id
                          AND r.status <> 'Withdrawn'
                    ) AS participant_count
                 FROM training_programs p
                 WHERE p.trainer_user_id = ?
                 ORDER BY p.start_date DESC, p.id DESC"
                );
                $stmt->execute([$trainerUserId]);
                $programRecords = $stmt->fetchAll();
            }

            $html .= '<section class="card profile-history-card">'
                . section_head('Training history', 'Training programs assigned to this trainer')
                . '<div class="profile-training-list">';

            foreach ($programRecords as $program) {
                $html .= '<article class="profile-training-item">'
                    . '<div class="profile-training-heading"><h4>' . h($program['title']) . '</h4>'
                    . badge($program['status']) . '</div>'
                    . '<p class="profile-training-specialization">' . h($program['specialization'] ?: 'No specialization') . '</p>'
                    . '<div class="profile-training-meta">'
                    . '<span><small>Start date</small><strong>' . h($program['start_date'] ?: 'Not set') . '</strong></span>'
                    . '<span><small>End date</small><strong>' . h($program['end_date'] ?: 'Not set') . '</strong></span>'
                    . '<span><small>Participants</small><strong>' . (int)$program['participant_count'] . '</strong></span>'
                    . '</div></article>';
            }

            if (!$programRecords) {
                $html .= '<p class="small-muted">No training programs are assigned to this trainer.</p>';
            }

            $html .= '</div></section>';
        } else {
            $stmt = $pdo->prepare(
                'SELECT
            p.title,
            p.start_date,
            r.status AS registration_status,
            a.attendance_status,
            ev.score,
            c.certificate_code
        FROM registrations r
        JOIN training_programs p ON p.id = r.program_id
        LEFT JOIN attendance a ON a.registration_id = r.id
        LEFT JOIN evaluations ev ON ev.registration_id = r.id
        LEFT JOIN certificates c ON c.registration_id = r.id
        WHERE r.employee_id = ?
        ORDER BY p.start_date DESC, r.id DESC'
            );

            $stmt->execute([$selectedId]);
            $trainingRecords = $stmt->fetchAll();

            $html .= '<section class="card profile-history-card">'
                . section_head('Training history', 'Programs, attendance, assessment results, and certificates')
                . '<div class="profile-training-list">';

            foreach ($trainingRecords as $record) {
                $score = $record['score'] !== null
                    ? number_format((float)$record['score'], 1) . '%'
                    : 'Pending';

                $html .= '<article class="profile-training-item">'
                    . '<div class="profile-training-heading"><h4>' . h($record['title']) . '</h4>'
                    . badge($record['registration_status']) . '</div>'
                    . '<div class="profile-training-meta">'
                    . '<span><small>Start date</small><strong>' . h($record['start_date'] ?: 'Not set') . '</strong></span>'
                    . '<span><small>Attendance</small><strong>' . h($record['attendance_status'] ?: 'Not recorded') . '</strong></span>'
                    . '<span><small>Assessment score</small><strong>' . h($score) . '</strong></span>'
                    . '<span><small>Certificate</small><strong>' . h($record['certificate_code'] ?: 'Not issued') . '</strong></span>'
                    . '</div></article>';
            }

            if (!$trainingRecords) {
                $html .= '<p class="small-muted">No training history has been recorded for this employee.</p>';
            }

            $html .= '</div></section>';
        }

        // Close the right-side stack (competency summary + training history)
        // and then the overall two-column profile grid.
        $html .= '</div></div>';

        return $html;
    }

    // Admin directory view: retain the existing search and account-role filters.
    $search = trim((string)($_GET['q'] ?? ''));
    $accountRole = trim((string)($_GET['account_role'] ?? ''));

    $html = page_intro(
        'Employee Profiles',
        '',
        '<a class="btn" href="employees.php#add-employee">'
            . icon('plus')
            . ' Add employee</a>'
    );

    $html .= '<section class="card profile-directory-card">'
        . '<form class="filter-row" method="get">'
        . '<input class="search-input" name="q" value="'
        . h($search)
        . '" placeholder="Search name, employee ID, department, or specialization...">'
        . '<select name="account_role">'
        . '<option value="">All account types</option>'
        . '<option value="employee" '
        . ($accountRole === 'employee' ? 'selected' : '')
        . '>Faculty Member</option>'
        . '<option value="trainer" '
        . ($accountRole === 'trainer' ? 'selected' : '')
        . '>Trainer</option>'
        . '</select>'
        . '<button class="btn btn-secondary" type="submit">'
        . icon('search')
        . ' Search</button>'
        . '<a class="btn btn-ghost" href="?">Clear</a>'
        . '</form>';

    $sql = 'SELECT e.*, u.role AS account_role
        FROM employees e
        LEFT JOIN users u ON u.employee_id = e.id';

    $conditions = [];
    $params = [];

    if ($search !== '') {
        $conditions[] = '(e.employee_code LIKE ?
            OR e.first_name LIKE ?
            OR e.last_name LIKE ?
            OR e.email LIKE ?
            OR e.department LIKE ?
            OR e.specialization LIKE ?)';

        $term = '%' . $search . '%';

        $params = [
            $term,
            $term,
            $term,
            $term,
            $term,
            $term
        ];
    }

    if (in_array($accountRole, ['employee', 'trainer'], true)) {
        $conditions[] = 'u.role = ?';
        $params[] = $accountRole;
    }

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY e.last_name, e.first_name';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll();

    $rows = [];

    foreach ($employees as $employee) {
        $name = $employee['first_name'] . ' ' . $employee['last_name'];

        $roleLabel = match ($employee['account_role'] ?? '') {
            'employee' => 'Faculty Member',
            'trainer' => 'Trainer',
            'admin' => 'HR Officer',
            default => 'No login account'
        };

        $rows[] = [
            '<div class="person-cell"><span class="avatar">'
                . h(initials($name))
                . '</span><span><b>'
                . h($name)
                . '</b><small>'
                . h($employee['employee_code'])
                . '</small></span></div>',
            h($employee['department']),
            h($employee['job_title']),
            h($employee['specialization']),
            h($roleLabel),
            badge($employee['employment_status']),
            '<div class="table-actions"><a class="btn btn-ghost" href="?employee_id='
                . (int)$employee['id']
                . '">View Profile</a></div>'
        ];
    }

    $html .= render_table(
        [
            'Employee',
            'Department',
            'Job title',
            'Specialization',
            'Account type',
            'Status',
            'Actions'
        ],
        $rows,
        'No employees match your search.'
    );

    $html .= '<p class="small-muted">'
        . count($employees)
        . ' employee record(s)</p></section>';

    return $html;
}
