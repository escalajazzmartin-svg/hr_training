<?php
declare(strict_types=1);
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/page_bootstrap.php';

function render_employee_directory(PDO $pdo): string {
    $search = trim((string)($_GET['q'] ?? ''));
    $editId = (int)($_GET['edit'] ?? 0);
    $edit = null;

    if ($editId) {
        $s = $pdo->prepare('SELECT * FROM employees WHERE id = ?');
        $s->execute([$editId]);
        $edit = $s->fetch() ?: null;
    }

    $html = page_intro(
        'Employee Directory',
        '',
        '<a class="btn" href="#add-employee">'
        . icon('plus')
        . ' Add employee</a>'
    );

    if ($edit) {
        $html .= '<section class="card" style="margin-bottom:1rem">'
            . section_head('Edit employee', 'Update the employee record')
            . '<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="'
            . h(csrf_token())
            . '"><input type="hidden" name="action" value="update_employee"><input type="hidden" name="employee_id" value="'
            . (int)$edit['id']
            . '">';

        foreach ([
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'email' => 'Email address',
            'phone' => 'Phone',
            'department' => 'Department',
            'job_title' => 'Job title',
            'specialization' => 'Specialization'
        ] as $key => $label) {
            $html .= '<div class="form-field"><label>'
                . h($label)
                . '</label><input name="'
                . h($key)
                . '" value="'
                . h($edit[$key])
                . '" '
                . ($key !== 'phone' ? 'required' : '')
                . '></div>';
        }

        $html .= '<div class="form-field"><label>Employment status</label><select name="employment_status">';

        foreach (['Active', 'On Leave', 'Inactive'] as $v) {
            $html .= '<option '
                . ($edit['employment_status'] === $v ? 'selected' : '')
                . '>'
                . h($v)
                . '</option>';
        }

        $html .= '</select></div><div class="form-field full"><button class="btn" type="submit">Save changes</button> '
            . '<a class="btn btn-secondary" href="?page=Employees">Cancel</a>'
            . '</div></form></section>';
    }

    $html .= '<section class="card" style="margin-bottom:1rem"><form class="filter-row" method="get"><input type="hidden" name="page" value="Employees">'
        . '<input class="search-input" name="q" placeholder="Search name, ID, department, or specialization..." value="'
        . h($search)
        . '"><button class="btn btn-secondary" type="submit">'
        . icon('search')
        . ' Search</button><a class="btn btn-ghost" href="?page=Employees">Clear</a></form>';

    $sql = 'SELECT * FROM employees';
    $params = [];

    if ($search !== '') {
        $sql .= ' WHERE employee_code LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR department LIKE ? OR specialization LIKE ?';
        $term = '%' . $search . '%';
        $params = [$term, $term, $term, $term, $term];
    }

    $sql .= ' ORDER BY last_name, first_name';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $records = $st->fetchAll();
    $rows = [];

    foreach ($records as $e) {
        $full = h($e['first_name'] . ' ' . $e['last_name']);

        $rows[] = [
            '<div class="person-cell"><span class="avatar">'
            . h(initials($e['first_name'] . ' ' . $e['last_name']))
            . '</span><span><b>'
            . $full
            . '</b><small>'
            . h($e['employee_code'])
            . '</small></span></div>',
            h($e['department']),
            h($e['job_title']),
            h($e['specialization']),
            badge($e['employment_status']),
            '<div class="table-actions"><a class="btn btn-ghost" href="?page=Employees&edit='
            . (int)$e['id']
            . '">Edit</a><form class="inline-form" method="post" onsubmit="return confirm(\'Delete this employee and their linked records?\')">'
            . '<input type="hidden" name="csrf_token" value="'
            . h(csrf_token())
            . '"><input type="hidden" name="action" value="delete_employee"><input type="hidden" name="employee_id" value="'
            . (int)$e['id']
            . '"><button class="btn btn-ghost delete-btn" type="submit">Delete</button></form></div>'
        ];
    }

    $html .= render_table(
        [
            'Employee',
            'Department',
            'Job title',
            'Specialization',
            'Status',
            'Actions'
        ],
        $rows,
        'No employees match your search.'
    )
        . '<p class="small-muted">'
        . count($records)
        . ' employee record(s)</p></section>';

    $html .= '<section class="card" id="add-employee">'
        . section_head(
            'Employee Registration',
            'Create a staff profile and a login account for a faculty member or trainer'
        )
        . '<form method="post" class="form-grid">'
        . '<input type="hidden" name="csrf_token" value="'
        . h(csrf_token())
        . '"><input type="hidden" name="action" value="add_employee">'

        . '<div class="form-field"><label>First name *</label>'
        . '<input name="first_name" required autocomplete="given-name"></div>'

        . '<div class="form-field"><label>Last name *</label>'
        . '<input name="last_name" required autocomplete="family-name"></div>'

        . '<div class="form-field"><label>Employee ID *</label>'
        . '<input name="employee_code" placeholder="FAC-2026-001" required></div>'

        . '<div class="form-field"><label>Email address *</label>'
        . '<input name="email" type="email" required autocomplete="email"></div>'

        . '<div class="form-field"><label>Phone number</label>'
        . '<input name="phone" type="tel" autocomplete="tel"></div>'

        . '<div class="form-field"><label>Account type *</label>'
        . '<select name="account_role" required>'
        . '<option value="employee">Faculty Member</option>'
        . '<option value="trainer">Trainer</option>'
        . '</select></div>'

        . '<div class="form-field"><label>Department *</label>'
        . '<select name="department" required><option value="">Select department</option>';

    foreach ([
        'Information Technology',
        'Science',
        'Mathematics',
        'Business',
        'English',
        'Education',
        'Human Resources',
        'Other'
    ] as $v) {
        $html .= '<option value="'
            . h($v)
            . '">'
            . h($v)
            . '</option>';
    }

    $html .= '</select></div>'

        . '<div class="form-field"><label>Job title *</label>'
        . '<input name="job_title" required></div>'

        . '<div class="form-field"><label>Specialization *</label>'
        . '<input name="specialization" required></div>'

        . '<div class="form-field"><label>Hire date</label>'
        . '<input name="hire_date" type="date"></div>'

        . '<div class="form-field"><label>Employment status</label>'
        . '<select name="employment_status">'
        . '<option value="Active">Active</option>'
        . '<option value="On Leave">On Leave</option>'
        . '<option value="Inactive">Inactive</option>'
        . '</select></div>'

        . '<div class="form-field"><label>Initial password *</label>'
        . '<input name="initial_password" type="password" minlength="8" required autocomplete="new-password">'
        . '</div>'

        . '<div class="form-field"><label>Confirm password *</label>'
        . '<input name="confirm_password" type="password" minlength="8" required autocomplete="new-password">'
        . '</div>'

        . '<div class="form-field full">'
        . '<p class="small-muted">Create a password with at least 8 characters. Share the initial password securely with the account holder.</p>'
        . '<button class="btn" type="submit">Register employee '
        . icon('arrow', 14)
        . '</button></div></form></section>';

    return $html;
}

$page = 'Employees';
$content = render_employee_directory($pdo);
render_app($pdo, $user, $page, $flash, $content);
