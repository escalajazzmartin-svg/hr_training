<?php
declare(strict_types=1);

function render_attendance(PDO $pdo,array $user): string {
    $sql='SELECT r.id registration_id,r.status '
        . 'reg_status,e.employee_code,e.first_name,e.last_name,e.department,p.title,p.start_date,a.attendance_status,a.remarks FROM '
        . 'registrations r JOIN employees e ON e.id=r.employee_id JOIN training_programs p ON p.id=r.program_id LEFT JOIN attendance a '
        . 'ON a.registration_id=r.id';
    $params=[];
    if($user['role']==='trainer'){
        $sql.=' WHERE p.trainer_user_id=?';
        $params[]=$user['id'];
    }
    $sql.=' ORDER BY p.start_date DESC,e.last_name';
    $st=$pdo->prepare($sql);
    $st->execute($params);
    $data=$st->fetchAll();
    $html=page_intro('Attendance Monitoring','Record each participant’s presence for scheduled training sessions.');
    if(!$data)return $html
    . '<section class="card"><div class="empty-module"><div class="program-icon">'
    . icon('check',26)
    . '</div><h3>No attendance records yet</h3><p>Register employees for training programs first, and they will appear here.</p>'
    .  '<a class="btn" href="?page='.($user['role']==='admin'?'Employee Registration':'My Training Programs')
    . '">View registrations</a></div></section>';
    $html.='<form method="post"><input type="hidden" name="csrf_token" value="'
    . h(csrf_token())
    . '"><input type="hidden" name="action" value="mark_attendance"><section class="card">'
    . section_head('Participant attendance','Use the status selector for each participant.')
    . '<div class="session-banner"><div class="date-tile light"><b>'
    . strtoupper(date('M'))
    . '</b><strong>'
    . date('d')
    . '</strong></div><div><span class="eyebrow">Attendance workspace</span><h3>Training session participant list</h3><p>'
    . icon('calendar',13)
    . ' Save attendance statuses to record presence.</p></div><div class="session-stats"><span><b>'
    . count($data)
    . '</b><small>Registrations</small></span><span><b>'
    . count(array_filter($data,fn($r)=>$r['attendance_status']==='Present'))
    . '</b><small>Present now</small></span></div></div><div class="table-wrap"><table><thead><tr><th>Faculty member</th>'
    .  '<th>Training program</th><th>Session date</th><th>Attendance status</th><th>Remarks</th></tr></thead><tbody>';
    foreach($data as $r){
        $id=(int)$r['registration_id'];
        $status=$r['attendance_status']?:'Present';
        $html.='<tr><td><div class="person-cell"><span class="avatar">'
        . h(initials($r['first_name'].' '.$r['last_name']))
        . '</span><span><b>'
        . h($r['first_name'].' '.$r['last_name'])
        . '</b><small>'
        . h($r['employee_code'].' · '.$r['department'])
        . '</small></span></div></td><td>'
        . h($r['title'])
        . '</td><td>'
        . h(date('M j, Y',strtotime($r['start_date'])))
        . '</td><td><select name="attendance_status['
        . $id.']" class="table-input">';
        foreach(['Present',
            'Late',
            'Absent',
            'Excused'] as $v)$html.='<option '.($status===$v?'selected':'').'>'.$v
        . '</option>';
        $html.='</select></td><td><input name="remarks['
        . $id.']" class="table-input" value="'.h($r['remarks']??'')
        . '" placeholder="Optional note"></td></tr>';
    }
    $html.='</tbody></table></div><div class="save-bar"><span>Changes are saved to the database when submitted.</span>'
    .  '<button class="btn" type="submit">Save attendance '
    . icon('check')
    . '</button></div></section></form>';
    return $html;
}

