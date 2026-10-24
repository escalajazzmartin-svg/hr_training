<?php
declare(strict_types=1);
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/page_bootstrap.php';

function render_registration(PDO $pdo): string {
    $emps=employees_for($pdo);
    $progs=program_select($pdo);
    $joined=$pdo->query('SELECT r.id,r.status,r.registered_at,e.employee_code,e.first_name,e.last_name,e.department,p.title,p.start_date FROM '
        . 'registrations r JOIN employees e ON e.id=r.employee_id JOIN training_programs p ON p.id=r.program_id ORDER BY '
        . 'r.registered_at DESC')->fetchAll();
    $rows=[];
    foreach($joined as $r)$rows[]=[h($r['employee_code']),
        h($r['first_name'].' '.$r['last_name']),
        h($r['department']),
        h($r['title']),
        h(date('M j, Y',
        strtotime($r['start_date']))),
        badge($r['status'])];
    $html=page_intro('Employee Registration','Register faculty members for relevant training programs based on their development needs.');
    $html.='<section class="card" style="margin-bottom:1rem">'
    . section_head('Register employee for training','Avoid duplicate enrollments; existing registrations are updated if withdrawn.')
    . '<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="'
    . h(csrf_token())
    . '"><input type="hidden" name="action" value="register_training"><div class="form-field"><label>Employee *</label>'
    .  '<select name="employee_id" required><option value="">Select employee</option>';
    foreach($emps as $e)$html.='<option value="'.(int)$e['id'].'">'.h($e['employee_code'].' · '.$e['first_name']
    . ' '.$e['last_name'])
    . '</option>';
    $html.='</select></div><div class="form-field"><label>Training program *</label><select name="program_id" required>'
    .  '<option value="">Select program</option>';
    foreach($progs as $p)$html.='<option value="'.(int)$p['id'].'">'.h($p['title'].' · '.date('M j, Y',strtotime($p['start_date'])))
    . '</option>';
    $html.='</select></div><div class="form-field full"><button class="btn" type="submit">Register employee '
    . icon('arrow',14)
    . '</button></div></form></section><section class="card">'
    . section_head('Training registrations','Registration history for faculty members').render_table(['Employee ID',
        'Faculty member',
        'Department',
        'Training program',
        'Training date',
        'Status'],$rows,'No registrations have been created yet.')
    . '</section>';
    return $html;
}

$page = 'Employee Registration';
$content = render_registration($pdo);
render_app($pdo, $user, $page, $flash, $content);
