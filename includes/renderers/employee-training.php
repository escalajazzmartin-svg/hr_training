<?php
declare(strict_types=1);

function render_employee_training(PDO $pdo,array $user): string {
    $st=$pdo->prepare('SELECT r.id,r.status,p.title,p.specialization,p.start_date,p.venue,a.attendance_status,ev.score,c.certificate_code FROM '
        . 'registrations r JOIN training_programs p ON p.id=r.program_id LEFT JOIN attendance a ON a.registration_id=r.id LEFT JOIN '
        . 'evaluations ev ON ev.registration_id=r.id LEFT JOIN certificates c ON c.registration_id=r.id WHERE r.employee_id=? AND '
    .  'r.status<>\'Withdrawn\' ORDER BY p.start_date DESC');
    $st->execute([(int)$user['employee_id']]);
    $rows=[];
    foreach($st->fetchAll() as $r)$rows[]=[h($r['title']),
        h($r['specialization']),
        h(date('M j, Y',
        strtotime($r['start_date']))),
        badge($r['status']),
        badge($r['attendance_status']?:'Not marked'),
        $r['score']!==null?h((string)$r['score'].'%'):'—',
        $r['certificate_code']?h($r['certificate_code']):'—'];
    return page_intro('My Training','Manage your program registrations and review training progress.','<a class="btn" href="?page=Available Training">'
    . icon('plus')
    . ' Find training</a>')
    . '<section class="card">'
    . render_table(['Program',
        'Specialization',
        'Start date',
        'Progress',
        'Attendance',
        'Score',
        'Certificate'],$rows,'You have no active training registrations.')
    . '</section>';
}

