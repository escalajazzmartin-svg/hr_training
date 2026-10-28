<?php

declare(strict_types=1);

function render_recommendations(PDO $pdo, array $user): string
{
    $eid = (int)($user['employee_id'] ?? 0);
    $st = $pdo->prepare(
        'SELECT p.*, '
        . '(SELECT COUNT(*) FROM registrations r WHERE r.program_id = p.id) AS registered_count, '
        . '(SELECT COUNT(*) FROM registrations r WHERE r.program_id = p.id AND r.employee_id = ?) AS existing '
        . 'FROM training_programs p '
        . 'WHERE p.status IN (\'Upcoming\',\'Ongoing\') '
        . 'ORDER BY CASE WHEN p.specialization = (SELECT specialization FROM employees WHERE id = ?) THEN 0 ELSE 1 END, p.start_date LIMIT 6'
    );
    $st->execute([$eid, $eid]);
    $progs = $st->fetchAll();

    $html = '<div class="recommendation-grid">';

    foreach ($progs as $p) {
        $storedStatus = trim((string)($p['status'] ?? 'Upcoming'));
        $startDate = !empty($p['start_date']) ? strtotime((string)$p['start_date']) : false;
        $endDate = !empty($p['end_date']) ? strtotime((string)$p['end_date']) : false;

        if (in_array($storedStatus, ['Cancelled', 'Draft'], true)) {
            $status = $storedStatus;
        } elseif ($startDate === false) {
            $status = $storedStatus !== '' ? $storedStatus : 'Upcoming';
        } else {
            $today = new DateTimeImmutable('today');
            $start = new DateTimeImmutable(date('Y-m-d', $startDate));
            $end = $endDate !== false ? new DateTimeImmutable(date('Y-m-d', $endDate)) : null;

            if ($end !== null && $today > $end) {
                $status = 'Completed';
            } elseif ($today >= $start) {
                $status = 'Ongoing';
            } else {
                $status = 'Upcoming';
            }
        }

        $statusClass = strtolower($status) === 'completed'
            ? 'is-completed'
            : (strtolower($status) === 'upcoming' ? 'is-upcoming' : 'is-ongoing');

        $registeredCount = (int)($p['registered_count'] ?? 0);
        $capacity = (int)($p['capacity'] ?? 0);
        $date = $p['start_date'] ? date('M j, Y', strtotime((string)$p['start_date'])) : 'Date TBD';
        $specialization = $p['specialization'] ?: 'General';
        $description = $p['description'] ?: 'Professional training to strengthen faculty competency.';
        $venueList = array_filter([
            trim((string)($p['venue'] ?? '')),
            trim((string)($p['venue_2'] ?? '')),
            trim((string)($p['venue_3'] ?? '')),
        ]);

        $html .= '<article class="recommendation-card">'
            . '<div class="recommendation-card-header">'
            . '<span class="recommendation-card-icon" aria-hidden="true">' . icon('book', 18) . '</span>'
            . '<span class="recommendation-status ' . $statusClass . '">' . h($status) . '</span>'
            . '</div>'
            . '<div class="recommendation-card-meta">'
            . '<span class="recommendation-category">' . h($p['category'] ?: 'Technical') . '</span>'
            . '<span class="recommendation-date">' . icon('calendar', 14) . ' ' . h($date) . '</span>'
            . '</div>'
            . '<h3 class="recommendation-card-title">' . h($p['title']) . '</h3>'
            . '<p class="recommendation-card-description">' . h($description) . '</p>'
            . '<div class="recommendation-divider"></div>'
            . '<div class="recommendation-card-stats">'
            . '<div class="recommendation-stat">'
            . '<span class="recommendation-label">Specialization</span>'
            . '<div class="recommendation-value"><span class="recommendation-dot"></span>' . h($specialization) . '</div>'
            . '</div>'
            . '<div class="recommendation-stat recommendation-stat-right">'
            . '<span class="recommendation-label">Participants</span>'
            . '<div class="recommendation-value"><span class="recommendation-user-icon" aria-hidden="true">' . icon('users', 14) . '</span> ' . $registeredCount . ' / ' . $capacity . '</div>'
            . '</div>'
            . '</div>'
            . '<div class="recommendation-progress"><span style="width:' . ($capacity > 0 ? min(100, ($registeredCount / $capacity) * 100) : 0) . '%"></span></div>'
            . '<div class="recommendation-locations">';

        if ($venueList) {
            foreach (array_slice($venueList, 0, 3) as $venue) {
                $html .= '<div class="recommendation-location">' . icon('location', 14) . ' ' . h($venue) . '</div>';
            }
        } else {
            $html .= '<div class="recommendation-location">' . icon('location', 14) . ' Venue to be announced</div>';
        }

        if ((int)$p['existing']) {
            $html .= '</div>'
                . '<button class="recommendation-action is-registered" type="button" disabled>Registered</button>'
                . '</article>';
        } else {
            $html .= '</div>'
                . '<form method="post" action="index.php?page=Recommended Training" class="recommendation-form">'
                . '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'
                . '<input type="hidden" name="action" value="register_training">'
                . '<input type="hidden" name="program_id" value="' . (int)$p['id'] . '">'
                . '<button class="recommendation-action" type="submit">Register <span aria-hidden="true">→</span></button>'
                . '</form>'
                . '</article>';
        }
    }

    return $html . '</div>';
}
