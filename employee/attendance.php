<?php

declare(strict_types=1);
$requiredRole = 'employee';
require_once __DIR__ . '/../includes/page_bootstrap.php';

function render_my_attendance(PDO $pdo, array $user): string
{
    $id = (int)$user['employee_id'];
    $stmt = $pdo->prepare('SELECT e.employee_code,e.first_name,e.last_name,p.title,p.start_date,a.attendance_status,a.remarks FROM registrations r JOIN employees e ON e.id=r.employee_id JOIN training_programs p ON p.id=r.program_id LEFT JOIN attendance a ON a.registration_id=r.id WHERE r.employee_id=? ORDER BY p.start_date DESC');
    $stmt->execute([$id]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [h($row['title']), h(date('M j, Y', strtotime($row['start_date']))), badge($row['attendance_status'] ?: 'Not marked'), h($row['remarks'] ?: '—')];
    }
    return page_intro('', '')
        . '<section class="card">' . render_table(['Training program', 'Date', 'Attendance', 'Remarks'], $rows, 'No attendance details are available yet.') . '</section>';
}
$page = 'My Attendance';
$content = render_my_attendance($pdo, $user);
render_app($pdo, $user, $page, $flash, $content);
