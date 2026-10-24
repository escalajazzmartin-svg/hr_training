<?php
declare(strict_types=1);
$requiredRole = 'trainer';
require_once __DIR__ . '/../includes/page_bootstrap.php';

function render_attendees(PDO $pdo,array $user): string {
    $sql='SELECT r.id,r.status,r.registered_at,e.employee_code,e.first_name,e.last_name,e.department,p.title,p.start_date FROM '
        . 'registrations r JOIN employees e ON e.id=r.employee_id JOIN training_programs p ON p.id=r.program_id';
    $params=[];
    if($user['role']==='trainer'){
        $sql.=' WHERE p.trainer_user_id=?';
        $params[]=$user['id'];
    }
    $sql.=' ORDER BY p.start_date,e.last_name';
    $s=$pdo->prepare($sql);
    $s->execute($params);
    $rows=[];
    foreach($s->fetchAll() as $r)$rows[]=[h($r['employee_code']),
        h($r['first_name'].' '.$r['last_name']),
        h($r['department']),
        h($r['title']),
        h(date('M j, Y',
        strtotime($r['start_date']))),
        badge($r['status'])];
    return page_intro('Attendees','Participants registered for training programs assigned to you.')
    . '<section class="card">'
    . section_head('Training attendees','Use Attendance Monitoring to record presence.').render_table(['Employee ID',
        'Faculty member',
        'Department',
        'Training program',
        'Session date',
        'Registration status'],$rows,'No participants are registered for your programs.')
    . '</section>';
}

$page = 'Attendees';
$content = render_attendees($pdo, $user);
render_app($pdo, $user, $page, $flash, $content);
