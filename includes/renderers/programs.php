<?php

declare(strict_types=1);

function render_programs(PDO $pdo, array $user): string
{
    $role = strtolower(trim((string) ($user['role'] ?? '')));
    $isAdmin = $role === 'admin';
    $isTrainer = $role === 'trainer';

    // Load active trainers for the admin's creation form.
    $trainerUsers = [];

    if ($isAdmin) {
        $trainerStmt = $pdo->query(
            "SELECT id, first_name, last_name
             FROM users
             WHERE role = 'trainer' AND active = 1
             ORDER BY last_name, first_name"
        );

        $trainerUsers = $trainerStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Retrieve programs and their non-withdrawn registration counts.
    $sql = "
        SELECT
            p.*,
            u.first_name AS trainer_first,
            u.last_name AS trainer_last,
            (
                SELECT COUNT(*)
                FROM registrations r
                WHERE r.program_id = p.id
                  AND r.status <> 'Withdrawn'
            ) AS registered
        FROM training_programs p
        LEFT JOIN users u ON u.id = p.trainer_user_id
    ";

    $params = [];

    if ($isTrainer) {
        $sql .= " WHERE p.trainer_user_id = ?";
        $params[] = $user['id'] ?? 0;
    }

    $sql .= " ORDER BY p.start_date ASC, p.title ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Only administrators can create training programs.
    $createButton = $isAdmin
        ? '<a class="btn" href="#create-program-modal">'
        . icon('plus')
        . ' Create program</a>'
        : '';

    $html = '<div id="programs-page">'
        . page_intro(
            '',
            '',
            $createButton
        );

    // Empty state.
    if (!$programs) {
        $html .= '<section class="card program-empty-state">'
            . '<span class="program-empty-icon">'
            . icon('book-open', 28)
            . '</span>'
            . '<h3>No training programs yet</h3>'
            . '<p>'
            . (
                $isTrainer
                ? 'No training programs are currently assigned to you.'
                : 'Create a training program to start organizing professional development sessions.'
            )
            . '</p>';

        if ($isAdmin) {
            $html .= '<a class="btn" href="#create-program-modal">'
                . icon('plus')
                . ' Create your first program</a>';
        }

        $html .= '</section>';
    } else {
        $html .= '<div class="program-grid">';

        foreach ($programs as $p) {
            $programId = (int) ($p['id'] ?? 0);
            $title = trim((string) ($p['title'] ?? ''));
            $status = trim((string) ($p['status'] ?? 'Upcoming'));
            $category = trim((string) ($p['category'] ?? 'General'));
            $specialization = trim((string) ($p['specialization'] ?? 'Not specified'));
            $description = trim((string) ($p['description'] ?? ''));
            $venue = trim((string) ($p['venue'] ?? ''));
            $capacity = max(0, (int) ($p['capacity'] ?? 0));
            $registered = max(0, (int) ($p['registered'] ?? 0));

            $startDate = !empty($p['start_date'])
                ? strtotime((string) $p['start_date'])
                : false;

            $formattedDate = $startDate !== false
                ? date('M j, Y', $startDate)
                : 'Date to be announced';

            $trainerName = trim(
                (string) ($p['trainer_first'] ?? '') . ' ' .
                    (string) ($p['trainer_last'] ?? '')
            );

            if ($trainerName === '') {
                $trainerName = 'Trainer not assigned';
            }

            if ($description === '') {
                $description = 'Professional development session supporting faculty skills and competency.';
            }

            if ($venue === '') {
                $venue = 'Venue to be announced';
            }

            $html .= '<article class="program-card">';

            // Card heading.
            $html .= '<div class="program-card-header">'
                . '<span class="program-icon">'
                . icon('book', 22)
                . '</span>'
                . '<div class="program-status">'
                . badge($status)
                . '</div>'
                . '</div>';

            // Category and date.
            $html .= '<div class="program-meta program-meta-top">'
                . '<span class="program-category">'
                . h($category)
                . '</span>'
                . '<span class="program-date">'
                . icon('calendar', 14)
                . h($formattedDate)
                . '</span>'
                . '</div>';

            // Program details.
            $html .= '<h3 class="program-title">'
                . h($title !== '' ? $title : 'Untitled program')
                . '</h3>'
                . '<p class="program-description">'
                . h($description)
                . '</p>';

            // Specialization and registration capacity.
            $html .= '<div class="program-details">'
                . '<div class="program-detail">'
                . '<span class="program-detail-label">Specialization</span>'
                . '<span class="program-detail-value">'
                . icon('target', 14)
                . h($specialization)
                . '</span>'
                . '</div>'
                . '<div class="program-detail">'
                . '<span class="program-detail-label">Participants</span>'
                . '<span class="program-detail-value">'
                . icon('users', 14)
                . '<strong>' . $registered . '</strong>'
                . '<span class="program-capacity">/ ' . $capacity . '</span>'
                . '</span>'
                . '</div>'
                . '</div>';

            // Registration progress.
            $progress = $capacity > 0
                ? min(100, (int) round(($registered / $capacity) * 100))
                : 0;

            $html .= '<div class="program-progress"'
                . ' role="progressbar"'
                . ' aria-label="Program registration capacity"'
                . ' aria-valuemin="0"'
                . ' aria-valuemax="100"'
                . ' aria-valuenow="' . $progress . '">'
                . '<span style="width: ' . $progress . '%"></span>'
                . '</div>';

            // Venue and trainer.
            $html .= '<div class="program-footer-info">'
                . '<span>'
                . icon('map-pin', 14)
                . h($venue)
                . '</span>'
                . '<span>'
                . icon('user-round', 14)
                . h($trainerName)
                . '</span>'
                . '</div>';

            // Attendance link.
            $html .= '<div class="program-card-actions">'
                . '<a class="btn btn-secondary program-attendance-btn"'
                . ' href="?page=Attendance%20Monitoring">'
                . 'View attendance '
                . icon('arrow', 14)
                . '</a>'
                . '</div>';

            $html .= '</article>';
        }

        $html .= '</div>';
    }

    // Admin-only program creation form.
    if ($isAdmin) {
        $html .= '<div class="program-modal" id="create-program-modal">'
            . '<a class="program-modal-backdrop" href="#programs-page" '
            . 'aria-label="Close modal"></a>'
            . '<section class="program-modal-dialog" role="dialog" '
            . 'aria-modal="true" aria-labelledby="create-program-title">'
            . '<div class="program-modal-header">'
            . '<div>'
            . '<h2 id="create-program-title">Create training program</h2>'
            . '</div>'
            . '<a class="program-modal-close" href="#programs-page" '
            . 'aria-label="Close modal">&times;</a>'
            . '</div>';

        $html .= '<form method="post" class="form-grid program-form">'
            . '<input type="hidden" name="csrf_token" value="'
            . h(csrf_token())
            . '">'
            . '<input type="hidden" name="action" value="add_program">';

        $html .= '<div class="form-field">'
            . '<label for="program-title">Program title *</label>'
            . '<input id="program-title" name="title" required maxlength="255"'
            . ' placeholder="e.g. Advanced Programming Training">'
            . '</div>';

        $html .= '<div class="form-field">'
            . '<label for="program-category">Category *</label>'
            . '<select id="program-category" name="category" required>'
            . '<option value="Technical">Technical</option>'
            . '<option value="Teaching">Teaching</option>'
            . '<option value="Research">Research</option>'
            . '<option value="Leadership">Leadership</option>'
            . '<option value="Professional">Professional</option>'
            . '</select>'
            . '</div>';

        $html .= '<div class="form-field">'
            . '<label for="program-specialization">Specialization *</label>'
            . '<input id="program-specialization" name="specialization"'
            . ' required maxlength="255" placeholder="e.g. Programming">'
            . '</div>';

        $html .= '<div class="form-field">'
            . '<label for="program-trainer">Trainer</label>'
            . '<select id="program-trainer" name="trainer_user_id">'
            . '<option value="">Select trainer</option>';

        foreach ($trainerUsers as $trainer) {
            $trainerId = (int) $trainer['id'];
            $trainerName = trim(
                (string) $trainer['first_name'] . ' ' .
                    (string) $trainer['last_name']
            );

            $html .= '<option value="' . $trainerId . '">'
                . h($trainerName)
                . '</option>';
        }

        $html .= '</select></div>';

        $html .= '<div class="form-field">'
            . '<label for="program-start-date">Start date *</label>'
            . '<input id="program-start-date" type="date"'
            . ' name="start_date" required>'
            . '</div>';

        $html .= '<div class="form-field">'
            . '<label for="program-end-date">End date</label>'
            . '<input id="program-end-date" type="date" name="end_date">'
            . '</div>';

        $html .= '<div class="form-field">'
            . '<label for="program-start-time">Start time</label>'
            . '<input id="program-start-time" type="time" name="start_time">'
            . '</div>';

        $html .= '<div class="form-field">'
            . '<label for="program-venue">Venue</label>'
            . '<input id="program-venue" name="venue" maxlength="255"'
            . ' placeholder="Room / laboratory">'
            . '</div>';

        $html .= '<div class="form-field">'
            . '<label for="program-capacity">Capacity *</label>'
            . '<input id="program-capacity" type="number" name="capacity"'
            . ' min="1" value="30" required>'
            . '</div>';

        $html .= '<div class="form-field">'
            . '<label for="program-status">Status</label>'
            . '<select id="program-status" name="status">'
            . '<option value="Upcoming">Upcoming</option>'
            . '<option value="Draft">Draft</option>'
            . '<option value="Ongoing">Ongoing</option>'
            . '<option value="Completed">Completed</option>'
            . '<option value="Cancelled">Cancelled</option>'
            . '</select>'
            . '</div>';

        $html .= '<div class="form-field full">'
            . '<label for="program-description">Description</label>'
            . '<textarea id="program-description" name="description" rows="4"'
            . ' placeholder="Describe the training objectives and coverage..."></textarea>'
            . '</div>';

        $html .= '<div class="form-field full program-form-actions">'
            . '<button class="btn" type="submit">'
            . icon('plus')
            . ' Create program'
            . '</button>'
            . '</div>';

        $html .= '</form></section></div>';
    }

    $html .= '</div>';

    return $html;
}
