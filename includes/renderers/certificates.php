<?php
declare(strict_types=1);

function render_certificates(PDO $pdo, array $user): string {
    $sql='SELECT r.id registration_id,e.employee_code,e.first_name,e.last_name,p.title,ev.score,c.certificate_code,c.issued_at FROM '
        . 'registrations r JOIN employees e ON e.id=r.employee_id JOIN training_programs p ON p.id=r.program_id JOIN evaluations ev ON '
        . 'ev.registration_id=r.id LEFT JOIN certificates c ON c.registration_id=r.id WHERE r.status=\'Completed\' AND ev.score>=75';
    $params=[];
    if ($user['role']==='employee') {
        $sql.=' AND r.employee_id=?';
        $params[]=(int)$user['employee_id'];
    }
    $sql.=' ORDER BY c.issued_at DESC';
    $st=$pdo->prepare($sql);
    $st->execute($params);
    $rowsData=$st->fetchAll();
    $rows=[];
    foreach($rowsData as $r){
        $certificate=$r['certificate_code']?'<span class="badge badge-success">'
        . h($r['certificate_code'])
        . '</span>':'<span class="badge badge-warn">Eligible to issue</span>';
        $action=$r['certificate_code']?h(date('M j, Y',strtotime($r['issued_at']))):($user['role']==='admin'?'<form method="post"><input type="hidden" name="csrf_token" value="'
        . h(csrf_token())
        . '"><input type="hidden" name="action" value="issue_certificate"><input type="hidden" name="registration_id" value="'
        . (int)$r['registration_id']
        . '"><button class="btn" type="submit">Issue</button></form>':'Pending HR review');
        $rows[]=[h($r['employee_code']),
            h($r['first_name'].' '.$r['last_name']),
            h($r['title']),
            h(number_format((float)$r['score'],
            1).'%'),
            $certificate,
            $action];
    }
    return page_intro('Certificates','Review completed training and issue certificates after a passing assessment.')
    . '<div class="metrics four">'
    . metric('Certificates issued',db_count($pdo,'certificates'),'Recorded certificates','award','green').metric('Eligible participants',count($rowsData),'Completed with score ≥ 75','check','gold')
    . metric('Training completions',db_count($pdo,'registrations',"status='Completed'"),'Marked completed','book')
    . metric('Assessments recorded',db_count($pdo,'evaluations'),'Scored training records','chart','blue')
    . '</div><section class="card">'
    . section_head('Certificate registry','Only completed registrations with score of 75 or higher are eligible.')
    . render_table(['Employee ID',
        'Faculty member',
        'Training program',
        'Score',
        'Certificate code',
        'Date / action'],$rows,'No completed registrations qualify for a certificate yet.')
    . '</section>';
}

