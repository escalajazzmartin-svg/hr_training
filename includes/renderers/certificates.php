<?php

declare(strict_types=1);

function render_certificates(PDO $pdo, array $user): string
{
    $role = strtolower(trim((string) ($user['role'] ?? '')));

    $sql = '
        SELECT
            r.id AS registration_id,
            e.employee_code,
            e.first_name,
            e.last_name,
            e.email AS employee_email,
            p.title,
            ev.score,
            c.certificate_code,
            c.issued_at
        FROM registrations r
        INNER JOIN employees e
            ON e.id = r.employee_id
        INNER JOIN training_programs p
            ON p.id = r.program_id
        INNER JOIN evaluations ev
            ON ev.registration_id = r.id
        LEFT JOIN certificates c
            ON c.registration_id = r.id
        WHERE r.status = ?
          AND ev.score >= ?
    ';

    $params = ['Completed', 75];

    // Employees can only view their own certificate records.
    if ($role === 'employee') {
        if (empty($user['employee_id'])) {
            return '<div class="certificates-page">'
                . page_intro(
                    '',
                    ''
                )
                . '<section class="card">'
                . '<p class="certificate-empty-message">'
                . 'Your employee profile could not be identified.'
                . '</p></section></div>';
        }

        $sql .= ' AND r.employee_id = ?';
        $params[] = (int) $user['employee_id'];
    }

    $sql .= ' ORDER BY c.issued_at DESC, r.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rowsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $registryCount = count($rowsData);
    $rows = [];

    foreach ($rowsData as $record) {
        $registrationId = (int) $record['registration_id'];

        $employeeName = trim(
            (string) ($record['first_name'] ?? '')
                . ' '
                . (string) ($record['last_name'] ?? '')
        );

        $employeeCode = (string) ($record['employee_code'] ?? '');
        $employeeEmail = trim((string) ($record['employee_email'] ?? ''));
        $programTitle = (string) ($record['title'] ?? '');

        $certificateCode = trim(
            (string) ($record['certificate_code'] ?? '')
        );

        $issuedAt = trim((string) ($record['issued_at'] ?? ''));
        $hasCertificate = $certificateCode !== '';

        // Certificate status.
        $certificate = $hasCertificate
            ? '<span class="badge badge-success certificate-status">'
            . '<span class="certificate-status-dot" aria-hidden="true"></span>'
            . h($certificateCode)
            . '</span>'
            : '<span class="badge badge-warn certificate-status">'
            . '<span class="certificate-status-dot" aria-hidden="true"></span>'
            . 'Eligible to issue'
            . '</span>';

        // Date issued or Issue Certificate action.
        if ($hasCertificate) {
            $timestamp = $issuedAt !== ''
                ? strtotime($issuedAt)
                : false;

            $dateAction = $timestamp !== false
                ? '<span class="certificate-issued-date">'
                . '<svg viewBox="0 0 24 24" aria-hidden="true">'
                . '<rect x="3.5" y="5" width="17" height="15" rx="2"/>'
                . '<path d="M7.5 3.5v3M16.5 3.5v3M3.5 9.5h17"/>'
                . '</svg>'
                . h(date('M j, Y', $timestamp))
                . '</span>'
                : '<span class="certificate-issued-date certificate-date-unknown">'
                . 'Date unavailable'
                . '</span>';
        } elseif ($role === 'admin') {
            $dateAction = '<form method="post" class="certificate-issue-form">'
                . '<input type="hidden" name="csrf_token" value="'
                . h(csrf_token())
                . '">'
                . '<input type="hidden" name="action" value="issue_certificate">'
                . '<input type="hidden" name="registration_id" value="'
                . $registrationId
                . '">'
                . '<button class="btn certificate-issue-btn" type="submit">'
                . '<span>Issue Certificate</span>'
                . '</button>'
                . '</form>';
        } else {
            $dateAction = '<span class="certificate-review-status">'
                . 'Pending HR review'
                . '</span>';
        }

        // Print action: printable certificate view.
        if ($hasCertificate) {
            $printAction = '<button type="button" '
                . 'class="certificate-action-btn certificate-print-btn" '
                . 'data-certificate-print '
                . 'data-employee="' . h($employeeName) . '" '
                . 'data-employee-id="' . h($employeeCode) . '" '
                . 'data-program="' . h($programTitle) . '" '
                . 'data-score="' . h(number_format((float) $record['score'], 1)) . '" '
                . 'data-certificate-code="' . h($certificateCode) . '" '
                . 'data-issued-at="' . h($issuedAt) . '" '
                . 'aria-label="Print certificate for ' . h($employeeName) . '" '
                . 'title="Print certificate">'
                . '<i class="fa fa-print" aria-hidden="true"></i>'
                . '</button>';
        } else {
            $printAction = '<button type="button" '
                . 'class="certificate-action-btn certificate-print-btn" '
                . 'disabled title="Issue certificate first" '
                . 'aria-label="Print unavailable">'
                . '<i class="fa fa-print" aria-hidden="true"></i>'
                . '</button>';
        }

        // Email action: submits to a server-side email handler.
        if ($hasCertificate && $role === 'admin') {
            if (filter_var($employeeEmail, FILTER_VALIDATE_EMAIL)) {
                $sendAction = '<form method="post" class="certificate-send-form">'
                    . '<input type="hidden" name="csrf_token" value="'
                    . h(csrf_token())
                    . '">'
                    . '<input type="hidden" name="action" value="send_certificate_email">'
                    . '<input type="hidden" name="registration_id" value="'
                    . $registrationId
                    . '">'
                    . '<button type="submit" '
                    . 'class="certificate-action-btn certificate-send-btn" '
                    . 'title="Send certificate by email" '
                    . 'aria-label="Send certificate to ' . h($employeeName) . '">'
                    . '<i class="fa fa-paper-plane" aria-hidden="true"></i>'
                    . '</button>'
                    . '</form>';
            } else {
                $sendAction = '<span class="certificate-send-unavailable" '
                    . 'title="Employee email unavailable">'
                    . '<i class="fa fa-envelope" aria-hidden="true"></i>'
                    . '</span>';
            }
        } else {
            $sendAction = '<span class="certificate-send-unavailable" '
                . 'title="' . ($hasCertificate ? 'Admin only' : 'Issue certificate first') . '">'
                . '<i class="fa-solid fa-envelope" aria-hidden="true"></i>'
                . '</span>';
        }

        $actions = '<div class="certificate-row-actions">'
            . $printAction
            . $sendAction
            . '</div>';

        $rows[] = [
            h($employeeCode),
            h($employeeName !== '' ? $employeeName : 'Unknown employee'),
            h($programTitle),
            h(number_format((float) $record['score'], 1) . '%'),
            $certificate,
            $dateAction,
            $actions,
        ];
    }

    // Summary metrics.
    $metrics = '<div class="metrics four">'
        . metric(
            'Certificates issued',
            db_count($pdo, 'certificates'),
            'Recorded certificates',
            'award',
            'green'
        )
        . metric(
            'Eligible participants',
            $registryCount,
            'Completed with score ≥ 75',
            'check',
            'gold'
        )
        . metric(
            'Training completions',
            db_count($pdo, 'registrations', "status='Completed'"),
            'Marked completed',
            'book'
        )
        . metric(
            'Assessments recorded',
            db_count($pdo, 'evaluations'),
            'Scored training records',
            'chart',
            'blue'
        )
        . '</div>';

    // Registry heading and record count.
    $registryHeader = '<div class="certificate-registry-header">'
        . '<div class="certificate-registry-heading">'
        . '<h2>Certificate registry</h2>'
        . '<p>Manage, print, and distribute certificates for completed training.</p>'
        . '</div>'
        . '<span class="certificate-registry-count">'
        . '<strong class="certificate-registry-count-number">'
        . $registryCount
        . '</strong>'
        . '<span>'
        . ($registryCount === 1 ? 'record found' : 'records found')
        . '</span>'
        . '</span>'
        . '</div>';

    // Search and status filters.
    $toolbar = '<div class="certificate-toolbar">'
        . '<label class="certificate-search">'
        . '<span class="sr-only">Search certificates</span>'
        . '<input type="search" data-certificate-search '
        . 'placeholder="Search employee ID or training program..." '
        . 'aria-label="Search certificates">'
        . '</label>'
        . '<label class="certificate-status-filter">'
        . '<span class="sr-only">Filter by certificate status</span>'
        . '<select data-certificate-status aria-label="Filter by certificate status">'
        . '<option value="">All Statuses</option>'
        . '<option value="issued">Issued</option>'
        . '<option value="eligible">Eligible to issue</option>'
        . '</select>'
        . '</label>'
        . '</div>';

    return '<div class="certificates-page">'
        . page_intro(
            '',
            ''
        )
        . $metrics
        . '<section class="card certificates-registry-card">'
        . $registryHeader
        . $toolbar
        . '<div class="certificates-table-wrap" data-max-rows="5">'
        . render_table(
            [
                'Employee ID',
                'Faculty member',
                'Training program',
                'Score',
                'Certificate code',
                'Date issued',
                'Actions',
            ],
            $rows,
            'No completed registrations qualify for a certificate yet.'
        )
        . '</div>'
        . '<p class="certificate-visible-count" data-certificate-visible-count>'
        . $registryCount
        . ($registryCount === 1 ? ' record' : ' records')
        . '</p>'
        . '</section>'
        . '</div>';
}
