<?php
declare(strict_types=1);

function render_programs(PDO $pdo, array $user): string {
    $trainerUsers=$pdo->query("SELECT id,first_name,last_name FROM users WHERE role='trainer' AND active=1 ORDER BY last_name")->fetchAll();
    $sql='SELECT p.*,u.first_name trainer_first,u.last_name trainer_last,(SELECT COUNT(*) FROM registrations r WHERE r.program_id=p.id '
    .  'AND r.status<>\'Withdrawn\') registered FROM training_programs p LEFT JOIN users u ON u.id=p.trainer_user_id';
    $params=[];
    if($user['role']==='trainer'){
        $sql.=' WHERE p.trainer_user_id=?';
        $params[]=$user['id'];
    }
    $sql.=' ORDER BY p.start_date';
    $st=$pdo->prepare($sql);
    $st->execute($params);
    $progs=$st->fetchAll();
    $html=page_intro($user['role']==='trainer'?'My Training Programs':'Training Programs','Plan professional development sessions and track participation.','<a class="btn" href="#create-program">'
    . icon('plus')
    . ' Create program</a>');
    $html.='<div class="program-grid">';
    foreach($progs as $p){
        $html.='<article class="program-card"><div class="program-top"><span class="program-icon">'
        . icon('book',22)
        . '</span>'
        . badge($p['status'])
        . '</div><div class="program-meta"><span>'
        . h($p['category'])
        . '</span><span>'
        . icon('calendar',13).' '.h(date('M j, Y',strtotime($p['start_date'])))
        . '</span></div><h3>'
        . h($p['title'])
        . '</h3><p>'
        . h($p['description']?:'Professional development session supporting faculty skills and competency.')
        . '</p><div class="program-meta"><span>'
        . icon('target',13).' '.h($p['specialization'])
        . '</span><span>'
        . icon('users',13).' '.(int)$p['registered'].' / '.(int)$p['capacity']
        . '</span></div><p class="small-muted">'
        . h($p['venue']?:'Venue to be announced').' · '.h(trim(($p['trainer_first']??'').' '.($p['trainer_last']??''))?:'Trainer not assigned')
        . '</p><a class="btn btn-secondary" href="?page=Attendance Monitoring">View attendance '
        . icon('arrow',14)
        . '</a></article>';
    }
    $html.='</div>';
    if($user['role']==='admin'){
        $html.='<section class="card" id="create-program" style="margin-top:1.2rem">'
        . section_head('Create training program','This information will appear in the faculty training catalog')
        . '<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="'
        . h(csrf_token())
        . '"><input type="hidden" name="action" value="add_program"><div class="form-field"><label>Program title *</label>'
        .  '<input name="title" required placeholder="e.g. Advanced Programming Training"></div><div class="form-field">'
        .  '<label>Category *</label><select name="category"><option>Technical</option><option>Teaching</option><option>Research</option>'
        .  '<option>Leadership</option><option>Professional</option></select></div><div class="form-field"><label>Specialization *</label>'
        .  '<input name="specialization" required placeholder="e.g. Programming"></div><div class="form-field"><label>Trainer</label>'
        .  '<select name="trainer_user_id"><option value="">Select trainer</option>';
        foreach($trainerUsers as $t)$html.='<option value="'.(int)$t['id'].'">'.h($t['first_name'].' '.$t['last_name'])
        . '</option>';
        $html.='</select></div><div class="form-field"><label>Start date *</label><input type="date" name="start_date" required>'
        .  '</div><div class="form-field"><label>End date</label><input type="date" name="end_date"></div><div class="form-field">'
        .  '<label>Start time</label><input type="time" name="start_time"></div><div class="form-field"><label>Venue</label>'
        .  '<input name="venue" placeholder="Room / laboratory"></div><div class="form-field"><label>Capacity</label>'
        .  '<input name="capacity" type="number" min="1" value="30"></div><div class="form-field"><label>Status</label>'
        .  '<select name="status"><option>Upcoming</option><option>Draft</option><option>Ongoing</option><option>Completed</option>'
        .  '<option>Cancelled</option></select></div><div class="form-field full"><label>Description</label><textarea rows="3" name="description" placeholder="Describe the training objectives and coverage...">'
        .  '</textarea></div><div class="form-field full"><button class="btn" type="submit">Create program '
        . icon('plus')
        . '</button></div></form></section>';
    }
    return $html;
}

