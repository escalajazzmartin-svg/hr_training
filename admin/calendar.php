<?php

declare(strict_types=1);

$requiredRole = 'admin';
require_once __DIR__ . '/../includes/page_bootstrap.php';

function render_calendar(PDO $pdo): string
{
    $month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT);
    $year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
    $month = $month === false || $month === null ? (int) date('n') : $month;
    $year = $year === false || $year === null ? (int) date('Y') : $year;

    // Normalize arbitrary month values, including URLs such as ?month=13.
    $date = DateTimeImmutable::createFromFormat('!Y-n-j', sprintf('%d-%d-1', $year, $month));
    if ($date === false) {
        $date = new DateTimeImmutable('first day of this month');
    }
    $date = $date->modify('first day of this month');
    $month = (int) $date->format('n');
    $year = (int) $date->format('Y');

    $first = $date->getTimestamp();
    $start = (int) date('N', $first); // Monday = 1, Sunday = 7
    $days = (int) $date->format('t');
    $prevDate = $date->modify('-1 month');
    $nextDate = $date->modify('+1 month');
    $today = date('Y-m-d');

    $st = $pdo->prepare(
        "SELECT id, title, start_date, status
         FROM training_programs
         WHERE YEAR(start_date) = ?
           AND MONTH(start_date) = ?
           AND status <> 'Cancelled'
         ORDER BY start_date ASC, title ASC"
    );
    $st->execute([$year, $month]);
    $progs = $st->fetchAll(PDO::FETCH_ASSOC);

    $byDay = [];
    foreach ($progs as $program) {
        $programDate = strtotime((string) ($program['start_date'] ?? ''));
        if ($programDate !== false) {
            $byDay[(int) date('j', $programDate)][] = $program;
        }
    }

    $html = page_intro(
        '',
        ''
    );

    $html .= '<section class="card calendar-card" aria-label="Training calendar">'
        . '<div class="calendar-toolbar">'
        . '<a class="btn btn-secondary calendar-nav" href="?page=Training%20Calendar&amp;month='
        . $prevDate->format('n') . '&amp;year=' . $prevDate->format('Y')
        . '" aria-label="Previous month">&#8249;</a>'
        . '<h2 class="calendar-month-title">' . h($date->format('F Y')) . '</h2>'
        . '<a class="btn btn-secondary calendar-nav" href="?page=Training%20Calendar&amp;month='
        . $nextDate->format('n') . '&amp;year=' . $nextDate->format('Y')
        . '" aria-label="Next month">&#8250;</a>'
        . '<div class="calendar-view-switch"><a class="btn btn-ghost" href="?page=Training%20Calendar">Today / current month</a></div>'
        . '</div>'
        . '<div class="calendar-grid" role="grid" aria-label="' . h($date->format('F Y')) . '">';

    foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName) {
        $html .= '<div class="weekday" role="columnheader">' . h($dayName) . '</div>';
    }

    $cellCount = (int) (ceil(($start + $days - 1) / 7) * 7);
    $previousMonthDays = (int) $date->modify('-1 month')->format('t');

    for ($i = 1; $i <= $cellCount; $i++) {
        $day = $i - $start + 1;
        $outside = false;
        $cellDate = '';

        if ($day < 1) {
            $label = $previousMonthDays + $day;
            $outside = true;
            $cellDate = $prevDate->format('Y-m-') . str_pad((string) $label, 2, '0', STR_PAD_LEFT);
        } elseif ($day > $days) {
            $label = $day - $days;
            $outside = true;
            $cellDate = $nextDate->format('Y-m-') . str_pad((string) $label, 2, '0', STR_PAD_LEFT);
        } else {
            $label = $day;
            $cellDate = $date->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        }

        $classes = ['cal-day'];
        if ($outside) {
            $classes[] = 'outside';
        }
        if ($cellDate === $today) {
            $classes[] = 'is-today';
        }

        $html .= '<div class="' . h(implode(' ', $classes)) . '" role="gridcell"'
            . ' aria-label="' . h(date('F j, Y', strtotime($cellDate))) . '">'
            . '<span class="cal-day-number">' . (int) $label . '</span>';

        if (!$outside && isset($byDay[$day])) {
            foreach ($byDay[$day] as $program) {
                $status = (string) ($program['status'] ?? '');
                $statusClass = match ($status) {
                    'Completed' => 'green',
                    'Ongoing' => 'blue',
                    default => 'maroon',
                };
                $title = (string) ($program['title'] ?? 'Training program');
                $html .= '<a class="cal-event ' . h($statusClass) . '" href="?page=Training%20Programs"'
                    . ' title="' . h($title) . ' — ' . h($status) . '">'
                    . '<span class="cal-event-title">' . h($title) . '</span>'
                    . '<span class="cal-event-status">' . h($status) . '</span>'
                    . '</a>';
            }
        }

        $html .= '</div>';
    }

    $html .= '</div>'
        . '<div class="calendar-footer"><p class="small-muted">'
        . count($progs) . ' program(s) scheduled in ' . h($date->format('F Y'))
        . '. Create or edit training programs from Training Programs.</p>'
        . '<div class="calendar-legend" aria-label="Program status legend">'
        . '<span><i class="legend-dot maroon"></i> Scheduled</span>'
        . '<span><i class="legend-dot blue"></i> Ongoing</span>'
        . '<span><i class="legend-dot green"></i> Completed</span>'
        . '</div></div></section>';

    return $html;
}

$page = 'Training Calendar';
$content = render_calendar($pdo);

// page_bootstrap normally provides these; fallbacks prevent undefined-variable notices.
$user = $user ?? (function_exists('current_user') ? current_user() : []);
$flash = $flash ?? ($_SESSION['flash'] ?? null);

render_app($pdo, $user, $page, $flash, $content);
